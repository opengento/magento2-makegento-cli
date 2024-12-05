<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;

class DbSchemaCreator
{

    public function __construct(
        private readonly DbSchemaPath $dbSchemaPath
    )
    {
    }

    /**
     * Creates a db_schema.xml file in the module directory with the data tables provided.
     *
     * @param string $selectedModule
     * @param array $dataTables
     * @return void
     * @throws FileSystemException
     * @throws TableDefinitionException
     */
    public function createDbSchema(string $selectedModule, array $dataTables): void
    {
        $xml = new \SimpleXMLElement('<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd"></schema>');
        foreach ($dataTables as $tableName => $tableDefinition) {
            $this->checkTableDefinition($tableDefinition);
            $table = $xml->addChild('table');
            $table->addAttribute('name', $tableName);
            foreach ($tableDefinition['table_attr'] as $attrName => $attrValue) {
                $table->addAttribute($attrName, $attrValue);
            }

            $this->addFields($table, $tableDefinition['fields']);

            $this->addIndexes($table, $tableDefinition['indexes']);

            if (isset($tableDefinition['primary'])) {
                /** @todo manage many to many */
                $primary = $table->addChild('constraint');
                $primary->addAttribute('xsi:type', 'primary', 'http://www.w3.org/2001/XMLSchema-instance');
                $primary->addAttribute('referenceId', 'PRIMARY');
                $primary->addChild('column')->addAttribute('name', $tableDefinition['primary']);
            }

            $this->addConstraints($table, $tableDefinition['constraints']);
        }
        $modulePath = $this->dbSchemaPath->get($selectedModule);
        $xml->asXML($modulePath);
    }

    /**
     * @param \SimpleXMLElement $table
     * @param array $fields
     * @return void
     */
    private function addFields(\SimpleXMLElement &$table, array $fields): void
    {
        foreach ($fields as $fieldName => $fieldAttributes) {
            $column = $table->addChild('column');
            $column->addAttribute('name', $fieldName);
            foreach ($fieldAttributes as $attrName => $attrValue) {
                if ($attrName === 'type') {
                    $column->addAttribute('xsi:type', $attrValue, 'http://www.w3.org/2001/XMLSchema-instance');
                    continue;
                }
                $column->addAttribute($attrName, $attrValue);
            }
        }
    }

    private function addIndexes(\SimpleXMLElement &$table, array $indexes): void
    {
        foreach ($indexes as $indexName => $indexDefinition) {
            $index = $table->addChild('index');
            $index->addAttribute('referenceId', $indexName);
            $index->addAttribute('indexType', $indexDefinition['type']);
            foreach ($indexDefinition['fields'] as $fieldName) {
                $index->addChild('column')->addAttribute('name', $fieldName);
            }
        }
    }

    /**
     * Adds the constraints to the table node.
     *
     * @param \SimpleXMLElement $table
     * @param array $constraints
     * @return void
     */
    private function addConstraints(\SimpleXMLElement &$table, array $constraints): void
    {
        foreach ($constraints as $constraintName => $constraintAttributes) {
            $constraint = $table->addChild('constraint');
            $constraint->addAttribute('xsi:type', $constraintAttributes['type'], 'http://www.w3.org/2001/XMLSchema-instance');
            $constraint->addAttribute('referenceId', $constraintName);
            if ($constraintAttributes['type'] === 'foreign') {
                $constraint->addAttribute('table', $constraintAttributes['table']);
                $constraint->addAttribute('column', $constraintAttributes['column']);
                $constraint->addAttribute('referenceTable', $constraintAttributes['referenceTable']);
                $constraint->addAttribute('referenceColumn', $constraintAttributes['referenceColumn']);
                $constraint->addAttribute('onDelete', $constraintAttributes['onDelete']);
            } else {
                foreach ($constraintAttributes['columns'] as $column) {
                    $constraint->addChild('column')->addAttribute('name', $column);
                }
            }
        }
    }

    /**
     * Makes some checks before creating the db_schema.xml file.
     *
     * @param array $tableDefinition
     * @return void
     * @throws TableDefinitionException
     */
    private function checkTableDefinition(array $tableDefinition): void
    {
        if (empty($tableDefinition['fields']) || !is_array($tableDefinition['fields'])) {
            throw new TableDefinitionException('fields is a required key in the table definition');
        }
        if (!isset($tableDefinition['indexes']) || !is_array($tableDefinition['indexes'])) {
            throw new TableDefinitionException('indexes is a required key in the table definition and it must be of type array');
        }
        if (!isset($tableDefinition['constraints']) || !is_array($tableDefinition['constraints'])) {
            throw new TableDefinitionException('constraints is a required key in the table definition and it must be of type array');
        }
    }
}
