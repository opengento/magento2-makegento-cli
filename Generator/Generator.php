<?php

namespace Opengento\MakegentoCli\Generator;

class Generator
{

    public function __construct(private string $moduleName, private string $modulePath)
    {
    }

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function getModulePath(): string
    {
        return $this->modulePath;
    }
}
