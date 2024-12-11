<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Filesystem\Io\File;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

class AbstractPhpClassGenerator
{
    private array $typeMapping = [
        'int' => 'int',
        'smallint' => 'int',
        'varchar' => 'string',
        'boolean' => 'bool',
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'float' => 'float',
        'blob' => 'string',
        'decimal' => 'float',
        'json' => 'array',
        'real' => 'float',
        'text' => 'string',
        'varbinary' => 'string',
    ];

    public function __construct(
        protected readonly StringTransformationTools $stringTransformationTools,
        protected readonly File $ioFile,
    )
    {
    }

    /**
     * Generates a PHP class.
     * Imports have been avoided to keep references safe.
     *
     * @param string $className
     * @param string|null $namespace
     * @param array $properties
     * @param array $methods
     * @param string|null $extends
     * @param array|null $implements
     * @return string
     */
    protected function generatePhpClass(
        string $className,
        string $namespace = null,
        array $properties = [],
        array $methods = [],
        string $extends = null,
        array $implements = null
    ): string
    {
        $namespacePart = $namespace ? "namespace $namespace;\n\n" : "";

        $extendsImplementPart = "";
        if ($extends) {
            $extendsImplementPart .= " extends $extends";
        }

        if ($implements) {
            $implements = implode(', ', $implements);
            $extendsImplementPart .= " implements $implements";
        }


        $propertiesPart = "";

        if ($extends === '\Magento\Framework\Model\AbstractModel') {
            $propertiesPart .= "    protected \$_idFieldName = self::ID;\n\n";
        }

        foreach ($properties as $property => $definition) {
            $type = $definition['type'];
            $phpType = $this->getPhpTypeFromEntityType($type);
            $propertiesPart .= "    private $phpType \$$property;\n";
        }

        $methodsPart = "";
        foreach ($methods as $methodName => $method) {
            $methodBody = $method['body'];
            if (is_array($methodBody)) {
                $methodBody = implode("\n", $methodBody);
            }
            $methodVisibility = $method['visibility'];
            $methodArguments = $method['arguments'] ?? [];
            $separator = count($methodArguments) > 3 ? ",\n        " : ', ';
            $methodArgumentsString = implode($separator, $methodArguments);
            if (count($methodArguments) > 3) {
                $methodArgumentsString = "\n        " . $methodArgumentsString . "\n    ";
            }
            $methodsPart .= "    $methodVisibility function $methodName($methodArgumentsString) {\n";
            $methodsPart .= "        $methodBody\n";
            $methodsPart .= "    }\n\n";
        }

        return "<?php\n\n" .
            $namespacePart .
            "\n" .
            "class $className $extendsImplementPart\n" .
            "{\n" .
            $propertiesPart .
            "\n" .
            "\n" .
            $methodsPart .
            "}\n";
    }


    /**
     * Generate a PHP interface
     *
     * @param string $className
     * @param string|null $namespace
     * @param array $constants
     * @param array $methods
     * @param array $extends
     * @return string
     */
    protected function generatePhpInterface(string $className, string $namespace = null, array $constants = [], array $methods = [], array $extends = []) {
        $namespacePart = $namespace ? "namespace $namespace;\n\n" : "";

        $extendsPart = "";
        if ($extends) {
            $extends = implode(', ', $extends);
            $extendsPart = " extends $extends";
        }

        $propertiesPart = "";
        foreach ($constants as $constName => $name) {
            $propertiesPart .= "    const $constName = '$name';\n";
        }

        $methodsPart = "";
        foreach ($methods as $methodName => $method) {
            if ($methodName === '__construct') {
                continue;
            }
            $methodVisibility = $method['visibility'];
            $methodArguments = isset($method['arguments']) ? implode(', ', $method['arguments']) : '';
            $methodsPart .= "    $methodVisibility function $methodName($methodArguments);\n";
        }

        return "<?php\n\n" .
            $namespacePart .
            "interface $className $extendsPart\n" .
            "{\n" .
            $propertiesPart .
            "\n" .
            $methodsPart .
            "}\n";
    }

    protected function getPhpTypeFromEntityType(string $entityType): string
    {
        return $this->typeMapping[$entityType];
    }

    /**
     * Strip the module path from everything before app/code/ or vendor/ to return the namespace
     *
     * @param string $modulePath
     * @param string $path
     * @param string $namespace
     * @return string
     */
    protected function getNamespace(string $modulePath, string $path, string $namespace = ''): string
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
