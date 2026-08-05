<?php

namespace Codifyo\TsGeneratorBundle\Tests\Converter;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type;

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

    public function testConvertPropertyInfoTypes(): void
    {
        $intType = new Type(Type::BUILTIN_TYPE_INT);
        $this->assertEquals('number', $this->converter->convertType($intType));

        $stringType = new Type(Type::BUILTIN_TYPE_STRING);
        $this->assertEquals('string', $this->converter->convertType($stringType));

        $collectionType = new Type(
            Type::BUILTIN_TYPE_OBJECT,
            false,
            'Doctrine\Common\Collections\Collection',
            true,
            null,
            new Type(Type::BUILTIN_TYPE_OBJECT, false, 'App\Entity\Role')
        );
        $this->assertEquals('Array<Role>', $this->converter->convertType($collectionType));
    }
}
