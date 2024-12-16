<?php

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Api\MakerInterface;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\ExistingFieldException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\Field;

class MakeField implements MakerInterface
{

    public function __construct(
        private readonly DbSchemaCreator         $dbSchemaCreator,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly DbSchemaParser          $dbSchemaParser,
        private readonly Field                   $field,
        private readonly ConstraintDefinition    $constraintDefinition,
        private readonly CommandIoProvider       $commandIoProvider,
        private readonly CurrentModule           $currentModule
    )
    {
    }

    /**
     * @return void
     * @throws ConstraintDefinitionException
     * @throws FileSystemException
     * @throws TableDefinitionException|\Opengento\MakegentoCli\Exception\CommandIoNotInitializedException
     */
    public function generate(): void
    {
        $selectedModule = $this->currentModule->getModuleName();
        $tableName = $this->dataTableAutoCompletion->tableSelector($selectedModule);
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);
        if (!isset($dataTables[$tableName])) {
            throw new TableDefinitionException("Table $tableName does not exist in the module $selectedModule");
        }
        $primary = $dataTables[$tableName]['primary'] ?? '';

        try {
            $field = $this->field->create($primary, $tableName);
        } catch (ExistingFieldException $e) {
            $this->commandIoProvider->getOutput()->writeln("<error>{$e->getMessage()}</error>");
            return;
        }
        $existingFields = $dataTables[$tableName]['fields'];
        $dataTables[$tableName]['fields'] = array_merge($existingFields, $field);

        $constraints = $this->constraintDefinition->define($tableName, $dataTables[$tableName]['fields']);
        $existingConstraints = $dataTables[$tableName]['constraints'];
        $dataTables[$tableName]['constraints'] = array_merge($existingConstraints, $constraints);

        $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);
    }
}
