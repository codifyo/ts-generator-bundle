<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Sample;

use Codifyo\TsGeneratorBundle\Sample\DoctrineSampleObjectProvider;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

class DoctrineSampleObjectProviderTest extends TestCase
{
    public function testFindsTheSampleWithTheManagerOfTheClass(): void
    {
        $tag = new DummyTag();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method('find')->with(DummyTag::class, ['tenant' => 1, 'code' => 'A'])->willReturn($tag);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DummyTag::class)->willReturn($manager);

        $this->assertSame($tag, (new DoctrineSampleObjectProvider($registry))->find(DummyTag::class, ['tenant' => 1, 'code' => 'A']));
    }

    public function testMissingRowReturnsNull(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('find')->willReturn(null);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $this->assertNull((new DoctrineSampleObjectProvider($registry))->find(DummyTag::class, 42));
    }

    public function testUnmanagedClassIsAConfigurationError(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not managed by Doctrine');

        (new DoctrineSampleObjectProvider($registry))->find(DummyTag::class, 1);
    }

    public function testWithoutDoctrineIsAConfigurationError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires DoctrineBundle');

        (new DoctrineSampleObjectProvider(null))->find(DummyTag::class, 1);
    }
}
