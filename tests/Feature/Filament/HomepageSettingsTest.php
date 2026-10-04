<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageHomepageSettings;
use App\Models\User;
use App\Settings\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_stats_can_be_edited_and_are_persisted(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageHomepageSettings::class)
            ->assertOk()
            ->fillForm([
                'heroStats' => [
                    [
                        'value' => 18000,
                        'unit' => ['lt' => 'm', 'en' => 'm'],
                        'label' => ['lt' => 'Nutiesta kabelio', 'en' => 'Cable installed'],
                    ],
                    [
                        'value' => 120,
                        'unit' => ['lt' => 'vnt.', 'en' => 'pcs'],
                        'label' => ['lt' => 'Surinkta skydų', 'en' => 'Panels assembled'],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stats = collect(app(HomepageSettings::class)->heroStats);

        $this->assertCount(2, $stats);
        $this->assertSame(18000, (int) $stats->first()['value']);
        $this->assertSame('Panels assembled', $stats->last()['label']['en']);
    }

    public function test_at_least_one_hero_stat_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageHomepageSettings::class)
            ->fillForm(['heroStats' => []])
            ->call('save')
            ->assertHasFormErrors(['heroStats']);
    }

    public function test_hero_image_can_be_uploaded_and_is_persisted(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->assertNull(app(HomepageSettings::class)->heroImage);

        Livewire::test(ManageHomepageSettings::class)
            ->fillForm(['heroImage' => UploadedFile::fake()->image('hero.jpg', 1800, 900)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = app(HomepageSettings::class)->heroImage;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('site/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_hero_image_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageHomepageSettings::class)
            ->fillForm(['heroImage' => UploadedFile::fake()->create('hero.pdf', 100, 'application/pdf')])
            ->call('save')
            ->assertHasFormErrors(['heroImage']);

        $this->assertNull(app(HomepageSettings::class)->heroImage);
    }

    public function test_hero_image_is_optional(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageHomepageSettings::class)
            ->fillForm(['heroImage' => null])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
