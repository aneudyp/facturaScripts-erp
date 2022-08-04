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

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Setup;
use FacturaScripts\Dinamic\Model\ApiAccess;
use FacturaScripts\Dinamic\Model\ApiKey;
use Symfony\Component\HttpFoundation\Request;

class ApiV3 implements ControllerInterface
{
    const API_VERSION = 3;

    /** @var ApiKey */
    private $apiKey;

    /** @var Request */
    private $request;

    /** @var string */
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->request = Request::createFromGlobals();

        $appSettings = new AppSettings();
        $appSettings->load();
    }

    public function run(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Content-Type: application/json');

        // ¿Está la api desactivada?
        if (AppSettings::get('default', 'enable_api', false) === false) {
            echo json_encode(['error' => 'api-disabled']);
            return;
        }

        // ¿Request method es options?
        if ($this->request->getMethod() === 'OPTIONS') {
            $requestHeaders = $this->request->server->get('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', '');
            header('Access-Control-Allow-Headers: ' . $requestHeaders);
            return;
        }

        // comprobamos el token
        if ($this->checkAuthToken() === false) {
            echo json_encode(['error' => 'auth-token-invalid']);
            return;
        }

        // comprobamos si tiene permiso para la acción
        if ($this->checkPermission() === false) {
            echo json_encode(['error' => 'permission-denied']);
            return;
        }

        if ($this->getUriParam(1) == self::API_VERSION) {
            $this->selectResource();
            return;
        }

        echo json_encode(['error' => 'api-version-not-found']);
    }

    private function checkAuthToken(): bool
    {
        $this->apiKey = new ApiKey();
        $altToken = $this->request->headers->get('Token', '');
        $token = $this->request->headers->get('X-Auth-Token', $altToken);
        if (empty($token)) {
            return false;
        }

        if (Setup::get('api_key') && $token == Setup::get('api_key')) {
            $this->apiKey->apikey = Setup::get('api_key');
            $this->apiKey->fullaccess = true;
            return true;
        }

        $where = [
            new DataBaseWhere('apikey', $token),
            new DataBaseWhere('enabled', true)
        ];
        return $this->apiKey->loadFromCode('', $where);
    }

    private function checkPermission(): bool
    {
        $resource = $this->getUriParam(2);
        if ($resource === '' || $this->apiKey->fullaccess) {
            return true;
        }

        $apiAccess = new ApiAccess();
        $where = [
            new DataBaseWhere('idapikey', $this->apiKey->id),
            new DataBaseWhere('resource', $resource)
        ];
        if ($apiAccess->loadFromCode('', $where)) {
            switch ($this->request->getMethod()) {
                case 'DELETE':
                    return $apiAccess->allowdelete;

                case 'GET':
                    return $apiAccess->allowget;

                case 'PATCH':
                case 'PUT':
                    return $apiAccess->allowput;

                case 'POST':
                    return $apiAccess->allowpost;
            }
        }

        return false;
    }

    private function getUriParam(int $num): string
    {
        $params = explode('/', substr($this->url, 1));
        return $params[$num] ?? '';
    }

    private function selectResource(): void
    {
        echo json_encode(['error' => 'resource-not-found']);
    }
}
