<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use DirectoryIterator;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Service\DbSchemaCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Question\Question;

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
        private readonly DbSchemaCreator $dbSchemaCreator,
        private readonly YesNo $yesNoQuestionPerformer
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
            'Choose the module you want to work with',
            $modulesList
        );
        $question->setErrorMessage('%s is an invalid choice.');

        $selectedModule = $helper->ask($input, $output, $question);

        $output->writeln("<info>You choose: $selectedModule</info>");
        if ($this->yesNoQuestionPerformer->execute(['Do you want to create or change the database schema for this module?'], $input, $output)){
            $output->writeln("<info>Database schema creation</info>");
            $this->dbSchemaQuestionner($input, $output);
        }
        $output->writeln("<info>Vive Opengento</info>");

        return Command::SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     * @return void
     */
    private function dbSchemaQuestionner(InputInterface $input, OutputInterface $output)
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $addNewTable = true;
        $dataTables = [];
        while ($addNewTable) {
            $tableNameQuestion = new Question('Enter the database name: ');
            $tablename = $helper->ask($input, $output, $tableNameQuestion);
            $tableFields = [];
            $addNewField = true;
            while ($addNewField) {
                $fieldNameQuestion = new Question('Enter the field name: ');
                $fieldName = $helper->ask($input, $output, $fieldNameQuestion);
                // Let's ask things to create database
                $fieldTypeQuestion = new ChoiceQuestion(
                    'Choose the field type',
                    $this->dbSchemaCreator->getFieldTypes()
                );
                $fieldType = $helper->ask($input, $output, $fieldTypeQuestion);
                $tableFields[$fieldName]['type'] = $fieldType;
                if ($fieldType === 'varchar') {
                    $fieldLengthQuestion = new Question('Enter the field length: ');
                    $fieldLength = $helper->ask($input, $output, $fieldLengthQuestion);
                    $tableFields[$fieldName]['length'] = $fieldLength;
                }
                if ($fieldType === 'int') {
                    $fieldLengthQuestion = new Question('Enter the field padding (length): ');
                    $fieldLength = $helper->ask($input, $output, $fieldLengthQuestion);
                    $tableFields[$fieldName]['padding'] = $fieldLength;
                }
                $tableFields[$fieldName]['primary'] = $this->yesNoQuestionPerformer->execute(
                    ['Is this field a primary key?'],
                    $input,
                    $output)
                ;
                $tableFields[$fieldName]['nullable'] = $this->yesNoQuestionPerformer->execute(
                    ['Is this field nullable?'],
                    $input,
                    $output
                );
                $addNewField = $this->yesNoQuestionPerformer->execute(
                    ['Do you want to add a new field?'],
                    $input,
                    $output
                );
            }
            $dataTables[$tablename] = $tableFields;
            $addNewTable = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add a new table?'],
                $input,
                $output
            );
        }

    }
}
