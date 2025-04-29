<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletes\LegacySoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string> $fillable
 */
class Fornecedor extends LegacyModel
{
    use LegacySoftDeletes;

    protected $table = 'cadastro.fornecedor';

    protected $fillable = [
        'ref_idpes',
    ];
}
