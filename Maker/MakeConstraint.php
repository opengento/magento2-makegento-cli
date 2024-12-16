<?php

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Api\MakerInterface;
use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;

class MakeConstraint implements MakerInterface
{

    public function __construct(
        private readonly DbSchemaCreator         $dbSchemaCreator,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly DbSchemaParser          $dbSchemaParser,
        private readonly ConstraintDefinition    $foreignKeyDefinition,
        private readonly CurrentModule           $currentModule
    )
    {
    }

    /**
     * @throws TableDefinitionException
     * @throws ConstraintDefinitionException
     */
    public function generate(): void
    {
        $selectedModule = $this->currentModule->getModuleName();
        $tableName = $this->dataTableAutoCompletion->tableSelector($selectedModule);
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);
        $fields = $dataTables[$tableName]['fields'];

        $constraintDefinition = $this->foreignKeyDefinition->define($tableName, $fields);

        $existingConstraints = $dataTables[$tableName]['constraints'];
        $existingConstraints = array_merge($existingConstraints, $constraintDefinition);
        $dataTables[$tableName]['constraints'] = $existingConstraints;

        $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);
    }
}
