<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['actor_id', 'action', 'resource_type', 'resource_id', 'metadata', 'ip_address', 'occurred_at'];

    protected $hidden = ['metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function setMetadataAttribute(array|string|null $value): void
    {
        $metadata = is_string($value) ? json_decode($value, true) : $value;
        $this->attributes['metadata'] = json_encode($this->removeSensitiveKeys(is_array($metadata) ? $metadata : []));
    }

    private function removeSensitiveKeys(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (preg_match('/password|secret|token|activation.?code|authorization|cookie|credential|pin|private.?key/i', (string) $key)) {
                unset($metadata[$key]);
            } elseif (is_array($value)) {
                $metadata[$key] = $this->removeSensitiveKeys($value);
            }
        }

        return $metadata;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
