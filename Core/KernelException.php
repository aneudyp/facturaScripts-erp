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
use FacturaScripts\Core\Contract\ErrorHandlerInterface;
use Throwable;

class KernelException extends Exception
{
    private $handler;

    public function __construct($handler, $message, $code = 0, Throwable $previous = null)
    {
        $this->handler = $handler;
        parent::__construct($message, $code, $previous);
    }

    public function getHandler(string $url): ErrorHandlerInterface
    {
        $dynClass = '\\FacturaScripts\\Dinamic\\ErrorHandler\\' . $this->handler;
        if (class_exists($dynClass)) {
            return new $dynClass($url);
        }

        $mainClass = '\\FacturaScripts\\Core\\ErrorHandler\\' . $this->handler;
        return new $mainClass($url);
    }
}
