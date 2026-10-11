<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Served by a route instead of a file written to public/, so it exists on every
 * environment with no deploy or scheduler step, and its Sitemap: line always
 * follows the current APP_URL. Do not add public/robots.txt: a real file would
 * shadow this route.
 */
class RobotsController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const DISALLOWED_PATHS = ['/admin'];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');
        $lines[] = '';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
