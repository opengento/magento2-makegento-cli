<?php

namespace Opengento\MakegentoCli\Generator;

use Opengento\MakegentoCli\Exception\ExistingClassException;

class GeneratorRepository extends AbstractPhpClassGenerator
{

    /**
     * @param string $modulePath
     * @param string $modelClassName
     * @param $namespace
     * @return string
     * @throws ExistingClassException
     */
    public function generateSearchCriteriaInterface(string $modulePath, string $modelClassName, $namespace = ''): string
    {
        $newFilePath = $modulePath . '/Api/Data/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'SearchCriteriaInterface.php';

        $namespace = $this->getNamespace($modulePath, '/Api/Data', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Interface already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'SearchCriteriaInterface');
        }

        $interfaceContent = $this->generatePhpInterface(
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
        string $modulePath,
        string $modelClassName,
        string $searchCriteriaInterface,
        string $resourceClass,
        string $collectionClass,
        string $modelInterface,
        string $namespace = ''
    ): string
    {
        $newFilePath = $modulePath . '/Api/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'RepositoryInterface.php';

        $namespace = $this->getNamespace($modulePath, '/Api', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Interface already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'RepositoryInterface');
        }

        $interfaceContent = $this->generatePhpInterface(
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
     * @param string $modulePath
     * @param string $modelClassName
     * @param string $searchCriteriaInterface
     * @param string $resourceClass
     * @param string $collectionClass
     * @param string $modelInterface
     * @param string $repositoryInterface
     * @param string $namespace
     * @return string
     * @throws ExistingClassException
     */
    public function generateRepository(
        string $modulePath,
        string $modelClassName,
        string $searchCriteriaInterface,
        string $resourceClass,
        string $collectionClass,
        string $modelInterface,
        string $repositoryInterface,
        string $namespace = ''
    ): string
    {
        $newFilePath = $modulePath . '/Model/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'Repository.php';

        $namespace = $this->getNamespace($modulePath, '/Model', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Model already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'Repository');
        }

        $repositoryContent = $this->generatePhpClass(
            $modelClassName . 'Repository',
            $namespace,
            [],
            $this->getRepositoryMethods($modelClassName, $searchCriteriaInterface, $resourceClass, $collectionClass, $modelInterface, $namespace),
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
        string $modelInterface,
        string $namespace
    ): array
    {
        $modelFactoryClass = '\\' . $namespace . '\\' . $modelClassName . 'Factory';
        $collectionFactoryClass = $collectionClass.'Factory';
        $searchCriteriaInterfaceFactoryClass = $searchCriteriaInterface.'Factory';

        return [
            '__construct' => [
                'visibility' => 'public',
                'returnType' => 'void',
                'arguments' => [
                    "private readonly $resourceClass \$resource",
                    "private readonly $modelFactoryClass \${$this->getConstructorArgumentName($modelFactoryClass)}",
                    "private readonly $collectionFactoryClass \${$this->getConstructorArgumentName($collectionFactoryClass)}",
                    "private readonly $searchCriteriaInterfaceFactoryClass \${$this->getConstructorArgumentName($searchCriteriaInterfaceFactoryClass)}",
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
                    \$searchResults = \$this->{$this->getConstructorArgumentName($searchCriteriaInterfaceFactoryClass)}->create();
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
