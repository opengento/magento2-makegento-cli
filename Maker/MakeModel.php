<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Filesystem\Io\File;
use Opengento\MakegentoCli\Api\MakerInterface;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Generator\GeneratorModel;
use Opengento\MakegentoCli\Generator\GeneratorRepository;
use Opengento\MakegentoCli\Service\CommandIoProvider;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Database\DataTableAutoCompletion;
use Opengento\MakegentoCli\Service\Database\DbSchemaParser;
use Opengento\MakegentoCli\Service\Php\DefaultClassNameGetter;
use Symfony\Component\Console\Question\Question;

class MakeModel implements MakerInterface
{
    public function __construct(
        private readonly DbSchemaParser  $dbSchemaParser,
        private readonly GeneratorModel   $generatorModel,
        private readonly GeneratorRepository $generatorRepository,
        private readonly File $ioFile,
        private readonly DefaultClassNameGetter $defaultClassNameGetter,
        private readonly DataTableAutoCompletion $dataTableAutoCompletion,
        private readonly CommandIoProvider $commandIoProvider,
        private readonly CurrentModule           $currentModule
    )
    {
    }

    /**
     * @param string $selectedModule
     * @param string $modulePath
     * @return void
     */
    public function generate(): void
    {
        $selectedModule = $this->currentModule->getModuleName();
        $input = $this->commandIoProvider->getInput();
        $output = $this->commandIoProvider->getOutput();
        $questionHelper = $this->commandIoProvider->getQuestionHelper();

        $output->writeln('Make Model');

        $output->writeln('Module: ' . $selectedModule);

        $tables = $this->dbSchemaParser->getModuleDataTables($selectedModule);

        $tableName = $this->dataTableAutoCompletion->tableSelector($selectedModule);

        $modelClassName = $this->defaultClassNameGetter->get($tableName, $selectedModule);

        $modelClassName = $questionHelper->ask($input, $output, new Question('Enter the class name <info>[default : '. $modelClassName .']</info>: ', $modelClassName));

        $modelSubFolder = ucfirst($questionHelper->ask($input, $output, new Question('Enter the subfolder <info>[default : none]</info>: ', '')));

        $properties = $tables[$tableName];

        $output->writeln('Start generating model for table ' . $tableName);
        try {
            $interface = $this->generatorModel->generateModelInterface($modelClassName, $properties['fields']);
            $output->writeln('Interface '. $interface . ' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $interface = $e->getClassName();
        }

        try {
            $modelClass = $this->generatorModel->generateModel($modelClassName, $properties['fields'], $interface, $modelSubFolder);
            $output->writeln('Model '. $modelClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $modelClass = $e->getClassName();
        }

        try {
            $resourceClass = $this->generatorModel->generateResourceModel($modelClassName, $interface, $tableName, $modelSubFolder);
            $output->writeln('Resource Model '. $resourceClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $resourceClass = $e->getClassName();
        }

        try {
            $collectionClass = $this->generatorModel->generateCollection($modelClassName, $modelClass, $resourceClass, $modelSubFolder);
            $output->writeln('Collection '. $collectionClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $collectionClass = $e->getClassName();
        }

        try {
            $searchResultInterface = $this->generatorRepository->generateSearchCriteriaInterface($modelClassName);
            $output->writeln('Repository '. $searchResultInterface .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $searchResultInterface = $e->getClassName();
        }

        try {
            $repositoryInterface = $this->generatorRepository->generateRepositoryInterface($modelClassName, $searchResultInterface, $resourceClass, $collectionClass, $interface);
            $output->writeln('Repository '. $repositoryInterface .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $repositoryInterface = $e->getClassName();
        }

        try {
            $repositoryClass = $this->generatorRepository->generateRepository($modelClassName, $searchResultInterface, $resourceClass, $collectionClass, $interface, $repositoryInterface);
            $output->writeln('Repository '. $repositoryClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $repositoryClass = $e->getClassName();
        }
        $output->writeln('Generating preferences in di.xml file');
        $this->addPreferencesInDI($modelClass, $interface, $repositoryInterface, $repositoryClass);

        $output->writeln('<success>Model generation completed</success>');

    }

    private function addPreferencesInDI(
        string $modelClassName,
        string $modelInterface,
        string $repositoryInterface,
        string $repositoryClass,
    ): void
    {
        $modulePath = $this->currentModule->getModulePath();
        $diXmlPath = $modulePath . '/etc/di.xml';
        $diXmlContent = $this->ioFile->read($diXmlPath);
        // we look for "<preference for="(interface)" type="(modelClassName)"/>" in the di.xml file
        $pattern = '/<preference for="([^"]+)" type="([^"]+)"/';
        $hasPreferences = preg_match_all($pattern, $diXmlContent, $matches);
        $existingPreferences = [];
        if ($hasPreferences && !empty($matches[1]) && !empty($matches[2])) {
            foreach ($matches[1] as $index => $interface) {
                $existingPreferences[$interface] = $matches[2][$index];
            }
        }
        $newPreferences = [];
        if (!isset($existingPreferences[$modelInterface])) {
            $newPreferences[$modelInterface] = $modelClassName;
        }
        if (!isset($existingPreferences[$repositoryInterface])) {
            $newPreferences[$repositoryInterface] = $repositoryClass;
        }
        $newPreferencesString = '';
        foreach ($newPreferences as $interface => $type) {
            $newPreferencesString .= "<preference for=\"$interface\" type=\"$type\"/>\n";
        }
        // we add the new preferences in the di.xml file
        $diXmlContent = str_replace('</config>', $newPreferencesString . "\n</config>", $diXmlContent);
        $this->ioFile->write($diXmlPath, $diXmlContent);
    }
}
