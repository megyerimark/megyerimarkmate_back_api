<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ajánlatsablonok: egy kattintással betölthető csomagok az ajánlatszerkesztőben. */
class AdminProposalTemplateController extends Controller
{
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        return [
            'name' => [$req, 'string', 'min:2', 'max:100'],
            'website_type' => ['sometimes', 'nullable', Rule::in(['landing', 'business', 'webshop', 'custom'])],
            'price_huf' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'deposit_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'summary' => [$req, 'string', 'min:10', 'max:7000'],
            'schedule' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'valid_days' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => ProposalTemplate::orderBy('name')->limit(100)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        if (ProposalTemplate::count() >= 100) {
            return response()->json(['message' => 'Legfeljebb 100 sablon menthető.'], 422);
        }
        $template = ProposalTemplate::create($request->validate($this->rules(true)));
        AdminAudit::record($request, 'proposal_template_created', 'proposal_template', $template->id);
        return response()->json(['data' => $template, 'message' => 'Sablon mentve.'], 201);
    }

    public function update(Request $request, ProposalTemplate $proposalTemplate): JsonResponse
    {
        $proposalTemplate->update($request->validate($this->rules(false)));
        AdminAudit::record($request, 'proposal_template_updated', 'proposal_template', $proposalTemplate->id);
        return response()->json(['data' => $proposalTemplate->fresh(), 'message' => 'Sablon frissítve.']);
    }

    public function destroy(Request $request, ProposalTemplate $proposalTemplate): JsonResponse
    {
        $proposalTemplate->delete();
        AdminAudit::record($request, 'proposal_template_deleted', 'proposal_template', $proposalTemplate->id);
        return response()->json(['message' => 'Sablon törölve.']);
    }
}
