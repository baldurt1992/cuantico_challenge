<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryEvent extends Model
{
    use HasFactory;

    protected $fillable = ['emitter_id', 'event_id', 'delivery_id', 'type', 'occurred_at', 'received_at', 'data', 'fingerprint'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public $timestamps = false;

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
