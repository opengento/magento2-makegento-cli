<?php

namespace Opengento\MakegentoCli\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\InvalidArrayException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Generator\GeneratorFactory;
use Opengento\MakegentoCli\Service\DbSchemaService;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MakegentoEntityCommand extends Command
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected QuestionHelper $questionHelper;

    public function __construct(
        private readonly DbSchemaService       $dbSchemaCreator,
        private readonly YesNo                 $yesNoQuestionPerformer,
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly ResourceConnection    $resourceConnection,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('make:entity')
            ->setDescription('Create a new entity')
            ->setHelp('This command allows you to create a new entity.');
        parent::configure();
    }

    /**
     * @throws TableDefinitionException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $this->getHelper('question');

        try {
            $selectedModule = $this->moduleSelector->execute($input, $output, $this->questionHelper, true);
        } catch (\Exception $e) {
            $this->output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $this->dbSchemaQuestioner($this->moduleSelector->getModulePath($selectedModule));
        return Command::SUCCESS;
    }

    /**
     * @param string $modulePath
     * @return void
     */
    private function dbSchemaQuestioner(string $modulePath): void
    {
        $dbSchemaExists = $this->dbSchemaCreator->moduleHasDbSchema($modulePath);
        if ($dbSchemaExists) {
            $this->output->writeln("<info>Database schema already exists</info>");
            $this->output->writeln("<info>Database schema modification</info>");
            $dataTables = $this->dbSchemaCreator->parseDbSchema($modulePath);
        } else {
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
                $this->dbSchemaCreator->createDbSchema($modulePath, $dataTables);
                $this->output->writeln("<info>Database schema created</info>");
            } catch (TableDefinitionException $e) {
                $this->output->writeln("<error>{$e->getMessage()}</error>");
            }
        }
    }

    /**
     * @return array[]
     * @throws InvalidArrayException
     */
    private function tableCreation(): array
    {
        $tableName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the datatable name <info>leave empty to go to db_schema.xml generation</info>: '.PHP_EOL)
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

    private function fieldCreation(&$primary, $tableName = ''): array
    {
        if (!$primary) {
            $fieldName = $this->questionHelper->ask(
                $this->input,
                $this->output,
                new Question(
                    'Please define a primary key <info>default : '.$tableName.'_id</info>: '.PHP_EOL,
                    $tableName.'_id'
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
            new Question('Enter the field name <info>(leave empty to advance to constraints)</info>: '.PHP_EOL)
        );
        if ($fieldName == '') {
            return [];
        }
        $fieldTypeQuestion = new Question(
            'Choose the field type <info>('.implode('|', $this->dbSchemaCreator->getFieldTypes()).')</info>: '. PHP_EOL,
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
        }
        /** @todo manage many to many relations */
        $fieldDefinition['nullable'] = $this->yesNoQuestionPerformer->execute(
            ['Is this field nullable? [y/n]'],
            $this->input,
            $this->output
        );
        if ($fieldDefinition['nullable'] === false) {
            $defaultValue = $this->yesNoQuestionPerformer->execute(
                ['Do you want to set a default value for this field? [y/n]'],
                $this->input,
                $this->output
            );
            if ($defaultValue) {
                if ($fieldType === 'datetime' && $this->yesNoQuestionPerformer->execute(
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
     * @throws InvalidArrayException
     */
    private function createIndex($fields): array
    {

        $indexName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the index name <info>(leave empty to add a new table)</info>: '.PHP_EOL)
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
            $fieldSelection = new Question('Enter the index field <info>(leave empty to stop adding field to this index)</info>: '.PHP_EOL);
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
     * @param $fields
     * @return array|array[]
     * @throws InvalidArrayException
     */
    private function createConstraint($fields, $tableName): array
    {
        $constraintName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the constraint name <info>(leave empty to advance to indexes)</info>: '.PHP_EOL)
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

            $fieldSelection = new Question('Choose the column: '.PHP_EOL);
            $fieldSelection->setAutocompleterValues(array_keys($fields));
            $constraintDefinition['column'] = $this->questionHelper->ask($this->input, $this->output, $fieldSelection);

            $tableQuestion = new Question('Choose the reference table <info>begin typing to start autocompletion</info>: '.PHP_EOL);
            $tableQuestion->setAutocompleterValues($this->getAllTables());
            $constraintDefinition['referenceTable'] = $this->questionHelper->ask($this->input, $this->output, $tableQuestion);

            $tableFields = $this->getTableFields($constraintDefinition['referenceTable']);
            $referenceFieldSelection = new Question('Choose the reference field <info>begin typing to start autocompletion</info>: '.PHP_EOL, $tableFields['identity']);
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
                $fieldSelection = new Question('Choose the column <info>(leave empty to stop adding columns to this constraint)</info>: '.PHP_EOL);
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
        return [$constraintName => $constraintDefinition];
    }

    private function getAllTables(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tables = $connection->getTables();
        $tableNames = [];
        foreach ($tables as $table) {
            $tableNames[] = $table;
        }
        return $tableNames;
    }

    private function getTableFields($tableName): array
    {
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
        return ['fields' => $fields, 'identity' => $primaryKey];
    }

    private function formatForeignKeyReference($constraintDefinition): string
    {
        return strtoupper($constraintDefinition['table'] . '_' . $constraintDefinition['column'] . '_' . $constraintDefinition['referenceTable'] . '_' . $constraintDefinition['referenceColumn']);
    }
}
