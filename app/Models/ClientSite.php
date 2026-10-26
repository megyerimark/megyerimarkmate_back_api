<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClientSite extends Model
{
    protected $fillable = [
        'name', 'domain', 'quote_request_id', 'client_name', 'client_email', 'launched_on', 'hosting_provider',
        'domain_registrar', 'domain_expires_on', 'hosting_expires_on', 'ssl_expires_on', 'tech_stack', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'launched_on' => 'date:Y-m-d', 'domain_expires_on' => 'date:Y-m-d',
            'hosting_expires_on' => 'date:Y-m-d', 'ssl_expires_on' => 'date:Y-m-d',
        ];
    }

    /** A következő N napban lejáró domain / tárhely / SSL. */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        $until = today()->addDays($days);
        return $query->where(fn (Builder $q) => $q
            ->whereBetween('domain_expires_on', [today()->subDays(30), $until])
            ->orWhereBetween('hosting_expires_on', [today()->subDays(30), $until])
            ->orWhereBetween('ssl_expires_on', [today()->subDays(30), $until]));
    }
}
