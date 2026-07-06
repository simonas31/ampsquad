<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $email;

    public string $phone;

    public string $address;

    public ?string $facebookUrl;

    public ?string $instagramUrl;

    public ?string $linkedinUrl;

    public static function group(): string
    {
        return 'general';
    }
}
