<?php

namespace Opengento\MakegentoCli\Console\Command\Database;

use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Maker\MakeField;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Field extends Command
{

    public function __construct(
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly MakeField $makeField,
        private readonly CommandIoProvider $commandIoProvider,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('makegento:db-schema:field')
            ->setDescription('Add a new field')
            ->setHelp('This command allows you to add a new field to db_schema.xml. It will propose then to add a new constraint.');
        parent::configure();
    }

    /**
     * @throws TableDefinitionException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $questionHelper = $this->getHelper('question');
        $this->commandIoProvider->init($input, $output, $questionHelper);

        try {
            $selectedModule = $this->moduleSelector->execute(true);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $this->makeField->generate($selectedModule);
        return Command::SUCCESS;
    }
}
