<?php

namespace App\Console\Commands;

use App\Mail\ReviewRequest;
use App\Models\QuoteRequest;
use App\Models\SiteSetting;
use App\Support\Mailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Naponta fut: a kódátadás után 5 nappal (legfeljebb 30 napig visszamenőleg) egyszer megkéri az ügyfelet
 * egy Google-értékelésre. Csak akkor, ha az adminban meg van adva a Google-értékelési link.
 */
class ReviewRequests extends Command
{
    protected $signature = 'portfolio:review-requests';
    protected $description = 'Értékeléskérés e-mail néhány nappal a projekt átadása után';

    public function handle(): int
    {
        $url = trim((string) (SiteSetting::allPublic()['google_review_url'] ?? ''));
        if ($url === '' || ! str_starts_with($url, 'https://') || ! Mailer::canSend()) {
            $this->info('Nincs Google-értékelési link vagy levélküldés – kihagyva.');
            return self::SUCCESS;
        }
        $sent = 0;
        $quotes = QuoteRequest::query()->whereNull('review_requested_at')->whereNotNull('handed_over_at')
            ->whereBetween('handed_over_at', [now()->subDays(30), now()->subDays(5)])->get();
        foreach ($quotes as $quote) {
            try {
                Mail::to($quote->email)->send(new ReviewRequest($quote, $url));
                $quote->forceFill(['review_requested_at' => now()])->save();
                $sent++;
            } catch (Throwable $e) {
                Log::error('Review request failed', ['quote_id' => $quote->id, 'exception_type' => $e::class]);
            }
        }
        $this->info($sent.' értékeléskérés elküldve.');
        return self::SUCCESS;
    }
}
