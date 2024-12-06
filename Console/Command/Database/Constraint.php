<?php

namespace Opengento\MakegentoCli\Console\Command\Database;

use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Maker\MakeConstraint;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Constraint extends Command
{

    public function __construct(
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly MakeConstraint $makeConstraint
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('makegento:db-schema:constraint')
            ->setDescription('Create a new entity')
            ->setHelp('This command allows you to create a new foreign key.');
        parent::configure();
    }

    /**
     * @throws TableDefinitionException
     * @throws ConstraintDefinitionException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $questionHelper = $this->getHelper('question');

        try {
            $selectedModule = $this->moduleSelector->execute($input, $output, $questionHelper, true);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $this->makeConstraint->generate($input, $output, $selectedModule);
        return Command::SUCCESS;
    }
}
