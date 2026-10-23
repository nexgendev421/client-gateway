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
        $this->attributes['metadata'] = json_encode($this->removeSensitiveKeys(is_array($metadata) ? $metadata : []), JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function removeSensitiveKeys(array $metadata, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $metadata = array_slice($metadata, 0, 100, true);
        foreach ($metadata as $key => $value) {
            if (preg_match('/password|secret|token|activation.?code|authorization|cookie|credential|pin|private.?key/i', (string) $key)) {
                unset($metadata[$key]);
            } elseif (is_array($value)) {
                $metadata[$key] = $this->removeSensitiveKeys($value, $depth + 1);
            } elseif (is_string($value)) {
                $metadata[$key] = $this->sanitizeString($value);
            }
        }

        return $metadata;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $value) ?? '';
        $value = preg_replace('/\bsypd_[A-Za-z0-9_-]{24,}/i', '[credential redacted]', $value) ?? '';
        $value = preg_replace('/\b[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[signed token redacted]', $value) ?? '';
        $value = preg_replace('/\b\d{4}-?\d{4}\b/', '[activation code redacted]', $value) ?? '';
        $value = preg_replace('/\b(password|secret|token|activation[ _-]?code|credential|pin)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $value) ?? '';
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 1000);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
