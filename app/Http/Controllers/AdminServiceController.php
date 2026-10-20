<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveServiceRequest;
use App\Models\Service;
use App\Support\AdminAudit;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Service::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(SaveServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = UniqueSlug::for($data['title'], Service::class);
        $service = Service::create($data);
        AdminAudit::record($request, 'created', 'service', $service->id);
        return response()->json(['data' => $service], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json(['data' => $service]);
    }

    public function update(SaveServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['title']) && $data['title'] !== $service->title) {
            $data['slug'] = UniqueSlug::for($data['title'], Service::class, $service->id);
        }
        $service->update($data);
        AdminAudit::record($request, 'updated', 'service', $service->id);
        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $id = $service->id;
        $service->delete();
        AdminAudit::record($request, 'deleted', 'service', $id);
        return response()->json(['message' => 'Szolgáltatás törölve.']);
    }
}
