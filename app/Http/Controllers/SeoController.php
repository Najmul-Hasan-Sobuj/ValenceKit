<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $adminPath = trim((string) config('admin.alias'), '/');

        return response(implode(PHP_EOL, [
            'User-agent: *',
            'Allow: /',
            'Allow: /llms.txt',
            'Allow: /sitemap.xml',
            "Disallow: /{$adminPath}",
            'Disallow: /api/',
            'Disallow: /settings',
            'Disallow: /.well-known/',
            "Sitemap: {$baseUrl}/sitemap.xml",
            '',
        ]))->header('Content-Type', 'text/plain');
    }

    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $entries = [
            [
                'path' => '/',
                'source' => resource_path('js/pages/Welcome.vue'),
            ],
            [
                'path' => '/services',
                'source' => resource_path('js/pages/Services.vue'),
            ],
        ];

        $urls = collect($entries)->map(function (array $entry) use ($baseUrl): string {
            $lastModified = file_exists($entry['source'])
                ? (int) filemtime($entry['source'])
                : 0;
            $lastmod = $lastModified ? date(DATE_ATOM, $lastModified) : null;
            $url = htmlspecialchars($baseUrl.$entry['path'], ENT_XML1 | ENT_COMPAT, 'UTF-8');

            return "<url><loc>{$url}</loc>".
                ($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '').
                '</url>';
        })->implode('');

        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
            $urls.
            '</urlset>'
        )->header('Content-Type', 'application/xml');
    }

    public function llms(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return response(implode(PHP_EOL, [
            '# ValenceKit',
            '',
            '> ValenceKit is a Laravel 13 and Vue.js 3 boilerplate for building production-ready web applications with Inertia.js.',
            '',
            'ValenceKit connects a Laravel backend with a Vue.js frontend through Inertia.js while keeping standard API and SPA architectures available. The project includes server-side rendering, typed route generation, Tailwind CSS, and admin/MCP capabilities.',
            '',
            '## Public Pages',
            '',
            "- [Homepage]({$baseUrl}/): Overview of ValenceKit and its Laravel/Vue web application work.",
            "- [Services]({$baseUrl}/services): Laravel web applications, POS, CRM, e-commerce, server engineering, and payment integrations.",
            '',
            '## Project Resources',
            '',
            '- [ValenceKit source repository](https://github.com/Najmul-Hasan-Sobuj/ValenceKit): Public project source and setup information.',
            '- [SSR guide](https://github.com/Najmul-Hasan-Sobuj/ValenceKit/blob/main/SSR.md): Local and server-side Inertia SSR setup and maintenance.',
            '',
            '## Optional',
            '',
            '- This file describes public content only; do not infer private, authenticated, admin, or API content from it.',
            '',
        ]))->header('Content-Type', 'text/markdown');
    }
}
