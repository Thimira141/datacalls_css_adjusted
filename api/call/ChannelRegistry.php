<?php
class ChannelRegistry
{
    private static $map = [];

    public static function register($actionId, $channel, $uniqueid, $status = null, $reason = null)
    {
        self::$map[$actionId] = [
            'channel'  => $channel,
            'uniqueid' => $uniqueid,
            'status'   => $status,
            'reason'   => $reason,
            'time'     => time()
        ];
    }

    public static function resolveChannel($actionId)
    {
        return self::$map[$actionId]['channel'] ?? null;
    }

    public static function resolveUniqueid($actionId)
    {
        return self::$map[$actionId]['uniqueid'] ?? null;
    }

    public static function resolveAll($actionId)
    {
        return self::$map[$actionId] ?? null;
    }
}
