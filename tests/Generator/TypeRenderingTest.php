<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Generator;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGenerator;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyArticle;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyNode;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace\DummyTag as OtherDummyTag;
use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

class TypeRenderingTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_rendering_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    /**
     * @param list<array<string, mixed>> $types
     *
     * @return array<string, string[]>
     */
    private function generate(array $types): array
    {
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor]);
        $extractor = new SymfonySerializerMetadataExtractor(new ClassMetadataFactory(new AttributeLoader()), $propertyInfo, new PhpToTypeScriptTypeConverter());

        $generator = new TypeScriptGenerator($extractor, new FileWriter(new Filesystem(), $this->tempDir), [
            'projet1' => ['dir' => $this->tempDir, 'types' => $types],
        ]);

        return $generator->generate('projet1');
    }

    public function testReferencesAreReplacedAsWholeIdentifiersAndNestedClassesAreInlined(): void
    {
        $this->generate([
            ['class' => DummyArticle::class, 'groups' => ['serializer_group']],
            ['class' => DummyTag::class, 'groups' => ['serializer_group'], 'name' => 'TagDTO'],
        ]);

        $content = (string) file_get_contents($this->tempDir . '/DummyArticle.ts');

        $this->assertStringContainsString("import { TagDTO } from './TagDTO';", $content);
        $this->assertStringContainsString('mainTag?: TagDTO | null;', $content);
        // "DummyTag" must not be replaced inside "DummyTagGroup"
        $this->assertStringContainsString('tagGroups: Array<{ id: number; title: string }>;', $content);
        $this->assertStringContainsString('featured?: { id: number; title: string } | TagDTO | null;', $content);
        // Nested inline: DummyUser -> DummyRole, both described since neither is generated
        $this->assertStringContainsString('author?: { id: number; username: string; role?: { id: number; name: string } | null } | null;', $content);
        // Non identifier property names are quoted
        $this->assertStringContainsString("'published-at'?: string | null;", $content);
        // Strict groups: properties of other groups never leak
        $this->assertStringNotContainsString('secret', $content);
    }

    public function testSelfReferenceUsesOwnNameWithoutImport(): void
    {
        $this->generate([['class' => DummyNode::class, 'groups' => ['serializer_group']]]);

        $content = (string) file_get_contents($this->tempDir . '/DummyNode.ts');

        $this->assertStringContainsString('parent?: DummyNode | null;', $content);
        $this->assertStringNotContainsString('import', $content);
    }

    public function testTwoClassesWithTheSameTypeNameAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('both generate the TypeScript type "DummyTag"');

        $this->generate([
            ['class' => DummyTag::class, 'groups' => ['serializer_group']],
            ['class' => OtherDummyTag::class, 'groups' => ['serializer_group']],
        ]);
    }

    public function testCollisionCanBeSolvedWithACustomName(): void
    {
        $results = $this->generate([
            ['class' => DummyTag::class, 'groups' => ['serializer_group']],
            ['class' => OtherDummyTag::class, 'groups' => ['serializer_group'], 'name' => 'OtherTag'],
        ]);

        $this->assertCount(2, $results['projet1']);
        $this->assertFileExists($this->tempDir . '/OtherTag.ts');
    }

    public function testInvalidTypeNameIsRejectedBeforeWriting(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid TypeScript identifier');

        $this->generate([['class' => DummyTag::class, 'name' => '../../evil']]);
    }
}
