<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Mail\ContactRequestReceivedMail;
use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
            'phone' => '+37060000000',
            'message' => 'Norėčiau pasiteirauti dėl elektros instaliacijos.',
            'company' => '',
        ], $overrides);
    }

    public function test_submitting_the_contact_form_persists_the_request_and_notifies_subscribed_active_recipients(): void
    {
        Mail::fake();

        $subscribed = Recipient::factory()->create(['notification_types' => [NotificationType::ContactFormSubmitted]]);
        Recipient::factory()->inactive()->create(['notification_types' => [NotificationType::ContactFormSubmitted]]);
        Recipient::factory()->create(['notification_types' => [NotificationType::NewProjectPublished]]);

        $response = $this->post('/contact', $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_requests', [
            'name' => 'Jonas Jonaitis',
            'email' => 'jonas@example.com',
        ]);

        Mail::assertQueued(
            ContactRequestReceivedMail::class,
            fn (ContactRequestReceivedMail $mail) => $mail->hasTo($subscribed->email),
        );
        Mail::assertQueuedCount(1);
    }

    public function test_honeypot_field_silently_rejects_bot_submissions(): void
    {
        $response = $this->post('/contact', $this->validPayload(['company' => 'Acme Bots Inc']));

        $response->assertSessionHasErrors('company');
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_contact_form_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->validPayload(['email' => "visitor{$i}@example.com"]))
                ->assertRedirect();
        }

        $this->post('/contact', $this->validPayload(['email' => 'onetoomany@example.com']))
            ->assertStatus(429);

        $this->assertDatabaseCount('contact_requests', 5);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->post('/contact', $this->validPayload(['name' => '', 'message' => '']));

        $response->assertSessionHasErrors(['name', 'message']);
        $this->assertDatabaseCount('contact_requests', 0);
    }
}
