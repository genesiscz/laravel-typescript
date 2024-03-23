<?php

namespace Based\TypeScript\Tests\Unit;

use PHPUnit\Framework\TestCase;

class IndentTest extends TestCase
{
    public function testIndent()
    {
        $this->assertEquals("  Foo", indent("Foo"));
    }

    public function testIndentWithSpaces()
    {
        $this->assertEquals("    Foo", indent("Foo", 4));
    }

    public function testIndentOddSpaces()
    {
        $this->assertEquals("   Foo", indent("Foo", 3));
    }

    public function testIndentMultiple()
    {
        $this->assertEquals("   Foo", indent(indent("Foo", 1)));
    }

    public function testIndentMultiLine()
    {
        $this->assertEquals(<<<EOF
  Foo
  Bar
  Baz
EOF
, indent(<<<EOF
Foo
Bar
Baz
EOF));
    }
    public function testDedent()
    {
        $this->assertEquals("Foo", dedent("  Foo"));
    }
    public function testDedentRemainder()
    {
        $this->assertEquals("  Foo", dedent("    Foo"));
    }

    public function testDedentWithSpaces()
    {
        $this->assertEquals("Foo", dedent("    Foo", 4));
    }

    public function testDedentOddSpaces()
    {
        $this->assertEquals("Foo", dedent("   Foo", 3));
    }

    public function testDedentMultiple()
    {
        $this->assertEquals("Foo", dedent(dedent("   Foo", 1)));
    }

    public function testDedentMinimum()
    {
        $this->assertEquals("Foo", dedent("  Foo", 3));
    }

    public function testDedentMultiLine()
    {
        $this->assertEquals(<<<EOF
Foo
Bar
Baz
EOF
, dedent(<<<EOF
  Foo
  Bar
  Baz
EOF));
    }

}
