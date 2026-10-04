<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_checkboxes_default_to_unchecked_and_can_be_set_on_create(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePage::class)
            ->assertFormSet(['show_in_header' => false, 'show_in_footer' => false])
            ->fillForm([
                'key' => 'faq',
                'title' => 'DUK',
                'slug' => 'duk',
                'show_in_header' => true,
                'show_in_footer' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('key', 'faq')->firstOrFail();

        $this->assertTrue($page->show_in_header);
        $this->assertTrue($page->show_in_footer);
    }

    public function test_menu_checkboxes_can_be_changed_on_edit(): void
    {
        $this->actingAs(User::factory()->create());

        $page = Page::factory()->create(['key' => 'faq']);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertFormSet(['show_in_header' => false, 'show_in_footer' => false])
            ->fillForm(['show_in_header' => true, 'show_in_footer' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();

        $this->assertTrue($page->show_in_header);
        $this->assertFalse($page->show_in_footer);
    }

    public function test_menu_checkboxes_are_locked_on_the_about_page(): void
    {
        $this->actingAs(User::factory()->create());

        $about = Page::factory()->about()->create();

        Livewire::test(EditPage::class, ['record' => $about->id])
            ->assertFormSet(['show_in_header' => true, 'show_in_footer' => true])
            ->assertFormFieldIsDisabled('show_in_header')
            ->assertFormFieldIsDisabled('show_in_footer');
    }

    public function test_content_blocks_are_saved_through_the_form(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePage::class)
            ->fillForm([
                'key' => 'faq',
                'title' => 'DUK',
                'slug' => 'duk',
                'blocks' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => ['lt' => 'Antraštė', 'en' => 'Heading'],
                            'level' => 'h2',
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('key', 'faq')->firstOrFail();

        $this->assertCount(1, $page->blocks);
        $this->assertSame('heading', $page->blocks[0]['type']);
        $this->assertSame('Heading', $page->blocks[0]['data']['text']['en']);
    }
}
