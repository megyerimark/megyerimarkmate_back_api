<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Személyes adatot nem tartalmazó, aggregálható weboldal-esemény. */
class SiteEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['event', 'path', 'source'];

    public const EVENTS = ['page_view', 'cta_click', 'quote_start', 'quiz_start', 'consultation_start'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
