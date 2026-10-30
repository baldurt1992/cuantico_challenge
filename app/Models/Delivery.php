<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Delivery extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'merchant_id', 'status', 'quality', 'issues', 'courier_id', 'delivery_address', 'planned_pickup', 'planned_destination', 'created_occurred_at', 'completed_at', 'cost_minor', 'calculation'];

    protected function casts(): array
    {
        return ['planned_pickup' => 'array', 'planned_destination' => 'array', 'issues' => 'array', 'calculation' => 'array', 'cost_minor' => 'integer'];
    }

    public $incrementing = false;

    protected $keyType = 'string';

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DeliveryRevision::class);
    }
}
