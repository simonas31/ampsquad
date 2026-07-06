<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_system_pages_are_protected(): void
    {
        $about = Page::factory()->about()->create();

        $this->assertTrue($about->isProtected());
    }

    public function test_arbitrary_pages_are_not_protected(): void
    {
        $faq = Page::factory()->create(['key' => 'faq']);

        $this->assertFalse($faq->isProtected());
    }

    public function test_policy_blocks_deleting_a_protected_page(): void
    {
        $user = User::factory()->create();
        $about = Page::factory()->about()->create();

        $this->assertFalse($user->can('delete', $about));
    }

    public function test_policy_allows_deleting_an_unprotected_page(): void
    {
        $user = User::factory()->create();
        $faq = Page::factory()->create(['key' => 'faq']);

        $this->assertTrue($user->can('delete', $faq));
    }
}
