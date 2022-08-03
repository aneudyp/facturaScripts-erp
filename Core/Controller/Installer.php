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

use DateTimeZone;
use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Setup;
use FacturaScripts\Core\Tools;
use mysqli;
use ParseCsv\Csv;
use Symfony\Component\HttpFoundation\Request;

final class Installer implements ControllerInterface
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $url;

    public function __construct(string $url)
    {
        $this->request = Request::createFromGlobals();
        $this->url = $url;
    }

    public function run(): void
    {
        $lang = $this->request->get('fs_lang', Setup::get('lang'));
        Setup::set('lang', $lang);

        $action = $this->request->get('action');
        switch ($action) {
            case 'deploy':
                break;

            case 'install':
                if ($this->installAction()) {
                    echo Html::render('Installer/Deploy.html.twig');
                    return;
                }
                break;

            case 'phpinfo':
                phpinfo();
                return;
        }

        echo Html::render('Installer/install.html.twig', [
            'countries' => $this->getAllCountries(),
            'license' => file_get_contents(FS_FOLDER . DIRECTORY_SEPARATOR . 'COPYING'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'version' => Plugins::CORE_VERSION
        ]);
    }

    private function createDatabase(): bool
    {
        $dbData = [
            'host' => $this->request->request->get('fs_db_host'),
            'port' => $this->request->request->get('fs_db_port'),
            'user' => $this->request->request->get('fs_db_user'),
            'pass' => $this->request->request->get('fs_db_pass'),
            'name' => $this->request->request->get('fs_db_name'),
            'socket' => $this->request->request->get('mysql_socket', '')
        ];

        $dbType = $this->request->request->get('fs_db_type');
        if ('postgresql' == $dbType && strtolower($dbData['name']) != $dbData['name']) {
            Tools::i18nLog()->warning('database-name-must-be-lowercase');
            return false;
        }

        switch ($dbType) {
            case 'mysql':
                if (class_exists('mysqli')) {
                    return $this->testMysql($dbData);
                }

                Tools::i18nLog()->critical('php-extension-not-found', ['%extension%' => 'mysqli']);
                return false;

            case 'postgresql':
                if (function_exists('pg_connect')) {
                    return $this->testPostgres($dbData);
                }

                Tools::i18nLog()->critical('php-extension-not-found', ['%extension%' => 'postgresql']);
                return false;
        }

        Tools::i18nLog()->critical('cant-connect-database');
        return false;
    }

    private function createFolders(): bool
    {
        // Check each needed folder to deploy
        foreach (['Plugins', 'Dinamic', 'MyFiles'] as $folder) {
            if (false === Tools::files()->createFolder($folder)) {
                Tools::i18nLog()->critical('cant-create-folders', ['%folder%' => $folder]);
                return false;
            }
        }

        Plugins::deploy();
        return true;
    }

    private function getAllCountries(): array
    {
        $csv = new Csv();
        $csv->auto('Core/Data/Lang/es/paises.csv');

        $list = [];
        foreach ($csv->data as $row) {
            $list[$row['codpais']] = $row['nombre'];
        }
        return $list;
    }

    private function installAction(): bool
    {
        if ($this->searchErrors()) {
            return false;
        }

        return $this->createDatabase() && $this->createFolders() && $this->saveConfig() && $this->saveHtaccess();
    }

    private function saveConfig(): bool
    {
        $route = substr($this->url, 0, strrpos($this->url, '/'));
        $file = fopen(FS_FOLDER . '/config.php', 'wb');
        if (false === is_resource($file)) {
            Tools::i18nLog()->critical('cant-save-install');
            return false;
        }

        fwrite($file, "<?php\n");
        fwrite($file, "define('FS_COOKIES_EXPIRE', "
            . $this->request->request->get('fs_cookie_expire', 604800) . ");\n");
        fwrite($file, "define('FS_ROUTE', '" . $this->request->request->get('fs_route', $route) . "');\n");
        fwrite($file, "define('FS_DB_FOREIGN_KEYS', true);\n");
        fwrite($file, "define('FS_DB_TYPE_CHECK', true);\n");
        fwrite($file, "define('FS_MYSQL_CHARSET', 'utf8');\n");
        fwrite($file, "define('FS_MYSQL_COLLATE', 'utf8_bin');\n");

        $fields = [
            'lang', 'timezone', 'db_type', 'db_host', 'db_port', 'db_name', 'db_user',
            'db_pass', 'cache_host', 'cache_port', 'cache_prefix', 'hidden_plugins'
        ];
        foreach ($fields as $field) {
            fwrite($file, "define('FS_" . strtoupper($field) . "', '"
                . $this->request->request->get('fs_' . $field, '') . "');\n");
        }

        $booleanFields = ['debug', 'disable_add_plugins', 'disable_rm_plugins'];
        foreach ($booleanFields as $field) {
            fwrite($file, "define('FS_" . strtoupper($field) . "', "
                . $this->request->request->get('fs_' . $field, 'false') . ");\n");
        }

        if ($this->request->request->get('db_type') === 'MYSQL' &&
            $this->request->request->get('mysql_socket') !== '') {
            fwrite($file, "\nini_set('mysqli.default_socket', '"
                . $this->request->request->get('mysql_socket') . "');\n");
        }

        fwrite($file, "\n");
        fclose($file);
        return true;
    }

    private function saveHtaccess(): bool
    {
        $contentFile = Tools::files()->extractFromMarkers(
            FS_FOLDER . DIRECTORY_SEPARATOR . 'htaccess-sample', 'FacturaScripts code'
        );
        return Tools::files()->insertWithMarkers(
            $contentFile, FS_FOLDER . DIRECTORY_SEPARATOR . '.htaccess', 'FacturaScripts code'
        );
    }

    private function searchErrors(): bool
    {
        $errors = false;

        if ((float)'3,1' >= (float)'3.1') {
            Tools::i18nLog()->critical('wrong-decimal-separator');
            $errors = true;
        }

        foreach (['bcmath', 'curl', 'fileinfo', 'gd', 'mbstring', 'openssl', 'simplexml', 'zip'] as $extension) {
            if (false === extension_loaded($extension)) {
                Tools::i18nLog()->critical('php-extension-not-found', ['%extension%' => $extension]);
                $errors = true;
            }
        }

        if (function_exists('apache_get_modules') && false === in_array('mod_rewrite', apache_get_modules())) {
            Tools::i18nLog()->critical('apache-module-not-found', ['%module%' => 'mod_rewrite']);
            $errors = true;
        }

        if (false === is_writable(FS_FOLDER)) {
            Tools::i18nLog()->critical('folder-not-writable');
            $errors = true;
        }

        return $errors;
    }

    private function testMysql(array $params): bool
    {
        if ($params['socket'] !== '') {
            ini_set('mysqli.default_socket', $params['socket']);
        }

        // Omit the DB name because it will be checked on a later stage
        $connection = new mysqli($params['host'], $params['user'], $params['pass'], '', (int)$params['port']);
        if ($connection->connect_error) {
            Tools::i18nLog()->critical('cant-connect-database');
            Tools::log()->critical($connection->connect_errno . ': ' . $connection->connect_error);
            return false;
        }

        $sql = 'CREATE DATABASE IF NOT EXISTS `' . $params['name'] . '`;';
        return (bool)$connection->query($sql);
    }

    private function testPostgres(array $params): bool
    {
        $connStr = 'host=' . $params['host'] . ' port=' . $params['port'];
        $connection = pg_connect($connStr . ' dbname=postgres user=' . $params['user'] . ' password=' . $params['pass']);
        if (false === is_resource($connection)) {
            Tools::i18nLog()->critical('cant-connect-database');
            return false;
        }

        // Check that the DB exists, if it doesn't, we try to create a new one
        $sqlExists = "SELECT 1 AS result FROM pg_database WHERE datname = '" . $params['name'] . "';";
        $result = pg_query($connection, $sqlExists);
        if (is_resource($result) && pg_num_rows($result) > 0) {
            return true;
        }

        // create the DB
        $sqlCreate = 'CREATE DATABASE "' . $params['name'] . '";';
        if (false !== pg_query($connection, $sqlCreate)) {
            return true;
        }

        if (pg_last_error($connection) != false) {
            Tools::log()->critical(pg_last_error($connection));
        }
        return false;
    }
}
