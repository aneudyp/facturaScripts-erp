<?php

namespace FacturaScripts\Core;

class Cache
{
    const EXPIRATION = 3600;
    const FILE_PATH = '/MyFiles/Tmp/FileCache';

    /**
     * Removes all data
     */
    public static function clear(): void
    {
        $folder = Setup::get('folder') . self::FILE_PATH;
        if (false === file_exists($folder)) {
            return;
        }

        foreach (scandir($folder) as $fileName) {
            if (str_ends_with($fileName, '.cache')) {
                unlink($folder . '/' . $fileName);
            }
        }
    }

    /**
     * Removes this key and value.
     *
     * @param string $key
     */
    public static function delete(string $key): void
    {
        $fileName = self::filename($key);
        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }

    /**
     * Removes all keys starting with $prefix
     *
     * @param string $prefix
     */
    public static function deleteMulti(string $prefix): void
    {
        $folder = Setup::get('folder') . self::FILE_PATH;
        foreach (scandir($folder) as $fileName) {
            $len = strlen($prefix);
            if (substr($fileName, 0, $len) === $prefix) {
                unlink($folder . '/' . $fileName);
            }
        }
    }

    /**
     * Removes all expired data.
     */
    public static function expire(): void
    {
        $folder = Setup::get('folder') . self::FILE_PATH;
        foreach (scandir($folder) as $fileName) {
            if (filemtime($folder . '/' . $fileName) < time() - self::EXPIRATION) {
                unlink($folder . '/' . $fileName);
            }
        }
    }

    /**
     * Returns value stored for the $key
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        $fileName = self::filename($key);
        if (file_exists($fileName)) {
            $data = file_get_contents($fileName);
            return unserialize($data);
        }

        return null;
    }

    /**
     * Stores the $key and $value with the default expiration time.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        $folder = Setup::get('folder') . self::FILE_PATH;
        if (false === file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $data = serialize($value);
        $fileName = self::filename($key);
        file_put_contents($fileName, $data);
    }

    /**
     * Returns the filename for this key.
     *
     * @param string $key
     *
     * @return string
     */
    private static function filename(string $key): string
    {
        return Setup::get('folder') . self::FILE_PATH . '/' . $key . '.cache';
    }
}