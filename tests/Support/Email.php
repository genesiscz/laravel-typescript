<?php

namespace Based\TypeScript\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Email implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): EmailValueObject
    {
        return new EmailValueObject($attributes['email']);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        if (! $value instanceof EmailValueObject) {
            throw new InvalidArgumentException('The given value is not an Email instance.');
        }

        return [
            'email' => $value->email,
        ];
    }
}
