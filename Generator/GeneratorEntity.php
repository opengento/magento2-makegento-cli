<?php

namespace Opengento\MakegentoCli\Generator;

class GeneratorEntity extends Generator
{
    public function generateDbSchema($definition, $outputPath): void
    {
        $this->generateWithInstructions(
            $this->reader->getModuleDir(null, self::OPENGENTO_MAKEGENTO_CLI) . '/Generator/templates/etc/db_schema.xml.tpl',
            $definition,
            $outputPath
        );
    }
}
