<?php

namespace App\Console\Commands;

use App\Mail\FollowUpReminder;
use App\Models\QuoteRequest;
use App\Support\Mailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Naponta fut: egyetlen összesítő e-mail az adminnak, ha
 *  - egy megkeresésre 2 napja nem született válasz (még „új” állapotú), vagy
 *  - egy elküldött ajánlatra 5 napja nem reagált az ügyfél (még érvényes, nincs elfogadva).
 * Tételenként egyszer jelez; 30 napnál régebbi tételt már nem hoz elő.
 */
class FollowUpReminders extends Command
{
    protected $signature = 'portfolio:follow-up-reminders';
    protected $description = 'Utánkövetési emlékeztető: megválaszolatlan megkeresések és válasz nélküli ajánlatok';

    public const REPLY_AFTER_DAYS = 2;
    public const QUOTE_AFTER_DAYS = 5;

    public function handle(): int
    {
        $owner = config('portfolio.admin_email');
        if (! $owner || ! Mailer::canSend()) {
            $this->info('Nincs admin e-mail-cím vagy levélküldés – kihagyva.');
            return self::SUCCESS;
        }

        $unanswered = QuoteRequest::query()->where('status', 'new')->whereNull('reply_reminded_at')
            ->whereBetween('created_at', [now()->subDays(30), now()->subDays(self::REPLY_AFTER_DAYS)])
            ->orderBy('created_at')->get();
        $silent = QuoteRequest::query()->where('status', 'quoted')->whereNull('quote_reminded_at')
            ->whereNull('proposal_accepted_at')->whereNotNull('quoted_at')
            ->whereBetween('quoted_at', [now()->subDays(30), now()->subDays(self::QUOTE_AFTER_DAYS)])
            ->where(fn ($q) => $q->whereNull('proposal_valid_until')->orWhereDate('proposal_valid_until', '>=', today()))
            ->orderBy('quoted_at')->get();

        if ($unanswered->isEmpty() && $silent->isEmpty()) {
            $this->info('Nincs utánkövetendő tétel.');
            return self::SUCCESS;
        }

        try {
            Mail::to($owner)->send(new FollowUpReminder($unanswered, $silent));
        } catch (Throwable $e) {
            Log::error('Follow-up reminder failed', ['exception_type' => $e::class]);
            return self::FAILURE;
        }
        QuoteRequest::whereKey($unanswered->modelKeys())->update(['reply_reminded_at' => now()]);
        QuoteRequest::whereKey($silent->modelKeys())->update(['quote_reminded_at' => now()]);
        $this->info(($unanswered->count() + $silent->count()).' tétel jelezve.');
        return self::SUCCESS;
    }
}
