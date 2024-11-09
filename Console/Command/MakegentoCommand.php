<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use DirectoryIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class MakegentoCommand extends Command
{
    /**
     * Construct
     *
     * @param State $appState
     */
    public function __construct(
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('makegento:create-db')
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
        $this->appState->setAreaCode(Area::AREA_GLOBAL);
        $path = BP . '/app/code';

        $modulesList = [];
        try {
            foreach (new DirectoryIterator($path) as $vendorDir) {
                if ($vendorDir->isDir() && !$vendorDir->isDot()) {
                    foreach (new DirectoryIterator($vendorDir->getPathname()) as $key => $moduleDir) {
                        if ($moduleDir->isDir() && !$moduleDir->isDot()) {
                            $modulesList[] = $vendorDir->getFilename() . "_" . $moduleDir->getFilename();
                        }
                    }
                }
            }
            sort($modulesList);
        } catch (\Exception $e) {
            $output->writeln("<error>Erreur lors de l'accès au dossier : {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if (!is_dir($path)) {
            $output->writeln("<error>app/code directory doesn't exist.</error>");
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Chose the module you want to work with',
            $modulesList
        );
        $question->setErrorMessage('%s is an invalid choice.');

        $selectedModule = $helper->ask($input, $output, $question);

        $output->writeln("<info>You choose: $selectedModule</info>");
        $output->writeln("<info>Vive Opengento</info>");

        return Command::SUCCESS;
    }
}
