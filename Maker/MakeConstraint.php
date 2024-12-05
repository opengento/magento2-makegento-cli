<?php

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Exception\ConstraintDefinitionException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaCreator;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Database\ConstraintDefinition;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeConstraint extends AbstractMaker
{

    public function __construct(
        private readonly DbSchemaCreator         $dbSchemaCreator,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly DbSchemaParser          $dbSchemaParser,
        private readonly ConstraintDefinition    $foreignKeyDefinition,
        protected readonly QuestionHelper        $questionHelper
    )
    {
    }

    /**
     * @throws TableDefinitionException
     * @throws ConstraintDefinitionException
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule): void
    {
        $tableName = $this->dataTableAutoCompletion->tableSelector($input, $output, $selectedModule);
        $dataTables = $this->dbSchemaParser->getModuleDataTables($selectedModule);
        $fields = $dataTables[$tableName]['fields'];

        $constraintDefinition = $this->foreignKeyDefinition->define($output, $input, $tableName, $fields, $this->questionHelper);

        $existingConstraints = $dataTables[$tableName]['constraints'];
        $existingConstraints = array_merge($existingConstraints, $constraintDefinition);
        $dataTables[$tableName]['constraints'] = $existingConstraints;
        dump($dataTables);
        $this->dbSchemaCreator->createDbSchema($selectedModule, $dataTables);
    }
}
