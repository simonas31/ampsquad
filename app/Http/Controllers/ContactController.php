<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ContactRequestSubmitted;
use App\Http\Requests\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Support\Seo\Breadcrumbs;
use App\Support\Seo\SeoData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        $breadcrumbs = Breadcrumbs::make([
            ['label' => __('nav.home'), 'url' => route('home')],
            ['label' => __('nav.contact'), 'url' => null],
        ]);

        return Inertia::render('Contact', [
            'breadcrumbs' => $breadcrumbs->toArray(),
            'seo' => SeoData::make(
                pageTitle: __('nav.contact'),
                jsonLd: [$breadcrumbs->jsonLd()],
            ),
        ]);
    }

    public function store(StoreContactRequestRequest $request): RedirectResponse
    {
        $contactRequest = ContactRequest::query()->create([
            ...$request->safe()->only(['name', 'email', 'phone', 'message']),
            'ip_address' => $request->ip(),
        ]);

        ContactRequestSubmitted::dispatch($contactRequest);

        Inertia::flash('contactSubmitted', true);

        return back();
    }
}
