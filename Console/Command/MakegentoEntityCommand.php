<?php

namespace Opengento\MakegentoCli\Console\Command;

use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Maker\MakeEntity;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class MakegentoEntityCommand extends Command
{

    public function __construct(
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly MakeEntity $makeEntity
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('makegento:entity')
            ->setDescription('Create a new entity')
            ->setHelp('This command allows you to create a new entity.');
        parent::configure();
    }

    /**
     * @throws TableDefinitionException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getHelper('question');

        try {
            $selectedModule = $this->moduleSelector->execute($input, $output, $questionHelper, true);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $modulePath = $this->moduleSelector->getModulePath($selectedModule);

        $this->makeEntity->generate($input, $output, $selectedModule, $modulePath);
        return Command::SUCCESS;
    }


}
