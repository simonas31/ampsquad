<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ShippingMethods\Pages\CreateShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\ListShippingMethods;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShippingMethodResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_cannot_reach_it(): void
    {
        auth()->logout();

        $this->get('/admin/shipping-methods')->assertRedirect();
    }

    public function test_a_method_is_created_with_its_price_typed_in_euros_and_stored_in_cents(): void
    {
        Livewire::test(CreateShippingMethod::class)
            ->fillForm([
                'name' => ['lt' => 'LP Express', 'en' => 'LP Express (EN)'],
                'description' => ['lt' => '2-3 darbo dienos.', 'en' => 'Within 2-3 business days.'],
                'price_excl_vat_cents' => '3.99',
                'is_pickup' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $method = ShippingMethod::query()->sole();

        $this->assertSame(399, $method->price_excl_vat_cents);
        $this->assertSame('LP Express', $method->getTranslation('name', 'lt'));
        $this->assertSame('LP Express (EN)', $method->getTranslation('name', 'en'));
        $this->assertSame('Within 2-3 business days.', $method->getTranslation('description', 'en'));
        $this->assertFalse($method->is_pickup);
    }

    public function test_pickup_can_be_free(): void
    {
        Livewire::test(CreateShippingMethod::class)
            ->fillForm(['name' => ['lt' => 'Atsiėmimas'], 'price_excl_vat_cents' => '0', 'is_pickup' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $method = ShippingMethod::query()->sole();

        $this->assertSame(0, $method->price_excl_vat_cents);
        $this->assertTrue($method->is_pickup);
    }

    public function test_a_name_is_required_and_a_price_cannot_be_negative_or_have_sub_cents(): void
    {
        Livewire::test(CreateShippingMethod::class)
            ->fillForm(['name' => ['lt' => ''], 'price_excl_vat_cents' => '-1'])
            ->call('create')
            ->assertHasFormErrors(['name.lt' => 'required', 'price_excl_vat_cents']);

        Livewire::test(CreateShippingMethod::class)
            ->fillForm(['name' => ['lt' => 'X'], 'price_excl_vat_cents' => '1.005'])
            ->call('create')
            ->assertHasFormErrors(['price_excl_vat_cents']);

        $this->assertSame(0, ShippingMethod::query()->count());
    }

    public function test_editing_shows_the_price_in_euros_and_saves_changes(): void
    {
        $method = ShippingMethod::factory()->priced(450)->create();

        Livewire::test(EditShippingMethod::class, ['record' => $method->id])
            ->assertFormSet(['price_excl_vat_cents' => '4.50', 'is_active' => true])
            ->fillForm(['price_excl_vat_cents' => '5.25', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $method->refresh();
        $this->assertSame(525, $method->price_excl_vat_cents);
        $this->assertFalse($method->is_active);
    }

    public function test_the_list_shows_the_methods_and_filters_them(): void
    {
        $active = ShippingMethod::factory()->named('Active one')->create();
        $off = ShippingMethod::factory()->named('Switched off')->inactive()->create();
        $pickup = ShippingMethod::factory()->pickup()->create();

        Livewire::test(ListShippingMethods::class)
            ->assertCanSeeTableRecords([$active, $off, $pickup])
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active, $pickup])
            ->assertCanNotSeeTableRecords([$off])
            ->removeTableFilter('is_active')
            ->filterTable('is_pickup', true)
            ->assertCanSeeTableRecords([$pickup])
            ->assertCanNotSeeTableRecords([$active, $off]);
    }

    public function test_a_method_can_be_deleted(): void
    {
        $method = ShippingMethod::factory()->create();

        Livewire::test(EditShippingMethod::class, ['record' => $method->id])
            ->callAction('delete');

        $this->assertModelMissing($method);
    }
}
