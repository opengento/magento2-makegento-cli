<?php

namespace Opengento\MakegentoCli\Test\Unit\Database\Service;

use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaPath;
use PHPUnit\Framework\TestCase;

class DbSchemaCreatorTest extends TestCase
{
    private $dbSchemaPath;
    private $dbSchemaCreator;

    private $constraintDefinition;

    protected function setUp(): void
    {
        $this->dbSchemaPath = $this->createMock(DbSchemaPath::class);
        $this->dbSchemaCreator = new DbSchemaCreator($this->dbSchemaPath);
        $this->constraintDefinition = $this->createMock(ConstraintDefinition::class);
    }

    public function testCreateDbSchemaWithForeignKey()
    {
        $selectedModule = 'Test_Module';
        $dataTables = [
            'test_table' => [
                'fields' => [
                    'id' => ['type' => 'int', 'nullable' => 'false', 'primary' => 'true'],
                    'name' => ['type' => 'varchar', 'nullable' => 'false'],
                    'test_table_2_id' => ['type' => 'int', 'nullable' => 'false']
                ],
                'constraints' => [
                    'PRIMARY' => ['type' => 'primary', 'columns' => ['id']],
                    'FK_TEST_TABLE_TEST_TABLE_2' => [
                        'type' => 'foreign',
                        'table' => 'test_table',
                        'column' => 'test_table_2_id',
                        'referenceTable' => 'test_table_2',
                        'referenceColumn' => 'id',
                        'onDelete' => 'CASCADE'
                    ]
                ],
                'primary' => 'id',
                'indexes' => [
                    'IDX_NAME' => ['type' => 'btree', 'fields' => ['name']]
                ],
                'table_attr' => [
                    'engine' => 'innodb',
                    'resource' => 'default',
                    'comment' => 'Test Table'
                ]
            ],
            'test_table_2' => [
                'fields' => [
                    'id' => ['type' => 'int', 'nullable' => 'false', 'primary' => 'true'],
                    'description' => ['type' => 'text', 'nullable' => 'true']
                ],
                'constraints' => [
                    'PRIMARY' => ['type' => 'primary', 'columns' => ['id']]
                ],
                'primary' => 'id',
                'indexes' => [],
                'table_attr' => [
                    'engine' => 'innodb',
                    'resource' => 'default',
                    'comment' => 'Test Table 2'
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'db_schema');
        $this->dbSchemaPath->method('get')->with($selectedModule)->willReturn($tempFile);

        $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);

        $this->assertFileExists($tempFile);
        $xmlContent = file_get_contents($tempFile);
        $this->assertStringContainsString('<table name="test_table" engine="innodb" resource="default" comment="Test Table">', $xmlContent);
        $this->assertStringContainsString('<column name="id" xsi:type="int" nullable="false" primary="true"/>', $xmlContent);
        $this->assertStringContainsString('<column name="name" xsi:type="varchar" nullable="false"/>', $xmlContent);
        $this->assertStringContainsString('<column name="test_table_2_id" xsi:type="int" nullable="false"/>', $xmlContent);
        $this->assertStringContainsString('<constraint xsi:type="foreign" referenceId="FK_TEST_TABLE_TEST_TABLE_2" table="test_table" column="test_table_2_id" referenceTable="test_table_2" referenceColumn="id" onDelete="CASCADE"/>', $xmlContent);
        $this->assertStringContainsString('<table name="test_table_2" engine="innodb" resource="default" comment="Test Table 2">', $xmlContent);
        $this->assertStringContainsString('<column name="id" xsi:type="int" nullable="false" primary="true"/>', $xmlContent);
        $this->assertStringContainsString('<column name="description" xsi:type="text" nullable="true"/>', $xmlContent);

        unlink($tempFile);
    }

    private function invokeCheckTableDefinition(array $tableDefinition)
    {
        $reflection = new \ReflectionClass($this->dbSchemaCreator);
        $method = $reflection->getMethod('checkTableDefinition');
        $method->setAccessible(true);
        $method->invoke($this->dbSchemaCreator, $tableDefinition);
    }

    public function testCheckTableDefinitionValid()
    {
        $validTableDefinition = [
            'fields' => [
                'id' => ['type' => 'int', 'nullable' => 'false', 'primary' => 'true']
            ],
            'indexes' => [],
            'constraints' => []
        ];

        $this->invokeCheckTableDefinition($validTableDefinition);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    public function testCheckTableDefinitionMissingFields()
    {
        $this->expectException(TableDefinitionException::class);
        $this->expectExceptionMessage('fields is a required key in the table definition');

        $invalidTableDefinition = [
            'indexes' => [],
            'constraints' => []
        ];

        $this->invokeCheckTableDefinition($invalidTableDefinition);
    }

    public function testCheckTableDefinitionMissingIndexes()
    {
        $this->expectException(TableDefinitionException::class);
        $this->expectExceptionMessage('indexes is a required key in the table definition and it must be of type array');

        $invalidTableDefinition = [
            'fields' => [
                'id' => ['type' => 'int', 'nullable' => 'false', 'primary' => 'true']
            ],
            'constraints' => []
        ];

        $this->invokeCheckTableDefinition($invalidTableDefinition);
    }

    public function testCheckTableDefinitionMissingConstraints()
    {
        $this->expectException(TableDefinitionException::class);
        $this->expectExceptionMessage('constraints is a required key in the table definition and it must be of type array');

        $invalidTableDefinition = [
            'fields' => [
                'id' => ['type' => 'int', 'nullable' => 'false', 'primary' => 'true']
            ],
            'indexes' => []
        ];

        $this->invokeCheckTableDefinition($invalidTableDefinition);
    }
}
