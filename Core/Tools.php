<?php

namespace FacturaScripts\Core;

final class Tools
{
    public static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $field) {
            if (isset($_SERVER[$field])) {
                return (string)$_SERVER[$field];
            }
        }

        return '::1';
    }

    public static function i18nLog(string $channel = ''): Logger
    {
        return new Logger($channel, true);
    }

    public static function log(string $channel = ''): Logger
    {
        return new Logger($channel);
    }
}
