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

use FacturaScripts\Core\Bridge\LoggerDbStorage;
use FacturaScripts\Core\Bridge\LoggerFileStorage;
use FacturaScripts\Core\Contract\LoggerStorageInterface;

final class Logger
{
    const DEFAULT_CHANNEL = 'master';
    const LIMIT = 10000;

    private $channel;
    private static $context = [];
    private static $data = [];
    private static $storage;
    private $translator;

    public function __construct(string $channel = '', bool $translate = false)
    {
        $this->channel = empty($channel) ? self::DEFAULT_CHANNEL : $channel;
        $this->translator = $translate ? new Translator() : null;
    }

    /**
     * Removes all log message for this channel ('' => all channels).
     *
     * @param string $channel
     */
    public static function clear(string $channel = ''): void
    {
        if (empty($channel)) {
            self::$data = [];
            return;
        }

        foreach (self::$data as $key => $item) {
            if ($item['channel'] === $channel) {
                unset(self::$data[$key]);
            }
        }
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (Setup::get('debug')) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Returns the value on the key on the global context.
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function getContext(string $key): mixed
    {
        return self::$context[$key];
    }

    /**
     * Interesting information, advices.
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Returns the log messages for this channel ('' => all channels).
     *
     * @param string $channel
     *
     * @return array
     */
    public static function read(string $channel = ''): array
    {
        $items = [];
        foreach (self::$data as $row) {
            if (empty($channel) || $row['channel'] === $channel) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * Store logs for this channel ('' => all channels).
     *
     * @param string $channel
     *
     * @return bool
     */
    public static function save(string $channel = ''): bool
    {
        if (!isset(self::$storage)) {
            self::$storage = Database::connected() ? new LoggerDbStorage() : new LoggerFileStorage();
        }

        $data = empty($channel) ? self::$data : self::read($channel);
        return self::$storage->save($data);
    }

    /**
     * Sets the property in the global context.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function setContext(string $key, mixed $value): void
    {
        self::$context[$key] = $value;
    }

    /**
     * Sets the storage engine.
     *
     * @param LoggerStorageInterface $storage
     */
    public static function setStorage(LoggerStorageInterface &$storage): void
    {
        self::$storage = $storage;
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // if we find this message in the log, we increase the counter
        foreach (self::$data as $key => $value) {
            if ($value['channel'] === $this->channel && $value['level'] === $level && $value['original'] === $message) {
                self::$data[$key]['count']++;
                return;
            }
        }

        self::$data[] = [
            'channel' => $this->channel,
            'context' => array_merge(self::$context, $context),
            'count' => 1,
            'level' => $level,
            'message' => is_null($this->translator) ? $message : $this->translator->trans($message),
            'original' => $message,
            'time' => microtime(true)
        ];
        $this->reduce();
    }

    /**
     * When the limit is reached save the history to disk and clean.
     */
    private function reduce(): void
    {
        if (count(self::$data) > self::LIMIT) {
            self::save($this->channel);
            self::clear($this->channel);
        }
    }
}