<?php

namespace Based\TypeScript\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Castable;

class EmailValueObject implements Castable
{
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public static function castUsing(array $arguments)
    {
        return Email::class;
    }
}
