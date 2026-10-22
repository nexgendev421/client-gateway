<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'public_id', 'name', 'platform', 'os_version', 'app_version', 'status', 'registered_at', 'last_seen_at', 'last_ip'];

    protected function casts(): array
    {
        return ['platform' => DevicePlatform::class, 'status' => DeviceStatus::class, 'registered_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DeviceCredential::class);
    }
}
