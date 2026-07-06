<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CalculatorCategoryResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\VideoResource;
use App\Models\CalculatorCategory;
use App\Models\Project;
use App\Models\Video;
use App\Settings\GeneralSettings;
use App\Settings\HomepageSettings;
use App\Support\Seo\SeoData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(HomepageSettings $homepage, GeneralSettings $general): Response
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

        return Inertia::render('Home', [
            'seo' => SeoData::make(),
            'hero' => [
                'title' => $homepage->heroTitle[app()->getLocale()],
                'subtitle' => $homepage->heroSubtitle[app()->getLocale()],
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
            'contact' => [
                'email' => $general->email,
                'phone' => $general->phone,
                'address' => $general->address,
            ],
        ]);
    }
}
