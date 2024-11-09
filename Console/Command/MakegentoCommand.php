<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Opengento\MakegentoCli\Generator\GeneratorFactory;
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
        private readonly State $appState,
        private readonly GeneratorFactory $generatorFactory,
        private readonly YesNo $yesNoQuestionPerformer
    ) {
        parent::__construct();
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('makegento:create-dbschemas')
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
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $this->appState->setAreaCode(Area::AREA_GLOBAL);

        $modulesPaths = $this->moduleService->getModulesPaths();

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Choose the module you want to work with',
            $modulesPaths
        );
        $question->setErrorMessage('%s is an invalid choice.');

        $selectedModule = $helper->ask($input, $output, $question);

        $generator = $this->generatorFactory->create(['moduleName' => $selectedModule, 'modulePath' => $modulesPaths[$selectedModule]]);

        $output->writeln("<info>You choose: $selectedModule</info>");
        if ($this->yesNoQuestionPerformer->execute(['Do you want to create or change the datatable schema for this module? [y/n]'], $input, $output)){

        }
        $output->writeln("<info>Vive Opengento</info>");

        return Command::SUCCESS;
    }
}
