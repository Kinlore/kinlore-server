<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Partner = 'partner';
    case Family = 'family';

    /** Admin and partner may change any kid and any content; only family members are limited by permissions. */
    public function canManageContent(): bool
    {
        return $this !== self::Family;
    }
}
