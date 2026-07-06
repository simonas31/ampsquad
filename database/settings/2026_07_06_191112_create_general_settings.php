<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.email', 'info@ampsquad.lt');
        $this->migrator->add('general.phone', '+370 600 00000');
        $this->migrator->add('general.address', 'Vilnius, Lietuva');
        $this->migrator->add('general.facebookUrl', null);
        $this->migrator->add('general.instagramUrl', null);
        $this->migrator->add('general.linkedinUrl', null);
    }
};
