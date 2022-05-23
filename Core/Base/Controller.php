<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Core\Base;

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Lib\MultiRequestProtection;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\User;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class from which all FacturaScripts controllers must inherit.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class Controller
{

    /**
     * Name of the class of the controller (although its in inheritance from this class,
     * the name of the final class we will have here)
     *
     * @var string __CLASS__
     */
    private $className;

    /**
     * It provides direct access to the database.
     *
     * @var DataBase
     */
    protected $dataBase;

    /**
     * Selected company.
     *
     * @var Empresa
     */
    public $empresa;

    /**
     * @var MultiRequestProtection
     */
    public $multiRequestProtection;

    /**
     * User permissions on this controller.
     *
     * @var ControllerPermissions
     */
    public $permissions;

    /**
     * Request on which we can get data.
     *
     * @var Request
     */
    public $request;

    /**
     * HTTP Response object.
     *
     * @var Response
     */
    protected $response;

    /**
     * Name of the file for the template.
     *
     * @var string|false nombre_archivo.html.twig
     */
    private $template;

    /**
     * Title of the page.
     *
     * @var string título de la página.
     */
    public $title;

    /**
     * Given uri, default is empty.
     *
     * @var string
     */
    public $uri;

    /**
     * User logged in.
     *
     * @var User|false
     */
    public $user = false;

    public function __construct(string $uri)
    {
        $this->className = substr(strrchr(static::class, "\\"), 1);
        $this->dataBase = new DataBase();
        $this->empresa = new Empresa();
        $this->multiRequestProtection = new MultiRequestProtection();
        $this->request = Request::createFromGlobals();
        $this->template = $this->className . '.html.twig';
        $this->uri = $uri;

        $pageData = $this->getPageData();
        $this->title = empty($pageData) ? $this->className : $this->toolBox()->i18n()->trans($pageData['title']);

        AssetManager::clear();
        AssetManager::setAssetsForPage($this->className);

        $this->checkPHPversion(7.2);
    }

    /**
     * @param mixed $extension
     */
    public static function addExtension($extension)
    {
        static::toolBox()->i18nLog()->error('no-extension-support', ['%className%' => static::class]);
    }

    /**
     * Return the basic data for this page.
     *
     * @return array
     */
    public function getPageData()
    {
        return [
            'name' => $this->className,
            'title' => $this->className,
            'icon' => 'fas fa-circle',
            'menu' => 'new',
            'submenu' => null,
            'showonmenu' => true,
            'ordernum' => 100
        ];
    }

    /**
     * Return the template to use for this controller.
     *
     * @return string|false
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function pipe($name, ...$arguments)
    {
        $this->toolBox()->i18nLog()->error('no-extension-support', ['%className%' => static::class]);
        return null;
    }

    /**
     * Runs the controller's private logic.
     *
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        $this->permissions = $permissions;
        $this->response = &$response;
        $this->user = $user;

        // Select the default company for the user
        $this->empresa = Empresas::get($this->user->idempresa);

        // add the user to the token generation seed
        $this->multiRequestProtection->addSeed($user->nick);

        // Have this user a default page?
        $defaultPage = $this->request->query->get('defaultPage', '');
        if ($defaultPage === 'TRUE') {
            $this->user->homepage = $this->className;
            $this->response->headers->setCookie(new Cookie('fsHomepage', $this->user->homepage, time() + FS_COOKIES_EXPIRE));
            $this->user->save();
        } elseif ($defaultPage === 'FALSE') {
            $this->user->homepage = null;
            $this->response->headers->setCookie(new Cookie('fsHomepage', $this->user->homepage, time() - FS_COOKIES_EXPIRE));
            $this->user->save();
        }
    }

    /**
     * Execute the public part of the controller.
     *
     * @param Response $response
     */
    public function publicCore(&$response)
    {
        $this->permissions = new ControllerPermissions();
        $this->response = &$response;
        $this->template = 'Login/Login.html.twig';

        $idempresa = $this->toolBox()->appSettings()->get('default', 'idempresa');
        $this->empresa = Empresas::get($idempresa);
    }

    /**
     * Redirect to an url or controller.
     *
     * @param string $url
     * @param int $delay
     */
    public function redirect(string $url, int $delay = 0)
    {
        $this->response->headers->set('Refresh', $delay . '; ' . $url);
        if ($delay === 0) {
            $this->setTemplate(false);
        }
    }

    public function run(): void
    {
        $cookieNick = $this->request->cookies->get('fsNick', '');
        if ($cookieNick === '') {
            throw new KernelException('LoginToContinue', 'login-to-continue');
        }

        $user = new User();
        if (false === $user->loadFromCode($cookieNick) && $user->enabled) {
            ToolBox::i18nLog()->warning('login-user-not-found', ['%nick%' => $cookieNick]);
            throw new KernelException('LoginToContinue', 'login-user-not-found');
        }

        if (false === $user->verifyLogkey($this->request->cookies->get('fsLogkey'))) {
            ToolBox::i18nLog()->warning('login-cookie-fail');
            // clear fsNick cookie
            setcookie('fsNick', '', time() - FS_COOKIES_EXPIRE, '/');
            throw new KernelException('LoginToContinue', 'login-cookie-fail');
        }

        $response = new Response();
        $this->updateCookies($user, $response);
        ToolBox::i18nLog()->debug('login-ok', ['%nick%' => $user->nick]);
        ToolBox::log()::setContext('nick', $user->nick);

        $menuManager = new MenuManager();
        $menuManager->setUser($user);
        $menuManager->selectPage($this->getPageData());

        $permissions = new ControllerPermissions($user, $this->getClassName());
        $this->privateCore($response, $user, $permissions);
        echo Html::render($this->template, [
            'fsc' => $this,
            'menuManager' => $menuManager
        ]);
    }

    /**
     * Set the template to use for this controller.
     *
     * @param string|false $template
     *
     * @return bool
     */
    public function setTemplate($template)
    {
        $this->template = ($template === false) ? false : $template . '.html.twig';
        return true;
    }

    /**
     * @return ToolBox
     */
    public static function toolBox(): ToolBox
    {
        return new ToolBox();
    }

    private function updateCookies(User &$user, Response &$response)
    {
        if (time() - strtotime($user->lastactivity) > 3600) {
            $ipAddress = ToolBox::ipFilter()->getClientIp();
            $user->updateActivity($ipAddress);
            $user->save();

            $expire = time() + FS_COOKIES_EXPIRE;
            $response->headers->setCookie(new Cookie('fsNick', $user->nick, $expire, FS_ROUTE));
            $response->headers->setCookie(new Cookie('fsLogkey', $user->logkey, $expire, FS_ROUTE));
            $response->headers->setCookie(new Cookie('fsLang', $user->langcode, $expire, FS_ROUTE));
            $response->headers->setCookie(new Cookie('fsCompany', $user->idempresa, $expire, FS_ROUTE));
        }
    }

    /**
     * Return the URL of the actual controller.
     *
     * @return string
     */
    public function url(): string
    {
        return $this->className;
    }

    /**
     * @param float $min
     */
    private function checkPHPversion(float $min)
    {
        $current = (float)substr(phpversion(), 0, 3);
        if ($current < $min) {
            $this->toolBox()->i18nLog()->warning('php-support-end', ['%current%' => $current, '%min%' => $min]);
        }
    }

    /**
     * Return the name of the controller.
     *
     * @return string
     */
    protected function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Check request token. Returns an error if:
     *   - the token does not exist
     *   - the token is invalid
     *   - the token is duplicated
     *
     * @return bool
     */
    protected function validateFormToken(): bool
    {
        // valid request?
        $token = $this->request->request->get('multireqtoken', '');
        if (empty($token) || false === $this->multiRequestProtection->validate($token)) {
            $this->toolBox()->i18nLog()->warning('invalid-request');
            return false;
        }

        // duplicated request?
        if ($this->multiRequestProtection->tokenExist($token)) {
            $this->toolBox()->i18nLog()->warning('duplicated-request');
            return false;
        }

        return true;
    }
}
