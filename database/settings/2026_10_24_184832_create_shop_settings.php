<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off until an admin switches the shop on.
        $this->migrator->add('shop.enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('shop.enabled');
    }
};
