<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\JmsSerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyRole;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUser;
use PHPUnit\Framework\TestCase;

class JmsSerializerMetadataExtractorTest extends TestCase
{
    /**
     * Minimal stand-in for JMS metadata (jms/serializer is an optional dependency).
     */
    private function createFactory(): object
    {
        $property = static function (array $values): \stdClass {
            return (object) array_merge(['groups' => null, 'serializedName' => null, 'type' => null, 'excluded' => false], $values);
        };

        $classMetadata = new \stdClass();
        $classMetadata->propertyMetadata = [
            'id' => $property(['type' => ['name' => 'int', 'params' => []], 'groups' => ['user:read']]),
            'username' => $property(['type' => ['name' => 'string', 'params' => []], 'serializedName' => 'user_name', 'groups' => ['user:read']]),
            'role' => $property(['type' => ['name' => DummyRole::class, 'params' => []], 'groups' => ['user:read']]),
            'tags' => $property(['type' => ['name' => 'ArrayCollection', 'params' => [['name' => DummyTag::class, 'params' => []]]], 'groups' => ['user:read']]),
            'password' => $property(['type' => ['name' => 'string', 'params' => []], 'groups' => ['admin']]),
            'excludedField' => $property(['type' => ['name' => 'string', 'params' => []], 'excluded' => true]),
        ];

        return new class($classMetadata) {
            public function __construct(private object $metadata)
            {
            }

            public function getMetadataForClass(string $className): object
            {
                return $this->metadata;
            }
        };
    }

    public function testExtractsJmsMetadataWithGroupsTypesAndReferences(): void
    {
        $extractor = new JmsSerializerMetadataExtractor($this->createFactory(), new PhpToTypeScriptTypeConverter());
        $definition = $extractor->extract(new TypeConfig(DummyUser::class, ['user:read']));

        $types = [];
        foreach ($definition->getProperties() as $property) {
            $types[$property->getName()] = $property->getTsType();
        }

        $this->assertSame([
            'id' => 'number',
            'user_name' => 'string',
            'role' => 'DummyRole',
            'tags' => 'Array<DummyTag>',
        ], $types);
        $this->assertSame(['DummyRole' => DummyRole::class, 'DummyTag' => DummyTag::class], $definition->getImports());
    }

    public function testWithoutJmsTheDefinitionIsEmpty(): void
    {
        $extractor = new JmsSerializerMetadataExtractor(null, new PhpToTypeScriptTypeConverter());

        $this->assertSame([], $extractor->extract(new TypeConfig(DummyUser::class))->getProperties());
    }
}
