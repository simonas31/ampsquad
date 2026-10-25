<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageExpiredTest extends TestCase
{
    /**
     * An expired session used to come back as a plain redirect, which the
     * browser treats as success: the cart showed "Added to cart" for a
     * product that was never added. It must arrive as an error.
     */
    public function test_an_expired_page_comes_back_as_an_error_the_page_can_show(): void
    {
        Route::middleware('web')->post('/_expired', fn () => throw new TokenMismatchException);

        $this->from('/shop')->post('/_expired')
            ->assertRedirect('/shop')
            ->assertSessionHasErrors(['page_expired' => 'page_expired']);
    }
}
