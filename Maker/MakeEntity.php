<?php

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Generator\Generator;
use Opengento\MakegentoCli\Utils\ConsoleStyle;
use Opengento\MakegentoCli\Service\DbSchemaService;
use Opengento\MakegentoCli\Utils\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class MakeEntity extends AbstractMaker
{

    public function __construct(
        private readonly DbSchemaService $dbSchemaCreator,
        private readonly YesNo           $yesNoQuestionPerformer,
        private readonly QuestionHelper  $questionHelper,
        ConsoleStyle $consoleStyle
    ) {
        parent::__construct($consoleStyle);
    }

    public static function getCommandName(): string
    {
        return 'make:entity';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription('Create a new entity')
            ->setHelp('This command allows you to create a new entity.');
    }

    public function generate(InputInterface $input, OutputInterface $output, Generator $generator)
    {
        $this->output = $output;
        $this->input = $input;
        $this->dbSchemaQuestionner($generator->getModulePath());
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
            $dataTables[] = $this->tableCreation();
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
        while ($addNewField) {
            $tableFields[] = $this->fieldCreation();
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
        return [$tableName => ['fields' => $tableFields, 'constraint' => $addConstraint]];
    }

    private function fieldCreation(): array
    {
        $fieldName = $this->questionHelper->ask($this->input, $this->output, new Question('Enter the field name: '));
        $primary = null;
        $indexes = [];
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
        if (is_null($primary)) {
            $fieldDefinition['primary'] = $this->yesNoQuestionPerformer->execute(
                ['Is this field a primary key? [y/n]'],
                $this->input,
                $this->output)
            ;
            $primary = $fieldName;
        } else {
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
            $indexes[$fieldName] = $this->yesNoQuestionPerformer->execute(
                ['Do you want to add an index to this field? [y/n]'],
                $this->input,
                $this->output
            );
        }
        $fieldDefinition['nullable'] = $this->yesNoQuestionPerformer->execute(
            ['Is this field nullable? [y/n]'],
            $this->input,
            $this->output
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
