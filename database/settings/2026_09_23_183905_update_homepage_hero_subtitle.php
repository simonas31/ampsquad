<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The seeded hero subtitle joined its two clauses with an em dash. The
 * redesign sets this line directly beneath the display headline at a size
 * where the dash reads as a typographic artefact rather than as punctuation,
 * so both locales are rewritten as two sentences.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update(
            'homepage.heroSubtitle',
            fn (): array => [
                'lt' => 'Nuo projektavimo iki įgyvendinimo. Patikimi elektros instaliacijos sprendimai namams ir verslui.',
                'en' => 'From design to delivery. Reliable electrical installation solutions for homes and businesses.',
            ],
        );
    }
};
