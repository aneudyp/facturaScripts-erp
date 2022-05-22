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

use FacturaScripts\Core\Model\Settings;

final class Setup
{
    private static $data = [];

    private static $settings = [];

    /**
     * @param string $property
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get(string $property, $default = null)
    {
        // properties are case-insensitive
        $name = strtolower($property);
        return self::$data[$name] ?? $default;
    }

    public static function load(string $folder): void
    {
        self::set('folder', $folder);
        define('FS_FOLDER', $folder);

        $fields = [
            'cookies_expire', 'db_type', 'db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'lang', 'route', 'timezone'
        ];

        // load config.php if exists
        if (file_exists($folder . '/config.php')) {
            require_once $folder . '/config.php';
            foreach ($fields as $field) {
                $constant = 'FS_' . strtoupper($field);
                if (defined($constant)) {
                    self::set($field, constant($constant));
                }
            }
            return;
        }

        // load data from env variables
        $env = getenv();
        foreach ($env as $key => $value) {
            // properties are case-insensitive
            $name = strtolower($key);
            if (in_array($name, $fields)) {
                self::set($name, $value);
            }
        }
    }

    public static function read(string $group, string $property, $default = null)
    {
        if (!isset(self::$settings[$group][$property])) {
            $settings = new Settings();
            if ($settings->loadFromCode($group)) {
                self::$settings[$group] = $settings->properties;
            }
        }

        return self::$settings[$group][$property] ?? $default;
    }

    public static function set(string $property, $value): void
    {
        // properties are case-insensitive
        $name = strtolower($property);
        if (false === array_key_exists($name, self::$data)) {
            self::$data[$name] = $value;
        }
    }
}
