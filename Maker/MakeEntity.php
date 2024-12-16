<?php

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Api\MakerInterface;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\ExistingFieldException;
use Opengento\MakegentoCli\Exception\InvalidArrayException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\Field;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MakeEntity implements MakerInterface
{
    private readonly OutputInterface $output;
    private readonly InputInterface $input;

    public function __construct(
        private readonly DbSchemaCreator        $dbSchemaCreator,
        private readonly DbSchemaParser         $dbSchemaParser,
        private readonly Field                  $field,
        protected readonly QuestionHelper       $questionHelper,
        protected DataTableAutoCompletion       $dataTableAutoCompletion,
        protected readonly ConstraintDefinition $constraintDefinition,
        private readonly CommandIoProvider      $commandIoProvider,
        private readonly CurrentModule           $currentModule
    )
    {
    }

    /**
     * @return void
     * @throws FileSystemException|\Opengento\MakegentoCli\Exception\CommandIoNotInitializedException
     */
    public function generate(): void
    {
        $this->output = $this->commandIoProvider->getOutput();
        $this->input = $this->commandIoProvider->getInput();
        $selectedModule = $this->currentModule->getModuleName();
        try {
            $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);
            $this->output->writeln("<info>Database schema already exists</info>");
            $this->output->writeln("<info>Database schema modification</info>");
            foreach ($dataTables as $tableName => $table) {
                $this->dataTableAutoCompletion->addDataTable($tableName);
                $this->dataTableAutoCompletion->addTableFields($tableName, array_keys($table['fields']), $table['primary']);
            }
        } catch (TableDefinitionException $e) {
            $this->output->writeln("<info>Database schema creation</info>");
            $dataTables = [];
        }
        $addNewTable = true;
        while ($addNewTable) {
            try {
                $tableDefinition = $this->tableCreation();
                if (empty($tableDefinition)) {
                    $addNewTable = false;
                } else {
                    $dataTables = array_merge($dataTables, $tableDefinition);
                }
            } catch (InvalidArrayException $e) {
                $this->output->writeln("<error>You did not create any field in last table, going to generation.</error>");
                $addNewTable = false;
            }
        }
        if (!empty($dataTables)) {
            try {
                $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);
                $this->output->writeln("<info>Database schema created</info>");
            } catch (TableDefinitionException $e) {
                $this->output->writeln("<error>{$e->getMessage()}</error>");
            }
        }
    }

    /**
     * This part will ask questions to user to be able to know general settings of the table. It will then call the
     * functions to create fields, indexes and constraints.
     *
     * @return array[]
     * @throws InvalidArrayException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function tableCreation(): array
    {
        $tableName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the datatable name <info>leave empty to go to db_schema.xml generation</info>: ' . PHP_EOL)
        );
        if ($tableName == '') {
            return [];
        }
        $engine = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new ChoiceQuestion(
                'Choose the table engine <info>(default : innodb)</info>',
                ['innodb', 'memory'],
                'innodb'
            )
        );
        $resource = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new ChoiceQuestion(
                'Choose the table resource <info>(default : default)</info>',
                ['default', 'sales', 'checkout'],
                'default'
            )
        );
        $comment = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the table comment <info>(default : Table comment)</info>: ', 'Table comment')
        );
        $this->dataTableAutoCompletion->addDataTable($tableName);
        $tableFields = [];
        $addNewField = true;
        $primary = false;
        $indexes = [];
        $constraints = [];
        while ($addNewField) {
            try {
                $field = $this->field->create($this->output, $this->input, $primary, $tableName);
            } catch (ExistingFieldException $e) {
                $this->output->writeln("<error>{$e->getMessage()}</error>");
            }
            if (empty($field)) {
                $addNewField = false;
            } else {
                $tableFields = array_merge($tableFields, $field);
            }
        }
        if (empty($tableFields) || count($tableFields) === 1) {
            throw new InvalidArrayException('Table fields cannot be empty');
        }
        $addConstraint = true;
        while ($addConstraint) {
            try {
                $constraint = $this->constraintDefinition->define($this->output, $this->input, $tableName, $tableFields, $this->questionHelper);
                if (empty($constraint)) {
                    $addConstraint = false;
                } else {
                    $constraints = array_merge($constraints, $constraint);
                }
            } catch (ConstraintDefinitionException $e) {
                $this->output->writeln("<error>Previous constraint was not added due to : {$e->getMessage()}. Please try again!</error>");
            }
        }
        $addIndex = true;
        while ($addIndex) {
            try {
                $index = $this->createIndex($tableFields);
                if (empty($index)) {
                    $addIndex = false;
                } else {
                    $indexes = array_merge($indexes, $index);
                }
            } catch (InvalidArrayException $e) {
                $addIndex = false;
            }
        }
        return [$tableName => [
            'fields' => $tableFields,
            'constraints' => $constraints,
            'primary' => $primary,
            'indexes' => $indexes,
            'table_attr' => [
                'engine' => $engine,
                'resource' => $resource,
                'comment' => $comment
            ]
        ]];
    }


    /**
     * This part will ask questions to user to be able to know what type of index he wants to create.
     *
     * @param $fields
     * @return array
     * @throws InvalidArrayException
     */
    private function createIndex($fields): array
    {

        $indexName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the index name <info>(leave empty to add a new table)</info>: ' . PHP_EOL)
        );
        if ($indexName == "") {
            return [];
        }
        $indexFields = [];
        $indexType = $this->questionHelper->ask($this->input, $this->output, new ChoiceQuestion(
            'Choose the index type',
            ['btree', 'fulltext', 'hash']
        ));
        $addNewIndexField = true;
        while ($addNewIndexField) {
            $fieldSelection = new Question('Enter the index field <info>(leave empty to stop adding field to this index)</info>: ' . PHP_EOL);
            $fieldSelection->setAutocompleterValues(array_keys($fields));
            $field = $this->questionHelper->ask($this->input, $this->output, $fieldSelection);
            if ($field == '') {
                $addNewIndexField = false;
            } else {
                $indexFields[] = $field;
            }
        }
        if (empty($indexFields)) {
            throw new InvalidArrayException('Index fields cannot be empty');
        }
        return [
            $indexName => [
                'type' => $indexType,
                'fields' => $indexFields
            ]
        ];
    }
}
