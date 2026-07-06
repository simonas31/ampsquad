<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_404_renders_custom_inertia_error_page_outside_local_env(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('seo.noindex', true)
        );
    }

    public function test_404_falls_back_to_default_handling_in_local_env(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertDontSee('data-page');
    }
}
