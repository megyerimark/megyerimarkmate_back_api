<?php

namespace App\Http\Controllers;

use App\Models\ProjectMilestone;
use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMilestoneController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'done'])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function index(QuoteRequest $quoteRequest): JsonResponse
    {
        return response()->json(['data' => $quoteRequest->milestones()->get()]);
    }

    public function store(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate(array_merge($this->rules(), ['title' => ['required', 'string', 'max:150']]));
        if ($quoteRequest->milestones()->count() >= 50) {
            return response()->json(['message' => 'Egy projekthez legfeljebb 50 mérföldkő vehető fel.'], 422);
        }
        $milestone = $quoteRequest->milestones()->create($data);
        AdminAudit::record($request, 'milestone_created', 'quote_request', $quoteRequest->id);
        return response()->json(['data' => $milestone], 201);
    }

    public function update(Request $request, QuoteRequest $quoteRequest, ProjectMilestone $milestone): JsonResponse
    {
        abort_unless($milestone->quote_request_id === $quoteRequest->id, 404);
        $data = $request->validate($this->rules());
        if (! $data) return response()->json(['message' => 'Nincs változtatás.'], 422);
        $milestone->update($data);
        AdminAudit::record($request, 'milestone_updated', 'quote_request', $quoteRequest->id);
        return response()->json(['data' => $milestone->fresh()]);
    }

    public function destroy(Request $request, QuoteRequest $quoteRequest, ProjectMilestone $milestone): JsonResponse
    {
        abort_unless($milestone->quote_request_id === $quoteRequest->id, 404);
        $milestone->delete();
        AdminAudit::record($request, 'milestone_deleted', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Mérföldkő törölve.']);
    }
}
