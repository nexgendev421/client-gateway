<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'name', 'store_code', 'endpoint_url', 'status', 'max_devices', 'notes'];

    protected $hidden = ['store_code_normalized'];

    public function setStoreCodeAttribute(string $value): void
    {
        $this->attributes['store_code'] = Str::upper(trim($value));
    }

    public function getStoreCodeAttribute(string $value): string
    {
        return Str::upper($value);
    }

    public function scopeWhereStoreCode(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code_normalized', Str::upper(trim($storeCode)));
    }

    protected function casts(): array
    {
        return ['status' => StoreStatus::class, 'max_devices' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class);
    }

    public function latestHealthCheck(): HasOne
    {
        return $this->hasOne(HealthCheck::class)->latestOfMany('checked_at');
    }
}
