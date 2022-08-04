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

use FacturaScripts\Core\Base\CronClass;
use FacturaScripts\Core\Base\InitClass;

final class Plugins
{
    const FILE = 'MyFiles/plugins.json';

    private static $list;

    public static function cron(string $plugin): ?CronClass
    {
        $className = 'FacturaScripts\\Plugins\\' . $plugin . '\\Cron';
        if (class_exists($className) && in_array($plugin, self::enabled())) {
            return new $className();
        }

        return null;
    }

    public static function enabled(): array
    {
        self::load();

        $enabled = [];
        foreach (self::$list as $value) {
            if ($value['enabled']) {
                $enabled[] = $value['name'];
            }
        }

        return $enabled;
    }

    public static function init(string $plugin): ?InitClass
    {
        $class = "FacturaScripts\\Plugins\\$plugin\\Init";
        if (class_exists($class) && in_array($plugin, self::enabled())) {
            return new $class();
        }

        return null;
    }

    private static function load(): void
    {
        if (self::$list !== null) {
            return;
        }

        if (false === file_exists(self::FILE)) {
            self::$list = [];
            return;
        }

        $content = file_get_contents(self::FILE);
        if ($content) {
            self::$list = json_decode($content, true);
        }
    }
}
