<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The hero closes on a short row of headline figures. The numbers seeded
 * here are placeholders to be replaced with the company's real totals in
 * the admin panel (Homepage Settings).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('homepage.heroStats', [
            [
                'value' => 24500,
                'unit' => ['lt' => 'm', 'en' => 'm'],
                'label' => ['lt' => 'Nutiesta kabelio', 'en' => 'Cable installed'],
            ],
            [
                'value' => 310,
                'unit' => ['lt' => 'vnt.', 'en' => 'pcs'],
                'label' => ['lt' => 'Surinkta elektros skydų', 'en' => 'Electrical panels assembled'],
            ],
            [
                'value' => 95,
                'unit' => ['lt' => 'vnt.', 'en' => 'pcs'],
                'label' => ['lt' => 'Įrengta sistemų (signalizacija, gaisro sauga)', 'en' => 'Systems installed (alarm, fire safety)'],
            ],
        ]);
    }
};
