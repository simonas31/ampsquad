<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off until an admin switches online checkout on, so the code can be
        // deployed before the payment provider is set up. The shop and cart
        // work without it.
        $this->migrator->add('shop.checkoutEnabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('shop.checkoutEnabled');
    }
};
