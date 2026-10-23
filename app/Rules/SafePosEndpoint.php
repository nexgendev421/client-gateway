<?php

namespace App\Rules;

use App\Services\PosEndpointGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePosEndpoint implements ValidationRule
{
    public function __construct(private readonly PosEndpointGuard $guard) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Enter a safe HTTPS endpoint URL using a public hostname.');

            return;
        }

        $reason = $this->guard->validationError($value, config('app.env') === 'production');
        if ($reason !== null) {
            $fail($reason);
        }
    }
}
