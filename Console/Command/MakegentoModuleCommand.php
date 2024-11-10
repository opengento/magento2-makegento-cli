<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Opengento\MakegentoCli\Generator\GeneratorModule;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\QuestionFactory;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class MakegentoModuleCommand extends Command
{
    private $rootDir;

    public function __construct(
        private readonly QuestionFactory $questionFactory,
        private readonly State $appState,
        private readonly GeneratorModule $generatorModule,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
        $this->rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('makegento:module')
            ->setDescription('Create a new module');
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

        $commandHelper = $this->getHelper('question');

        $question = $this->questionFactory->create([
            'question' => '<info>Enter Vendor Name</info>' . PHP_EOL,
        ]);

        $vendorName = $commandHelper->ask($input, $output, $question);

        $question = $this->questionFactory->create([
            'question' => '<info>Enter Module Name</info>' . PHP_EOL,
        ]);

        $moduleName = $commandHelper->ask($input, $output, $question);

        $newModuleName = $this->generatorModule->generateModule($vendorName, $moduleName);

        $output->writeln(PHP_EOL);
        $output->writeln("\t" . '<bg=green;fg=white>                                                </>');
        $output->writeln("\t" . '<bg=green;fg=white>                                                </>');
        $output->writeln("\t" . '<bg=green;fg=white>     Module and files created with Success!     </>');
        $output->writeln("\t" . '<bg=green;fg=white>                                                </>');
        $output->writeln("\t" . '<bg=green;fg=white>                                                </>');
        $output->writeln(PHP_EOL);
        $output->writeln("\t" . '<fg=default;bg=default>Your new module\'s name is <fg=green>$newModuleName</><fg=default;bg=default></>');
        $output->writeln(PHP_EOL);
        $output->writeln("\t" . '<fg=default;bg=default>Do not forget to run <fg=green>bin/magento setup:upgrade</><fg=default;bg=default> to activate your new module!</>');
        $output->writeln(PHP_EOL);

        $output->writeln("<info>Long live to Opengento!</info>");

        return Command::SUCCESS;
    }
}
