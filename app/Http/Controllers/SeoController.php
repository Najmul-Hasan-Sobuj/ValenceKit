<?php

namespace App\Http\Controllers;

use App\Services\SeoService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    public function robots(): Response
    {
        return response($this->seoService->generateRobots())
            ->header('Content-Type', 'text/plain');
    }

    public function sitemap(): Response
    {
        return response($this->seoService->generateSitemap())
            ->header('Content-Type', 'application/xml');
    }

    public function llms(): Response
    {
        return response($this->seoService->generateLlms())
            ->header('Content-Type', 'text/markdown');
    }
}
