<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\CommandIoNotInitializedException;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class ConstraintDefinition
{

    public function __construct(
        private readonly YesNo                  $yesNoQuestionPerformer,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly CommandIoProvider      $commandIoProvider
    )
    {
    }

    /**
     * This function will format the foreign key reference to fit with the standards.
     *
     * @param array $constraintDefinition
     * @return string
     * @throws ConstraintDefinitionException
     */
    public function getName(array $constraintDefinition): string
    {
        if (!isset($constraintDefinition['table']) || !isset($constraintDefinition['column']) || !isset($constraintDefinition['referenceTable']) || !isset($constraintDefinition['referenceColumn'])) {
            throw new ConstraintDefinitionException(__('The constraint definition is not valid'));
        }
        return strtoupper($constraintDefinition['table'] . '_' . $constraintDefinition['column'] . '_' . $constraintDefinition['referenceTable'] . '_' . $constraintDefinition['referenceColumn']);
    }


    /**
     * This part will ask questions to user to be able to know what type of constraint he wants to create. If the user
     * chooses to create a foreign key, it will ask for the reference table and field. Autocompletion is available for
     * both table and fields.
     *
     * @param string $tableName
     * @param array $fields
     * @return array
     * @throws ConstraintDefinitionException|CommandIoNotInitializedException
     */
    public function define(string $tableName, array $fields): array
    {
        $input = $this->commandIoProvider->getInput();
        $output = $this->commandIoProvider->getOutput();
        $questionHelper = $this->commandIoProvider->getQuestionHelper();
        if (!$this->yesNoQuestionPerformer->execute(
            ['Do you want to add constraint? [y/n]'],
            $input,
            $output
        )) {
            return [];
        }
        $constraintType = $questionHelper->ask($input, $output, new ChoiceQuestion(
            'Choose the constraint type',
            ['unique', 'foreign']
        ));
        $constraintDefinition = ['type' => $constraintType];
        if ($constraintType === 'foreign') {
            $output->writeln("<info>Note that referenceId will be automatically generated to fit with standards</info>");
            $constraintDefinition['table'] = $tableName;

            $fieldSelection = new Question('Choose the column: ' . PHP_EOL);
            $fieldSelection->setAutocompleterValues(array_keys($fields));
            $constraintDefinition['column'] = $questionHelper->ask($input, $output, $fieldSelection);

            $tableQuestion = new Question('Choose the reference table <info>begin typing to start autocompletion</info>: ' . PHP_EOL);
            $tableQuestion->setAutocompleterValues($this->dataTableAutoCompletion->getAllTables());
            $constraintDefinition['referenceTable'] = $questionHelper->ask($input, $output, $tableQuestion);

            $tableFields = $this->dataTableAutoCompletion->getTableFields($constraintDefinition['referenceTable']);
            $referenceFieldSelection = new Question('Choose the reference field <info>begin typing to start autocompletion</info>: ' . PHP_EOL, $tableFields['identity']);
            $referenceFieldSelection->setAutocompleterValues($tableFields['fields']);
            $constraintDefinition['referenceColumn'] = $questionHelper->ask($input, $output, $referenceFieldSelection);

            $constraintDefinition['onDelete'] = $questionHelper->ask($input, $output, new ChoiceQuestion(
                'Choose the onDelete action <info>(default : CASCADE)</info>',
                ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'],
                'CASCADE'
            ));
            $constraintName = $this->getName($constraintDefinition);
        } else {
            $constraintName = $questionHelper->ask(
                $input,
                $output,
                new Question('Enter the constraint name <info>(leave empty to advance to indexes)</info>: ' . PHP_EOL)
            );
            $columns = [];
            $addColumn = true;
            while ($addColumn) {
                $fieldSelection = new Question('Choose the column <info>(leave empty to stop adding columns to this constraint)</info>: ' . PHP_EOL);
                $fieldSelection->setAutocompleterValues(array_keys($fields));
                $column = $questionHelper->ask($input, $output, $fieldSelection);
                if ($column == '') {
                    $addColumn = false;
                } else {
                    $columns[] = $column;
                }
            }
            if (empty($columns)) {
                throw new ConstraintDefinitionException(__('Columns cannot be empty for unique constraint'));
            }
            $constraintDefinition['columns'] = $columns;
        }
        if (empty($constraintDefinition['type'])) {
            throw new ConstraintDefinitionException(__('Constraint definition must have a type'));
        }
        return [$constraintName => $constraintDefinition];
    }
}
