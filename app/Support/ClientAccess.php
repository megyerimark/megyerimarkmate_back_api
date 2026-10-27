<?php

namespace App\Support;

use App\Models\QuoteRequest;

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
}
