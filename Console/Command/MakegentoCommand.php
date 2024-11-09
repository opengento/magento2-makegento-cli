<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Console\Command;

use DirectoryIterator;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Service\DbSchemaCreator;
use Symfony\Component\Console\Command\Command;
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

        $modulesList = [];
        try {
            foreach (new DirectoryIterator($path) as $vendorDir) {
                if ($vendorDir->isDir() && !$vendorDir->isDot()) {
                    foreach (new DirectoryIterator($vendorDir->getPathname()) as $moduleDir) {
                        if ($moduleDir->isDir() && !$moduleDir->isDot()) {
                            $modulesList[] = $vendorDir->getFilename() . "_" . $moduleDir->getFilename();
                            $modulesPaths[$vendorDir->getFilename() . "_" . $moduleDir->getFilename()] = $moduleDir->getPathname();
                        }
                    }
                }
            }
            sort($modulesList);
        } catch (\Exception $e) {
            $output->writeln("<error>Erreur lors de l'accès au dossier : {$e->getMessage()}</error>");
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
        if ($this->yesNoQuestionPerformer->execute(['Do you want to create or change the datatable schema for this module? [y/n]'], $input, $output)){
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
            $dataTables[] = $this->tableCreation($input, $output, $helper);
            $addNewTable = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add a new table?'],
                $input,
                $output
            );
        }
        $this->dbSchemaCreator->createDbSchema($modulePath, $dataTables, $helper);
    }

    private function tableCreation(InputInterface $input, OutputInterface $output, QuestionHelper $helper): array
    {
        $tableName = $helper->ask($input, $output, new Question('Enter the datatable name: '));
        $tableFields = [];
        $addNewField = true;
        while ($addNewField) {
            $tableFields[] = $this->fieldCreation($input, $output, $helper);
            $addNewField = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add a new field? [y/n]'],
                $input,
                $output
            );
        }
        $addConstraint = $this->yesNoQuestionPerformer->execute(
            ['Do you want to add a constraint to this table? [y/n]'],
            $input,
            $output
        );
        return [$tableName => ['fields' => $tableFields, 'constraint' => $addConstraint]];
    }

    private function fieldCreation(InputInterface $input, OutputInterface $output, QuestionHelper $helper): array
    {
        $fieldName = $helper->ask($input, $output, new Question('Enter the field name: '));
        $primary = null;
        $indexes = [];
        // Let's ask things to create database
        $fieldTypeQuestion = new ChoiceQuestion(
            'Choose the field type',
            $this->dbSchemaCreator->getFieldTypes()
        );
        $fieldType = $helper->ask($input, $output, $fieldTypeQuestion);
        $fieldDefinition['type'] = $fieldType;
        if ($fieldType === 'varchar') {
            $fieldLength = $helper->ask($input, $output, new Question('Enter the field length: '));
            $fieldDefinition['length'] = $fieldLength;
        }
        if ($fieldType === 'int') {
            $fieldLength = $helper->ask($input, $output, new Question('Enter the field padding (length): '));
            $fieldDefinition['padding'] = $fieldLength;
        }
        if (is_null($primary)) {
            $fieldDefinition['primary'] = $this->yesNoQuestionPerformer->execute(
                ['Is this field a primary key? [y/n]'],
                $input,
                $output)
            ;
            $primary = $fieldName;
        } else {
            $defaultValue = $this->yesNoQuestionPerformer->execute(
                ['Do you want to set a default value for this field? [y/n]'],
                $input,
                $output
            );
            if ($defaultValue) {
                if ($fieldType === 'datetime' && $this->yesNoQuestionPerformer->execute(
                        ['Do you want to set the default value to the current time? [y/n]'],
                        $input,
                        $output
                    )) {
                    $fieldDefinition['default'] = 'CURRENT_TIMESTAMP';
                } else {
                    $defaultValueQuestion = new Question('Enter the default value: ');
                    $defaultValue = $helper->ask($input, $output, $defaultValueQuestion);
                    $fieldDefinition['default'] = $defaultValue;
                }
            }
            $indexes[$fieldName] = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add an index to this field? [y/n]'],
                $input,
                $output
            );
        }
        $fieldDefinition['nullable'] = $this->yesNoQuestionPerformer->execute(
            ['Is this field nullable? [y/n]'],
            $input,
            $output
        );
        return [
            $fieldName => [
                'field' => $fieldDefinition,
                'primary' => $primary,
                'indexes' => $indexes
            ]
        ];
    }
}
