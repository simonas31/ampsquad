<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageGeneralSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logos_can_be_uploaded_and_are_persisted(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $settings = app(GeneralSettings::class);
        $this->assertNull($settings->logo);
        $this->assertNull($settings->logoDark);

        Livewire::test(ManageGeneralSettings::class)
            ->assertOk()
            ->fillForm([
                'logo' => UploadedFile::fake()->image('logo.png', 337, 240),
                'logoDark' => UploadedFile::fake()->image('logo-dark.png', 337, 240),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(GeneralSettings::class);

        $this->assertNotNull($settings->logo);
        $this->assertNotNull($settings->logoDark);
        Storage::disk('public')->assertExists($settings->logo);
        Storage::disk('public')->assertExists($settings->logoDark);
    }

    public function test_logos_are_optional(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageGeneralSettings::class)
            ->fillForm(['logo' => null, 'logoDark' => null])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_logo_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageGeneralSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->call('save')
            ->assertHasFormErrors(['logo']);
    }
}
