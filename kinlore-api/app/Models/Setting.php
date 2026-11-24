<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One value for the whole family, by key. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';
}
