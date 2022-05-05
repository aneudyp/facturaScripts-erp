<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Core\Base;

/**
 * Generic class of access to the database, either MySQL or PostgreSQL.
 *
 * @author Carlos García Gómez  <carlos@facturascripts.com>
 * @author Artex Trading sa     <jcuello@artextrading.com>
 */
final class DataBase
{

    public function beginTransaction()
    {
        return \FacturaScripts\Core\Database::beginTransaction();
    }

    public function close(): bool
    {
        return \FacturaScripts\Core\Database::close();
    }

    public function commit()
    {
        return \FacturaScripts\Core\Database::commit();
    }

    public function connect(): bool
    {
        return true;
    }

    public function connected(): bool
    {
        return true;
    }

    public function escapeColumn($name)
    {
        return \FacturaScripts\Core\Database::escapeColumn($name);
    }

    public function escapeString($str)
    {
        return \FacturaScripts\Core\Database::escapeString($str);
    }

    public function exec($sql)
    {
        return \FacturaScripts\Core\Database::exec($sql);
    }

    public function getColumns($tableName)
    {
        return \FacturaScripts\Core\Database::getColumns($tableName);
    }

    public function getConstraints($tableName, $extended = false)
    {
        return \FacturaScripts\Core\Database::getConstraints($tableName);
    }

    public function getIndexes($tableName)
    {
        return [];
    }

    public function getTables()
    {
        return \FacturaScripts\Core\Database::getTables();
    }

    public function inTransaction()
    {
        return \FacturaScripts\Core\Database::inTransaction();
    }

    public function lastval()
    {
        return \FacturaScripts\Core\Database::lastval();
    }

    public function rollback()
    {
        return \FacturaScripts\Core\Database::rollback();
    }

    public function select($sql)
    {
        return \FacturaScripts\Core\Database::select($sql);
    }

    public function selectLimit($sql, $limit = FS_ITEM_LIMIT, $offset = 0)
    {
        return \FacturaScripts\Core\Database::selectLimit($sql, $limit, $offset);
    }

    public function tableExists($tableName, array $list = [])
    {
        return \FacturaScripts\Core\Database::tableExists($tableName);
    }

    public function var2str($val)
    {
        return \FacturaScripts\Core\Database::var2str($val);
    }

    public function version()
    {
        return \FacturaScripts\Core\Database::version();
    }
}
