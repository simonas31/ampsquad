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

    public function test_editing_an_option_loads_its_name_in_both_languages(): void
    {
        $this->actingAs(User::factory()->create());

        $option = CapabilityOption::factory()->create([
            'name' => ['lt' => 'Būsto tipas', 'en' => 'Property type'],
        ]);

        $this->optionsManager($option->capability)
            ->mountAction(TestAction::make('edit')->table($option))
            ->assertSchemaStateSet(['name' => ['lt' => 'Būsto tipas', 'en' => 'Property type']]);
    }

    public function test_editing_an_option_saves_the_name_in_both_languages(): void
    {
        $this->actingAs(User::factory()->create());

        $option = CapabilityOption::factory()->create([
            'name' => ['lt' => 'Būsto tipas', 'en' => 'Property type'],
        ]);

        $this->optionsManager($option->capability)
            ->callAction(TestAction::make('edit')->table($option), ['name' => ['lt' => 'Namo tipas', 'en' => 'House type'], 'order' => 3])
            ->assertHasNoFormErrors();

        $option->refresh();

        $this->assertSame('Namo tipas', $option->getTranslation('name', 'lt'));
        $this->assertSame('House type', $option->getTranslation('name', 'en'));
        $this->assertSame(3, $option->order);
    }

    public function test_creating_an_option_stores_the_name_in_both_languages(): void
    {
        $this->actingAs(User::factory()->create());

        $capability = Capability::factory()->create();

        $this->optionsManager($capability)
            ->callAction(TestAction::make('create')->table(), ['name' => ['lt' => 'Ploto dydis', 'en' => 'Area size'], 'order' => 1])
            ->assertHasNoFormErrors();

        $option = $capability->options()->firstOrFail();

        $this->assertSame('Ploto dydis', $option->getTranslation('name', 'lt'));
        $this->assertSame('Area size', $option->getTranslation('name', 'en'));
    }

    public function test_an_option_can_be_created_in_lithuanian_only_without_storing_an_empty_english_name(): void
    {
        $this->actingAs(User::factory()->create());

        $capability = Capability::factory()->create();

        $this->optionsManager($capability)
            ->callAction(TestAction::make('create')->table(), ['name' => ['lt' => 'Ploto dydis', 'en' => ''], 'order' => 1])
            ->assertHasNoFormErrors();

        $option = $capability->options()->firstOrFail();

        $this->assertSame(['lt' => 'Ploto dydis'], json_decode($option->getRawOriginal('name'), true));
    }

    public function test_the_lithuanian_option_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->optionsManager(Capability::factory()->create())
            ->callAction(TestAction::make('create')->table(), ['name' => ['lt' => '', 'en' => 'Area size'], 'order' => 1])
            ->assertHasFormErrors(['name.lt' => 'required']);
    }
}
