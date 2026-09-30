<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\ContactRequestStatus;
use App\Filament\Resources\ContactRequests\Pages\EditContactRequest;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContactRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachments_survive_changing_the_status_of_a_request(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $contactRequest = ContactRequest::factory()->create();
        $contactRequest->addMedia(UploadedFile::fake()->create('plan.pdf', 50))->toMediaCollection('attachments');

        Livewire::test(EditContactRequest::class, ['record' => $contactRequest->id])
            ->assertOk()
            ->fillForm(['status' => ContactRequestStatus::Read->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $contactRequest->refresh();

        $this->assertSame(ContactRequestStatus::Read, $contactRequest->status);
        $this->assertCount(1, $contactRequest->getMedia('attachments'));
    }

    public function test_the_list_shows_how_many_files_each_request_carries(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $withFiles = ContactRequest::factory()->create();
        $withFiles->addMedia(UploadedFile::fake()->create('a.pdf', 10))->toMediaCollection('attachments');
        $withFiles->addMedia(UploadedFile::fake()->create('b.pdf', 10))->toMediaCollection('attachments');

        Livewire::test(ListContactRequests::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$withFiles])
            ->assertTableColumnStateSet('media_count', 2, $withFiles);
    }
}
