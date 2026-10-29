<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Support\PublicCache;
use App\Support\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PublicContentController extends Controller
{
    public function services(): JsonResponse
    {
        return $this->cached(['data' => PublicCache::remember('services', fn () => Service::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()->toArray())]);
    }

    public function projects(): JsonResponse
    {
        return $this->cached(['data' => PublicCache::remember('projects', fn () => PortfolioProject::where('is_published', true)->where('is_demo', false)
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id')->get()->toArray())]);
    }

    public function project(string $slug): JsonResponse
    {
        $project = PortfolioProject::where('slug', $slug)->where('is_published', true)->where('is_demo', false)->firstOrFail();
        return response()->json(['data' => $project]);
    }

    public function settings(): JsonResponse
    {
        $settings = PublicCache::remember('settings', fn () => SiteSetting::allPublic());
        // Az akció állapota (lejárat!) mindig frissen számolódik, a gyorsítótárazott beállításokból.
        // A Turnstile nyilvános (site) kulcsa: a frontend ezzel jeleníti meg a robotszűrő widgetet.
        return $this->cached(['data' => $settings + [
            'promo' => Promotion::current($settings),
            'turnstile_site_key' => config('services.turnstile.secret_key') ? config('services.turnstile.site_key') : null,
        ]], 60);
    }

    /** Csak az admin által publikált vélemények; a projekthivatkozás csak valódi, publikus referenciára mutat. */
    public function testimonials(): JsonResponse
    {
        $items = PublicCache::remember('testimonials', fn () => Testimonial::with('project:id,slug,title,is_published,is_demo')
            ->where('is_published', true)
            ->orderBy('sort_order')->orderByDesc('id')->limit(12)->get()
            ->map(fn (Testimonial $item) => [
                'id' => $item->id,
                'author_name' => $item->author_name,
                'author_role' => $item->author_role,
                'company' => $item->company,
                'quote' => $item->quote,
                'rating' => $item->rating,
                'project' => $item->project && $item->project->is_published && ! $item->project->is_demo
                    ? ['slug' => $item->project->slug, 'title' => $item->project->title] : null,
            ])->values()->all());
        return $this->cached(['data' => $items]);
    }

    /** Rövid böngésző-/proxy-gyorsítótár a nyilvános JSON-ra; utána háttérben frissít. */
    private function cached(array $payload, int $maxAge = 120): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', "private, max-age={$maxAge}, stale-while-revalidate=600");
    }

    /**
     * Dinamikus oldaltérkép: a statikus oldalak mellett minden publikált referencia
     * (munkak/{slug}) automatikusan bekerül, így a Google új esettanulmányokat is megtalál.
     * Minden oldal magyarul és angolul (?lang=en) is szerepel, egymásra hivatkozó hreflang-párokkal – így a Google a magyar és a külföldi keresésekben is a megfelelő nyelvű változatot mutatja.
     */
    public function sitemap(): Response
    {
        $base = config('portfolio.frontend_url');
        $pages = ['/', '/tervezo', '/ajanlatkeres', '/projekt-kviz', '/konzultacio', '/adatkezeles', '/impresszum', '/aszf'];
        $urls = collect($pages)->map(fn ($path) => ['loc' => $base.$path, 'lastmod' => null]);
        PortfolioProject::where('is_published', true)->where('is_demo', false)
            ->orderBy('sort_order')->get(['slug', 'updated_at'])
            ->each(fn ($p) => $urls->push(['loc' => $base.'/munkak/'.rawurlencode($p->slug), 'lastmod' => $p->updated_at?->toDateString()]));

        $e = fn (string $url) => htmlspecialchars($url, ENT_XML1);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($urls as $url) {
            $variants = ['hu' => $url['loc'], 'en' => $url['loc'].'?lang=en'];
            $alternates = '';
            foreach ($variants + ['x-default' => $url['loc']] as $lang => $href) {
                $alternates .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.$e($href).'"/>';
            }
            foreach ($variants as $loc) {
                $xml .= '  <url><loc>'.$e($loc).'</loc>'
                    .($url['lastmod'] ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '').$alternates.'</url>'."\n";
            }
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
