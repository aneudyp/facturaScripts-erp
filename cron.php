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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Controller\Cron;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Setup;

if (php_sapi_name() !== "cli") {
    die("Please use command line: php cron.php\n");
}

// checks the PHP version
if (version_compare(PHP_VERSION, '7.2') < 0) {
    die('You need PHP 7.2 or later<br/>You have PHP ' . phpversion());
}

// set up the autoloader
require_once __DIR__ . '/vendor/autoload.php';

// change to the file folder, to prevent path problems
chdir(__DIR__);

// set up the config and session
Setup::load(__DIR__);
Session::init();

// run all plugins init
foreach (Plugins::enabled() as $plugin) {
    $init = Plugins::init($plugin);
    if ($init) {
        $init->init();
    }
}

// run the cron controller
$cron = new Cron('');
$cron->run();

// disconnect from the database
$db = new DataBase();
$db->close();

// remove old data from the cache
Cache::expire();
