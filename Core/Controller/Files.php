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

namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Setup;

class Files implements ControllerInterface
{

    public function __construct(string $url)
    {
        // favicon.ico
        if ('/favicon.ico' == $url) {
            $filePath = Setup::get('folder') . '/Dinamic/Assets/Images/favicon.ico';
            header('Content-Type: ' . $this->getMime($filePath));
            readfile($filePath);
            return;
        }

        $filePath = Setup::get('folder') . $url;

        // Not a file? Not a safe file?
        if (false === is_file($filePath) || false === $this->isFileSafe($filePath)) {
            throw new KernelException('FileNotFound', 'File not found or not safe: ' . $filePath);
        }

        // File found and safe
        header('Content-Type: ' . $this->getMime($filePath));

        // disable the buffer if enabled
        if (ob_get_contents()) {
            ob_end_flush();
        }

        // force to download svg files to prevent XSS attacks
        if (strpos($filePath, '.svg') !== false) {
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        }

        readfile($filePath);
    }

    public static function isFileSafe(string $filePath): bool
    {
        $parts = explode('.', $filePath);
        $safe = [
            'avi', 'css', 'csv', 'eot', 'gif', 'gz', 'ico', 'jpeg', 'jpg', 'js',
            'json', 'map', 'mkv', 'mp4', 'ogg', 'pdf', 'png', 'sql', 'svg',
            'ttf', 'webm', 'woff', 'woff2', 'xls', 'xlsx', 'xml', 'xsig', 'zip'
        ];
        return empty($parts) || count($parts) === 1 || in_array(end($parts), $safe, true);
    }

    public function run(): void
    {
    }

    private function getMime(string $filePath): string
    {
        $info = pathinfo($filePath);
        $extension = strtolower($info['extension']);
        switch ($extension) {
            case 'css':
                return 'text/css';

            case 'js':
                return 'application/javascript';

            case 'xml':
            case 'xsig':
                return 'text/xml';
        }

        return mime_content_type($filePath);
    }
}
