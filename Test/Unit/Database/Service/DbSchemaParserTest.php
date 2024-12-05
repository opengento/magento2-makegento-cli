<?php

namespace Opengento\MakegentoCli\Test\Unit\Database\Service;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\DbSchemaPath;
use PHPUnit\Framework\TestCase;

class DbSchemaParserTest extends TestCase
{
    private $dbSchemaPath;
    private $dbSchemaParser;

    protected function setUp(): void
    {
        $this->dbSchemaPath = $this->createMock(DbSchemaPath::class);
        $this->dbSchemaParser = new DbSchemaParser($this->dbSchemaPath);
    }

    public function testGetModuleDataTables()
    {
        $selectedModule = 'Test_Module';
        $tempFile = tempnam(sys_get_temp_dir(), 'db_schema.xml');
        $xmlContent = <<<XML
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <table name="test_table" engine="innodb" resource="default" comment="Test Table">
        <column name="id" xsi:type="int" nullable="false" primary="true"/>
        <column name="name" xsi:type="varchar" nullable="false"/>
        <constraint xsi:type="primary" name="PRIMARY">
            <column name="id"/>
        </constraint>
        <constraint xsi:type="foreign" name="FK_TEST_TABLE_ANOTHER_TABLE_ID" referenceTable="another_table" referenceColumn="id">
            <column name="another_table_id"/>
        </constraint>
        <index referenceId="IDX_NAME" indexType="btree">
            <column name="name"/>
        </index>
    </table>
</schema>
XML;

        $this->dbSchemaPath->method('get')->with($selectedModule)->willReturn($tempFile);
        file_put_contents($tempFile, $xmlContent);

        $expectedDataTables = [
            'test_table' => [
                'fields' => [
                    'id' => [
                        'nullable' => 'false',
                        'primary' => 'true',
                        'type' => 'int'
                    ],
                    'name' => [
                        'nullable' => 'false',
                        'type' => 'varchar'
                    ]
                ],
                'constraints' => [
                    'PRIMARY' => [
                        'type' => 'primary',
                        'name' => 'PRIMARY',
                        'columns' => [
                            [
                                '@attributes' => [
                                    'name' => 'id'
                                ]
                            ]
                        ]
                    ],
                    'FK_TEST_TABLE_ANOTHER_TABLE_ID' => [
                        'type' => 'foreign',
                        'name' => 'FK_TEST_TABLE_ANOTHER_TABLE_ID',
                        'referenceTable' => 'another_table',
                        'referenceColumn' => 'id',
                        'columns' => [
                            [
                                '@attributes' => [
                                    'name' => 'another_table_id'
                                ]
                            ]
                        ]
                    ]
                ],
                'primary' => 'id',
                'indexes' => [
                    'IDX_NAME' => [
                        'type' => 'btree',
                        'fields' => ['name']
                    ]
                ],
                'table_attr' => [
                    'engine' => 'innodb',
                    'resource' => 'default',
                    'comment' => 'Test Table'
                ]
            ]
        ];

        $actualDataTables = json_decode(json_encode($this->dbSchemaParser->getModuleDataTables($selectedModule)), true);
        $this->assertEquals($expectedDataTables, $actualDataTables);
    }
}
