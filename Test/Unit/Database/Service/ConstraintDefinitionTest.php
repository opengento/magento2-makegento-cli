<?php

namespace Opengento\MakegentoCli\Test\Unit\Database\Service;

use Magento\Framework\Console\QuestionPerformer\YesNo;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConstraintDefinitionTest extends TestCase
{
    private $dataTableAutoCompletion;
    private $constraintDefinition;
    private $questionHelper;

    protected function setUp(): void
    {
        $this->yesNoQuestionPerformer = $this->createMock(YesNo::class);
        $this->dataTableAutoCompletion = $this->createMock(DataTableAutoCompletion::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $this->questionHelper = $this->createMock(QuestionHelper::class);
        $commandIoProvider = $this->createMock(CommandIoProvider::class);
        $commandIoProvider->method('getInput')->willReturn($input);
        $commandIoProvider->method('getOutput')->willReturn($output);
        $commandIoProvider->method('getQuestionHelper')->willReturn($this->questionHelper);
        $this->constraintDefinition = new ConstraintDefinition($this->yesNoQuestionPerformer, $this->dataTableAutoCompletion, $commandIoProvider);
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

        $result = $this->constraintDefinition->define('table_name', ['column1' => 'int']);

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

        $result = $this->constraintDefinition->define('table_name', ['column1' => 'int']);

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

        $result = $this->constraintDefinition->define('table_name', ['column1' => 'int']);

        $this->assertEmpty($result);
    }
}
