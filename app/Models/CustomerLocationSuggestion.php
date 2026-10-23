<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLocationSuggestion extends Model
{
    protected $fillable = ['type', 'name', 'normalized_name', 'created_by'];
}
