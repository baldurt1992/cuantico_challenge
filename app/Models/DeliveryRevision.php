<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryRevision extends Model
{
    use HasFactory;

    protected $fillable = ['delivery_id', 'event_id', 'snapshot', 'reason'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
