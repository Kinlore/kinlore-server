<?php

declare(strict_types=1);

namespace App\Enums;

// shortcut: the database check still allows the 2025 types 'text' and 'mixed'; no version of the app ever stored them, add cases or a data migration if an install has such rows
enum MomentType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Story = 'story';
    case Milestone = 'milestone';
    case Growth = 'growth';
}
