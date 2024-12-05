<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Field
{
    private array $fieldTypes = [
        'int',
        'smallint',
        'varchar',
        'boolean',
        'date',
        'datetime',
        'timestamp',
        'float',
        'blob',
        'decimal',
        'json',
        'real',
        'text',
        'varbinary'
    ];

    public function __construct(
        private readonly YesNo            $yesNoQuestionPerformer,
        protected readonly QuestionHelper $questionHelper,
    )
    {
    }

    /**
     * @return array|string[]
     */
    public function getFieldTypes(): array
    {
        return $this->fieldTypes;
    }

    /**
     * This part will ask questions to user to be able to know what type of field he wants to create
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param $primary
     * @param string $tableName
     * @return array
     */
    public function create(OutputInterface $output, InputInterface $input, &$primary, string $tableName = ''): array
    {
        if (!$primary) {
            return $this->createPrimary($output, $input, $primary, $tableName);
        }
        $fieldName = $this->questionHelper->ask(
            $input,
            $output,
            new Question('Enter the field name <info>(leave empty to advance to constraints)</info>: ' . PHP_EOL)
        );
        if ($fieldName == '') {
            return [];
        }
        $fieldTypeQuestion = new Question(
            'Choose the field type <info>(' . implode('|', $this->getFieldTypes()) . ')</info>: ' . PHP_EOL,
            'varchar'
        );
        $fieldTypeQuestion->setAutocompleterValues($this->getFieldTypes());
        $fieldType = $this->questionHelper->ask($input, $output, $fieldTypeQuestion);
        $fieldDefinition['type'] = $fieldType;
        if ($fieldType === 'varchar') {
            $fieldLength = $this->questionHelper->ask(
                $input,
                $output,
                new Question('Enter the field length <info>(default : 255)</info>: ', 255)
            );
            $fieldDefinition['length'] = $fieldLength;
        }
        if ($fieldType === 'int') {
            $fieldLength = $this->questionHelper->ask(
                $input,
                $output,
                new Question('Enter the field padding (length) <info>(default : 6)</info> : ', 6)
            );
            $fieldDefinition['padding'] = $fieldLength;
            $fieldDefinition['unsigned'] = $this->yesNoQuestionPerformer->execute(
                ['Is this field unsigned? [y/n]'],
                $input,
                $output
            ) ? "true" : "false";
        }
        /** @todo manage many to many relations */
        $fieldDefinition['nullable'] = $this->yesNoQuestionPerformer->execute(
            ['Is this field nullable? [y/n]'],
            $input,
            $output
        ) ? "true" : "false";
        if ($fieldDefinition['nullable'] === 'false') {
            $defaultValue = $this->yesNoQuestionPerformer->execute(
                ['Do you want to set a default value for this field? [y/n]'],
                $input,
                $output
            );
            if ($defaultValue) {
                if ($fieldType === 'datetime' || $fieldType === 'timestamp' && $this->yesNoQuestionPerformer->execute(
                        ['Do you want to set the default value to the current time? [y/n]'],
                        $input,
                        $output
                    )) {
                    $fieldDefinition['default'] = 'CURRENT_TIMESTAMP';
                } else {
                    $defaultValueQuestion = new Question('Enter the default value: ');
                    $defaultValue = $this->questionHelper->ask($input, $output, $defaultValueQuestion);
                    $fieldDefinition['default'] = $defaultValue;
                }
            }
        }
        dump($fieldName, $fieldDefinition);
        return [$fieldName => $fieldDefinition];
    }

    public function createPrimary(OutputInterface $output, InputInterface $input, &$primary, string $tableName = '')
    {
        $fieldName = $this->questionHelper->ask(
            $input,
            $output,
            new Question(
                'Please define a primary key <info>default : ' . $tableName . '_id</info>: ' . PHP_EOL,
                $tableName . '_id'
            )
        );
        $primary = $fieldName;
        $defaultPrimary = $this->yesNoQuestionPerformer->execute(
            ['Do you accept this definition <info>int(5)</info>?'],
            $input,
            $output
        );
        $padding = 5;
        if (!$defaultPrimary) {
            $padding = $this->questionHelper->ask(
                $input,
                $output,
                new Question('Enter the field padding (length): ', 6)
            );
        }
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
