<?php

namespace Based\TypeScript\Tests\Generators;

use Based\TypeScript\Generators\ModelGenerator;
use Based\TypeScript\Tests\Models\User;
use Based\TypeScript\Tests\Support\EmailValueObject;
use Based\TypeScript\Tests\TestCase;
use ReflectionClass;

class ModelGeneratorTest extends TestCase
{
    protected ModelGenerator $generator;

    public function setUp(): void
    {
        parent::setUp();

        $knownClasses = collect([
            'Based\TypeScript\Tests\Models\User',
            'Based\TypeScript\Tests\Models\Role',
        ]);
        $this->generator = new ModelGenerator(new ReflectionClass(User::class), $knownClasses->toArray());
    }

    public function testGetDependencies()
    {
        $dependencies = $this->generator->getDependencies();
        $this->assertContains('Based\TypeScript\Tests\Models\Role', $dependencies);
        $this->assertCount(2, $dependencies);
    }

    public function testGetDefinition()
    {
        $definition = $this->generator->getDefinition();
        $this->assertEquals(<<<EOF
export interface Based.TypeScript.Tests.Models.User {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  created_at: string | null;
  updated_at: string | null;
  roles?: Array<Based.TypeScript.Tests.Models.Role> | null;
  roles_count?: number | null;
  readonly first_name?: Based.TypeScript.Tests.Models.User | null;
}

EOF, $definition);
    }

    public function testGetDefinitionWithUnknownClasses()
    {
        $this->generator = new ModelGenerator(new ReflectionClass(User::class), collect());

        $definition = $this->generator->getDefinition();
        $this->assertEquals(<<<EOF
export interface Based.TypeScript.Tests.Models.User {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  created_at: string | null;
  updated_at: string | null;
  roles?: Array<any> | null;
  roles_count?: number | null;
  readonly first_name?: any | null;
}

EOF, $definition);
    }

}
