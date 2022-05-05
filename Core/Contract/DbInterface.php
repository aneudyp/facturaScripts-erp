<?php

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

    public function version(): string;
}