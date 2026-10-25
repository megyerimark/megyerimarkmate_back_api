<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTestimonialController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Testimonial::orderBy('sort_order')->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $testimonial = Testimonial::create($this->validated($request, true));
        AdminAudit::record($request, 'created', 'testimonial', $testimonial->id);
        return response()->json(['data' => $testimonial], 201);
    }

    public function update(Request $request, Testimonial $testimonial): JsonResponse
    {
        $testimonial->fill($this->validated($request, false))->save();
        AdminAudit::record($request, 'updated', 'testimonial', $testimonial->id);
        return response()->json(['data' => $testimonial->fresh()]);
    }

    public function destroy(Request $request, Testimonial $testimonial): JsonResponse
    {
        $id = $testimonial->id;
        $testimonial->delete();
        AdminAudit::record($request, 'deleted', 'testimonial', $id);
        return response()->json(['message' => 'Vélemény törölve.']);
    }

    private function validated(Request $request, bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';
        return $request->validate([
            'author_name' => [$presence, 'required', 'string', 'max:120'],
            'author_role' => ['sometimes', 'nullable', 'string', 'max:120'],
            'company' => ['sometimes', 'nullable', 'string', 'max:150'],
            'quote' => [$presence, 'required', 'string', 'min:20', 'max:1000'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'portfolio_project_id' => ['sometimes', 'nullable', 'integer', 'exists:portfolio_projects,id'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
