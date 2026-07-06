<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\NotificationType;
use App\Filament\Resources\Recipients\Pages\CreateRecipient;
use App\Filament\Resources\Recipients\Pages\EditRecipient;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * notification_types round-trips through Filament's CheckboxList as a plain
 * array of enum values, while the model casts it to a Collection of
 * NotificationType instances (AsEnumCollection) — this locks down that the
 * two sides actually agree on the wire format.
 */
class RecipientResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_recipient_persists_the_selected_notification_types(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateRecipient::class)
            ->fillForm([
                'email' => 'ops@ampsquad.test',
                'is_active' => true,
                'notification_types' => [
                    NotificationType::ContactFormSubmitted->value,
                    NotificationType::NewProjectPublished->value,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipient = Recipient::query()->where('email', 'ops@ampsquad.test')->firstOrFail();

        $this->assertTrue($recipient->notification_types->contains(NotificationType::ContactFormSubmitted));
        $this->assertTrue($recipient->notification_types->contains(NotificationType::NewProjectPublished));
    }

    public function test_editing_a_recipient_rehydrates_the_previously_selected_notification_types(): void
    {
        $this->actingAs(User::factory()->create());

        $recipient = Recipient::factory()->create([
            'notification_types' => [NotificationType::ContactFormSubmitted],
        ]);

        Livewire::test(EditRecipient::class, ['record' => $recipient->id])
            ->assertFormSet([
                'notification_types' => [NotificationType::ContactFormSubmitted],
            ])
            ->fillForm(['notification_types' => [NotificationType::NewProjectPublished->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            [NotificationType::NewProjectPublished],
            $recipient->fresh()->notification_types->all(),
        );
    }
}
