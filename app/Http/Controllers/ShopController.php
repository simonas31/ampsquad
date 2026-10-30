<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShopIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Shop\ShopCatalog;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\LocalizedUrls;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(ShopIndexRequest $request, ShopCatalog $catalog): Response|RedirectResponse
    {
        $filters = $request->filters();
        $products = $catalog->products($filters);

        // A page past the end (a bookmark from before products were removed,
        // or a hand-edited link) would otherwise render "no products yet" for
        // a shop that has plenty. Send it to the last page that exists.
        if ($products->currentPage() > 1 && $products->isEmpty()) {
            $lastPage = $products->lastPage();

            return redirect()->route('shop.index', [...$request->query(), 'page' => $lastPage > 1 ? $lastPage : null]);
        }

        $selectedCategory = $catalog->selectedCategory($filters);
        $breadcrumbs = $this->breadcrumbs($selectedCategory);

        // The plain first page opens with the category menu and the popular
        // shelf; any search, filter or later page is only the results.
        $isLanding = ! $filters->isActive() && $products->currentPage() === 1;

        return Inertia::render('Shop/Index', [
            'products' => ProductResource::collection($products),
            'popularProducts' => $isLanding
                ? fn (): array => ProductResource::collection($catalog->popularProducts())->resolve()
                : null,
            'categoryMenu' => $isLanding ? fn (): array => $catalog->categoryMenu() : null,
            // A category that no longer exists is dropped, so the form and the
            // links built from it never carry a slug that matches nothing.
            'filters' => [...$filters->toArray(), 'category' => $selectedCategory?->slug],
            'categories' => $catalog->categoryTree($filters),
            'manufacturers' => $catalog->manufacturers($filters),
            'shopUrl' => route('shop.index'),
            'breadcrumbs' => $breadcrumbs->toArray(),
            // Searches and filtered cuts repeat the same products.
            'seo' => SeoData::make(
                pageTitle: __('nav.shop'),
                noindex: $filters->isActive(),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }

    public function show(string $slug): Response|RedirectResponse
    {
        $slugColumn = 'slug_'.app()->getLocale();

        $product = Product::query()
            ->active()
            ->with(['media', 'category', 'manufacturer'])
            ->where($slugColumn, $slug)
            ->first();

        if ($product === null) {
            $moved = Product::query()->active()->movedFromSlug($slug)->firstOrFail();

            return redirect()->route('shop.show', $moved->slug, 301);
        }

        LocalizedUrls::useSlugs($product->getTranslations('slug'));

        $breadcrumbs = $this->breadcrumbs($product->category, $product->name);

        $description = $product->excerpt ?: Str::limit((string) $product->description, 160);
        $image = $product->featuredImageUrl('large');

        return Inertia::render('Shop/Show', [
            'product' => new ProductResource($product),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: $product->name,
                description: $description !== '' ? $description : null,
                ogImage: $image,
                jsonLd: [$breadcrumbs->jsonLd(), $this->productJsonLd($product, $description, $image)],
            ),
        ]);
    }

    /**
     * Home, the shop, then the category's whole path from its top-level
     * ancestor down, each step linking to that category's listing. Ends on
     * the page itself: the product when one is given, otherwise the category
     * (a trail with no category is just Home and the shop). Only the
     * category is carried in the links, never a search or other filter, so
     * every step is a clean address a search engine can follow.
     */
    private function breadcrumbs(?ProductCategory $category, ?string $productName = null): Breadcrumbs
    {
        $trail = $category?->trail() ?? [];
        $isOnShopPage = $trail === [] && $productName === null;

        $items = [
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => $isOnShopPage ? null : route('shop.index')],
        ];

        foreach ($trail as $position => $node) {
            $isCurrentPage = $productName === null && $position === array_key_last($trail);

            $items[] = [
                'label' => $node->name,
                'url' => $isCurrentPage || blank($node->slug) ? null : route('shop.index', ['category' => $node->slug]),
            ];
        }

        if ($productName !== null) {
            $items[] = ['label' => $productName, 'url' => null];
        }

        return Breadcrumbs::make($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function productJsonLd(Product $product, string $description, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $description,
            'image' => $image,
            'sku' => $product->sku,
            'gtin' => $product->ean,
            'brand' => $product->manufacturer === null ? null : ['@type' => 'Brand', 'name' => $product->manufacturer->name],
            'offers' => [
                '@type' => 'Offer',
                'url' => url()->current(),
                'price' => number_format($product->priceInclVatCents() / 100, 2, '.', ''),
                'priceCurrency' => Product::CURRENCY,
                'availability' => $product->isInStock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ]);
    }
}
