<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Crypt;

/** Ügyfél privát projektlinkje (64 hex jegyű kulcs) alapján az ajánlat – csak érvényes, le nem járt linkkel. */
class ClientAccess
{
    public static function quote(string $token): QuoteRequest
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            abort(404);
        }
        return QuoteRequest::where('portal_token_hash', hash('sha256', $token))
            ->where('portal_token_expires_at', '>', now())
            ->firstOrFail();
    }

    /**
     * Az ügyfél privát projektlinkje. A meglévő, érvényes link marad; lejárt vagy hiányzó link helyett újat készít.
     * Kizárólag e-mail-küldés előtt hívd: a link csak az ügyfélnek küldött levélben jelenik meg.
     */
    public static function portalUrl(QuoteRequest $quote): string
    {
        $token = $quote->portal_token_encrypted ? Crypt::decryptString($quote->portal_token_encrypted) : null;
        if (! $token || ! $quote->portal_token_expires_at || $quote->portal_token_expires_at->isPast()) {
            $token = bin2hex(random_bytes(32));
            $quote->portal_token_hash = hash('sha256', $token);
            $quote->portal_token_encrypted = Crypt::encryptString($token);
            $quote->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
            $quote->save();
        }
        return config('portfolio.frontend_url').'/ugyfel/projekt#'.$token;
    }
}
