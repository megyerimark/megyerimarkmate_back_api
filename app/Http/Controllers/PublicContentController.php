<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\SiteSetting;
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
}
