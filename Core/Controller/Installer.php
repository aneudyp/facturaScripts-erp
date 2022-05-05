<?php

namespace FacturaScripts\Core\Controller;

use DateTimeZone;
use FacturaScripts\Core\Base\PluginManager;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Html;

class Installer implements ControllerInterface
{
    public function __construct(string $url)
    {
    }

    public function run(): void
    {
        $templateVars = [
            'license' => file_get_contents(FS_FOLDER . DIRECTORY_SEPARATOR . 'COPYING'),
            'memcache_prefix' => ToolBox::utils()->randomString(8),
            'timezones' => DateTimeZone::listIdentifiers(),
            'version' => PluginManager::CORE_VERSION
        ];
        echo Html::render('Installer/install.html.twig', $templateVars);
    }

    private function createDataBase(): bool
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
            ToolBox::i18nLog()->warning('database-name-must-be-lowercase');
            return false;
        }

        switch ($dbType) {
            case 'mysql':
                if (class_exists('mysqli')) {
                    return $this->testMysql($dbData);
                }

                ToolBox::i18nLog()->critical('php-extension-not-found', ['%extension%' => 'mysqli']);
                return false;

            case 'postgresql':
                if (function_exists('pg_connect')) {
                    return $this->testPostgreSql($dbData);
                }

                ToolBox::i18nLog()->critical('php-extension-not-found', ['%extension%' => 'postgresql']);
                return false;
        }

        ToolBox::i18nLog()->critical('cant-connect-database');
        return false;
    }

    private function createFolders(): bool
    {
        // Check each needed folder to deploy
        foreach (['Plugins', 'Dinamic', 'MyFiles'] as $folder) {
            if (false === ToolBox::files()->createFolder($folder)) {
                ToolBox::i18nLog()->critical('cant-create-folders', ['%folder%' => $folder]);
                return false;
            }
        }

        $pluginManager = new PluginManager();
        $pluginManager->deploy();
        return true;
    }

    private function getUserLanguage(): string
    {
        $dataLanguage = explode(';', filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE'));
        $userLanguage = str_replace('-', '_', explode(',', $dataLanguage[0])[0]);
        return file_exists(FS_FOLDER . '/Core/Translation/' . $userLanguage . '.json') ? $userLanguage : 'en_EN';
    }

    private function saveHtaccess(): bool
    {
        $contentFile = ToolBox::files()->extractFromMarkers(FS_FOLDER . DIRECTORY_SEPARATOR . 'htaccess-sample', 'FacturaScripts code');
        return ToolBox::files()->insertWithMarkers($contentFile, FS_FOLDER . DIRECTORY_SEPARATOR . '.htaccess', 'FacturaScripts code');
    }

    private function saveInstall(): bool
    {
        $file = fopen(FS_FOLDER . '/config.php', 'wb');
        if (is_resource($file)) {
            fwrite($file, "<?php\n");
            fwrite($file, "define('FS_COOKIES_EXPIRE', " . $this->request->request->get('fs_cookie_expire', 604800) . ");\n");
            fwrite($file, "define('FS_ROUTE', '" . $this->request->request->get('fs_route', $this->getUri()) . "');\n");
            fwrite($file, "define('FS_DB_FOREIGN_KEYS', true);\n");
            fwrite($file, "define('FS_DB_TYPE_CHECK', true);\n");
            fwrite($file, "define('FS_MYSQL_CHARSET', 'utf8');\n");
            fwrite($file, "define('FS_MYSQL_COLLATE', 'utf8_bin');\n");

            $fields = [
                'lang', 'timezone', 'db_type', 'db_host', 'db_port', 'db_name', 'db_user',
                'db_pass', 'cache_host', 'cache_port', 'cache_prefix', 'hidden_plugins'
            ];
            foreach ($fields as $field) {
                fwrite($file, "define('FS_" . strtoupper($field) . "', '" . $this->request->request->get('fs_' . $field, '') . "');\n");
            }

            $booleanFields = ['debug', 'disable_add_plugins', 'disable_rm_plugins'];
            foreach ($booleanFields as $field) {
                fwrite($file, "define('FS_" . strtoupper($field) . "', " . $this->request->request->get('fs_' . $field, 'false') . ");\n");
            }

            if ($this->request->request->get('db_type') === 'MYSQL' && $this->request->request->get('mysql_socket') !== '') {
                fwrite($file, "\nini_set('mysqli.default_socket', '" . $this->request->request->get('mysql_socket') . "');\n");
            }

            fwrite($file, "\n");
            fclose($file);
            return true;
        }

        ToolBox::i18nLog()->critical('cant-save-install');
        return false;
    }

    private function searchErrors(): bool
    {
        $errors = false;

        if ((float)'3,1' >= (float)'3.1') {
            ToolBox::i18nLog()->critical('wrong-decimal-separator');
            $errors = true;
        }

        foreach (['bcmath', 'curl', 'fileinfo', 'gd', 'mbstring', 'openssl', 'simplexml', 'zip'] as $extension) {
            if (false === extension_loaded($extension)) {
                ToolBox::i18nLog()->critical('php-extension-not-found', ['%extension%' => $extension]);
                $errors = true;
            }
        }

        if (function_exists('apache_get_modules') && false === in_array('mod_rewrite', apache_get_modules())) {
            ToolBox::i18nLog()->critical('apache-module-not-found', ['%module%' => 'mod_rewrite']);
            $errors = true;
        }

        if (false === is_writable(FS_FOLDER)) {
            ToolBox::i18nLog()->critical('folder-not-writable');
            $errors = true;
        }

        return $errors;
    }

    private function testMysql(array $dbData): bool
    {
        if ($dbData['socket'] !== '') {
            ini_set('mysqli.default_socket', $dbData['socket']);
        }

        // Omit the DB name because it will be checked on a later stage
        $connection = @new mysqli($dbData['host'], $dbData['user'], $dbData['pass'], '', (int)$dbData['port']);
        if ($connection->connect_error) {
            ToolBox::i18nLog()->critical('cant-connect-database');
            ToolBox::log()->critical($connection->connect_errno . ': ' . $connection->connect_error);
            return false;
        }

        $sqlCrearBD = 'CREATE DATABASE IF NOT EXISTS `' . $dbData['name'] . '`;';
        return (bool)$connection->query($sqlCrearBD);
    }

    private function testPostgreSql(array $dbData): bool
    {
        $connectionStr = 'host=' . $dbData['host'] . ' port=' . $dbData['port'];
        $connection = @pg_connect($connectionStr . ' dbname=postgres user=' . $dbData['user'] . ' password=' . $dbData['pass']);
        if (is_resource($connection)) {
            // Check that the DB exists, if it doesn't, we try to create a new one
            $sqlExistsBD = "SELECT 1 AS result FROM pg_database WHERE datname = '" . $dbData['name'] . "';";
            $result = pg_query($connection, $sqlExistsBD);
            if (is_resource($result) && pg_num_rows($result) > 0) {
                return true;
            }

            $sqlCreateBD = 'CREATE DATABASE "' . $dbData['name'] . '";';
            if (false !== pg_query($connection, $sqlCreateBD)) {
                return true;
            }
        }

        ToolBox::i18nLog()->critical('cant-connect-database');
        if (is_resource($connection) && pg_last_error($connection) != false) {
            ToolBox::log()->critical(pg_last_error($connection));
        }

        return false;
    }
}