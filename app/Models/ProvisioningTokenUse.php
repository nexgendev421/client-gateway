<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningTokenUse extends Model
{
    use HasFactory;

    protected $fillable = ['device_id', 'jti_hash', 'used_at', 'expires_at'];

    protected $hidden = ['jti_hash'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
