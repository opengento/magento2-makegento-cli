<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use DirectoryIterator;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Service\DbSchemaCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
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
        $path = BP . '/app/code';

        if (!is_dir($path)) {
            $output->writeln("<error>app/code directory doesn't exist.</error>");
            return Command::FAILURE;
        }
        $this->appState->setAreaCode(Area::AREA_GLOBAL);

        $modulesPaths = [];
        try {
            foreach (new DirectoryIterator($path) as $vendorDir) {
                if ($vendorDir->isDir() && !$vendorDir->isDot()) {
                    foreach (new DirectoryIterator($vendorDir->getPathname()) as $moduleDir) {
                        if ($moduleDir->isDir() && !$moduleDir->isDot()) {
                            $modulesPaths[] = $vendorDir->getFilename() . "_" . $moduleDir->getFilename();
                        }
                    }
                }
            }
            sort($modulesPaths);
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
            $modulesPaths
        );
        $question->setErrorMessage('%s is an invalid choice.');

        $selectedModule = $helper->ask($input, $output, $question);

        $output->writeln("<info>You choose: $selectedModule</info>");
        if ($this->yesNoQuestionPerformer->execute(['Do you want to create or change the datatable schema for this module?'], $input, $output)){
            $this->dbSchemaQuestionner($modulesPaths[$selectedModule], $input, $output);
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
    private function dbSchemaQuestionner(string $modulePath, InputInterface $input, OutputInterface $output)
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $dbSchemaExists = $this->dbSchemaCreator->moduleHasDbSchema($modulePath);
        if ($dbSchemaExists) {
            $output->writeln("<info>Database schema already exists</info>");
            $output->writeln("<info>Database schema modification</info>");
            $dataTables = $this->dbSchemaCreator->parseDbSchema($modulePath);
        } else {
            $output->writeln("<info>Database schema creation</info>");
            $dataTables = [];
        }
        $addNewTable = true;
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
                $defaultValue = $this->yesNoQuestionPerformer->execute(
                    ['Do you want to set a default value for this field?'],
                    $input,
                    $output
                );
                if ($defaultValue) {
                    if ($fieldType === 'datetime' && $this->yesNoQuestionPerformer->execute(
                            ['Do you want to set the default value to the current time?'],
                            $input,
                            $output
                        )) {
                        $tableFields[$fieldName]['default'] = 'CURRENT_TIMESTAMP';
                    } else {
                        $defaultValueQuestion = new Question('Enter the default value: ');
                        $defaultValue = $helper->ask($input, $output, $defaultValueQuestion);
                        $tableFields[$fieldName]['default'] = $defaultValue;
                    }
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
        $this->dbSchemaCreator->createDbSchema($modulePath, $dataTables);
    }

    /**
     * Write Success Message
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function writeSuccessMessage(OutputInterface $output)
    {
        $output->writeln(PHP_EOL);
        $output->writeln(' <bg=green;fg=white>          </>');
        $output->writeln(' <bg=green;fg=white> Success! </>');
        $output->writeln(' <bg=green;fg=white>          </>');
        $output->writeln(PHP_EOL);
    }

    /**
     * Write Error Message
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function writeErrorMessage(OutputInterface $output)
    {
        $output->writeln(PHP_EOL);
        $output->writeln("<error>          </error>");
        $output->writeln("<error>  Error!  </error>");
        $output->writeln("<error>          </error>");
        $output->writeln(PHP_EOL);
    }
}
