<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Opengento\MakegentoCli\Maker\MakeCrud;
use Opengento\MakegentoCli\Utils\ConsoleCrudEntitySelector;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class MakegentoCrudCommand extends Command
{
    public function __construct(
        private readonly ConsoleModuleSelector $consoleModuleSelector,
        private readonly ConsoleCrudEntitySelector $consoleCrudEntitySelector,
        private readonly State $appState,
        private readonly MakeCrud $makeCrud,
    ) {
        parent::__construct();
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('makegento:crud')
            ->setDescription('makegento let you do everything you want');
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
        try {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        } catch (LocalizedException $e) {
            // Do nothing area code is already set
        }

        $commandHelper = $this->getHelper('question');
        $selectedModule = $this->consoleModuleSelector->execute($input, $output, $commandHelper, true);
        $entityName = $this->consoleCrudEntitySelector->execute($input, $output, $commandHelper, $selectedModule);

        try {
            $this->makeCrud->generate($input, $output, $selectedModule, $entityName);
        } catch (LocalizedException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
