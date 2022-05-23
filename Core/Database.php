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

use FacturaScripts\Core\Bridge\DatabaseMysql;
use FacturaScripts\Core\Contract\DbInterface;

final class Database
{
    public const CHANNEL = 'database';
    public const LIMIT = 50;

    /**
     * @var DbInterface
     */
    private static $bridge;

    /**
     * @var bool
     */
    private static $inTransaction = false;

    /**
     * @var Logger
     */
    private static $logger;

    /**
     * @throws KernelException
     */
    public static function beginTransaction(): bool
    {
        if (self::bridge()->beginTransaction()) {
            self::$inTransaction = true;
            return true;
        }

        return false;
    }

    /**
     * @throws KernelException
     */
    private static function bridge(): DbInterface
    {
        if (isset(self::$bridge)) {
            return self::$bridge;
        }

        if (empty(Setup::get('db_host'))) {
            throw new KernelException('DatabaseError', 'no-db-setup');
        }

        self::$bridge = new DatabaseMysql(
            Setup::get('db_host'),
            Setup::get('db_user'),
            Setup::get('db_pass'),
            Setup::get('db_name'),
            Setup::get('db_port')
        );
        return self::$bridge;
    }

    /**
     * @throws KernelException
     */
    public static function close(): bool
    {
        self::$inTransaction = false;
        return is_null(self::$bridge) || self::bridge()->close();
    }

    /**
     * @throws KernelException
     */
    public static function commit(): bool
    {
        if (self::bridge()->commit()) {
            self::$inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * @throws KernelException
     */
    public static function escapeColumn(string $name): string
    {
        return self::bridge()->escapeColumn($name);
    }

    /**
     * @throws KernelException
     */
    public static function escapeString(string $str): string
    {
        return self::bridge()->escapeString($str);
    }

    /**
     * @throws KernelException
     */
    public static function exec(string $sql): bool
    {
        self::log()->debug($sql);
        $return = self::bridge()->exec($sql);
        if (self::bridge()->getLastErrorMsg()) {
            self::log()->error(self::bridge()->getLastErrorMsg(), ['sql' => $sql]);
            self::bridge()->clearLastErrorMsg();
        }
        return $return;
    }

    /**
     * @throws KernelException
     */
    public static function getColumns(string $tableName): array
    {
        return self::bridge()->getColumns($tableName);
    }

    /**
     * @throws KernelException
     */
    public static function getConstraints(string $tableName): array
    {
        return self::bridge()->getConstraints($tableName);
    }

    public static function inTransaction(): bool
    {
        return self::$inTransaction;
    }

    /**
     * @throws KernelException
     */
    public static function getTables(): array
    {
        return self::bridge()->getTables();
    }

    /**
     * @throws KernelException
     */
    public static function lastval(): int
    {
        return self::bridge()->lastval();
    }

    /**
     * @throws KernelException
     */
    public static function rollback(): bool
    {
        if (self::bridge()->rollback()) {
            self::$inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * @throws KernelException
     */
    public static function select(string $sql): array
    {
        return self::selectLimit($sql, 0);
    }

    /**
     * @throws KernelException
     */
    public static function selectLimit(string $sql, int $limit = self::LIMIT, int $offset = 0): array
    {
        self::log()->debug($sql);
        $return = self::bridge()->select($sql, $limit, $offset);
        if (self::bridge()->getLastErrorMsg()) {
            self::log()->error(self::bridge()->getLastErrorMsg(), ['sql' => $sql]);
            self::bridge()->clearLastErrorMsg();
        }
        return $return;
    }

    /**
     * @throws KernelException
     */
    public static function tableExists(string $tableName): bool
    {
        foreach (self::bridge()->getTables() as $table) {
            if ($table === $tableName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws KernelException
     */
    public static function var2str($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (is_bool($val)) {
            return $val ? 'TRUE' : 'FALSE';
        }

        // it's a date?
        if (preg_match("/^([\d]{1,2})-([\d]{1,2})-([\d]{4})$/i", $val)) {
            return "'" . date(self::bridge()->dateStyle(), strtotime($val)) . "'";
        }

        // it's a date time?
        if (preg_match("/^([\d]{1,2})-([\d]{1,2})-([\d]{4}) ([\d]{1,2}):([\d]{1,2}):([\d]{1,2})$/i", $val)) {
            return "'" . date(self::bridge()->dateStyle() . ' H:i:s', strtotime($val)) . "'";
        }

        return "'" . self::escapeString($val) . "'";
    }

    /**
     * @throws KernelException
     */
    public static function updateSequence(string $tableName, string $fields): void
    {
        self::bridge()->updateSequence($tableName, $fields);
    }

    /**
     * @throws KernelException
     */
    public static function version(): string
    {
        return self::bridge()->version();
    }

    private static function log(): Logger
    {
        if (!isset(self::$logger)) {
            self::$logger = new Logger(self::CHANNEL);
        }

        return self::$logger;
    }
}
