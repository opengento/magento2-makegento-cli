<?php

namespace Opengento\MakegentoCli\Service;

use Opengento\MakegentoCli\Exception\TableDefinitionException;

class DbSchemaService
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

    public function getFieldTypes(): array
    {
        return $this->fieldTypes;
    }

    public function moduleHasDbSchema(string $modulePath): bool
    {
        $modulePath .= '/etc/db_schema.xml';
        return file_exists($modulePath);
    }

    public function parseDbSchema(string $modulePath): array
    {
        $modulePath .= '/etc/db_schema.xml';
        $xml = simplexml_load_file($modulePath);
        /**
         * let's try to parse the xml file to find table names, their fields and attributes of the fields
         */
        $tables = [];
        foreach ($xml->table as $table) {
            $primary = null;
            $tableName = (string)$table['name'];
            $tableAttributes = [];
            foreach ($table->attributes() as $attrName => $attrValue) {
                if ($attrName === 'name') {
                    continue;
                }
                $tableAttributes[$attrName] = (string)$attrValue;
            }
            $columns = [];
            foreach ($table->column as $column) {
                $columnName = (string)$column['name'];
                $type = (string)$column->attributes('xsi', true)['type'];
                $attributes = [
                    'type' => $type
                ];
                foreach ($column->attributes() as $attrName => $attrValue) {
                    if ($attrName === 'name') {
                        continue;
                    }
                    $attributes[$attrName] = (string)$attrValue;
                }
                $columns[$columnName] = $attributes;
            }
            $domPrimary = $table->xpath('constraint[@xsi:type="primary"]');
            if ($domPrimary) {
                $primary = (string)$domPrimary[0]->column['name'];
            }
            $constraints = [];
            foreach ($table->constraint as $constraint) {
                /** @var \SimpleXMLElement $constraint */
                $constraintName = (string)$constraint['name'];
                $constraintAttributes = [];
                foreach ($constraint->attributes() as $attrName => $attrValue) {
                    if ($attrName === 'referenceId' && (string)$attrValue === 'PRIMARY') {
                        continue 2;
                    }
                    $constraintAttributes[$attrName] = (string)$attrValue;
                }
                foreach ($constraint->column ?? [] as $column) {
                    if (!isset($constraintAttributes['columns'])) {
                        $constraintAttributes['columns'] = [];
                    }
                    $constraintAttributes['columns'][] = $column;
                }
                $constraints[$constraintName] = $constraintAttributes;
            }
            $indexes = [];
            foreach ($table->index as $index) {
                $indexName = (string)$index['referenceId'];
                $indexType = (string)$index['indexType'];
                $fields = [];
                foreach ($index->column as $field) {
                    $fields[] = (string)$field['name'];
                }
                $indexes[$indexName] = [
                    'type' => $indexType,
                    'fields' => $fields
                ];
            }
            $tables[$tableName] =
                [
                    'fields' => $columns,
                    'constraints' => $constraints,
                    'primary' => $primary,
                    'indexes' => $indexes,
                    'table_attr' => $tableAttributes
                ];
        }
        return $tables;
    }

    /**
     * @throws TableDefinitionException
     */
    public function createDbSchema(string $modulePath, array $dataTables): void
    {
        $modulePath .= '/etc/db_schema.xml';
        $xml = new \SimpleXMLElement('<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd"></schema>');
        foreach ($dataTables as $tableName => $tableDefinition) {
            $this->checkTableDefinition($tableDefinition);
            $table = $xml->addChild('table');
            $table->addAttribute('name', $tableName);
            foreach ($tableDefinition['table_attr'] as $attrName => $attrValue) {
                $table->addAttribute($attrName, $attrValue);
            }
            foreach ($tableDefinition['fields'] as $fieldName => $fieldAttributes) {
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
            foreach ($tableDefinition['indexes'] as $indexName => $indexDefinition) {
                $index = $table->addChild('index');
                $index->addAttribute('referenceId', $indexName);
                $index->addAttribute('indexType', $indexDefinition['type']);
                foreach ($indexDefinition['fields'] as $fieldName) {
                    $index->addChild('column')->addAttribute('name', $fieldName);
                }
            }
            if (isset($tableDefinition['primary'])) {
                /** @todo manage many to many */
                $primary = $table->addChild('constraint');
                $primary->addAttribute('xsi:type', 'primary', 'http://www.w3.org/2001/XMLSchema-instance');
                $primary->addAttribute('referenceId', 'PRIMARY');
                $primary->addChild('column')->addAttribute('name', $tableDefinition['primary']);
            }
            foreach ($tableDefinition['constraints'] as $constraintName => $constraintAttributes) {
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
        $xml->asXML($modulePath);
    }

    public function getModuleTables(string $modulePath): array
    {
        $modulePath .= '/etc/db_schema.xml';
        $xml = simplexml_load_file($modulePath);
        $tables = [];
        foreach ($xml->table as $table) {
            $tableName = (string)$table['name'];
            $tables[$tableName] = [];
            foreach ($table->column as $column) {
                $columnName = (string)$column['name'];
                $type = (string)$column->attributes('xsi', true)['type'];
                $tables[$tableName][$columnName] = $type;
            }
        }
        return $tables;
    }

    /**
     * @throws TableDefinitionException
     */
    private function checkTableDefinition(array $tableDefinition): void
    {
        if (!isset($tableDefinition['fields']) || !is_array($tableDefinition['fields']) || empty($tableDefinition['fields'])) {
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
