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

use FacturaScripts\Core\Setup;
use FacturaScripts\Core\Contract\LoggerStorageInterface;

final class LoggerFileStorage implements LoggerStorageInterface
{
    const LOG_FOLDER = '/MyFiles/Tmp/Logs';

    private $id;
    private $saveCount;

    public function __construct()
    {
        $this->id = time() . '-' . mt_rand(0, 9999);
        $this->saveCount = 0;
    }

    public function getLastFilePath(): string
    {
        if (empty($this->saveCount)) {
            return '';
        }

        $lastCount = $this->saveCount - 1;
        return Setup::get('folder') . self::LOG_FOLDER . '/' . $this->id . '-' . $lastCount . '.json';
    }

    public function save(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $folder = Setup::get('folder') . self::LOG_FOLDER;
        if (false === file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $filePath = $folder . '/' . $this->id . '-' . $this->saveCount . '.json';
        if (false === file_put_contents($filePath, json_encode($data))) {
            return false;
        }

        $this->saveCount++;
        return true;
    }
}