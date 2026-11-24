<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;

/**
 * The name of the family on this server. Members and holders of a valid invitation see it;
 * public pages and endpoints show APP_NAME instead, so a stranger does not learn whose server this is.
 */
class FamilyService
{
    public const NAME_MAX_LENGTH = 60;

    /** The name the admin set, else a suggestion from the admin's full name, else APP_NAME. */
    public function name(): string
    {
        $stored = Setting::find('family_name')?->value;
        if (filled($stored)) {
            return $stored;
        }

        $admin = User::where('role', Role::Admin)->oldest('id')->first();

        return $admin ? self::suggest($admin->name) : (string) config('app.name');
    }

    public function rename(string $name): string
    {
        Setting::updateOrCreate(['key' => 'family_name'], ['value' => $name]);

        return $name;
    }

    /** "Anna Example" gives "Anna Example Family". The full name keeps relatives with one last name apart. */
    public static function suggest(string $personName): string
    {
        $fullName = (string) preg_replace('/\s+/', ' ', trim($personName));

        return trim(mb_substr($fullName, 0, self::NAME_MAX_LENGTH - 7)).' Family';
    }
}
