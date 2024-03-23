<?php

namespace Based\TypeScript\Tests\Models;

use Based\TypeScript\Tests\Support\Email;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): User => new User($value),
        );
    }
}
