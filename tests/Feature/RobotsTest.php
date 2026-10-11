<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_blocks_the_admin_and_exposes_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
