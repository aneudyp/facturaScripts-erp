<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class Html
{
    private static $twig;

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function render(string $template, array $params = []): string
    {
        $default = [
            'i18n' => new Translator(),
            'langcode' => Setup::get('langcode', 'es_ES'),
            'log' => new Logger(),
        ];
        return self::twig()->render($template, array_merge($params, $default));
    }

    private static function twig(): Environment
    {
        if (!isset(self::$twig)) {
            $path = Setup::get('folder') . '/Core/View';
            $loader = new FilesystemLoader($path);
            self::$twig = new Environment($loader);

            // asset() function
            $assetFunction = new TwigFunction('asset', function ($string) {
                $path = Setup::get('route') . '/';
                if (str_starts_with($string, $path)) {
                    return $string;
                }
                return str_replace('//', '/', $path . $string);
            });
            self::$twig->addFunction($assetFunction);
        }

        return self::$twig;
    }
}