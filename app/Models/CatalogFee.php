<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogFee extends Model
{
    public const KINDS = ['required' => 'Required charge', 'optional' => 'Optional charge', 'alternative' => 'Choose one from a set'];

    protected $fillable = ['name', 'amount', 'kind', 'choice_group', 'notes'];

    protected $casts = ['amount' => 'decimal:2'];
}
