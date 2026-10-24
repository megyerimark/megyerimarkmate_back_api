<?php

namespace App\Http\Controllers;

use App\Mail\ProposalAccepted;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ClientProjectController extends Controller
{
    private function findByToken(string $token): QuoteRequest
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            abort(404);
        }
        return QuoteRequest::where('portal_token_hash', hash('sha256', $token))
            ->where('portal_token_expires_at', '>', now())
            ->firstOrFail();
    }

    private function publicData(QuoteRequest $quote): array
    {
        return [
            'reference' => $quote->id,
            'customer_name' => $quote->customer_name,
            'website_type' => $quote->website_type,
            'design_style' => $quote->design_style,
            'industry' => $quote->industry,
            'layout_style' => $quote->layout_style,
            'font_style' => $quote->font_style,
            'status' => $quote->status,
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
            'quoted_at' => $quote->quoted_at,
            'quoted_price_huf' => $quote->quoted_at ? $quote->quoted_price_huf : null,
            'proposal_summary' => $quote->quoted_at ? $quote->proposal_summary : null,
            'proposal_schedule' => $quote->quoted_at ? $quote->proposal_schedule : null,
            'proposal_terms' => $quote->quoted_at ? $quote->proposal_terms : null,
            'proposal_valid_until' => $quote->quoted_at ? $quote->proposal_valid_until : null,
            'proposal_accepted_at' => $quote->proposal_accepted_at,
            'access_expires_at' => $quote->portal_token_expires_at,
            'milestones' => in_array($quote->status, ['accepted', 'completed'], true)
                ? $quote->milestones()->get(['id', 'title', 'description', 'status', 'due_date', 'sort_order'])->toArray()
                : [],
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $quote = $this->findByToken($data['token']);
        return response()->json(['data' => $this->publicData($quote)])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'accept_terms' => ['required', 'accepted'],
        ]);
        $quote = $this->findByToken($data['token']);
        // Lock while deciding to avoid duplicate acceptance from concurrent requests.
        $alreadyAccepted = false;
        $quote = \Illuminate\Support\Facades\DB::transaction(function () use ($quote, &$alreadyAccepted) {
            $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'accepted' && $locked->proposal_accepted_at) {
                $alreadyAccepted = true;
                return $locked;
            }
            if ($locked->status !== 'quoted' || ! $locked->quoted_at || ! $locked->proposal_valid_until || $locked->proposal_valid_until->isBefore(today())) {
                throw ValidationException::withMessages(['proposal' => 'Ez az ajánlat jelenleg nem fogadható el.']);
            }
            $locked->status = 'accepted';
            $locked->proposal_accepted_at = now();
            $locked->save();
            return $locked;
        });
        if (! $alreadyAccepted && config('portfolio.mail_enabled') && config('portfolio.admin_email') && ! in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            try {
                Mail::to(config('portfolio.admin_email'))->send(new ProposalAccepted($quote));
            } catch (\Throwable $exception) {
                Log::error('Proposal acceptance notice failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
            }
        }
        return response()->json(['message' => 'Az ajánlat elfogadását rögzítettük.', 'data' => $this->publicData($quote)])
            ->header('Cache-Control', 'no-store, private');
    }
}
