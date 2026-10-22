<?php

namespace App\Models;

use App\Enums\ActivationCodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationCode extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'code_hash', 'masked_suffix', 'expires_at', 'max_uses', 'used_count', 'status', 'intended_device_name', 'created_by'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'status' => ActivationCodeStatus::class, 'max_uses' => 'integer', 'used_count' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
