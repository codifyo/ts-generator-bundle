<?php

namespace Codifyo\TsGeneratorBundle\Tests\Command;

use Codifyo\TsGeneratorBundle\Command\GenerateTypeScriptCommand;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateTypeScriptCommandTest extends TestCase
{
    public function testExecuteCommandSuccess(): void
    {
        $generatorMock = $this->createMock(TypeScriptGeneratorInterface::class);
        $generatorMock->expects($this->once())
            ->method('generate')
            ->with('projet1')
            ->willReturn([
                'projet1' => [
                    'public/ts/types/DummyUser.ts',
                ],
            ]);

        $command = new GenerateTypeScriptCommand($generatorMock);
        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($application->find('codifyo:ts-generator:generate'));
        $statusCode = $commandTester->execute([
            '--project' => 'projet1',
        ]);

        $this->assertEquals(0, $statusCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Codifyo TypeScript Generator', $output);
        $this->assertStringContainsString('Successfully generated 1 TypeScript definition file(s).', $output);
    }
}
