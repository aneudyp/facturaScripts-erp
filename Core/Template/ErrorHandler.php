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

namespace FacturaScripts\Core\Template;

use Exception;
use FacturaScripts\Core\Contract\ErrorHandlerInterface;

abstract class ErrorHandler implements ErrorHandlerInterface
{
    const HTTP_CODE = 500;

    protected $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function exception(Exception $exception): void
    {
        ob_clean();
        http_response_code(self::HTTP_CODE);

        if (str_starts_with($this->url, '/api/')) {
            echo json_encode(['error' => $exception->getMessage()]);
            return;
        }

        echo '<h1>Error ' . $exception->getCode() . '</h1>'
            . '<p>' . $exception->getFile() . ':' . $exception->getLine() . '</p>'
            . '<p>' . $exception->getMessage() . '</p>';
    }

    public function error(array $error): void
    {
        ob_clean();
        http_response_code(self::HTTP_CODE);

        if (str_starts_with($this->url, '/api/')) {
            echo json_encode(['error' => $error['message']]);
            return;
        }

        echo '<h1>Fatal error!!!</h1>'
            . '<p>' . $error['file'] . ':' . $error['line'] . '</p>'
            . nl2br($error['message']);
    }
}