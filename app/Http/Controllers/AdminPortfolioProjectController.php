<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePortfolioProjectRequest;
use App\Models\PortfolioProject;
use App\Support\AdminAudit;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPortfolioProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PortfolioProject::orderBy('sort_order')->orderByDesc('id')->get()]);
    }

    public function store(SavePortfolioProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['cover_image'], $data['remove_cover_image']);
        $data['slug'] = UniqueSlug::for($data['title'], PortfolioProject::class);
        $project = new PortfolioProject($data);
        if ($request->hasFile('cover_image')) {
            $project->cover_image_path = $request->file('cover_image')->store('portfolio', 'public');
        }
        try {
            $project->save();
        } catch (\Throwable $exception) {
            if ($project->cover_image_path) {
                Storage::disk('public')->delete($project->cover_image_path);
            }
            throw $exception;
        }
        AdminAudit::record($request, 'created', 'portfolio_project', $project->id);
        return response()->json(['data' => $project], 201);
    }

    public function show(PortfolioProject $project): JsonResponse
    {
        return response()->json(['data' => $project]);
    }

    public function update(SavePortfolioProjectRequest $request, PortfolioProject $project): JsonResponse
    {
        $data = $request->validated();
        unset($data['cover_image'], $data['remove_cover_image']);
        if (isset($data['title']) && $data['title'] !== $project->title) {
            $data['slug'] = UniqueSlug::for($data['title'], PortfolioProject::class, $project->id);
        }
        $oldImage = $project->cover_image_path;
        $newImage = null;
        if ($request->hasFile('cover_image')) {
            $newImage = $request->file('cover_image')->store('portfolio', 'public');
        }
        $project->fill($data);
        if ($newImage) {
            $project->cover_image_path = $newImage;
        } elseif ($request->boolean('remove_cover_image')) {
            $project->cover_image_path = null;
        }
        try {
            $project->save();
        } catch (\Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }
            throw $exception;
        }
        if ($oldImage && $oldImage !== $project->cover_image_path) {
            Storage::disk('public')->delete($oldImage);
        }
        AdminAudit::record($request, 'updated', 'portfolio_project', $project->id);
        return response()->json(['data' => $project->fresh()]);
    }

    public function destroy(Request $request, PortfolioProject $project): JsonResponse
    {
        $id = $project->id;
        $image = $project->cover_image_path;
        $project->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }
        AdminAudit::record($request, 'deleted', 'portfolio_project', $id);
        return response()->json(['message' => 'Referenciaprojekt törölve.']);
    }
}
