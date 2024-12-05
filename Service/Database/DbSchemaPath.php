<?php

namespace Opengento\MakegentoCli\Service\Database;

use Magento\Framework\Exception\FileSystemException;
use Opengento\MakegentoCli\Utils\ConsoleModuleSelector;

class DbSchemaPath
{


    public function __construct(
        private readonly ConsoleModuleSelector $moduleSelector,
        private readonly \Magento\Framework\Filesystem $filesystem
    )
    {
    }

    /**
     * Returns the path of the db_schema.xml file of a module. If the file does not exist, it will throw a FileSystemException.
     *
     * @param string $selectedModule
     * @return string
     * @throws FileSystemException
     */
    public function get(string $selectedModule): string
    {
        $modulePath = $this->moduleSelector->getModulePath($selectedModule);
        if (!$this->filesystem->getDirectoryReadByPath($modulePath)->isExist('etc/db_schema.xml')) {
            throw new FileSystemException(__('No db_schema.xml found in module ' . $selectedModule));
        }
        return $modulePath . '/etc/db_schema.xml';
    }
}
