<?php

namespace Based\TypeScript\Contracts;

use Illuminate\Support\Collection;
use ReflectionClass;

interface Generator
{
    public function getDefinition(): ?string;

    public function getDependencies(): Collection;
}
