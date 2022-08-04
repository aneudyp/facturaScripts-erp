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
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Setup;

// checks the PHP version
if (version_compare(PHP_VERSION, '7.2') < 0) {
    die('You need PHP 7.2 or later<br/>You have PHP ' . phpversion());
}

// set up the autoloader
require_once __DIR__ . '/vendor/autoload.php';

// set up the error handler
register_shutdown_function('FacturaScripts\\Core\\Kernel::errorHandler');

// set up the config and session
Setup::load(__DIR__);
Session::init();

// security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000');

// gets the url to serve and runs the kernel
$url = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
Kernel::run($url);

// disconnect from the database
$db = new DataBase();
$db->close();
