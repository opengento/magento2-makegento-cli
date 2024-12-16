<?php

namespace Opengento\MakegentoCli\Service\Php;

class ClassGenerator
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

    /**
     * Generates a PHP class.
     * Imports have been avoided to keep references safe.
     *
     * @param string $className
     * @param string|null $namespace
     * @param array $constants
     * @param array $properties
     * @param array $methods
     * @param string|null $extends
     * @param array|null $implements
     * @return string
     */
    public function generate(
        string $className,
        string $namespace = null,
        array $constants = [],
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

        $constantsPart = "";

        foreach ($constants as $constName => $name) {
            $constantsPart .= "    const $constName = '$name';\n";
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
            $constantsPart .
            "\n" .
            $propertiesPart .
            "\n" .
            "\n" .
            $methodsPart .
            "}\n";
    }

    public function getPhpTypeFromEntityType(string $entityType): string
    {
        return $this->typeMapping[$entityType];
    }
}
