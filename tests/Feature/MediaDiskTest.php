<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Public site images must land on the public disk whatever FILESYSTEM_DISK
 * is. Production runs with it set to `local` (the private disk), where
 * Filament's upload used to put them: nginx then answered 404 for every
 * image, because only storage/app/public is linked into public/.
 */
class MediaDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filament.default_filesystem_disk' => 'local', 'filesystems.default' => 'local']);
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs(User::factory()->create());
    }

    public function test_the_project_form_uploads_to_the_public_disk(): void
    {
        Livewire::test(CreateProject::class)
            ->assertFormFieldExists('featured_image', fn ($field) => $field->getDiskName() === 'public')
            ->assertFormFieldExists('gallery', fn ($field) => $field->getDiskName() === 'public');
    }

    public function test_the_page_form_uploads_to_the_public_disk(): void
    {
        Livewire::test(CreatePage::class)
            ->assertFormFieldExists('featured_image', fn ($field) => $field->getDiskName() === 'public');
    }

    public function test_the_video_form_uploads_to_the_public_disk(): void
    {
        Livewire::test(CreateVideo::class)
            ->assertFormFieldExists('video', fn ($field) => $field->getDiskName() === 'public')
            ->assertFormFieldExists('poster', fn ($field) => $field->getDiskName() === 'public');
    }

    public function test_images_added_to_a_project_or_page_land_on_the_public_disk_with_their_conversions(): void
    {
        foreach ([Project::factory()->create(), Page::factory()->create()] as $model) {
            $media = $model->addMedia(UploadedFile::fake()->image('photo.jpg', 800, 600))->toMediaCollection('featured_image');

            $this->assertSame('public', $media->disk);
            $this->assertSame('public', $media->conversions_disk);
            Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
            Storage::disk('local')->assertMissing($media->getPathRelativeToRoot());
            $this->assertStringContainsString('/storage/'.$media->id.'/', $media->getUrl());
        }
    }
}
