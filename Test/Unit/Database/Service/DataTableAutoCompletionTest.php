<?php

namespace Opengento\MakegentoCli\Test\Unit\Database\Service;

use Magento\Framework\App\ResourceConnection;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DataTableAutoCompletionTest extends TestCase
{
    private $questionHelper;
    private $resourceConnection;
    private $dbSchemaParser;
    private $dataTableAutoCompletion;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->dbSchemaParser = $this->createMock(DbSchemaParser::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $this->questionHelper = $this->createMock(QuestionHelper::class);
        $commandIoProvider = $this->createMock(CommandIoProvider::class);
        $commandIoProvider->init($input, $output, $this->questionHelper);
        $commandIoProvider->method('getInput')->willReturn($input);
        $commandIoProvider->method('getOutput')->willReturn($output);
        $commandIoProvider->method('getQuestionHelper')->willReturn($this->questionHelper);
        $this->dataTableAutoCompletion = new DataTableAutoCompletion($this->resourceConnection, $this->dbSchemaParser, $commandIoProvider);
    }

    public function testGetAllTables()
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $connection->method('getTables')->willReturn(['table1', 'table2']);

        $result = $this->dataTableAutoCompletion->getAllTables();

        $this->assertEquals(['table1', 'table2'], $result);
    }

    public function testGetTableFields()
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $connection->method('describeTable')->willReturn([
            'id' => ['PRIMARY' => true],
            'field1' => ['PRIMARY' => false],
            'field2' => ['PRIMARY' => false]
        ]);

        $result = $this->dataTableAutoCompletion->getTableFields('table1');

        $expected = [
            'fields' => ['id', 'field1', 'field2'],
            'identity' => 'id'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTableSelector()
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $this->dbSchemaParser->method('getModuleDataTables')->willReturn(['table1' => 'Table 1', 'table2' => 'Table 2']);
        $this->questionHelper->method('ask')->willReturn('table1');

        $result = $this->dataTableAutoCompletion->tableSelector('module_name');

        $this->assertEquals('table1', $result);
    }
}
