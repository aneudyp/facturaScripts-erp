<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

final class Session
{
    const CACHE_KEY = 'CSRF_TOKENS';
    const MAX_TOKEN_AGE = 4;
    const MAX_TOKENS = 500;
    const RANDOM_STRING_LENGTH = 6;

    private static $data = [];
    private static $seed = '';

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public static function get(string $key)
    {
        return self::$data[$key] ?? null;
    }

    public static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $field) {
            if (isset($_SERVER[$field])) {
                return (string)$_SERVER[$field];
            }
        }

        return '::1';
    }

    public static function init(): void
    {
        // something unique in each installation and session
        self::$seed = Setup::get('seed', PHP_VERSION . __FILE__) . self::getClientIp();
    }

    public static function newToken(): string
    {
        // something that changes every hour
        $num = intval(date('YmdH')) + strlen(self::$seed);

        // combine and generate the token
        $value = self::$seed . $num;
        return sha1($value) . '|' . self::getRandomStr();
    }

    public static function set(string $key, $value): void
    {
        if (false === array_key_exists($key, self::$data)) {
            self::$data[$key] = $value;
        }
    }

    public static function tokenExist(string $token): bool
    {
        $tokens = self::getTokens();
        if (in_array($token, $tokens)) {
            return true;
        }

        self::saveToken($token);
        return false;
    }

    public static function validate(string $token): bool
    {
        $tokenParts = explode('|', $token);
        if (count($tokenParts) != 2) {
            // invalid token format
            // the random part can be incremented in javascript so there is no fixed length
            return false;
        }

        // check all valid tokens roots
        $num = intval(date('YmdH')) + strlen(self::$seed);
        $valid = [sha1(self::$seed . $num)];
        for ($hour = 1; $hour <= self::MAX_TOKEN_AGE; $hour++) {
            $time = strtotime('-' . $hour . ' hours');
            $altNum = intval(date('YmdH', $time)) + strlen(self::$seed);
            $valid[] = sha1(self::$seed . $altNum);
        }

        return in_array($tokenParts[0], $valid);
    }

    private static function getRandomStr(): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($chars), 0, self::RANDOM_STRING_LENGTH);
    }

    private static function getTokens(): array
    {
        $values = Cache::get(self::CACHE_KEY);
        $tokens = is_array($values) ? $values : [];
        if (count($tokens) < self::MAX_TOKENS) {
            return $tokens;
        }

        // reduce tokens
        return array_slice($tokens, -10);
    }

    private static function saveToken(string $token): void
    {
        $tokens = self::getTokens();

        // save new token
        $tokens[] = $token;
        Cache::set(self::CACHE_KEY, $tokens);
    }
}
