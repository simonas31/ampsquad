<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HomepageSettings extends Settings
{
    /**
     * Bilingual text is stored as a plain ['lt' => ..., 'en' => ...] array
     * rather than an array-shape docblock — spatie/laravel-settings tries to
     * resolve any @var docblock into a cast type and can't parse shape
     * syntax, so the native `array` type is left to speak for itself.
     */
    public array $heroTitle;

    public array $heroSubtitle;

    public array $introTitle;

    public array $introContent;

    public array $ctaTitle;

    public array $ctaButtonLabel;

    public static function group(): string
    {
        return 'homepage';
    }
}
