<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Tariff extends Model
{
    use HasFactory;

    protected $fillable = ['merchant_id', 'effective_from', 'base_minor', 'per_km_minor', 'currency', 'timezone'];

    protected function casts(): array
    {
        return ['base_minor' => 'integer', 'per_km_minor' => 'integer'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
