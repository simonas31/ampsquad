<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountOrderResource;
use App\Http\Resources\AccountOrderSummaryResource;
use App\Models\Order;
use App\Services\Customers\CustomerOrderLinker;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's orders. These are the shop's orders, read as they are: their
 * status is the order's status, and nothing here moves one along.
 */
class OrderController extends Controller
{
    private const int PER_PAGE = 10;

    public function __construct(private readonly CustomerOrderLinker $linker) {}

    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $orders = $customer->orders()
            ->withSum('lines as items_count', 'quantity')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Account/Orders/Index', [
            'orders' => AccountOrderSummaryResource::collection($orders),
            'linkableCount' => $this->linker->linkableCount($customer),
            'linkUrl' => route('account.orders.link', absolute: false),
            'breadcrumbs' => $this->breadcrumbs(null)->toArray(),
            'seo' => SeoData::make(pageTitle: __('nav.orders'), noindex: true),
        ]);
    }

    public function show(Request $request, Order $customerOrder): Response
    {
        Gate::forUser($request->user('customer'))->authorize('viewOwn', $customerOrder);

        $title = __('nav.order').' '.$customerOrder->number;

        return Inertia::render('Account/Orders/Show', [
            'order' => new AccountOrderResource($customerOrder->load('lines.product.media')),
            'breadcrumbs' => $this->breadcrumbs($title)->toArray(),
            'seo' => SeoData::make(pageTitle: $title, noindex: true),
        ]);
    }

    private function breadcrumbs(?string $leaf): Breadcrumbs
    {
        return Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.account'), 'url' => route('account.dashboard')],
            ['label' => __('nav.orders'), 'url' => $leaf === null ? null : route('account.orders.index')],
            ...($leaf === null ? [] : [['label' => $leaf, 'url' => null]]),
        ]);
    }
}
