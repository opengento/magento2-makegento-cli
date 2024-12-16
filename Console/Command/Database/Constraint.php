<?php

namespace Opengento\MakegentoCli\Console\Command\Database;

use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Maker\MakeConstraint;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Constraint extends Command
{

    public function __construct(
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly MakeConstraint $makeConstraint,
        private readonly CommandIoProvider $commandIoProvider,
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
        $this->commandIoProvider->init($input, $output, $questionHelper);

        try {
            $this->moduleSelector->execute(true);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $this->makeConstraint->generate();
        return Command::SUCCESS;
    }
}
