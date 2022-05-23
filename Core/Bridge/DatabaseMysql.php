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
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Setup;
use mysqli;

final class DatabaseMysql implements DbInterface
{
    private $lastErrorMsg = '';

    private $link;

    /**
     * @throws KernelException
     */
    public function __construct(string $host, string $user, string $passwd, string $dbname, int $port)
    {
        $this->link = new mysqli($host, $user, $passwd, $dbname, $port);

        // failure?
        if ($this->link->connect_errno) {
            throw new KernelException('DatabaseError', $this->link->connect_error);
        }

        // disable foreign keys?
        if (false === Setup::get('foreign_keys')) {
            $this->exec('SET foreign_key_checks = 0;');
        }
    }

    public function beginTransaction(): bool
    {
        return $this->exec('START TRANSACTION;');
    }

    public function clearLastErrorMsg(): void
    {
        $this->lastErrorMsg = '';
    }

    public function close(): bool
    {
        return empty($this->link) || $this->link->close();
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
        return '`' . $name . '`';
    }

    public function escapeString(string $str): string
    {
        return $this->link->escape_string($str);
    }

    public function exec(string $sql): bool
    {
        if ($this->link->multi_query($sql)) {
            do {
                $more = $this->link->more_results() && $this->link->next_result();
            } while ($more);
        }

        $this->lastErrorMsg = $this->link->error ?? '';
        return $this->link->errno === 0;
    }

    public function getColumns(string $tableName): array
    {
        $columns = [];
        foreach ($this->select("SHOW COLUMNS FROM `$tableName`;") as $row) {
            $data = [
                'name' => $row['Field'],
                'type-full' => $row['Type'],
                'type' => $row['Type'],
                'size' => 0,
                'nullable' => $row['Null'] === 'YES',
                'default' => $row['Default']
            ];

            if (str_contains($row['Type'], '(')) {
                $data['type'] = substr($row['Type'], 0, strpos($row['Type'], '('));
                $data['size'] = (int)substr($row['Type'], 1 + strpos($row['Type'], '('), -1);
            }

            $columns[$row['Field']] = $data;
        }
        return $columns;
    }

    public function getConstraints(string $tableName): array
    {
        $constraints = [];
        $sql = 'SELECT CONSTRAINT_NAME as name, CONSTRAINT_TYPE as type'
            . ' FROM information_schema.table_constraints '
            . ' WHERE table_schema = schema()'
            . " AND table_name = '$tableName';";
        foreach ($this->select($sql) as $row) {
            $constraints[$row['name']] = $row;
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
        foreach ($this->select('SHOW TABLES;') as $row) {
            $prefix = 'Tables_in_';
            foreach ($row as $key => $value) {
                if (str_starts_with($key, $prefix)) {
                    $tables[] = $value;
                }
            }
        }

        return $tables;
    }

    public function lastval(): int
    {
        foreach ($this->select('SELECT LAST_INSERT_ID() as num;') as $row) {
            return (int)$row['num'];
        }

        return 0;
    }

    public function rollback(): bool
    {
        return $this->exec('ROLLBACK;');
    }

    public function select(string $sql, int $limit = 0, int $offset = 0): array
    {
        if ($limit > 0) {
            // add limit and offset to the sql query
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset . ';';
        }

        $rows = [];
        $results = $this->link->query($sql);
        while ($row = $results->fetch_array(MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
        $this->lastErrorMsg = $this->link->error ?? '';
        $results->close();
        return $rows;
    }

    public function updateSequence(string $tableName, string $fields): void
    {
        // unnecessary on mysql
    }

    public function version(): string
    {
        return 'MYSQL ' . $this->link->server_version;
    }
}
