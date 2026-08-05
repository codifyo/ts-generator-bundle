<?php

namespace Codifyo\TsGeneratorBundle\Command;

use Codifyo\TsGeneratorBundle\Generator\TypeScriptGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'codifyo:ts-generator:generate',
    description: 'Generates TypeScript definition files from Symfony Serializer metadata.'
)]
class GenerateTypeScriptCommand extends Command
{
    public function __construct(
        private TypeScriptGeneratorInterface $generator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('project', 'p', InputOption::VALUE_OPTIONAL, 'Target project name to generate (if omitted, all projects are generated)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectName = $input->getOption('project');

        $io->title('Codifyo TypeScript Generator');

        try {
            $results = $this->generator->generate($projectName);

            $totalFiles = 0;
            foreach ($results as $projName => $files) {
                $io->section(sprintf('Project: %s', $projName));
                foreach ($files as $file) {
                    $io->writeln(sprintf('  <info>[Generated]</info> %s', $file));
                    $totalFiles++;
                }
            }

            $io->newLine();
            $io->success(sprintf('Successfully generated %d TypeScript definition file(s).', $totalFiles));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error generating TypeScript definitions: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
