<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['business_name', 'trading_name', 'contact_name', 'email', 'phone', 'status', 'notes'];

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function licence(): HasOne
    {
        return $this->hasOne(Licence::class);
    }
}
