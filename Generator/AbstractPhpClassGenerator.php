<?php

namespace Opengento\MakegentoCli\Generator;

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
    ) {
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

        foreach ($properties as $property => $type) {
            $phpType = $this->getPhpTypeFromEntityType($type);
            $propertiesPart .= "    private $phpType \$$property;\n";
        }

        $methodsPart = "";
        foreach ($methods as $methodName => $method) {
            $methodBody = $method['body'];
            $methodVisibility = $method['visibility'];
            $methodsPart .= "    $methodVisibility function $methodName() {\n";
            $methodsPart .= "        $methodBody\n";
            $methodsPart .= "    }\n\n";
        }

        return "<?php\n\n" .
            $namespacePart .
            "\n" .
            "class $className $extendsImplementPart{\n" .
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
            $methodVisibility = $method['visibility'];
            $methodsPart .= "    $methodVisibility function $methodName();\n";
        }

        return "<?php\n\n" .
            $namespacePart .
            "interface $className $extendsPart {\n" .
            $propertiesPart .
            "\n" .
            $methodsPart .
            "}\n";
    }

    protected function getPhpTypeFromEntityType(string $entityType): string
    {
        return $this->typeMapping[$entityType];
    }
}
