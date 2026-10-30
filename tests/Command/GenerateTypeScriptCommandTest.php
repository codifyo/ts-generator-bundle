<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Command;

use Codifyo\TsGeneratorBundle\Command\GenerateTypeScriptCommand;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
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
        $this->assertSame('codifyo:ts-generator:generate', $command->getName());

        $commandTester = new CommandTester($command);
        $statusCode = $commandTester->execute([
            '--project' => 'projet1',
        ]);

        $this->assertEquals(Command::SUCCESS, $statusCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Codifyo TypeScript Generator', $output);
        $this->assertStringContainsString('Successfully generated 1 TypeScript definition file(s).', $output);
    }

    public function testErrorsAreReportedAsFailure(): void
    {
        $generatorMock = $this->createMock(TypeScriptGeneratorInterface::class);
        $generatorMock->method('generate')->willThrowException(new \TypeError('boom'));

        $commandTester = new CommandTester(new GenerateTypeScriptCommand($generatorMock));
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('boom', $commandTester->getDisplay());
    }

    public function testProjectOptionRequiresAValue(): void
    {
        $command = new GenerateTypeScriptCommand($this->createMock(TypeScriptGeneratorInterface::class));

        $this->assertTrue($command->getDefinition()->getOption('project')->isValueRequired());
    }
}
