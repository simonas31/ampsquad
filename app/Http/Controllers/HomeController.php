<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CalculatorCategoryResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\VideoResource;
use App\Models\CalculatorCategory;
use App\Models\Project;
use App\Models\Video;
use App\Settings\HomepageSettings;
use App\Support\Seo\SeoData;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(HomepageSettings $homepage): Response
    {
        $featuredProjects = Project::query()
            ->published()
            ->featured()
            ->with('category')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        $videos = Video::query()
            ->active()
            ->orderBy('order')
            ->get();

        $calculatorCategories = CalculatorCategory::query()
            ->active()
            ->with('options')
            ->orderBy('order')
            ->get();

        $locale = app()->getLocale();

        return Inertia::render('Home', [
            'seo' => SeoData::make(),
            'hero' => [
                'title' => $homepage->heroTitle[$locale],
                'subtitle' => $homepage->heroSubtitle[$locale],
                'imageUrl' => $homepage->heroImage
                    ? Storage::disk('public')->url($homepage->heroImage)
                    : null,
                'stats' => collect($homepage->heroStats)
                    ->map(fn (array $stat): array => [
                        'value' => (int) $stat['value'],
                        'unit' => $stat['unit'][$locale],
                        'label' => $stat['label'][$locale],
                    ])
                    ->values()
                    ->all(),
            ],
            'intro' => [
                'title' => $homepage->introTitle[app()->getLocale()],
                'content' => $homepage->introContent[app()->getLocale()],
            ],
            'cta' => [
                'title' => $homepage->ctaTitle[app()->getLocale()],
                'buttonLabel' => $homepage->ctaButtonLabel[app()->getLocale()],
            ],
            'featuredProjects' => ProjectResource::collection($featuredProjects),
            'videos' => VideoResource::collection($videos),
            'calculatorCategories' => CalculatorCategoryResource::collection($calculatorCategories),
        ]);
    }
}
