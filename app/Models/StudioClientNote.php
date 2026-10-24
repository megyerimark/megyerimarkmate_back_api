<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioClientNote extends Model
{
    protected $fillable = ['client_email', 'body'];
}
