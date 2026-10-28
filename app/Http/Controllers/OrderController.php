<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * An order's own page, found by its uuid, which only the customer has.
     * Where the payment provider sends the customer back to, and the page
     * that tells them how it went: it only reads, and an order only becomes
     * paid when the provider's confirmation arrives, never because the
     * customer came back.
     */
    public function show(Request $request, Order $order): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.order').' '.$order->number, 'url' => null],
        ]);

        return Inertia::render('Orders/Show', [
            'order' => new OrderResource($order->load('lines')),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.order').' '.$order->number, noindex: true),
        ])
            ->toResponse($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
