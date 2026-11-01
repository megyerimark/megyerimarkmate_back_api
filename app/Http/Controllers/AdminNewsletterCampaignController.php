<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Support\AdminAudit;
use App\Support\Mailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hírlevél-küldés az adminból: tesztlevél magamnak, majd kiküldés a megerősített feliratkozóknak
 * (mindenki saját leiratkozó linket kap). A küldés a válasz után fut, az eredmény a kampánynaplóban látszik.
 */
class AdminNewsletterCampaignController extends Controller
{
    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:10000'],
            'button_text' => ['nullable', 'required_with:button_url', 'string', 'max:60'],
            'button_url' => ['nullable', 'required_with:button_text', 'url:https', 'max:500'],
            'audience' => ['required', Rule::in(['all', 'hu', 'en'])],
        ];
    }

    private function recipients(string $audience)
    {
        return NewsletterSubscriber::query()->where('status', 'confirmed')
            ->when($audience !== 'all', fn ($q) => $q->where('lang', $audience));
    }

    private function unsubscribeUrl(?string $token): string
    {
        return rtrim((string) config('portfolio.frontend_url'), '/').'/hirlevel/leiratkozas'.($token ? '#'.$token : '');
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => NewsletterCampaign::orderByDesc('id')->limit(50)->get(), 'audience' => [
            'all' => $this->recipients('all')->count(), 'hu' => $this->recipients('hu')->count(), 'en' => $this->recipients('en')->count(),
        ]]);
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $owner = (string) config('portfolio.admin_email');
        if ($owner === '' || ! Mailer::canSend()) {
            throw ValidationException::withMessages(['subject' => 'A levélküldés nincs beállítva (PORTFOLIO_ADMIN_EMAIL, MAIL_MAILER).']);
        }
        Mail::to($owner)->send(new NewsletterCampaignMail($data['subject'], $data['body'], $data['button_text'] ?? null, $data['button_url'] ?? null, $this->unsubscribeUrl(null), true));
        return response()->json(['message' => 'Tesztlevél elküldve ide: '.$owner]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + ['confirm' => ['accepted']]);
        if (! Mailer::canSend()) {
            throw ValidationException::withMessages(['subject' => 'A levélküldés nincs beállítva.']);
        }
        $total = $this->recipients($data['audience'])->count();
        if ($total === 0) {
            throw ValidationException::withMessages(['audience' => 'Nincs megerősített feliratkozó ebben a körben.']);
        }
        $campaign = NewsletterCampaign::create(collect($data)->except('confirm')->all() + ['recipients' => $total, 'sent_at' => now()]);
        AdminAudit::record($request, 'newsletter_sent', 'newsletter_campaign', $campaign->id);

        \Illuminate\Support\defer(function () use ($campaign) {
            $sent = 0; $failed = 0;
            $this->recipients($campaign->audience)->orderBy('id')->chunkById(100, function ($subscribers) use ($campaign, &$sent, &$failed) {
                foreach ($subscribers as $subscriber) {
                    if (! $subscriber->unsubscribe_token) {
                        $subscriber->forceFill(['unsubscribe_token' => bin2hex(random_bytes(32))])->save();
                    }
                    try {
                        Mail::to($subscriber->email)->send(new NewsletterCampaignMail(
                            $campaign->subject, $campaign->body, $campaign->button_text, $campaign->button_url, $this->unsubscribeUrl($subscriber->unsubscribe_token)));
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Newsletter send failed', ['campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id, 'exception_type' => $e::class]);
                    }
                }
                $campaign->forceFill(['sent_count' => $sent, 'failed_count' => $failed])->save();
            });
        });

        return response()->json(['message' => 'A hírlevél kiküldése elindult ('.$total.' címzett).', 'data' => $campaign], 201);
    }
}
