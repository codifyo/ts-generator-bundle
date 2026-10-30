<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Helper;

use Codifyo\TsGeneratorBundle\Helper\ValueTypeInferrer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValueTypeInferrerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideValues(): iterable
    {
        yield 'null tells nothing' => [null, 'any'];
        yield 'scalars' => [[1, 1.5, 'a', true], 'Array<number | string | boolean>'];
        yield 'empty array' => [[], 'any[]'];
        yield 'list of strings' => [['a', 'b'], 'Array<string>'];
        yield 'list with nulls' => [['a', null], 'Array<string | null>'];
        yield 'list of nulls' => [[null, null], 'any[]'];
        yield 'object' => [['id' => 1, 'label' => 'x'], '{ id: number; label: string }'];
        yield 'nested object' => [['author' => ['id' => 1, 'tags' => ['a']]], '{ author: { id: number; tags: Array<string> } }'];
        yield 'objects merged, missing keys optional' => [
            [['id' => 1, 'name' => 'a'], ['id' => 2, 'extra' => true]],
            'Array<{ id: number; name?: string; extra?: boolean }>',
        ];
        yield 'nullable field in merged objects' => [[['note' => 'x'], ['note' => null]], 'Array<{ note: string | null }>'];
        yield 'integer keyed map' => [[3 => 'a', 7 => 'b'], 'Record<number, string>'];
        yield 'non identifier keys are quoted' => [['first-name' => 'Ada', "it's" => 1], "{ 'first-name': string; 'it\\'s': number }"];
        yield 'depth is bounded' => [['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]], '{ a: { b: { c: { d: { e: Record<string, any> } } } } }'];
        yield 'unknown object' => [new \stdClass(), 'any'];
    }

    #[DataProvider('provideValues')]
    public function testInfer(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ValueTypeInferrer::infer($value));
    }
}
