<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\SeoData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Home', [
            'seo' => SeoData::make(),
        ]);
    }
}
