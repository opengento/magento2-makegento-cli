<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Maker;

use Magento\Framework\Filesystem\Io\File;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Generator\GeneratorModel;
use Opengento\MakegentoCli\Generator\GeneratorRepository;
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
        private readonly GeneratorModel   $generatorModel,
        private readonly GeneratorRepository $generatorRepository,
        private readonly File $ioFile
    )
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $selectedModule
     * @param string $modulePath
     * @return void
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

        $modelSubFolder = ucfirst($this->questionHelper->ask($input, $output, new Question('Enter the subfolder <info>[default : none]</info>: ', '')));

        $properties = $tables[$tableName];

        $namespace = '';
        try {
            $this->generatorModel->getNamespaceFromPath($modulePath);
        } catch (\InvalidArgumentException $e) {
            $proposedNamespace = str_replace('_', '\\', $selectedModule).'\\';
            $output->writeln('<info>Namespace not found, please set it manually</info>');
            $namespaceQuestion = new Question('Enter the namespace <info>[default : ' . $proposedNamespace . '] : ', $proposedNamespace);
            $inputNamespace = $this->questionHelper->ask($input, $output, $namespaceQuestion);
            $namespace = $this->validateNamespaceInput($inputNamespace);
            if ($inputNamespace !== $namespace) {
                $output->writeln('<info>Namespace corrected to ' . $namespace . '</info>');
            }
        }

        $output->writeln('Start generating model for table ' . $tableName);
        try {
            $interface = $this->generatorModel->generateModelInterface($modulePath, $modelClassName, $properties['fields'], $namespace);
            $output->writeln('Interface '. $interface . ' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $interface = $e->getClassName();
        }

        try {
            $modelClass = $this->generatorModel->generateModel($modulePath, $modelClassName, $properties['fields'], $interface, $namespace, $modelSubFolder);
            $output->writeln('Model '. $modelClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $modelClass = $e->getClassName();
        }

        try {
            $resourceClass = $this->generatorModel->generateResourceModel($modulePath, $modelClassName, $interface, $tableName, $namespace, $modelSubFolder);
            $output->writeln('Resource Model '. $resourceClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $resourceClass = $e->getClassName();
        }

        try {
            $collectionClass = $this->generatorModel->generateCollection($modulePath, $modelClassName, $modelClass, $resourceClass, $namespace, $modelSubFolder);
            $output->writeln('Collection '. $collectionClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $collectionClass = $e->getClassName();
        }

        try {
            $searchResultInterface = $this->generatorRepository->generateSearchCriteriaInterface($modulePath, $modelClassName, $namespace);
            $output->writeln('Repository '. $searchResultInterface .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $searchResultInterface = $e->getClassName();
        }

        try {
            $repositoryInterface = $this->generatorRepository->generateRepositoryInterface($modulePath, $modelClassName, $searchResultInterface, $resourceClass, $collectionClass, $interface, $namespace);
            $output->writeln('Repository '. $repositoryInterface .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $repositoryInterface = $e->getClassName();
        }

        try {
            $repositoryClass = $this->generatorRepository->generateRepository($modulePath, $modelClassName, $searchResultInterface, $resourceClass, $collectionClass, $interface, $repositoryInterface, $namespace);
            $output->writeln('Repository '. $repositoryClass .' generated');
        } catch (ExistingClassException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $repositoryClass = $e->getClassName();
        }
        $output->writeln('Generating preferences in di.xml file');
        $this->addPreferencesInDI($modulePath, $modelClass, $interface, $repositoryInterface, $repositoryClass);

        $output->writeln('<success>Model generation completed</success>');

    }

    /**
     * @param string $tableName
     * @param string $moduleName
     * @return string
     */
    private function getDefaultClassName(string $tableName, string $moduleName): string
    {
        $moduleNameParts = explode('_', $moduleName);

        foreach ($moduleNameParts as $part) {
            $tableName = str_replace(strtolower($part), '', $tableName);
            $tableName = str_replace(strtolower($part), '', $tableName);
        }

        $className = str_replace('_', ' ', $tableName);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);
        return $className;
    }

    /**
     * Removes spaces, replaces / by \, removes trailing \ and removes leading \
     *
     * @param string $namespace
     * @return string
     */
    private function validateNamespaceInput(string $namespace): string
    {
        $namespace = str_replace('/', '\\', $namespace);
        $namespace = str_replace(' ', '', $namespace);
        $namespace = trim($namespace, '\\');
        return $namespace;
    }

    private function addPreferencesInDI(
        string $modulePath,
        string $modelClassName,
        string $modelInterface,
        string $repositoryInterface,
        string $repositoryClass,
    ): void
    {
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
