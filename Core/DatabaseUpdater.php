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
use FacturaScripts\Core\Bridge\DatabaseUpdaterMysql;
use FacturaScripts\Core\Contract\DbUpdaterInterface;

final class DatabaseUpdater
{
    /**
     * @var DbUpdaterInterface
     */
    private static $bridge;

    public static function checkAllTables(): bool
    {
        $tables = [
            'logs', 'pages', 'roles', 'roles_access', 'attached_files', 'empresas', 'users', 'attached_files_rel',
            'roles_users', 'pages_filters', 'pages_options', 'settings', 'agenciastrans', 'almacenes', 'api_keys',
            'api_access', 'atributos', 'atributos_valores', 'balances', 'balancescuentas', 'balancescuentasabreviadas',
            'conceptos_partidas', 'cronjobs', 'cuentasbanco', 'cuentasesp', 'diarios', 'divisas', 'doctransformations',
            'ejercicios', 'cuentas', 'subcuentas', 'asientos', 'partidas', 'emails_notifications', 'emails_sent',
            'estados_documentos', 'fabricantes', 'familias', 'formaspago', 'idsfiscales', 'impuestos', 'impuestoszonas',
            'paises', 'provincias', 'ciudades', 'productos', 'variantes', 'stocks', 'reports', 'reportsamounts',
            'reportsbalance', 'reportsledger', 'retenciones', 'series', 'secuencias_documentos', 'tarifas', 'agentes',
            'gruposclientes', 'clientes', 'cuentasbcocli', 'proveedores', 'cuentasbcopro', 'contactos', 'albaranescli',
            'pedidoscli', 'presupuestoscli', 'facturascli', 'lineasalbaranescli', 'lineasfacturascli',
            'lineaspedidoscli', 'lineaspresupuestoscli', 'albaranesprov', 'pedidosprov', 'presupuestosprov',
            'facturasprov', 'lineasalbaranesprov', 'lineasfacturasprov', 'lineaspedidosprov', 'lineaspresupuestosprov',
            'recibospagoscli', 'pagoscli', 'recibospagosprov', 'pagosprov', 'regularizacionimpuestos'
        ];

        foreach ($tables as $table) {
            if (false === self::checkTable($table)) {
                echo "Fail on table $table.\n";
                return false;
            }
        }

        return true;
    }

    /**
     * @throws KernelException
     * @throws Exception
     */
    public static function checkTable(string $tableName): bool
    {
        $filePath = self::getXmlTableLocation($tableName);
        $structure = self::readXmlTable($filePath);
        if (false === Database::tableExists($tableName)) {
            return self::createTable($tableName, $structure);
        }

        return self::updateTable($tableName, $structure);
    }

    private static function getXmlTableLocation(string $tableName): string
    {
        $dynPath = Setup::get('folder') . '/Dinamic/Table/' . $tableName . '.xml';
        if (file_exists($dynPath)) {
            return $dynPath;
        }

        return Setup::get('folder') . '/Core/Table/' . $tableName . '.xml';
    }

    /**
     * @throws Exception
     */
    public static function readXmlTable(string $filePath): array
    {
        $structure = [
            'columns' => [],
            'constraints' => []
        ];
        $xml = simplexml_load_file($filePath);

        // columns
        foreach ($xml->column as $col) {
            $structure['columns'][(string)$col->name] = [
                'name' => (string)$col->name,
                'type' => self::checkColumnType((string)$col->type),
                'null' => self::checkColumnNull((string)$col->type, $col->null),
                'default' => isset($col->default) && (string)$col->type != 'serial' ? (string)$col->default : null
            ];
        }

        // constraints
        foreach ($xml->constraint as $col) {
            $structure['constraints'][(string)$col->name] = [
                'name' => (string)$col->name,
                'type' => (string)$col->type
            ];
        }

        return $structure;
    }

    /**
     * @throws Exception
     */
    private static function checkColumnType(string $type): string
    {
        $validTypes = ['boolean', 'date', 'double precision', 'integer', 'serial', 'text', 'time', 'timestamp'];
        if (in_array($type, $validTypes)) {
            return $type;
        } elseif (str_starts_with($type, 'character varying')) {
            return $type;
        }

        throw new Exception('invalid-db-column-type: ' . $type);
    }

    private static function checkColumnNull(string $type, $null): string
    {
        if ($type === 'serial') {
            return 'NO';
        }

        return $null && strtolower($null) === 'no' ? 'NO' : 'YES';
    }

    public static function createTable(string $tableName, array $structure): bool
    {
        return self::bridge()->createTable($tableName, $structure);
    }

    private static function bridge(): DbUpdaterInterface
    {
        if (!isset(self::$bridge)) {
            self::$bridge = new DatabaseUpdaterMysql();
        }

        return self::$bridge;
    }

    public static function updateTable(string $tableName, array $structure): bool
    {
        return self::bridge()->updateTable($tableName, $structure);
    }

    public static function dropTable(string $tableName): bool
    {
        return self::bridge()->dropTable($tableName);
    }
}