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

    /**
     * Paths on the public disk of the logo for light backgrounds and the
     * white-wordmark logo for dark ones; null falls back to the bundled files.
     */
    public ?string $logo;

    public ?string $logoDark;

    public static function group(): string
    {
        return 'general';
    }
}
