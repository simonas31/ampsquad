<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\LocalizedUrls;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->active()
            ->with('media')
            ->orderBy('order')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.shop'), 'url' => null],
        ]);

        return Inertia::render('Shop/Index', [
            'products' => ProductResource::collection($products),
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: __('nav.shop'),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }

    public function show(string $slug): Response|RedirectResponse
    {
        $slugColumn = 'slug_'.app()->getLocale();

        $product = Product::query()
            ->active()
            ->with('media')
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
        $image = $product->getFirstMediaUrl('featured_image', 'large') ?: null;

        return Inertia::render('Shop/Show', [
            'product' => new ProductResource($product),
            'maxQuantity' => CartItem::MAX_QUANTITY,
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
            'offers' => [
                '@type' => 'Offer',
                'url' => url()->current(),
                'price' => number_format($product->price_cents / 100, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
            ],
        ]);
    }
}
