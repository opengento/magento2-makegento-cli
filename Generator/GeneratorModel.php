<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Filesystem\Io\File;
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
     * @throws \Exception
     */
    public function generateModelInterface(string $modulePath, string $modelClassName, array $properties): string
    {
        $newFilePath = $modulePath . '/Api/Data/';
        $newFilePathWithName = $newFilePath . $modelClassName . 'Interface.php';

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new \Exception('Interface already exists');
        }

        $namespace = $this->getNamespace($modulePath, '/Api/Data');

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
     * @throws \Exception
     */
    public function generateModel(string $modulePath, string $modelClassName, array $properties, string $interface): string
    {

        $newFilePath = $modulePath . '/Model/';
        $newFilePathWithName = $newFilePath . $modelClassName . '.php';

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new \Exception('Model already exists');
        }

        $namespace = $this->getNamespace($modulePath, '/Model');

        $classContent = $this->generatePhpClass(
            $modelClassName,
            $namespace,
            $properties,
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
     * @throws \Exception
     */
    public function generateResourceModel(string $modulePath, string $modelClassName, string $modelClassInterface, string $tableName): string
    {
        $newFilePath = $modulePath . '/Model/ResourceModel/';
        $newFilePathWithName = $newFilePath . $modelClassName . '.php';

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new \Exception('Resource model already exists');
        }

        $namespace = $this->getNamespace($modulePath, '/Model/ResourceModel');

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
     * @param string $resourceModelClass
     * @return string
     * @throws \Exception
     */
    public function generateCollection(string $modulePath, string $modelClassName, string $modelClass, string $resourceModelClass): string
    {
        $newFilePath = $modulePath . '/Model/ResourceModel/' . $modelClassName . '/';
        $newFilePathWithName = $newFilePath . 'Collection.php';

        if ($this->ioFile->fileExists($newFilePathWithName)) {
            throw new \Exception('Collection already exists');
        }

        $namespace = $this->getNamespace($modulePath, '/Model/ResourceModel/' . $modelClassName);

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
        foreach ($properties as $property => $type) {
            $methods["get" . ucfirst($property)] = [
                'arguments' => [],
                'body' => "return \$this->$property;",
                'visibility' => 'public'
            ];
            $methods["set" . ucfirst($property)] = [
                'arguments' => ["$type \$$property"],
                'body' => "\$this->$property = \$$property;",
                'visibility' => 'public'
            ];
        }

        return $methods;
    }

    /**
     * Loops through the properties and creates the constants for the interface
     *
     * @param $properties
     * @return array
     */
    private function initInterfaceConst($properties): array
    {
        $methods = [];
        foreach ($properties as $property => $type) {
            $constName = strtoupper($this->stringTransformationTools->getSnakeCase($property));
            if (str_contains($property, 'ID')) {
                $constName = "ID";
            }
            $methods[$constName] = $property;
        }

        return $methods;
    }

    /**
     * Strip the module path from everything before app/code/ or vendor/ to return the namespace
     *
     * @param string $modulePath
     * @param string $path
     * @return string
     * @throws \Exception
     */
    private function getNamespace(string $modulePath, string $path): string
    {
        if (str_contains($modulePath, 'app/code')) {
            $namespace = preg_replace('~.*app/code/~', '', $modulePath);
        } elseif (str_contains($modulePath, 'vendor')) {
            $namespace = preg_replace('~.*vendor/~', '', $modulePath);
        } else {
            throw new \Exception('Module path is not in app/code or vendor');
        }
        return str_replace('/', '\\', $namespace . $path);
    }
}
