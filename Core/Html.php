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

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Model\AttachedFile;
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
            'assetManager' => new AssetManager(),
            'i18n' => new Translator(),
            'log' => new MiniLog(),
            'setup' => new Setup()
        ];
        return self::twig()->render($template, array_merge($params, $default));
    }

    private static function assetFunction(): TwigFunction
    {
        return new TwigFunction('asset', function ($string) {
            $path = FS_ROUTE . '/';
            if (substr($string, 0, strlen($path)) == $path) {
                return $string;
            }
            return str_replace('//', '/', $path . $string);
        });
    }

    private static function attachedFileFunction(): TwigFunction
    {
        return new TwigFunction('attachedFile', function ($idfile) {
            $attached = new AttachedFile();
            $attached->loadFromCode($idfile);
            return $attached;
        });
    }

    private static function formTokenFunction(): TwigFunction
    {
        return new TwigFunction('formToken', function () {
            return '<input type="hidden" name="_token" value="' . Session::newToken() . '"/>';
        }, ['is_safe' => ['html']]);
    }

    private static function transFunction(): TwigFunction
    {
        return new TwigFunction('trans', function ($text) {
            $i18n = new Translator();
            return $i18n->trans($text);
        });
    }

    private static function twig(): Environment
    {
        if (!isset(self::$twig)) {
            $path = Setup::get('folder') . '/Core/View';
            $loader = new FilesystemLoader($path);
            self::$twig = new Environment($loader);

            self::$twig->addFunction(self::assetFunction());
            self::$twig->addFunction(self::attachedFileFunction());
            self::$twig->addFunction(self::formTokenFunction());
            self::$twig->addFunction(self::transFunction());
        }

        return self::$twig;
    }
}