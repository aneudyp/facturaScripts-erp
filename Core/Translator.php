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

final class Translator
{
    public const DEFAULT_LANG = 'es_ES';
    public const FOLDERS = ['Core/Translation', 'Dynamic/Translation', 'MyFiles/Translation'];

    private $lang;
    private static $languages = [];
    private static $translations = [];

    public function __construct(string $langcode = '')
    {
        $this->setLang($langcode);
    }

    public function customTrans(string $langcode, string $txt, array $parameters = []): string
    {
        $this->load($langcode);

        $key = $txt . '@' . $langcode;
        $translation = self::$translations[$key] ?? $txt;

        // replaces the parameters on the translation
        return str_replace(
            array_keys($parameters),
            array_values($parameters),
            $translation
        );
    }

    public function getAvailableLanguages(): array
    {
        $languages = [];
        foreach (scandir(self::FOLDERS[0], SCANDIR_SORT_ASCENDING) as $fileName) {
            if (str_ends_with($fileName, '.json')) {
                $key = substr($fileName, 0, -5);
                $languages[$key] = $this->trans('languages-' . $key);
            }
        }

        return $languages;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setLang(string $langcode): void
    {
        $this->lang = empty($langcode) ?
            Setup::get('langcode', self::DEFAULT_LANG) :
            $langcode;
    }

    public function trans(string $txt, array $parameters = []): string
    {
        return $this->customTrans($this->getLang(), $txt, $parameters);
    }

    private function load(string $langcode): void
    {
        if (in_array($langcode, self::$languages, true)) {
            return;
        }

        // load the translation files of the selected language
        foreach (self::FOLDERS as $folder) {
            $fileName = $folder . '/' . $langcode . '.json';
            if (false === file_exists($fileName)) {
                continue;
            }

            $data = file_get_contents($fileName);
            foreach (json_decode($data, true) as $code => $translation) {
                self::$translations[$code . '@' . $langcode] = $translation;
            }
        }

        // save the language in the loaded list
        self::$languages[] = $langcode;
    }
}