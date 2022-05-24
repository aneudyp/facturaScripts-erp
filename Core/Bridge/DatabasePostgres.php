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

namespace FacturaScripts\Core\Bridge;

use FacturaScripts\Core\Contract\DbInterface;
use FacturaScripts\Core\Setup;

final class DatabasePostgres implements DbInterface
{
    private $lastErrorMsg = '';

    private $link;

    public function __construct(string $host, string $user, string $passwd, string $dbname, int $port)
    {
        $this->link = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$passwd");
    }

    public function beginTransaction(): bool
    {
        return $this->exec('START TRANSACTION;');
    }

    public function castColumn(string $name, string $type): string
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return "CAST($name AS INTEGER)";

            case 'double':
                return "CAST($name AS DOUBLE PRECISION)";

            case 'string':
                return "CAST($name AS TEXT)";

            case 'boolean':
                return "CAST($name AS BOOLEAN)";

            case 'date':
                return "CAST($name AS DATE)";

            case 'datetime':
                return "CAST($name AS TIMESTAMP)";

            default:
                return $name;
        }
    }

    public function clearLastErrorMsg(): void
    {
        $this->lastErrorMsg = '';
    }

    public function close(): bool
    {
        return empty($this->link) || pg_close($this->link);
    }

    public function commit(): bool
    {
        return $this->exec('COMMIT;');
    }

    public function dateStyle(): string
    {
        return 'Y-m-d';
    }

    public function escapeColumn(string $name): string
    {
        return '"' . $name . '"';
    }

    public function escapeString(string $str): string
    {
        return pg_escape_string($this->link, $str);
    }

    public function exec(string $sql): bool
    {
        $result = pg_query($this->link, $sql);
        if ($result === false) {
            $this->lastErrorMsg = pg_last_error($this->link);
            return false;
        }

        return true;
    }

    public function getColumns(string $tableName): array
    {
        $columns = [];
        $sql = 'SELECT column_name as name, data_type as type, character_maximum_length, column_default as default, is_nullable'
            . ' FROM information_schema.columns'
            . " WHERE table_catalog = '" . Setup::get('db_name') . "' AND table_name = '" . $tableName . "'"
            . ' ORDER BY 1 ASC;';
        foreach ($this->select($sql) as $row) {
            $data = [
                'name' => $row['name'],
                'type-full' => $row['type'],
                'type' => $row['type'],
                'size' => 0,
                'nullable' => $row['Null'] === 'YES',
                'default' => $row['Default']
            ];
            $columns[$row['Field']] = $data;
        }
        return $columns;
    }

    public function getConstraints(string $tableName): array
    {
        $constraints = [];
        $sql = 'SELECT constraint_name as name, constraint_type as type'
            . ' FROM information_schema.table_constraints'
            . " WHERE table_catalog = '" . Setup::get('db_name') . "' AND table_name = '" . $tableName . "'"
            . ' ORDER BY 1 ASC;';
        foreach ($this->select($sql) as $row) {
            $constraints[$row['name']] = $row['type'];
        }
        return $constraints;
    }

    public function getLastErrorMsg(): string
    {
        return $this->lastErrorMsg;
    }

    public function getTables(): array
    {
        $tables = [];
        $sql = 'SELECT table_name as name'
            . ' FROM information_schema.tables'
            . " WHERE table_catalog = '" . Setup::get('db_name') . "' AND table_type = 'BASE TABLE'"
            . ' ORDER BY 1 ASC;';
        foreach ($this->select($sql) as $row) {
            $tables[] = $row['name'];
        }
        return $tables;
    }

    public function lastval(): int
    {
        $sql = 'SELECT lastval() as num;';
        foreach ($this->select($sql) as $row) {
            return (int)$row['num'];
        }

        return 0;
    }

    public function rollback(): bool
    {
        return $this->exec('ROLLBACK;');
    }

    public function select(string $sql, int $limit, int $offset): array
    {
        $result = pg_query_params($this->link, $sql, [$limit, $offset]);
        if ($result === false) {
            $this->lastErrorMsg = pg_last_error($this->link);
            return [];
        }

        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        pg_free_result($result);
        return $rows;
    }

    public function updateSequence(string $tableName, array $fields): void
    {
        foreach ($fields as $colName => $field) {
            // serial type
            if (stripos($field['default'], 'nextval(') !== false) {
                $sql = "SELECT setval('" . $tableName . "_" . $colName . "_seq', (SELECT MAX(" . $colName . ") from " . $tableName . "));";
                $this->exec($sql);
            }
        }
    }

    public function version(): string
    {
        return 'POSTGRESQL ' . pg_version($this->link)['server'];
    }
}
