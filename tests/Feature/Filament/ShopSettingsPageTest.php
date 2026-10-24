<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageShopSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ShopSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function storedPayload(): string
    {
        return (string) DB::table('settings')
            ->where('group', 'shop')
            ->where('name', 'enabled')
            ->value('payload');
    }

    public function test_guests_cannot_reach_the_shop_settings(): void
    {
        $this->get('/admin/manage-shop-settings')->assertRedirect();
    }

    public function test_the_page_shows_the_shop_as_disabled_by_default(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageShopSettings::class)
            ->assertOk()
            ->assertFormSet(['enabled' => false]);
    }

    public function test_the_shop_can_be_enabled_and_disabled_and_the_choice_persists(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageShopSettings::class)
            ->fillForm(['enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('true', $this->storedPayload());

        Livewire::test(ManageShopSettings::class)
            ->assertFormSet(['enabled' => true])
            ->fillForm(['enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('false', $this->storedPayload());
    }
}
