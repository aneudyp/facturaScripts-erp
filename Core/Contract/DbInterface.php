<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2021-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Core\Contract;

interface DbInterface
{
    public function beginTransaction(): bool;

    public function clearLastErrorMsg(): void;

    public function close(): bool;

    public function commit(): bool;

    public function dateStyle(): string;

    public function escapeColumn(string $name): string;

    public function escapeString(string $str): string;

    public function exec(string $sql): bool;

    public function getColumns(string $tableName): array;

    public function getConstraints(string $tableName): array;

    public function getLastErrorMsg(): string;

    public function getTables(): array;

    public function lastval(): int;

    public function rollback(): bool;

    public function select(string $sql, int $limit, int $offset): array;

    public function updateSequence(string $tableName, string $fields): void;

    public function version(): string;
}