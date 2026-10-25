<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioTask extends Model
{
    protected $fillable = ['title', 'description', 'due_date', 'is_done', 'quote_request_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'is_done' => 'boolean'];
    }
}
