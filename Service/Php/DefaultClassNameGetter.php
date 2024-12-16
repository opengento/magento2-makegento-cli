<?php

namespace Opengento\MakegentoCli\Service\Php;

class DefaultClassNameGetter
{
    /**
     * @param string $tableName
     * @param string $moduleName
     * @return string
     */
    public function get(string $tableName, string $moduleName): string
    {
        $moduleNameParts = explode('_', $moduleName);

        foreach ($moduleNameParts as $part) {
            $tableName = str_replace(strtolower($part), '', $tableName);
            $tableName = str_replace(strtolower($part), '', $tableName);
        }

        $className = str_replace('_', ' ', $tableName);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);
        return $className;
    }
}
