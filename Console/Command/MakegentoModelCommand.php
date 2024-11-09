<?php

namespace Opengento\MakegentoCli\Console\Command;

use Opengento\MakegentoCli\Maker\MakeModel;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakegentoModelCommand extends Command
{
    protected $selectedModule;

    public function __construct(
        private readonly ConsoleModuleSelector $consoleModuleSelector,
        private readonly MakeModel $makeModel,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('makegento:model')
            ->setDescription('Makegento create a model from a db_schema.xml file');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandHelper = $this->getHelper('question');

        $this->selectedModule = $this->consoleModuleSelector->execute($input, $output, $commandHelper, true);

        $this->makeModel->generate();

        return Command::SUCCESS;
    }
}