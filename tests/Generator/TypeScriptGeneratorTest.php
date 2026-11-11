<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Generator;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\MetadataExtractorInterface;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGenerator;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyRole;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUser;
use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Rendering and orchestration rules of the generator. Most tests use a fake extractor so that
 * each rule is checked in isolation from the metadata extraction.
 */
class TypeScriptGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_generator_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    /**
     * @param list<array<string, mixed>> $types
     *
     * @return list<string>
     */
    private function generate(MetadataExtractorInterface $extractor, array $types): array
    {
        $generator = new TypeScriptGenerator($extractor, new FileWriter(new Filesystem(), $this->tempDir), [
            'front' => ['dir' => $this->tempDir, 'types' => $types],
        ]);

        return $generator->generate('front')['front'];
    }

    private function read(string $typeName): string
    {
        return (string) file_get_contents($this->tempDir . \DIRECTORY_SEPARATOR . $typeName . '.ts');
    }

    /**
     * @param array<class-string, callable(TypeConfig): list<PropertyDefinition>> $propertiesByClass
     */
    private function fakeExtractor(array $propertiesByClass = []): MetadataExtractorInterface
    {
        return new class($propertiesByClass) implements MetadataExtractorInterface {
            /**
             * @param array<class-string, callable(TypeConfig): list<PropertyDefinition>> $propertiesByClass
             */
            public function __construct(private array $propertiesByClass)
            {
            }

            public function extract(TypeConfig $typeConfig): TypeDefinition
            {
                $definition = new TypeDefinition($typeConfig->getName(), $typeConfig->getClass(), [], [], $typeConfig->getKind());
                $factory = $this->propertiesByClass[$typeConfig->getClass()] ?? static fn (): array => [new PropertyDefinition('id', 'number')];
                foreach ($factory($typeConfig) as $property) {
                    $definition->addProperty($property);
                }

                return $definition;
            }
        };
    }

    public function testGeneratesInterfacesWithImportsFromTheStaticExtractor(): void
    {
        $extractor = new SymfonySerializerMetadataExtractor(null, null, new PhpToTypeScriptTypeConverter());

        $files = $this->generate($extractor, [
            ['class' => DummyUser::class, 'groups' => ['serializer_group']],
            ['class' => DummyRole::class, 'groups' => ['serializer_group']],
        ]);

        $this->assertCount(2, $files);
        $content = $this->read('DummyUser');
        $this->assertStringContainsString("import { DummyRole } from './DummyRole';", $content);
        $this->assertStringContainsString('export interface DummyUser {', $content);
        $this->assertStringContainsString('  id: number;', $content);
        $this->assertStringContainsString('  username: string;', $content);
        $this->assertStringContainsString('  role?: DummyRole | null;', $content);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideKinds(): iterable
    {
        yield 'interface' => ['interface', 'export interface DummyTag {', "}\n"];
        yield 'class' => ['class', 'export class DummyTag {', "}\n"];
        yield 'type' => ['type', 'export type DummyTag = {', "};\n"];
    }

    #[DataProvider('provideKinds')]
    public function testKindDrivesTheDeclaration(string $kind, string $opening, string $closing): void
    {
        $this->generate($this->fakeExtractor(), [['class' => DummyTag::class, 'kind' => $kind]]);

        $this->assertStringEndsWith($opening . "\n  id: number;\n" . $closing, $this->read('DummyTag'));
    }

    public function testImportsAreSortedForStableDiffs(): void
    {
        $extractor = $this->fakeExtractor([
            DummyTag::class => static fn (): array => [
                new PropertyDefinition('user', 'DummyUser', false, DummyUser::class),
                new PropertyDefinition('role', 'DummyRole', false, DummyRole::class),
            ],
        ]);

        $this->generate($extractor, [
            ['class' => DummyTag::class],
            ['class' => DummyUser::class],
            ['class' => DummyRole::class],
        ]);

        $this->assertStringContainsString(
            "import { DummyRole } from './DummyRole';\nimport { DummyUser } from './DummyUser';\n",
            $this->read('DummyTag')
        );
    }

    public function testNamesDifferingOnlyByCaseAreRejected(): void
    {
        // Tag.ts and TAG.ts are the same file on Windows and macOS
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('both generate the TypeScript type "TAG"');

        $this->generate($this->fakeExtractor(), [
            ['class' => DummyTag::class, 'name' => 'Tag'],
            ['class' => DummyRole::class, 'name' => 'TAG'],
        ]);
    }

    public function testFirstDeclarationOfAClassWins(): void
    {
        $files = $this->generate($this->fakeExtractor(), [
            ['class' => DummyTag::class, 'name' => 'First'],
            ['class' => DummyTag::class, 'name' => 'Second'],
        ]);

        $this->assertCount(1, $files);
        $this->assertFileExists($this->tempDir . '/First.ts');
        $this->assertFileDoesNotExist($this->tempDir . '/Second.ts');
    }

    public function testInlinedClassesAreDescribedWithTheGroupsOfEachParent(): void
    {
        $extractor = $this->fakeExtractor([
            DummyTag::class => static fn (): array => [new PropertyDefinition('role', 'DummyRole', false, DummyRole::class)],
            DummyUser::class => static fn (): array => [new PropertyDefinition('role', 'DummyRole', false, DummyRole::class)],
            // DummyRole is not generated: it is inlined, with the groups of the type referencing it
            DummyRole::class => static fn (TypeConfig $config): array => [new PropertyDefinition(implode('_', $config->getGroups()), 'number')],
        ]);

        $this->generate($extractor, [
            ['class' => DummyTag::class, 'groups' => ['public']],
            ['class' => DummyUser::class, 'groups' => ['admin']],
        ]);

        $this->assertStringContainsString('role: { public: number };', $this->read('DummyTag'));
        $this->assertStringContainsString('role: { admin: number };', $this->read('DummyUser'));
    }

    public function testDiscoveredDependenciesInheritTheSettingsOfTheTypeReferencingThem(): void
    {
        /** @var list<TypeConfig> $received */
        $received = [];
        $recordRole = static function (TypeConfig $config) use (&$received): array {
            $received[] = $config;

            return [new PropertyDefinition('id', 'number')];
        };
        $extractor = $this->fakeExtractor([
            DummyTag::class => static fn (): array => [new PropertyDefinition('role', 'DummyRole', false, DummyRole::class)],
            DummyRole::class => $recordRole,
        ]);

        $this->generate($extractor, [
            ['class' => DummyTag::class, 'groups' => ['public'], 'kind' => 'type', 'mode' => 'static', 'auto_generate_deps' => true],
        ]);

        $this->assertCount(1, $received);
        $this->assertSame(['public'], $received[0]->getGroups());
        $this->assertSame('type', $received[0]->getKind());
        $this->assertSame('static', $received[0]->getMode());
        $this->assertTrue($received[0]->isAutoGenerateDeps());
        $this->assertStringContainsString("import { DummyRole } from './DummyRole';", $this->read('DummyTag'));
    }

    public function testPropertyNamesAreQuotedAndEscapedWhenNeeded(): void
    {
        $extractor = $this->fakeExtractor([
            DummyTag::class => static fn (): array => [
                new PropertyDefinition('it\'s', 'string'),
                new PropertyDefinition('back\\slash', 'string'),
                new PropertyDefinition('$valid_id', 'string'),
            ],
        ]);

        $this->generate($extractor, [['class' => DummyTag::class]]);

        $content = $this->read('DummyTag');
        $this->assertStringContainsString("  'it\\'s': string;", $content);
        $this->assertStringContainsString("  'back\\\\slash': string;", $content);
        $this->assertStringContainsString('  $valid_id: string;', $content);
    }

    public function testUnknownProjectIsRejected(): void
    {
        $generator = new TypeScriptGenerator($this->fakeExtractor(), new FileWriter(null, $this->tempDir), ['front' => ['types' => []]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Project "back" is not configured.');

        $generator->generate('back');
    }
}
