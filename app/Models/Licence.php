<?php

namespace App\Models;

use App\Enums\LicenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Licence extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'plan', 'plan_code', 'status', 'starts_at', 'valid_until', 'grace_until', 'max_stores', 'max_devices', 'metadata', 'internal_notes'];

    protected function casts(): array
    {
        return ['status' => LicenceStatus::class, 'starts_at' => 'datetime', 'valid_until' => 'datetime', 'grace_until' => 'datetime', 'metadata' => 'array', 'max_stores' => 'integer', 'max_devices' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
