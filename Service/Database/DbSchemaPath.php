<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Exception\TableDefinitionException;
use Opengento\MakegentoCli\Service\CurrentModule;

class DbSchemaPath
{


    public function __construct(
        private readonly \Magento\Framework\Filesystem $filesystem,
        private readonly CurrentModule           $currentModule
    )
    {
    }

    /**
     * Returns the path of the db_schema.xml file of a module. If the file does not exist, it will throw a FileSystemException.
     *
     * @param string $selectedModule
     * @return string
     * @throws TableDefinitionException
     */
    public function get(string $selectedModule): string
    {
        $modulePath = $this->currentModule->getModulePath();
        if (!$this->filesystem->getDirectoryReadByPath($modulePath)->isExist('etc/db_schema.xml')) {
            throw new TableDefinitionException(__('No db_schema.xml found in module ' . $selectedModule));
        }
        return $modulePath . '/etc/db_schema.xml';
    }
}
