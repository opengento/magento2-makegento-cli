<?php

namespace Opengento\MakegentoCli\Service\Php;

class InterfaceGenerator
{

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
    public function generate(string $className, string $namespace = null, array $constants = [], array $methods = [], array $extends = []) {
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
}
