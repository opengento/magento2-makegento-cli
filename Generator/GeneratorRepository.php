<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Filesystem\Io\File;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Php\ClassGenerator;
use Opengento\MakegentoCli\Service\Php\InterfaceGenerator;
use Opengento\MakegentoCli\Service\Php\NamespaceGetter;

class GeneratorRepository
{
    public function __construct(
        private readonly ClassGenerator $classGenerator,
        private readonly InterfaceGenerator $interfaceGenerator,
        private readonly File $ioFile,
        private readonly NamespaceGetter $namespaceGetter,
        private readonly CurrentModule           $currentModule
    )
    {

    }

    /**
     * @param string $modulePath
     * @param string $modelClassName
     * @param $namespace
     * @return string
     * @throws ExistingClassException
     */
    public function generateSearchCriteriaInterface(string $modelClassName): string
    {
        $modulePath = $this->currentModule->getModulePath();
        $newFilePath = $modulePath . '/Api/Data/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'SearchCriteriaInterface.php';

        $namespace = $this->currentModule->getModuleNamespace('/Api/Data');

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Interface already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'SearchCriteriaInterface');
        }

        $interfaceContent = $this->interfaceGenerator->generate(
            $modelClassName . 'SearchCriteriaInterface',
            $namespace,
            [],
            [
                'setItems' => ['visibility' => 'public', 'returnType' => 'void', 'arguments' => ['array $items']],
                'getItems' => ['visibility' => 'public', 'returnType' => 'array']
            ]
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $interfaceContent);

        return '\\' . $namespace . '\\' . $modelClassName . 'SearchCriteriaInterface';
    }

    /**
     * @param string $modulePath
     * @param string $modelClassName
     * @param string $searchCriteriaInterface
     * @param string $resourceClass
     * @param string $collectionClass
     * @param string $modelInterface
     * @param string $namespace
     * @return string
     * @throws ExistingClassException
     */
    public function generateRepositoryInterface(
        string $modelClassName,
        string $searchCriteriaInterface,
        string $resourceClass,
        string $collectionClass,
        string $modelInterface
    ): string
    {
        $modulePath = $this->currentModule->getModulePath();
        $newFilePath = $modulePath . '/Api/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'RepositoryInterface.php';

        $namespace = $this->currentModule->getModuleNamespace( '/Api');

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Interface already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'RepositoryInterface');
        }

        $interfaceContent = $this->interfaceGenerator->generate(
            $modelClassName . 'RepositoryInterface',
            $namespace,
            [],
            $this->getRepositoryMethods($modelClassName, $searchCriteriaInterface, $resourceClass, $collectionClass, $modelInterface, $namespace)
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $interfaceContent);

        return '\\' . $namespace . '\\' . $modelClassName . 'RepositoryInterface';
    }

    /**
     * @param string $modelClassName
     * @param string $searchCriteriaInterface
     * @param string $resourceClass
     * @param string $collectionClass
     * @param string $modelInterface
     * @param string $repositoryInterface
     * @return string
     * @throws ExistingClassException
     */
    public function generateRepository(
        string $modelClassName,
        string $searchCriteriaInterface,
        string $resourceClass,
        string $collectionClass,
        string $modelInterface,
        string $repositoryInterface,
    ): string
    {
        $modulePath = $this->currentModule->getModulePath();
        $newFilePath = $modulePath . '/Model/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'Repository.php';

        $namespace = $this->currentModule->getModuleNamespace('/Model');

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Model already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'Repository');
        }

        $repositoryContent = $this->classGenerator->generate(
            $modelClassName . 'Repository',
            $namespace,
            [],
            [],
            $this->getRepositoryMethods($modelClassName, $searchCriteriaInterface, $resourceClass, $collectionClass, $modelInterface),
            '',
            [$repositoryInterface]
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $repositoryContent);

        return '\\' . $namespace . '\\' . $modelClassName . 'Repository';
    }

    private function getRepositoryMethods(
        string $modelClassName,
        string $searchCriteriaInterface,
        string $resourceClass,
        string $collectionClass,
        string $modelInterface
    ): array
    {
        $namespace = $this->currentModule->getModuleNamespace('');
        $modelFactoryClass = '\\' . $namespace . '\\Model\\' . $modelClassName . 'Factory';
        $collectionFactoryClass = $collectionClass.'Factory';

        return [
            '__construct' => [
                'visibility' => 'public',
                'returnType' => 'void',
                'arguments' => [
                    "private readonly $resourceClass \$resource",
                    "private readonly $modelFactoryClass \${$this->getConstructorArgumentName($modelFactoryClass)}",
                    "private readonly $collectionFactoryClass \${$this->getConstructorArgumentName($collectionFactoryClass)}",
                    "private readonly \Magento\Framework\Api\Search\SearchResultFactory \$searchResultFactory",
                    "private readonly \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface \$collectionProcessor"
                ],
                'body' => ''
            ],
            'save' => [
                'visibility' => 'public',
                'returnType' => 'void',
                'arguments' => [
                    "$modelInterface \${$this->getConstructorArgumentName($modelClassName)}"
                ],
                'body' =>
                    "try {
                        \$this->resource->save(\${$this->getConstructorArgumentName($modelClassName)});
                    } catch (\\Exception \$exception) {
                        throw new \\Magento\\Framework\\Exception\\CouldNotSaveException(__(\"Could not save the {$modelClassName}: %1\", \$exception->getMessage()));
                    }
                    return \${$this->getConstructorArgumentName($modelInterface)};"
            ],
            'getById' => [
                'visibility' => 'public',
                'returnType' => $modelInterface,
                'arguments' => [
                    '$id'
                ],
                'body' =>
                    "\${$this->getConstructorArgumentName($modelClassName)} = \$this->{$this->getConstructorArgumentName($modelFactoryClass)}->create();
                    \$this->resource->load(\${$this->getConstructorArgumentName($modelClassName)}, \$id);
                    if (!\${$this->getConstructorArgumentName($modelClassName)}->getId()) {
                        throw new \\Magento\\Framework\\Exception\\NoSuchEntityException(__(\"{$modelClassName} with id \\\"%1\\\" does not exist.\", \$id));
                    }
                    return \${$this->getConstructorArgumentName($modelClassName)};"
            ],
            'getList' => [
                'visibility' => 'public',
                'returnType' => '\Magento\Framework\Api\SearchResultsInterface',
                'arguments' => [
                    "\Magento\Framework\Api\SearchCriteriaInterface \$searchCriteria"
                ],
                'body' =>
                    "\$collection = \$this->{$this->getConstructorArgumentName($collectionFactoryClass)}->create();
                    \$this->collectionProcessor->process(\$searchCriteria, \$collection);
                    \$searchResults = \$this->searchResultFactory->create();
                    \$searchResults->setSearchCriteria(\$searchCriteria);
                    \$searchResults->setItems(\$collection->getItems());
                    \$searchResults->setTotalCount(\$collection->getSize());
                    return \$searchResults;"
            ],
            'delete' => [
                'visibility' => 'public',
                'returnType' => 'void',
                'arguments' => [
                    "$modelInterface \${$this->getConstructorArgumentName($modelClassName)}"
                ],
                'body' =>
                    "try {
                        \$this->resource->delete(\${$this->getConstructorArgumentName($modelClassName)});
                    } catch (\\Exception \$exception) {
                        throw new \\Magento\\Framework\\Exception\\CouldNotDeleteException(__(\"Could not delete the {$modelClassName}: %1\", \$exception->getMessage()));
                    }
                    return true;"
            ]
        ];
    }

    private function getConstructorArgumentName(string $className): string
    {
        $classNameParts = explode('\\', $className);
        $className = end($classNameParts);
        $className = str_replace('Interface', '', $className);
        return lcfirst($className);
    }
}
