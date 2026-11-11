<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Converter;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyIntStatus;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

class PhpToTypeScriptTypeConverterTest extends TestCase
{
    private PhpToTypeScriptTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PhpToTypeScriptTypeConverter();
    }

    public function testConvertStringTypes(): void
    {
        $this->assertEquals('number', $this->converter->convertType('int'));
        $this->assertEquals('number', $this->converter->convertType('float'));
        $this->assertEquals('string', $this->converter->convertType('string'));
        $this->assertEquals('boolean', $this->converter->convertType('bool'));
        $this->assertEquals('string', $this->converter->convertType('DateTimeImmutable'));
        $this->assertEquals('any[]', $this->converter->convertType('array'));
        $this->assertEquals('MyClass', $this->converter->convertType('App\Entity\MyClass'));
    }

    public function testConvertStringGenericsUnionsAndShapes(): void
    {
        $this->assertEquals('Array<Tag>', $this->converter->convertType('Collection<int, App\Tag>'));
        $this->assertEquals('Array<Record<string, Tag>>', $this->converter->convertType('array<int, array<string, Tag>>'));
        $this->assertEquals('Array<Tag>', $this->converter->convertType('ArrayCollection<int, App\Tag>'));
        $this->assertEquals('Array<Tag>', $this->converter->convertType('iterable<Tag>'));
        $this->assertEquals('Record<string, number>', $this->converter->convertType('array<string, int>'));
        $this->assertEquals('string', $this->converter->convertType('class-string<Tag>'));
        $this->assertEquals('Paginator', $this->converter->convertType('App\Paginator<Tag>'));
        $this->assertEquals('Array<{ id: number; name?: string }>', $this->converter->convertType('array<array{id: int, name?: string}>'));
        $this->assertEquals('string | number', $this->converter->convertType('string|int|null'));
        $this->assertEquals('Tag', $this->converter->convertType('?Tag'));
        $this->assertEquals('string', $this->converter->convertType(DummyStatus::class));
        $this->assertEquals('number', $this->converter->convertType(DummyIntStatus::class));
    }

    public function testTypeAliasesArePassedPerCallAndNotShared(): void
    {
        $aliases = ['ClassB' => 'array{id: int}'];

        $this->assertEquals('Array<{ id: number }>', $this->converter->convertType('array<ClassB>', $aliases));
        // Without aliases in scope, the same name is a plain class name again
        $this->assertEquals('Array<ClassB>', $this->converter->convertType('array<ClassB>'));
        // Self-referencing alias does not loop forever
        $this->assertEquals('Loop', $this->converter->convertType('Loop', ['Loop' => 'Loop']));
    }

    public function testConvertTypeInfoTypes(): void
    {
        $this->assertEquals('number', $this->converter->convertType(Type::int()));
        $this->assertEquals('string', $this->converter->convertType(Type::string()));
        $this->assertEquals('string', $this->converter->convertType(Type::nullable(Type::string())));
        $this->assertEquals('string', $this->converter->convertType(Type::object(\DateTimeImmutable::class)));
        $this->assertEquals('Role', $this->converter->convertType(Type::object('App\Entity\Role')));
        $this->assertEquals('Array<Role>', $this->converter->convertType(Type::list(Type::object('App\Entity\Role'))));
        $this->assertEquals('Record<string, number>', $this->converter->convertType(Type::dict(Type::int())));
        $this->assertEquals('number | string', $this->converter->convertType(Type::union(Type::string(), Type::int())));
        $this->assertEquals('string', $this->converter->convertType(Type::enum(DummyStatus::class)));
        $this->assertEquals('number', $this->converter->convertType(Type::enum(DummyIntStatus::class)));
        $this->assertEquals(
            '{ id: number; label?: string }',
            $this->converter->convertType(Type::arrayShape(['id' => Type::int(), 'label' => ['type' => Type::string(), 'optional' => true]]))
        );
        $this->assertEquals(
            'Array<Role>',
            $this->converter->convertType(Type::collection(Type::object(\ArrayIterator::class), Type::object('App\Entity\Role'), Type::int()))
        );
    }

    public function testConvertTypeInfoEdgeTypes(): void
    {
        $this->assertSame('Countable & Stringable', $this->converter->convertType(Type::intersection(Type::object(\Countable::class), Type::object(\Stringable::class))));
        $this->assertSame('Record<string, any>', $this->converter->convertType(Type::object()));
        $this->assertSame('any[]', $this->converter->convertType(Type::object(\ArrayIterator::class)));
        $this->assertSame('any[]', $this->converter->convertType(Type::list()));
        $this->assertSame('Record<string, any>', $this->converter->convertType(Type::arrayShape([])));
        $this->assertSame('number', $this->converter->convertType(Type::template('T', Type::int())));
        $this->assertSame('boolean', $this->converter->convertType(Type::true()));
        $this->assertSame('any', $this->converter->convertType(Type::mixed()));
        $this->assertSame('void', $this->converter->convertType(Type::void()));
        $this->assertSame('null', $this->converter->convertType(Type::null()));
        $this->assertSame('any', $this->converter->convertType(null));
    }

    public function testConvertStringEdgeTypes(): void
    {
        $this->assertSame('Record<string, any>', $this->converter->convertType('object'));
        $this->assertSame('any', $this->converter->convertType('mixed'));
        $this->assertSame('never', $this->converter->convertType('never'));
        $this->assertSame('null', $this->converter->convertType('null'));
        $this->assertSame('Array<Tag>', $this->converter->convertType('App\Tag[]'));
        $this->assertSame('Array<Tag>', $this->converter->convertType('Collection<App\Tag>'));
        $this->assertSame('Record<string, any>', $this->converter->convertType('array{}'));
        $this->assertSame('{ id: number }', $this->converter->convertType('array{id: int, ...}'));
        $this->assertSame('{ id: number }', $this->converter->convertType('\array{id: int}'));
    }
}
