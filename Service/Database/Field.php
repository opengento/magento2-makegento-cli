<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\CommandIoNotInitializedException;
use Opengento\MakegentoCli\Exception\ExistingFieldException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class Field
 * @author Christophe Ferreboeuf <christophe@crealoz.fr>
 */
class Field
{
    /**
     * Array of field types with their properties. The value of the property is the default value. For unsigned fields,
     * the default value is an empty string because question is yes/no.
     *
     * @var array
     */
    private array $fieldTypes = [
        'int' => [
            'padding' => 6,
            'unsigned' => ''
        ],
        'smallint' => [
            'padding' => 6,
            'unsigned' => ''
        ],
        'varchar' => [
            'length' => 255
        ],
        'boolean' => [],
        'date' => [],
        'datetime' => [],
        'timestamp' => [],
        'float' => [
            'precision' => 10,
            'scale' => 2,
            'unsigned' => ''
        ],
        'blob' => [],
        'decimal' => [
            'precision' => 10,
            'scale' => 2,
            'unsigned' => ''
        ],
        'json' => [],
        'real' => [
            'precision' => 10,
            'scale' => 2,
            'unsigned' => ''
        ],
        'text' => [],
        'varbinary' => [
            'length' => 255
        ],
    ];
    private \Symfony\Component\Console\Helper\QuestionHelper $questionHelper;
    private OutputInterface $output;
    private InputInterface $input;

    public function __construct(
        private readonly YesNo            $yesNoQuestionPerformer,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly CommandIoProvider $commandIoProvider,
    )
    {
    }

    /**
     * This part will ask questions to user to be able to know what type of field he wants to create
     *
     * @param $primary
     * @param string $tableName
     * @return array
     * @throws ExistingFieldException
     * @throws CommandIoNotInitializedException
     */
    public function create(&$primary, string $tableName = ''): array
    {

        $this->output = $this->commandIoProvider->getOutput();
        $this->input = $this->commandIoProvider->getInput();
        $this->questionHelper = $this->commandIoProvider->getQuestionHelper();
        if (!$primary) {
            return $this->createPrimary($primary, $tableName);
        }
        $fieldName = $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the field name <info>(leave empty to advance to constraints)</info>: ' . PHP_EOL)
        );
        if ($fieldName == '') {
            return [];
        }
        $existingFields = $this->dataTableAutoCompletion->getTableFields($tableName);
        if (in_array($fieldName, $existingFields['fields'])) {
            throw new ExistingFieldException('Field '. $fieldName .' already exists');
        }
        $fieldTypeQuestion = new Question(
            'Choose the field type <info>(' . implode('|', array_keys($this->fieldTypes)) . ')</info>: ' . PHP_EOL,
            'varchar'
        );
        $fieldTypeQuestion->setAutocompleterValues(array_keys($this->fieldTypes));
        $fieldType = $this->questionHelper->ask($this->input, $this->output, $fieldTypeQuestion);
        $fieldDefinition['type'] = $fieldType;
        /**
         * Ask question for all the attributes of the field type. We exclude the unsigned attribute because it's managed
         * by a yes/no question.
         */
        foreach ($this->fieldTypes[$fieldType] as $attribute => $defaultValue) {
            if ($attribute === 'unsigned') {
                continue;
            }
            $fieldDefinition[$attribute] = $this->getFieldSpecificDefinition($attribute, $fieldType, $this->input, $this->output);
        }
        if (isset($this->fieldTypes[$fieldType]['unsigned'])) {
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
                $hasDefault = null;
                if ($fieldType === 'datetime' || $fieldType === 'timestamp' || $fieldType === 'date') {
                    $hasDefault = 'CURRENT_TIMESTAMP';
                }
                $questionString = 'Enter the default value ';
                $questionString .= $hasDefault ? '<info>(default : ' . $hasDefault . ')</info>: ' : ': ';
                $defaultValueQuestion = new Question($questionString, $hasDefault);
                $defaultValue = $this->questionHelper->ask($this->input, $this->output, $defaultValueQuestion);
                $fieldDefinition['default'] = $defaultValue;
            }
        }
        $this->dataTableAutoCompletion->addFieldToTable($tableName, $fieldName);

        return [$fieldName => $fieldDefinition];
    }

    /**
     * This part will ask questions to user to be able to know what type of field needs to be created
     *
     * @param string $attribute
     * @param string $fieldType
     * @return string
     */
    private function getFieldSpecificDefinition(string $attribute, string $fieldType): string
    {
        $defaultAnswer = $this->fieldTypes[$fieldType][$attribute];
        return $this->questionHelper->ask(
            $this->input,
            $this->output,
            new Question('Enter the field ' . $attribute .' <info>(default : '.$defaultAnswer.')</info>: ', $defaultAnswer)
        );
    }

    /**
     * This part will ask questions to user to create the primary key
     *
     * @param $primary
     * @param string $tableName
     * @return array
     * @throws CommandIoNotInitializedException
     */
    public function createPrimary(&$primary, string $tableName = '')
    {
        $this->output = $this->commandIoProvider->getOutput();
        $this->input = $this->commandIoProvider->getInput();
        $this->questionHelper = $this->commandIoProvider->getQuestionHelper();
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
        $this->dataTableAutoCompletion->addFieldToTable($tableName, $fieldName, true);
        return [$fieldName =>
            [
                'padding' => $padding,
                'type' => 'int',
                'unsigned' => "true",
                'nullable' => "false",
                'identity' => "true"
            ]
        ];
    }
}
