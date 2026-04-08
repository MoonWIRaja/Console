<?php

namespace Pterodactyl\Services\Servers\Players;

final class PlayerScope
{
    public const ALL = 'all';
    public const ONLINE = 'online';
    public const OPERATORS = 'operators';
    public const ADMINS = 'admins';
    public const STAFF = 'staff';
    public const BANNED = 'banned';

    public static function all(): array
    {
        return [
            self::ALL,
            self::ONLINE,
            self::OPERATORS,
            self::ADMINS,
            self::STAFF,
            self::BANNED,
        ];
    }
}
