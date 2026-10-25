<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

class PublicContentController extends Controller
{
    public function services(): JsonResponse
    {
        return response()->json(['data' => Service::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function projects(): JsonResponse
    {
        return response()->json(['data' => PortfolioProject::where('is_published', true)->where('is_demo', false)
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id')->get()]);
    }

    public function project(string $slug): JsonResponse
    {
        $project = PortfolioProject::where('slug', $slug)->where('is_published', true)->where('is_demo', false)->firstOrFail();
        return response()->json(['data' => $project]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::allPublic()]);
    }

    /** Csak az admin által publikált vélemények; a projekthivatkozás csak valódi, publikus referenciára mutat. */
    public function testimonials(): JsonResponse
    {
        $items = Testimonial::with('project:id,slug,title,is_published,is_demo')
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
            ])->values();
        return response()->json(['data' => $items]);
    }
}
