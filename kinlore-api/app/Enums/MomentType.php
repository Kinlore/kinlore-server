<?php

declare(strict_types=1);

namespace App\Enums;

enum MomentType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Story = 'story';
    case Milestone = 'milestone';
    case Growth = 'growth';
}
