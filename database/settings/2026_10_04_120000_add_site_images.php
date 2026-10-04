<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The hero photograph and the header/footer logos used to be static files
 * in /public. They are now uploaded in the admin panel and stored as paths
 * on the public disk. A null value falls back to the bundled artwork (logos)
 * or to the plain navy background (hero).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('homepage.heroImage', null);
        $this->migrator->add('general.logo', null);
        $this->migrator->add('general.logoDark', null);
    }
};
