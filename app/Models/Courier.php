<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Courier extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class);
    }
}
