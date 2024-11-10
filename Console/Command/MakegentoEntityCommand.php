<?php

namespace Opengento\MakegentoCli\Console\Command;

use Magento\Framework\Console\QuestionPerformer\YesNo;
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
        private readonly DbSchemaService $dbSchemaCreator,
        private readonly YesNo           $yesNoQuestionPerformer,
        private readonly GeneratorFactory       $generatorFactory,
        private readonly ConsoleModuleSelector $moduleSelector
    ) {
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

        $this->dbSchemaQuestionner($this->moduleSelector->getModulePath($selectedModule));
        return Command::SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     * @return void
     */
    private function dbSchemaQuestionner(string $modulePath)
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
            $dataTables = array_merge($dataTables, $this->tableCreation());
            $addNewTable = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add a new table?'],
                $this->input,
                $this->output
            );
        }
        $this->dbSchemaCreator->createDbSchema($modulePath, $dataTables);
    }

    private function tableCreation(): array
    {
        $tableName = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the datatable name: '));
        $tableFields = [];
        $addNewField = true;
        $primary = null;
        $indexes = [];
        $constraints = [];
        while ($addNewField) {
            $tableFields = array_merge($tableFields, $this->fieldCreation($primary, $indexes));
            $addNewField = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add a new field? [y/n]'],
                $this->input,
                $this->output
            );
        }
        $addConstraint = $this->yesNoQuestionPerformer->execute(
            ['Do you want to add a constraint to this table? [y/n]'],
            $this->input,
            $this->output
        );
        if ($addConstraint) {
            $constraintName = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the constraint name: '));
            $constraintTypeQuestion = new ChoiceQuestion(
                'Choose the constraint type',
                ['unique', 'foreign']
            );
            $constraintType = $this->questionHelper->ask($this->input, $this->output, $constraintTypeQuestion);
            $constraintDefinition = ['type' => $constraintType];
            if ($constraintType === 'foreign') {
                $constraintDefinition['referenceTable'] = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the reference table: '));
                $constraintDefinition['referenceField'] = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the reference field: '));
            }
            $constraints[$constraintName] = $constraintDefinition;
        }
        $addIndex = $this->yesNoQuestionPerformer->execute(
            ['Do you want to add an index to this table? [y/n]'],
            $this->input,
            $this->output
        );
        if ($addIndex) {
            $indexName = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the index name: '));
            $indexFields = [];
            $indexType = $this->questionHelper->ask($this->input, $this->output, new ChoiceQuestion(
                'Choose the index type',
                ['btree', 'fulltext', 'hash']
            ));
            $addNewIndexField = true;
            while ($addNewIndexField) {
                $indexFields[] = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the index field: '));
                $addNewIndexField = $this->yesNoQuestionPerformer->execute(
                    ['Do you want to add a new field to the index? [y/n]'],
                    $this->input,
                    $this->output
                );
            }
            $indexes[$indexName] = [
                'type' => $indexType,
                'fields' => $indexFields
            ];
        }
        return [$tableName => [
            'fields' => $tableFields,
            'constraints' => $constraints,
            'primary' => $primary,
            'indexes' => $indexes,
        ]];
    }

    private function fieldCreation(&$primary, &$indexes): array
    {
        $fieldName = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the field name: '));
        // Let's ask things to create database
        $fieldTypeQuestion = new ChoiceQuestion(
            'Choose the field type',
            $this->dbSchemaCreator->getFieldTypes()
        );
        $fieldType = $this->questionHelper->ask($this->input, $this->output, $fieldTypeQuestion);
        $fieldDefinition['type'] = $fieldType;
        if ($fieldType === 'varchar') {
            $fieldLength = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the field length: '));
            $fieldDefinition['length'] = $fieldLength;
        }
        if ($fieldType === 'int') {
            $fieldLength = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the field padding (length): '));
            $fieldDefinition['padding'] = $fieldLength;
        }
        /** @todo manage many to many relations */
        if (is_null($primary)) {
            $fieldDefinition['primary'] = $this->yesNoQuestionPerformer->execute(
                ['Is this field a primary key? [y/n]'],
                $this->input,
                $this->output)
            ;
            $primary = $fieldName;
        } else {
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
            $indexes[$fieldName] = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add an index to this field? [y/n]'],
                $this->input,
                $this->output
            );
        }
        return [$fieldName => $fieldDefinition];
    }
}
