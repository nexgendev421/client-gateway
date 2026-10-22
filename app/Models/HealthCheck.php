<?php

namespace App\Models;

use App\Enums\HealthCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCheck extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'status', 'response_time_ms', 'pos_version', 'api_version', 'checked_at', 'safe_error_code', 'safe_error_message'];

    protected function casts(): array
    {
        return ['status' => HealthCheckStatus::class, 'checked_at' => 'datetime', 'response_time_ms' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
