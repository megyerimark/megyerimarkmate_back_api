<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = ['subject', 'body', 'button_text', 'button_url', 'audience', 'recipients', 'sent_count', 'failed_count', 'sent_at'];
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients' => 'integer', 'sent_count' => 'integer', 'failed_count' => 'integer'];
    }
}
