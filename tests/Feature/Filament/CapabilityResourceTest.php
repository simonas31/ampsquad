<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Capabilities\Pages\EditCapability;
use App\Filament\Resources\Capabilities\RelationManagers\OptionsRelationManager;
use App\Models\Capability;
use App\Models\CapabilityOption;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CapabilityResourceTest extends TestCase
{
    use RefreshDatabase;

    private function optionsManager(Capability $capability): Testable
    {
        return Livewire::test(OptionsRelationManager::class, [
            'ownerRecord' => $capability,
            'pageClass' => EditCapability::class,
        ]);
    }

    public function test_editing_an_option_loads_its_name_for_the_active_locale(): void
    {
        $this->actingAs(User::factory()->create());

        $option = CapabilityOption::factory()->create([
            'name' => ['lt' => 'Būsto tipas', 'en' => 'Property type'],
        ]);

        $this->optionsManager($option->capability)
            ->mountAction(TestAction::make('edit')->table($option))
            ->assertSchemaStateSet(['name' => 'Būsto tipas']);
    }

    public function test_editing_an_option_saves_the_name_for_the_active_locale_only(): void
    {
        $this->actingAs(User::factory()->create());

        $option = CapabilityOption::factory()->create([
            'name' => ['lt' => 'Būsto tipas', 'en' => 'Property type'],
        ]);

        $this->optionsManager($option->capability)
            ->callAction(TestAction::make('edit')->table($option), ['name' => 'Namo tipas', 'order' => 3])
            ->assertHasNoFormErrors();

        $option->refresh();

        $this->assertSame('Namo tipas', $option->getTranslation('name', 'lt'));
        $this->assertSame('Property type', $option->getTranslation('name', 'en'));
        $this->assertSame(3, $option->order);
    }

    public function test_creating_an_option_stores_the_name_for_the_active_locale(): void
    {
        $this->actingAs(User::factory()->create());

        $capability = Capability::factory()->create();

        $this->optionsManager($capability)
            ->callAction(TestAction::make('create')->table(), ['name' => 'Ploto dydis', 'order' => 1])
            ->assertHasNoFormErrors();

        $option = $capability->options()->firstOrFail();

        $this->assertSame('Ploto dydis', $option->getTranslation('name', 'lt'));
    }
}
