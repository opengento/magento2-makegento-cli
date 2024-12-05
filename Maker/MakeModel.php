<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Opengento\MakegentoCli\Generator\GeneratorModel;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class MakeModel extends AbstractMaker
{
    public function __construct(
        protected readonly QuestionHelper $questionHelper,
        private readonly DbSchemaParser  $dbSchemaParser,
        private readonly GeneratorModel   $generatorModel
    )
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $selectedModule
     * @param string $modulePath
     * @return void
     * @throws \Exception
     */
    public function generate(InputInterface $input, OutputInterface $output, string $selectedModule, string $modulePath = ''): void
    {
        $output->writeln('Make Model');

        $output->writeln('Module: ' . $selectedModule);

        $tables = $this->dbSchemaParser->getModuleDataTables($selectedModule);

        $dbSelectionQuestion = new Question('Enter the name of the database table <info>Start typing for autocompletion</info>: ');
        $dbSelectionQuestion->setAutocompleterValues(array_keys($tables));
        $tableName = $this->questionHelper->ask($input, $output, $dbSelectionQuestion);

        $modelClassName = $this->getDefaultClassName($tableName, $selectedModule);

        $modelClassName = $this->questionHelper->ask($input, $output, new Question('Enter the class name <info>[default : '. $modelClassName .']</info>: ', $modelClassName));

        $properties = $tables[$tableName];

        $output->writeln('Start generating model for table ' . $tableName);
        $interface = $this->generatorModel->generateModelInterface($modulePath, $modelClassName, $properties);
        $output->writeln('Interface '. $interface . ' generated');

        $modelClass = $this->generatorModel->generateModel($modulePath, $modelClassName, $properties, $interface);
        $output->writeln('Model '. $modelClass .' generated');

        $resourceClass = $this->generatorModel->generateResourceModel($modulePath, $modelClassName, $interface, $tableName);
        $output->writeln('Resource Model '. $resourceClass .' generated');

        $collectionClass = $this->generatorModel->generateCollection($modulePath, $modelClassName, $modelClass, $resourceClass);
        $output->writeln('Collection '. $collectionClass .' generated');


    }

    private function getDefaultClassName(string $tableName, string $moduleName): string
    {
        $moduleNameParts = explode('_', $moduleName);

        foreach ($moduleNameParts as $part) {
            $tableName = str_replace($part, '', $tableName);
            $tableName = str_replace(ucfirst($part), '', $tableName);
        }

        $className = str_replace('_', ' ', $tableName);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);
        return $className;
    }
}
