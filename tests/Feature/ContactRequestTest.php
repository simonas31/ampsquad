<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Mail\ContactRequestReceivedMail;
use App\Models\ContactRequest;
use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_attached_files_are_stored_privately_against_the_request(): void
    {
        Storage::fake('local');

        $this->post('/contact', $this->validPayload([
            'attachments' => [
                UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->image('panel.jpg'),
                UploadedFile::fake()->create('layout.dwg', 300),
            ],
        ]))->assertRedirect();

        $contactRequest = ContactRequest::query()->firstOrFail();
        $attachments = $contactRequest->getMedia('attachments');

        $this->assertCount(3, $attachments);
        $this->assertEqualsCanonicalizing(
            ['plan.pdf', 'panel.jpg', 'layout.dwg'],
            $attachments->pluck('file_name')->all(),
        );

        foreach ($attachments as $attachment) {
            $this->assertSame('local', $attachment->disk);
            Storage::disk('local')->assertExists($attachment->getPathRelativeToRoot());
        }
    }

    public function test_attachments_are_optional(): void
    {
        $this->post('/contact', $this->validPayload())->assertRedirect();

        $this->assertCount(0, ContactRequest::query()->firstOrFail()->getMedia('attachments'));
    }

    public function test_disallowed_file_types_are_rejected_and_nothing_is_saved(): void
    {
        Storage::fake('local');

        $response = $this->post('/contact', $this->validPayload([
            'attachments' => [UploadedFile::fake()->create('shell.php', 10)],
        ]));

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('contact_requests', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_files_over_the_size_limit_are_rejected(): void
    {
        Storage::fake('local');

        $response = $this->post('/contact', $this->validPayload([
            'attachments' => [
                UploadedFile::fake()->create('huge.pdf', ContactRequest::MAX_ATTACHMENT_KILOBYTES + 1),
            ],
        ]));

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_a_file_exactly_at_the_size_limit_is_accepted(): void
    {
        Storage::fake('local');

        $this->post('/contact', $this->validPayload([
            'attachments' => [
                UploadedFile::fake()->create('limit.pdf', ContactRequest::MAX_ATTACHMENT_KILOBYTES),
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertCount(1, ContactRequest::query()->firstOrFail()->getMedia('attachments'));
    }

    public function test_too_many_files_are_rejected(): void
    {
        Storage::fake('local');

        $files = array_map(
            fn (int $number) => UploadedFile::fake()->create("file-{$number}.pdf", 10),
            range(1, ContactRequest::MAX_ATTACHMENTS + 1),
        );

        $response = $this->post('/contact', $this->validPayload(['attachments' => $files]));

        $response->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_the_maximum_number_of_files_is_accepted(): void
    {
        Storage::fake('local');

        $files = array_map(
            fn (int $number) => UploadedFile::fake()->create("file-{$number}.pdf", 10),
            range(1, ContactRequest::MAX_ATTACHMENTS),
        );

        $this->post('/contact', $this->validPayload(['attachments' => $files]))
            ->assertSessionHasNoErrors();

        $this->assertCount(
            ContactRequest::MAX_ATTACHMENTS,
            ContactRequest::query()->firstOrFail()->getMedia('attachments'),
        );
    }

    public function test_notification_email_lists_the_attached_files(): void
    {
        Storage::fake('local');

        $contactRequest = ContactRequest::factory()->create();
        $contactRequest->addMedia(UploadedFile::fake()->create('plan.pdf', 50))->toMediaCollection('attachments');

        $html = (new ContactRequestReceivedMail($contactRequest))->render();

        $this->assertStringContainsString('Attachments (1)', $html);
        $this->assertStringContainsString('plan.pdf', $html);
    }

    public function test_notification_email_omits_the_attachments_line_when_there_are_none(): void
    {
        $html = (new ContactRequestReceivedMail(ContactRequest::factory()->create()))->render();

        $this->assertStringNotContainsString('Attachments', $html);
    }
}
