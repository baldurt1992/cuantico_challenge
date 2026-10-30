<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Merchant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'notes', 'timezone'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function couriers(): BelongsToMany
    {
        return $this->belongsToMany(Courier::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
