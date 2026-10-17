<?php

namespace App\Services;

class SeoService
{
    /**
     * Get the list of public indexable pages for the sitemap and metadata.
     *
     * @return array<int, array{path: string, source: string, title: string, description: string}>
     */
    public function getPublicPages(): array
    {
        return [
            [
                'path' => '/',
                'source' => resource_path('js/pages/Welcome.vue'),
                'title' => 'Homepage',
                'description' => 'Overview of ValenceKit and its Laravel/Vue web application work.',
            ],
            [
                'path' => '/services',
                'source' => resource_path('js/pages/Services.vue'),
                'title' => 'Services',
                'description' => 'Laravel web applications, POS, CRM, e-commerce, server engineering, and payment integrations.',
            ],
            [
                'path' => '/privacy-policy',
                'source' => resource_path('js/pages/PrivacyPolicy.vue'),
                'title' => 'Privacy Policy',
                'description' => 'Transparent data handling, credential custody, and user data rights.',
            ],
            [
                'path' => '/terms',
                'source' => resource_path('js/pages/Terms.vue'),
                'title' => 'Terms of Service',
                'description' => 'Usage guidelines, terms of service, acceptable use, and platform licensing.',
            ],
        ];
    }

    /**
     * Generate robots.txt content.
     */
    public function generateRobots(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $adminPath = trim((string) config('admin.alias'), '/');

        return implode(PHP_EOL, [
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
        ]);
    }

    /**
     * Generate sitemap.xml content.
     */
    public function generateSitemap(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $entries = $this->getPublicPages();

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

        return '<?xml version="1.0" encoding="UTF-8"?>'.
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
            $urls.
            '</urlset>';
    }

    /**
     * Generate llms.txt content.
     */
    public function generateLlms(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $pages = collect($this->getPublicPages())->map(function (array $page) use ($baseUrl): string {
            return "- [{$page['title']}]({$baseUrl}{$page['path']}): {$page['description']}";
        })->implode(PHP_EOL);

        return implode(PHP_EOL, [
            '# ValenceKit',
            '',
            '> ValenceKit is a Laravel 13 and Vue.js 3 boilerplate for building production-ready web applications with Inertia.js.',
            '',
            'ValenceKit connects a Laravel backend with a Vue.js frontend through Inertia.js while keeping standard API and SPA architectures available. The project includes server-side rendering, typed route generation, Tailwind CSS, and admin/MCP capabilities.',
            '',
            '## Public Pages',
            '',
            $pages,
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
        ]);
    }
}
