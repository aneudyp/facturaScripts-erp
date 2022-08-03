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

namespace FacturaScripts\Core;

use Exception;
use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Contract\ErrorHandlerInterface;
use FacturaScripts\Core\ErrorHandler\FatalError;

final class Kernel
{
    const ERR500_HANDLER = 'FatalError';

    private static $routes;

    public static function addRoute(string $route, string $controller): void
    {
        if (self::$routes === null) {
            self::$routes = [];
        }

        self::$routes[$route] = $controller;
    }

    public static function errorHandler(): void
    {
        $error = error_get_last();
        if (isset($error)) {
            $url = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            $relativeUrl = substr($url, strlen(Setup::get('route')));

            $handler = self::getErrorHandler($relativeUrl);
            $handler->error($error);
        }
    }

    public static function getRoutes(): array
    {
        $defaults = [
            '/' => '\\FacturaScripts\\Core\\Controller\\Dashboard',
            '/Core/*' => '\\FacturaScripts\\Core\\Controller\\Files',
            '/Dinamic/*' => '\\FacturaScripts\\Core\\Controller\\Files',
            '/install' => '\\FacturaScripts\\Core\\Controller\\Installer',
            '/login' => '\\FacturaScripts\\Core\\Controller\\Login',
            '/MyFiles/*' => '\\FacturaScripts\\Core\\Controller\\Myfiles',
            '/node_modules/*' => '\\FacturaScripts\\Core\\Controller\\Files',
            '/Plugins/*' => '\\FacturaScripts\\Core\\Controller\\Files'
        ];

        return array_merge($defaults, self::$routes ?? []);
    }

    public static function run(string $url): void
    {
        $relativeUrl = substr($url, strlen(Setup::get('route')));

        try {
            self::runController($relativeUrl);
        } catch (KernelException $exception) {
            error_clear_last();
            $handler = $exception->getHandler($relativeUrl);
            $handler->exception($exception);
        } catch (Exception $exception) {
            error_clear_last();
            $handler = self::getErrorHandler($relativeUrl);
            $handler->exception($exception);
        }
    }

    private static function getErrorHandler(string $url): ErrorHandlerInterface
    {
        $dynClass = '\\FacturaScripts\\Dinamic\\ErrorHandler\\' . self::ERR500_HANDLER;
        if (class_exists($dynClass)) {
            return new $dynClass($url);
        }

        return new FatalError($url);
    }

    /**
     * @param string $url
     * @throws KernelException
     */
    private static function runController(string $url): void
    {
        foreach (self::getRoutes() as $route => $controller) {
            if ($url === $route) {
                $app = new $controller($url);
                if ($app instanceof ControllerInterface) {
                    $app->run();
                    return;
                }
                throw new KernelException('FatalError', $controller . ' is not a ControllerInterface');
            }

            if (!str_ends_with($route, '*')) {
                continue;
            }

            if (0 === strncmp($url, $route, strlen($route) - 1)) {
                $app = new $controller($url);
                if ($app instanceof ControllerInterface) {
                    $app->run();
                    return;
                }
                throw new KernelException('FatalError', $controller . ' is not a ControllerInterface');
            }
        }

        throw new KernelException('PageNotFound', 'page-not-found');
    }
}