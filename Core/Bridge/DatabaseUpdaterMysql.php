<?php

namespace FacturaScripts\Core\Bridge;

use FacturaScripts\Core\Contract\DbUpdaterInterface;
use FacturaScripts\Core\Database;
use FacturaScripts\Core\KernelException;

class DatabaseUpdaterMysql implements DbUpdaterInterface
{
    /**
     * @throws KernelException
     */
    public function createTable(string $tableName, array $structure): bool
    {
        $items = [];
        foreach ($structure['columns'] as $col) {
            $items[] = $this->getFieldOnCreateTable($col);
        }
        foreach ($structure['constraints'] as $con) {
            $items[] = 'CONSTRAINT ' . $con['name'] . ' ' . $con['type'];
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (" . implode(', ', $items) . ') ENGINE=InnoDB;';
        return Database::exec($sql);
    }

    /**
     * @throws KernelException
     */
    public function dropTable(string $tableName): bool
    {
        return Database::exec("DROP TABLE IF EXISTS $tableName;");
    }

    /**
     * @throws KernelException
     */
    public function updateTable(string $tableName, array $structure): bool
    {
        $sql = $this->updateTableColumns($tableName, $structure)
            . $this->updateTableConstraints($tableName, $structure);

        return empty($sql) || Database::exec($sql);
    }

    private function addColumn(string $tableName, array $col): string
    {
        if ($col['type'] === 'serial') {
            return "ALTER TABLE $tableName ADD `" . $col['name'] . '` integer NOT NULL AUTO_INCREMENT;';
        }

        $extra = $col['null'] === 'NO' ? ' NOT NULL' : ' NULL';
        $extra .= $col['default'] === null ? '' : ' DEFAULT ' . $col['default'];
        return "ALTER TABLE $tableName ADD `" . $col['name'] . '` ' . $col['type'] . $extra . ';';
    }

    private function addConstraint(string $tableName, array $con): string
    {
        return "ALTER TABLE $tableName ADD CONSTRAINT " . $con['name'] . ' ' . $con['type'] . ';';
    }

    private function compareColumns(string $tableName, array $col, array $dbCol): string
    {
        // compare types
        $modify = match ($col['type']) {
            'boolean' => $dbCol['type'] != 'tinyint',
            'character varying' => !str_starts_with($dbCol['type'], 'varchar'),
            'double' => !str_starts_with($dbCol['type'], 'double'),
            'integer', 'serial' => !str_starts_with($dbCol['type'], 'int'),
            default => $dbCol['type'] != $col['type'],
        };

        // compare null
        if ($col['null'] === 'NO' && $dbCol['nullable']) {
            $modify = true;
        } elseif ($col['null'] === 'YES' && false === $dbCol['nullable']) {
            $modify = true;
        }

        // compare default
        switch ($col['default']) {
            case 'false':
                if ($dbCol != 0) {
                    $modify = true;
                }
                break;

            case 'true':
                if ($dbCol != 1) {
                    $modify = true;
                }
                break;

            default:
                if ($col['default'] != $dbCol['default']) {
                    $modify = true;
                }
                break;
        }

        if ($modify) {
            $extra = $col['null'] === 'NO' ? ' NOT NULL' : ' NULL';
            $extra .= $col['default'] === null ? '' : ' DEFAULT ' . $col['default'];
            return "ALTER TABLE $tableName MODIFY `" . $col['name'] . '` ' . $col['type'] . $extra . ';';
        }

        return '';
    }

    private function getFieldOnCreateTable(array $col): string
    {
        if ($col['type'] === 'serial') {
            return '`' . $col['name'] . '` integer NOT NULL AUTO_INCREMENT';
        }

        $extra = $col['null'] === 'NO' ? ' NOT NULL' : ' NULL';
        $extra .= $col['default'] === null ? '' : ' DEFAULT ' . $col['default'];
        return '`' . $col['name'] . '` ' . $col['type'] . $extra;
    }

    /**
     * @throws KernelException
     */
    private function updateTableColumns(string $tableName, array $structure): string
    {
        $sql = '';
        $dbColumns = Database::getColumns($tableName);
        foreach ($structure['columns'] as $col) {
            foreach ($dbColumns as $dbCol) {
                if ($dbCol['name'] === $col['name']) {
                    $sql .= $this->compareColumns($tableName, $col, $dbCol);
                    continue 2; // continue on the first loop
                }
            }

            $sql .= $this->addColumn($tableName, $col);
        }

        return $sql;
    }

    /**
     * @throws KernelException
     */
    private function updateTableConstraints(string $tableName, array $structure): string
    {
        $dbConstraints = Database::getConstraints($tableName);
        $delete = false;
        $sqlDelete = '';
        $sqlDeleteFK = '';
        foreach ($dbConstraints as $dbCon) {
            if (strtolower($dbCon['type']) === 'primary key') {
                // exclude primary key
                continue;
            }

            // it is better to delete the foreign keys before the rest
            if (strtolower($dbCon['type']) === 'foreign key') {
                $sqlDeleteFK .= "ALTER TABLE $tableName DROP FOREIGN KEY " . $dbCon['name'] . ';';
            } elseif (strtolower($dbCon['type']) === 'unique') {
                $sqlDelete .= "ALTER TABLE $tableName DROP INDEX " . $dbCon['name'] . ';';
            }

            foreach ($structure['constraints'] as $con) {
                if ($dbCon['name'] === $con['name']) {
                    continue 2; // continue on the first loop
                }
            }
            $delete = true;
        }

        $sqlAdd = '';
        foreach ($structure['constraints'] as $con) {
            foreach ($dbConstraints as $dbCon) {
                if ($dbCon['name'] === $con['name']) {
                    continue 2; // continue on the first loop
                }

                if (strtolower($dbCon['type']) === 'primary key' &&
                    str_starts_with(strtolower($con['type']), 'primary key')) {
                    continue 2; // continue on the first loop
                }
            }

            $sqlAdd .= $this->addConstraint($tableName, $con);
        }

        return $delete ? $sqlDeleteFK . $sqlDelete . $sqlAdd : $sqlAdd;
    }
}