<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Models\AdminActivity;
use App\Models\StudioClientNote;
use App\Models\StudioTask;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminWorkspaceController extends Controller
{
    private const STATUSES = ['new', 'reviewing', 'quoted', 'accepted', 'rejected', 'completed'];

    public function board(): JsonResponse
    {
        $columns = [];
        $totals = [];
        foreach (self::STATUSES as $status) {
            $query = QuoteRequest::where('status', $status);
            $totals[$status] = (clone $query)->count();
            $columns[$status] = $query->latest('created_at')->latest('id')->limit(40)
                ->get(['id', 'customer_name', 'company_name', 'email', 'website_type', 'quoted_price_huf', 'status', 'created_at']);
        }

        return response()->json(['data' => ['columns' => $columns, 'totals' => $totals, 'limit_per_column' => 40]]);
    }

    public function projects(): JsonResponse
    {
        $projects = QuoteRequest::query()->whereIn('status', ['accepted', 'completed'])
            ->select(['id', 'customer_name', 'company_name', 'email', 'website_type', 'quoted_price_huf', 'status', 'created_at', 'proposal_accepted_at'])
            ->withCount(['milestones', 'milestones as milestones_done_count' => fn ($query) => $query->where('status', 'done')])
            ->latest('created_at')->limit(100)->get();
        return response()->json(['data' => $projects]);
    }

    public function communications(): JsonResponse
    {
        return response()->json(['data' => [
            'proposals' => QuoteRequest::whereNotNull('quoted_at')->latest('quoted_at')->limit(80)
                ->get(['id', 'customer_name', 'email', 'status', 'quoted_at', 'proposal_valid_until']),
            'events' => AdminActivity::whereIn('action', ['proposal_sent', 'portal_access_rotated'])
                ->latest('created_at')->limit(50)->get(['id', 'action', 'entity_id', 'created_at']),
        ]]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->validate(['state' => ['sometimes', Rule::in(['open', 'done', 'all'])]]);
        $query = StudioTask::query();
        if (($filters['state'] ?? 'all') === 'open') $query->where('is_done', false);
        if (($filters['state'] ?? 'all') === 'done') $query->where('is_done', true);

        return response()->json(['data' => $query->orderBy('is_done')->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')->latest('id')->limit(150)->get()]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'quote_request_id' => ['nullable', 'integer', 'exists:quote_requests,id'],
        ]);
        $task = StudioTask::create($data);
        AdminAudit::record($request, 'created', 'studio_task', $task->id);
        return response()->json(['data' => $task, 'message' => 'Feladat létrehozva.'], 201);
    }

    public function updateTask(Request $request, StudioTask $studioTask): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'is_done' => ['sometimes', 'required', 'boolean'],
        ]);
        if ($data === []) throw ValidationException::withMessages(['task' => 'Nincs módosítandó adat.']);
        $studioTask->update($data);
        AdminAudit::record($request, 'updated', 'studio_task', $studioTask->id);
        return response()->json(['data' => $studioTask->fresh()]);
    }

    public function destroyTask(Request $request, StudioTask $studioTask): JsonResponse
    {
        $id = $studioTask->id;
        $studioTask->delete();
        AdminAudit::record($request, 'deleted', 'studio_task', $id);
        return response()->json(['message' => 'Feladat törölve.']);
    }

    public function notes(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        return response()->json(['data' => StudioClientNote::where('client_email', $data['email'])->latest('id')->limit(100)->get()]);
    }

    public function storeNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'body' => ['required', 'string', 'min:2', 'max:1500'],
        ]);
        if (! QuoteRequest::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'Ehhez az e-mail-címhez még nincs ajánlatkérés.']);
        }
        $note = StudioClientNote::create(['client_email' => $data['email'], 'body' => $data['body']]);
        AdminAudit::record($request, 'created', 'studio_client_note', $note->id);
        return response()->json(['data' => $note, 'message' => 'Belső jegyzet mentve.'], 201);
    }

    public function destroyNote(Request $request, StudioClientNote $studioClientNote): JsonResponse
    {
        $id = $studioClientNote->id;
        $studioClientNote->delete();
        AdminAudit::record($request, 'deleted', 'studio_client_note', $id);
        return response()->json(['message' => 'Jegyzet törölve.']);
    }
}
