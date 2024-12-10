<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Filesystem\Io\File;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

class GeneratorModel extends AbstractPhpClassGenerator
{

    public function __construct(
        StringTransformationTools $stringTransformationTools,
        protected readonly File $ioFile,
    )
    {
        parent::__construct($stringTransformationTools);
    }

    /**
     * Generate a model interface
     *
     * @param string $modulePath
     * @param string $modelClassName
     * @param array $properties
     * @return string
     * @throws ExistingClassException
     */
    public function generateModelInterface(string $modulePath, string $modelClassName, array $properties, string $namespace = ''): string
    {
        $newFilePath = $modulePath . '/Api/Data/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'Interface.php';

        $namespace = $this->getNamespace($modulePath, '/Api/Data', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Interface already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . 'Interface');
        }

        $interfaceContent = $this->generatePhpInterface(
            $modelClassName . 'Interface',
            $namespace,
            $this->initInterfaceConst($properties),
            $this->initGetterSetter($properties)
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $interfaceContent);

        return '\\' . $namespace . '\\' . $modelClassName . 'Interface';
    }

    /**
     * Generate a model class for the model interface
     *
     * @param string $modulePath
     * @param string $modelClassName
     * @param array $properties
     * @param string $interface
     * @return string
     * @throws ExistingClassException
     */
    public function generateModel(string $modulePath, string $modelClassName, array $properties, string $interface, string $namespace = ''): string
    {

        $newFilePath = $modulePath . '/Model/';
        $newFilePathWithName = $newFilePath . $modelClassName . '.php';

        $namespace = $this->getNamespace($modulePath, '/Model', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Model already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName);
        }

        $classContent = $this->generatePhpClass(
            $modelClassName,
            $namespace,
            [],
            $this->initGetterSetter($properties),
            '\Magento\Framework\Model\AbstractModel',
            [$interface]
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $classContent);

        return '\\' . $namespace . '\\' . $modelClassName;
    }

    /**
     * Generate a resource model class for the model
     *
     * @param string $modulePath
     * @param string $modelClassName
     * @param string $modelClassInterface
     * @param string $tableName
     * @return string
     * @throws ExistingClassException
     */
    public function generateResourceModel(string $modulePath, string $modelClassName, string $modelClassInterface, string $tableName, string $namespace = ''): string
    {
        $newFilePath = $modulePath . '/Model/ResourceModel/';
        $newFilePathWithName = $newFilePath . $modelClassName . '.php';

        $namespace = $this->getNamespace($modulePath, '/Model/ResourceModel', $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Resource model already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName);
        }

        $resourceModelConstructor['_construct'] = [
            'body' => "\$this->_init('{$tableName}', {$modelClassInterface}::ID);",
            'visibility' => 'protected'
        ];

        $classContent = $this->generatePhpClass(
            $modelClassName,
            $namespace,
            [],
            $resourceModelConstructor,
            '\Magento\Framework\Model\ResourceModel\Db\AbstractDb'
        );

        if (!$this->ioFile->fileExists($newFilePath, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $classContent);

        return '\\' . $namespace . '\\' . $modelClassName;
    }

    /**
     * Generate a collection class for the model and the resource model
     *
     * @param string $modulePath
     * @param string $modelClassName
     * @param string $modelClass
     * @param string $resourceModelClass
     * @param string $namespace
     * @return string
     * @throws ExistingClassException
     */
    public function generateCollection(string $modulePath, string $modelClassName, string $modelClass, string $resourceModelClass, string $namespace = ''): string
    {
        $newFilePath = $modulePath . '/Model/ResourceModel/' . $modelClassName . '/';
        $newFilePathWithName = $newFilePath . 'Collection.php';

        $namespace = $this->getNamespace($modulePath, '/Model/ResourceModel/' . $modelClassName, $namespace);

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new ExistingClassException('Collection already exists', $newFilePathWithName, '\\' . $namespace . '\\' . $modelClassName . '\\Collection');
        }

        $collectionConstructor['_construct'] = [
            'body' => "\$this->_init($modelClass::class, $resourceModelClass::class);",
            'visibility' => 'protected'
        ];

        $classContent = $this->generatePhpClass(
            'Collection',
            $namespace,
            [],
            $collectionConstructor,
            '\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection'
        );

        if (!$this->ioFile->fileExists($newFilePathWithName, false)) {
            $this->ioFile->mkdir($newFilePath, 0755);
        }

        $this->ioFile->write($newFilePathWithName, $classContent);

        return '\\' . $namespace . '\\' . $modelClassName . '\\Collection';
    }

    /**
     * Loops through the properties and creates the getter and setter methods
     *
     * @param $properties
     * @return array
     */
    private function initGetterSetter($properties): array
    {
        $methods = [];
        foreach ($properties as $property => $definition) {
            $type = $this->getPhpTypeFromEntityType($definition['type']);
            $constName = $this->getConstName($property, $definition);
            $methods["get" . $this->stringTransformationTools->getPascalCase($property)] = [
                'arguments' => [],
                'body' => "return \$this->getData(self::$constName);",
                'visibility' => 'public'
            ];
            $methods["set" . $this->stringTransformationTools->getPascalCase($property)] = [
                'arguments' => ["$type \$$property"],
                'body' => "\$this->setData(self::$constName, \$$property);",
                'visibility' => 'public'
            ];
        }

        return $methods;
    }

    /**
     * Loops through the properties and creates the constants for the interface
     *
     * @param array $properties
     * @return array
     */
    private function initInterfaceConst(array $properties): array
    {
        $methods = [];
        foreach ($properties as $property => $definition) {
            $constName = $this->getConstName($property, $definition);
            $methods[$constName] = $property;
        }

        return $methods;
    }

    /**
     * Get the constant name for the property
     *
     * @param string $property
     * @param array $definition
     * @return string
     */
    private function getConstName(string $property, array $definition): string
    {
        if (!empty($definition['identity'])) {
            return 'ID';
        }
        return strtoupper($this->stringTransformationTools->getSnakeCase($property));
    }

    /**
     * Strip the module path from everything before app/code/ or vendor/ to return the namespace
     *
     * @param string $modulePath
     * @param string $path
     * @param string $namespace
     * @return string
     */
    private function getNamespace(string $modulePath, string $path, string $namespace = ''): string
    {
        if (empty($namespace)) {
            $namespace = $this->getNamespaceFromPath($modulePath);
        }
        return str_replace('/', '\\', $namespace . $path);
    }

    /**
     * Get the namespace from the path
     *
     * @param string $modulePath
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getNamespaceFromPath(string $modulePath): string
    {
        if (str_contains($modulePath, 'app/code')) {
            $namespace = preg_replace('~.*app/code/~', '', $modulePath);
        } elseif (str_contains($modulePath, 'vendor')) {
            $namespace = preg_replace('~.*vendor/~', '', $modulePath);
        } else {
            throw new \InvalidArgumentException('Module path is not in app/code or vendor');
        }
        return $namespace;
    }
}
