<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Religion extends Model
{
    protected $table = 'religions';

    protected $fillable = [
        'name',
    ];

    // Laravel vai preencher created_at e updated_at
    public $timestamps = true;
}
