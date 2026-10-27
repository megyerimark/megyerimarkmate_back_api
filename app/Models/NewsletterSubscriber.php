<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class NewsletterSubscriber extends Model
{
    use Prunable;

    protected $fillable = ['email', 'status', 'lang', 'source', 'consent_text', 'consent_ip', 'consent_user_agent'];
    protected $hidden = ['confirm_token_hash', 'unsubscribe_token', 'consent_user_agent'];
    protected $casts = ['confirmation_sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];

    /** Meg nem erősített feliratkozás 30 nap után törlődik (adattakarékosság). */
    public function prunable(): Builder
    {
        return static::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(30));
    }
}
