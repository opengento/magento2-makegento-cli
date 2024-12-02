<?php

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\InvalidArrayException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\DbSchemaService;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MakeEntity extends AbstractMaker
{
    private readonly OutputInterface $output;
    private readonly InputInterface $input;

    private bool $dataTableInitialized = false;

    private bool $tableFieldInitialized = false;

    private array $dataTables = [];

    private array $tableFields = [];

    public function __construct(
        private readonly DbSchemaService    $dbSchemaCreator,
        private readonly YesNo              $yesNoQuestionPerformer,
        private readonly ResourceConnection $resourceConnection,
        protected readonly QuestionHelper   $questionHelper
    )
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $selectedModule
     * @param string $modulePath
     * @return void
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule, string $modulePath = ''): void
    {
        $this->output = $output;
        $this->input = $input;
        $dbSchemaExists = $this->dbSchemaCreator->moduleHasDbSchema($modulePath);
        if ($dbSchemaExists) {
            $output->writeln("<info>Database schema already exists</info>");
            $output->writeln("<info>Database schema modification</info>");
            $dataTables = $this->dbSchemaCreator->parseDbSchema($modulePath);
            foreach ($dataTables as $tableName => $table) {
                $this->dataTables[] = $tableName;
                $this->addTableFields($tableName, array_keys($table['fields']), $table['primary']);
            }
        } else {
            $output->writeln("<info>Database schema creation</info>");
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
                $output->writeln("<error>You did not create any field in last table, going to generation.</error>");
                $addNewTable = false;
            }
        }
        if (!empty($dataTables)) {
            try {
                $this->dbSchemaCreator->createDbSchema($modulePath, $dataTables);
                $output->writeln("<info>Database schema created</info>");
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
        $tableFields = [];
        $addNewField = true;
        $primary = false;
        $indexes = [];
        $constraints = [];
        while ($addNewField) {
            $field = $this->fieldCreation($primary, $tableName);
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
                $constraint = $this->createConstraint($tableFields, $tableName);
                if (empty($constraint)) {
                    $addConstraint = false;
                } else {
                    $constraints = array_merge($constraints, $constraint);
                }
            } catch (InvalidArrayException $e) {
                $addConstraint = false;
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
        $this->dataTables[] = $tableName;
        $this->addTableFields($tableName, array_keys($tableFields), $primary);
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
     * This part will ask questions to user to be able to know what type of field he wants to create
     *
     * @param $primary
     * @param $tableName
     * @return array
     */
    private function fieldCreation(&$primary, $tableName = ''): array
    {
        if (!$primary) {
            $fieldName = $this->questionHelper->ask(
                $this->input,
                $this->output,
                new Question(
                    'Please define a primary key <info>default : ' . $tableName . '_id</info>: ' . PHP_EOL,
                    $tableName . '_id'
                )
            );
            $primary = $fieldName;
            $defaultPrimary = $this->yesNoQuestionPerformer->execute(
                ['Do you accept this definition <info>int(5)</info>?'],
                $this->input,
                $this->output
            );
            $padding = 5;
            if (!$defaultPrimary) {
                $padding = $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    new Question('Enter the field padding (length): ', 6)
                );
            }
            return [$fieldName =>
                [
                    'padding' => $padding,
                    'type' => 'int',
                    'unsigned' => "true",
                    'nullable' => "false"
                ]
            ];
        }
        $fieldName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the field name <info>(leave empty to advance to constraints)</info>: ' . PHP_EOL)
        );
        if ($fieldName == '') {
            return [];
        }
        $fieldTypeQuestion = new Question(
            'Choose the field type <info>(' . implode('|', $this->dbSchemaCreator->getFieldTypes()) . ')</info>: ' . PHP_EOL,
            'varchar'
        );
        $fieldTypeQuestion->setAutocompleterValues($this->dbSchemaCreator->getFieldTypes());
        $fieldType = $this->questionHelper->ask($this->input, $this->output, $fieldTypeQuestion);
        $fieldDefinition['type'] = $fieldType;
        if ($fieldType === 'varchar') {
            $fieldLength = $this->questionHelper->ask(
                $this->input,
                $this->output,
                new Question('Enter the field length <info>(default : 255)</info>: ', 255)
            );
            $fieldDefinition['length'] = $fieldLength;
        }
        if ($fieldType === 'int') {
            $fieldLength = $this->questionHelper->ask(
                $this->input,
                $this->output,
                new Question('Enter the field padding (length) <info>(default : 6)</info> : ', 6)
            );
            $fieldDefinition['padding'] = $fieldLength;
            $fieldDefinition['unsigned'] = $this->yesNoQuestionPerformer->execute(
                ['Is this field unsigned? [y/n]'],
                $this->input,
                $this->output
            ) ? "true" : "false";
        }
        /** @todo manage many to many relations */
        $fieldDefinition['nullable'] = $this->yesNoQuestionPerformer->execute(
            ['Is this field nullable? [y/n]'],
            $this->input,
            $this->output
        ) ? "true" : "false";
        if ($fieldDefinition['nullable'] === 'false') {
            $defaultValue = $this->yesNoQuestionPerformer->execute(
                ['Do you want to set a default value for this field? [y/n]'],
                $this->input,
                $this->output
            );
            if ($defaultValue) {
                if ($fieldType === 'datetime' || $fieldType === 'timestamp' && $this->yesNoQuestionPerformer->execute(
                        ['Do you want to set the default value to the current time? [y/n]'],
                        $this->input,
                        $this->output
                    )) {
                    $fieldDefinition['default'] = 'CURRENT_TIMESTAMP';
                } else {
                    $defaultValueQuestion = new Question('Enter the default value: ');
                    $defaultValue = $this->questionHelper->ask($this->input, $this->output, $defaultValueQuestion);
                    $fieldDefinition['default'] = $defaultValue;
                }
            }
        }
        return [$fieldName => $fieldDefinition];
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

    /**
     * This part will ask questions to user to be able to know what type of constraint he wants to create. If the user
     * chooses to create a foreign key, it will ask for the reference table and field. Autocompletion is available for
     * both table and fields.
     *
     * @param $fields
     * @param $tableName
     * @return array
     * @throws InvalidArrayException
     */
    private function createConstraint($fields, $tableName): array
    {
        $constraintName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the constraint name <info>(leave empty to advance to indexes)</info>: ' . PHP_EOL)
        );
        if ($constraintName == '') {
            return [];
        }
        $constraintType = $this->questionHelper->ask($this->input, $this->output, new ChoiceQuestion(
            'Choose the constraint type',
            ['unique', 'foreign']
        ));
        $constraintDefinition = ['type' => $constraintType];
        if ($constraintType === 'foreign') {
            $this->output->writeln("<info>Note that referenceId will be automatically generated to fit with standards</info>");
            $constraintDefinition['table'] = $tableName;

            $fieldSelection = new Question('Choose the column: ' . PHP_EOL);
            $fieldSelection->setAutocompleterValues(array_keys($fields));
            $constraintDefinition['column'] = $this->questionHelper->ask($this->input, $this->output, $fieldSelection);

            $tableQuestion = new Question('Choose the reference table <info>begin typing to start autocompletion</info>: ' . PHP_EOL);
            $tableQuestion->setAutocompleterValues($this->getAllTables());
            $constraintDefinition['referenceTable'] = $this->questionHelper->ask($this->input, $this->output, $tableQuestion);

            $tableFields = $this->getTableFields($constraintDefinition['referenceTable']);
            $referenceFieldSelection = new Question('Choose the reference field <info>begin typing to start autocompletion</info>: ' . PHP_EOL, $tableFields['identity']);
            $referenceFieldSelection->setAutocompleterValues($tableFields['fields']);
            $constraintDefinition['referenceColumn'] = $this->questionHelper->ask($this->input, $this->output, $referenceFieldSelection);

            $constraintDefinition['onDelete'] = $this->questionHelper->ask($this->input, $this->output, new ChoiceQuestion(
                'Choose the onDelete action <info>(default : CASCADE)</info>',
                ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'],
                'CASCADE'
            ));

            $constraintName = $this->formatForeignKeyReference($constraintDefinition);
        } else {
            $columns = [];
            $addColumn = true;
            while ($addColumn) {
                $fieldSelection = new Question('Choose the column <info>(leave empty to stop adding columns to this constraint)</info>: ' . PHP_EOL);
                $fieldSelection->setAutocompleterValues(array_keys($fields));
                $column = $this->questionHelper->ask($this->input, $this->output, $fieldSelection);
                if ($column == '') {
                    $addColumn = false;
                } else {
                    $columns[] = $column;
                }
            }
            if (empty($columns)) {
                throw new InvalidArrayException('Columns cannot be empty for unique constraint');
            }
            $constraintDefinition['columns'] = $columns;
        }
        if (empty($constraintDefinition['type'])) {
            throw new InvalidArrayException('Constraint definition must have a type');
        }
        return [$constraintName => $constraintDefinition];
    }

    /**
     * Returns all the tables in the database to be able to manage autocomplete for foreign key reference table.
     *
     * @return array
     */
    private function getAllTables(): array
    {
        if (!$this->dataTableInitialized) {
            $connection = $this->resourceConnection->getConnection();
            $tables = $connection->getTables();
            foreach ($tables as $table) {
                $this->dataTables[] = $table;
            }
        }
        return $this->dataTables;
    }

    /**
     * Returns all the fields of a table to be able to manage autocomplete for foreign key reference field.
     *
     * @param string $tableName
     * @return array
     */
    private function getTableFields(string $tableName): array
    {
        if (!$this->tableFieldInitialized && !isset($this->tableFields[$tableName])) {
            $connection = $this->resourceConnection->getConnection();
            $table = $connection->describeTable($tableName);
            $fields = [];
            $primaryKey = '';
            foreach ($table as $name => $field) {
                if ($field['PRIMARY'] === true) {
                    $primaryKey = $name;
                }
                $fields[] = $name;
            }
            $this->addTableFields($tableName, $fields, $primaryKey);
        }
        return $this->tableFields[$tableName];
    }

    /**
     * Adds a table to table fields array
     * @param string $tableName
     * @param array $fields
     * @param string $primaryKey
     */
    private function addTableFields(string $tableName, array $fields, string $primaryKey): void
    {
        $this->tableFields[$tableName] = [
            'fields' => $fields,
            'identity' => $primaryKey
        ];
    }

    /**
     * This function will format the foreign key reference to fit with the standards.
     *
     * @param array $constraintDefinition
     * @return string
     */
    private function formatForeignKeyReference(array $constraintDefinition): string
    {
        return strtoupper($constraintDefinition['table'] . '_' . $constraintDefinition['column'] . '_' . $constraintDefinition['referenceTable'] . '_' . $constraintDefinition['referenceColumn']);
    }
}
