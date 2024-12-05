<?php

namespace Opengento\MakegentoCli\Test\Unit\Database\Service;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConstraintDefinitionTest extends TestCase
{
    private $yesNoQuestionPerformer;
    private $dataTableAutoCompletion;
    private $constraintDefinition;
    private $input;
    private $output;
    private $questionHelper;

    protected function setUp(): void
    {
        $this->yesNoQuestionPerformer = $this->createMock(YesNo::class);
        $this->dataTableAutoCompletion = $this->createMock(DataTableAutoCompletion::class);
        $this->constraintDefinition = new ConstraintDefinition($this->yesNoQuestionPerformer, $this->dataTableAutoCompletion);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->questionHelper = $this->createMock(QuestionHelper::class);
    }

    /**
     * @throws ConstraintDefinitionException
     */
    public function testDefineForeignKeyConstraint()
    {
        $this->yesNoQuestionPerformer->method('execute')->willReturn(true);
        $this->questionHelper->method('ask')
            ->will($this->onConsecutiveCalls(
                'foreign', // Constraint type
                'column1', // Column
                'reference_table', // Reference table
                'reference_column', // Reference column
                'CASCADE' // onDelete action
            ));
        $this->dataTableAutoCompletion->method('getAllTables')->willReturn(['reference_table']);
        $this->dataTableAutoCompletion->method('getTableFields')->willReturn(['identity' => 'id', 'fields' => ['reference_column']]);

        $result = $this->constraintDefinition->define($this->output, $this->input, 'table_name', ['column1' => 'int'], $this->questionHelper);

        $expected = [
            'TABLE_NAME_COLUMN1_REFERENCE_TABLE_REFERENCE_COLUMN' => [
                'type' => 'foreign',
                'table' => 'table_name',
                'column' => 'column1',
                'referenceTable' => 'reference_table',
                'referenceColumn' => 'reference_column',
                'onDelete' => 'CASCADE'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @throws ConstraintDefinitionException
     */
    public function testDefineUniqueConstraint()
    {
        $this->yesNoQuestionPerformer->method('execute')->willReturn(true);
        $this->questionHelper->method('ask')
            ->will($this->onConsecutiveCalls(
                'unique', // Constraint type
                'constraint_name', // Constraint name
                'column1', // Column
                '' // End of columns
            ));

        $result = $this->constraintDefinition->define($this->output, $this->input, 'table_name', ['column1' => 'int'], $this->questionHelper);

        $expected = [
            'constraint_name' => [
                'type' => 'unique',
                'columns' => ['column1']
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @throws ConstraintDefinitionException
     */
    public function testDefineNoConstraint()
    {
        $this->yesNoQuestionPerformer->method('execute')->willReturn(false);

        $result = $this->constraintDefinition->define($this->output, $this->input, 'table_name', ['column1' => 'int'], $this->questionHelper);

        $this->assertEmpty($result);
    }
}
