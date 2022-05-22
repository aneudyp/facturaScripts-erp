<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\FormToken;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Core\Setup;
use FacturaScripts\Core\Tools;
use Symfony\Component\HttpFoundation\Request;

class Login implements ControllerInterface
{
    const INCIDENT_EXPIRATION_TIME = 600;
    const IP_LIST = 'login-ip-list';
    const MAX_INCIDENT_COUNT = 5;
    const USER_LIST = 'login-user-list';

    public function __construct(string $url)
    {
        FormToken::addSeed($url);
        FormToken::addSeed(Tools::getClientIp());
    }

    public function run(): void
    {
        $request = Request::createFromGlobals();
        switch ($request->get('action')) {
            case 'change-password':
                $this->changePasswordAction($request);
                break;

            case 'login':
                $this->loginAction($request);
                break;

            case 'logout':
                $this->logoutAction($request);
                break;
        }

        echo Html::render('Login/login.html.twig');
    }

    private function changePasswordAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        $username = $request->request->get('fsNick');
        $password = $request->request->get('fsNewPasswd');
        $password2 = $request->request->get('fsNewPasswd2');
        if (empty($username) || empty($password) || empty($password2)) {
            Tools::i18nLog()->warning('login-empty-fields');
            return;
        }

        if ($password !== $password2) {
            Tools::i18nLog()->warning('login-passwords-not-match');
            return;
        }

        if ($this->userHasManyIncidents($username)) {
            Tools::i18nLog()->warning('login-incident-count-exceeded');
            return;
        }

        $user = new User();
        if (false === $user->loadFromCode($username)) {
            Tools::i18nLog()->warning('login-user-not-found');
            $this->saveIncident($username);
            return;
        }

        if (false === $user->enabled) {
            Tools::i18nLog()->warning('login-user-disabled');
            return;
        }

        $user->setPassword($password);
        if (false === $user->save()) {
            Tools::i18nLog()->warning('login-user-not-saved');
            $this->saveIncident($username);
            return;
        }

        Tools::i18nLog()->notice('login-password-changed');
    }

    private function validateFormToken(Request $request): bool
    {
        $token = $request->request->get('_token');
        if (empty($token)) {
            Tools::i18nLog()->warning('form-token-not-found');
            return false;
        }

        if (false === FormToken::validate($token)) {
            Tools::i18nLog()->warning('form-token-invalid');
            return false;
        }

        if (FormToken::tokenExist($token)) {
            Tools::i18nLog()->warning('form-token-expired');
            return false;
        }

        return true;
    }

    private function userHasManyIncidents(string $username = ''): bool
    {
        // get ip count on the list
        $currentIp = Tools::getClientIp();
        $ipCount = 0;
        foreach ($this->getIpList() as $item) {
            if ($item['ip'] === $currentIp) {
                $ipCount++;
            }
        }
        if ($ipCount > self::MAX_INCIDENT_COUNT) {
            return true;
        }

        // get user count on the list
        $userCount = 0;
        foreach ($this->getUserList() as $item) {
            if ($item['user'] === $username) {
                $userCount++;
            }
        }
        return $userCount > self::MAX_INCIDENT_COUNT;
    }

    private function getIpList(): array
    {
        $ipList = Cache::get(self::IP_LIST);
        if (false === is_array($ipList)) {
            return [];
        }

        // remove expired items
        $newList = [];
        foreach ($ipList as $item) {
            if (time() - $item['time'] < self::INCIDENT_EXPIRATION_TIME) {
                $newList[] = $item;
            }
        }
        return $newList;
    }

    private function getUserList(): array
    {
        $userList = Cache::get(self::USER_LIST);
        if (false === is_array($userList)) {
            return [];
        }

        // remove expired items
        $newList = [];
        foreach ($userList as $item) {
            if (time() - $item['time'] < self::INCIDENT_EXPIRATION_TIME) {
                $newList[] = $item;
            }
        }
        return $newList;
    }

    private function saveIncident(string $user = ''): void
    {
        // add the current IP to the list
        $ipList = $this->getIpList();
        $ipList[] = [
            'ip' => Tools::getClientIp(),
            'time' => time()
        ];

        // save the list in cache
        Cache::set('login-ip-list', $ipList);

        // if the user is not empty, save the incident
        if (empty($user)) {
            return;
        }

        // add the current user to the list
        $userList = $this->getUserList();
        $userList[] = [
            'user' => $user,
            'time' => time()
        ];

        // save the list in cache
        Cache::set('login-user-list', $userList);
    }

    private function loginAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        $username = $request->request->get('fsNick');
        $password = $request->request->get('fsPassword');
        if (empty($username) || empty($password)) {
            Tools::i18nLog()->warning('login-error-empty-fields');
            return;
        }

        // check if the user is in the incident list
        if ($this->userHasManyIncidents($username)) {
            Tools::i18nLog()->warning('login-error-many-incidents');
            return;
        }

        $user = new User();
        if (false === $user->loadFromCode($username)) {
            Tools::i18nLog()->warning('login-error-invalid-user');
            $this->saveIncident();
            return;
        }

        if (false === $user->enabled) {
            Tools::i18nLog()->warning('login-error-user-disabled');
            return;
        }

        if (false === $user->verifyPassword($password)) {
            Tools::i18nLog()->warning('login-error-invalid-password');
            $this->saveIncident($username);
            return;
        }

        // save cookies
        $expiration = time() + (int)Setup::get('cookies_expire');
        setcookie('fsNick', $user->nick, $expiration, Setup::get('route'));
        setcookie('fsLogkey', $user->logkey, $expiration, Setup::get('route'));
        setcookie('fsLang', $user->langcode, $expiration, Setup::get('route'));

        // redirect to the main page
        header('Location: ' . $user->homepage);
    }

    private function logoutAction(Request $request): void
    {
        if (false === $this->validateFormToken($request)) {
            return;
        }

        // remove cookies
        setcookie('fsNick', '', time() - 3600, Setup::get('route'));
        setcookie('fsLogkey', '', time() - 3600, Setup::get('route'));
        setcookie('fsLang', '', time() - 3600, Setup::get('route'));
    }
}
