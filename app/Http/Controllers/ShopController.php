<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShopIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
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

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => null],
        ]);

        return Inertia::render('Shop/Index', [
            'products' => ProductResource::collection($products),
            // A category that no longer exists is dropped, so the form and the
            // links built from it never carry a slug that matches nothing.
            'filters' => [...$filters->toArray(), 'category' => $catalog->selectedCategory($filters)?->slug],
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

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => route('shop.index')],
            ['label' => $product->name, 'url' => null],
        ]);

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
