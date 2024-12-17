<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\ObjectManagerInterface;
use Opengento\MakegentoCli\Service\CurrentModule;

class GeneratorUiComponent
{
    private string $modulePath = '';
    private string $resourceModel = '';
    private string $listingLayoutUiComponent = '';

    private string $route = '';

    private string $entityName = '';
    private string $dataProviderName = '';
    private string $gridCollectionName;

    private ?\Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null;

    public function __construct(
        private readonly File                   $ioFile,
        private readonly Reader                 $reader,
        private readonly ObjectManagerInterface $objectManager,
        private readonly CurrentModule          $currentModule
    )
    {
    }

    public function generateListing(string $entityName, string $listingLayoutUiComponent, string $route): void
    {
        $this->modulePath = $this->currentModule->getModulePath();
        $this->resourceModel = $entityName;
        $this->listingLayoutUiComponent = $listingLayoutUiComponent;
        $this->route = $route;
        $this->entityName = $entityName;
        $this->addVirtualTypes();
        $this->addUiComponent();
    }

    /**
     * @throws LocalizedException
     */
    private function getResource(): \Magento\Framework\Model\ResourceModel\Db\AbstractDb
    {
        if ($this->resource === null) {
            $modelFiles = glob($this->modulePath . '/Model/ResourceModel/*');
            foreach ($modelFiles as $modelFile) {
                $modelFileParts = explode('/', $modelFile);
                $modelFileName = end($modelFileParts);
                $modelFileNameParts = explode('.', $modelFileName);
                $modelFileName = reset($modelFileNameParts);
                if ($modelFileName === $this->resourceModel) {
                    $resourceModelClass = $this->currentModule->getModuleNamespace('/Model/ResourceModel') . '\\' . $modelFileName;
                    $this->resource = $this->objectManager->create($resourceModelClass);
                    break;
                }
            }
            if ($this->resource === null) {
                throw new LocalizedException(__('Resource model not found'));
            }
        }
        return $this->resource;
    }

    /**
     * Create virtual types for the UI component and write them in the di.xml file.
     *
     * @throws LocalizedException
     */
    private function addVirtualTypes(): void
    {
        $mainTable = $this->getResource()->getMainTable();
        $this->dataProviderName = $this->currentModule->getModuleNamespace() . '\Ui\DataProvider\\' . $this->resourceModel;
        $filterPoolName = $this->currentModule->getModuleNamespace() . '\Ui\FilterPool\\' . $this->resourceModel;
        $this->gridCollectionName = $this->currentModule->getModuleNamespace() . '\\' . $this->resourceModel . '\Grid\Collection';
        $virtualTypes = [
            "dataprovider" => [
                'name' => $this->dataProviderName,
                'type' => 'Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider',
                'arguments' => [
                    [
                        'name' => 'collection',
                        'type' => 'object',
                        'value' => $this->gridCollectionName
                    ],
                    [
                        'name' => 'filterPool',
                        'type' => 'object',
                        'value' => $filterPoolName
                    ]
                ]
            ],
            "filterpool" => [
                'name' => $filterPoolName,
                'type' => 'Magento\Framework\View\Element\UiComponent\DataProvider\FilterPool',
                'arguments' => [
                    [
                        'name' => 'appliers',
                        'type' => 'array',
                        'value' => [
                            'regular' => [
                                'name' => 'regular',
                                'type' => 'object',
                                'value' => 'Magento\Framework\View\Element\UiComponent\DataProvider\RegularFilter'
                            ],
                            'fulltext' => [
                                'name' => 'fulltext',
                                'type' => 'object',
                                'value' => 'Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter'
                            ]
                        ]
                    ]
                ]
            ],
            "collection" => [
                'name' => $this->gridCollectionName,
                'type' => 'Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult',
                'arguments' => [
                    [
                        'name' => 'mainTable',
                        'type' => 'string',
                        'value' => $mainTable
                    ],
                    [
                        'name' => 'resourceModel',
                        'type' => 'string',
                        'value' => $this->getResource()::class
                    ]
                ]
            ]
        ];
        $this->searchAndReplaceInDI($virtualTypes);

    }

    /**
     * Search and replace the virtual types in the di.xml file. If the virtual type already exists, we don't add it.
     * It also adds the grid collection name in the collection factory type.
     *
     * @param array $entries
     */
    private function searchAndReplaceInDI(array $entries): void
    {
        $path = $this->modulePath . '/etc/di.xml';
        $xmlContent = $this->ioFile->read($path);
        $additionalXml = '';
        $entriesToAdd = $this->filterEntries($entries, $xmlContent);

        foreach ($entriesToAdd as $entry) {
            $additionalXml .= '    <virtualType name="' . $entry['name'] . '" type="' . $entry['type'] . '">' . PHP_EOL;
            $additionalXml .= '        <arguments>' . PHP_EOL;
            foreach ($entry['arguments'] as $argument) {
                $additionalXml .= $this->getArgumentLine($argument);
            }
            $additionalXml .= '        </arguments>' . PHP_EOL;
            $additionalXml .= '    </virtualType>' . PHP_EOL;
        }

        if (!empty($additionalXml)) {
            $xmlContent = str_replace('</config>', $additionalXml . '</config>', $xmlContent);
        }

        $xmlContent = $this->addOrUpdateCollectionFactory($xmlContent);

        $this->ioFile->write($path, $xmlContent);
    }

    /**
     * Filter the entries to add only the ones that don't already exist in the di.xml file.
     */
    private function filterEntries(array $entries, string $xmlContent): array
    {
        $regex = '/<virtualType\s+(?:(?:name="(?P<name1>[^"]+)"\s+type="(?P<type1>[^"]+)")|(?:type="(?P<type2>[^"]+)"\s+name="(?P<name2>[^"]+)"))\s*>/i';

        $existingEntries = [];
        if (preg_match_all($regex, $xmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match['name1'] ?: $match['name2'];
                $type = $match['type1'] ?: $match['type2'];
                $existingEntries["{$name}_{$type}"] = true;
            }
        }

        return array_filter($entries, function ($entry) use ($existingEntries) {
            $key = "{$entry['name']}_{$entry['type']}";
            return !isset($existingEntries[$key]);
        });
    }

    /**
     * If the type already exists, we add the new item to the arguments array. Else, we create the type.
     */
    private function addOrUpdateCollectionFactory(string $xmlContent): string
    {
        $typeRegex = '/<type\s+name=[\'"]Magento\\\Framework\\\View\\\Element\\\UiComponent\\\DataProvider\\\CollectionFactory[\'"]\s*>.*?<\/type>/s';
        $itemRegex = '/<item\s+name=[\'"]' . preg_quote($this->listingLayoutUiComponent, '/') . '_data_source[\'"]\s+xsi:type=[\'"]string[\'"]\s*>.*?<\/item>/';

        $newItem = '                <item name="' . $this->listingLayoutUiComponent . '_data_source" xsi:type="string">'
            . $this->gridCollectionName . '</item>' . PHP_EOL;

        // If type already exists, we add the new item to the arguments array
        if (preg_match($typeRegex, $xmlContent, $typeMatch)) {
            $typeContent = $typeMatch[0];

            // Check if the item already exists, if not, we add it
            if (!preg_match($itemRegex, $typeContent)) {
                $updatedContent = $this->insertItemIntoType($typeContent, $newItem);
                $xmlContent = str_replace($typeMatch[0], $updatedContent, $xmlContent);
            }
        } else {
            // If the type doesn't exist, we create it
            $newType = $this->generateNewType($newItem);
            $xmlContent = str_replace('</config>', $newType . '</config>', $xmlContent);
        }

        return $xmlContent;
    }

    /**
     * Insert the new item into the existing type.
     */
    private function insertItemIntoType(string $typeContent, string $newItem): string
    {
        return preg_replace(
            '/<\/argument><\/arguments>\s*<\/type>/',
            $newItem . '</argument>' . PHP_EOL . '            </arguments>' . PHP_EOL . '        </type>',
            $typeContent
        );
    }

    /**
     * Generate the new type for the collection factory with the new item in the arguments array.
     */
    private function generateNewType(string $newItem): string
    {
        return '    <type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">' . PHP_EOL
            . '        <arguments>' . PHP_EOL
            . '            <argument name="collections" xsi:type="array">' . PHP_EOL
            . $newItem
            . '            </argument>' . PHP_EOL
            . '        </arguments>' . PHP_EOL
            . '    </type>' . PHP_EOL;
    }


    private function getArgumentLine(array $argument): string
    {
        if ($argument['type'] === 'array') {
            $value = '';
            foreach ($argument['value'] as $item) {
                $value .= '<item name="' . $item['name'] . '" xsi:type="' . $item['type'] . '">' . $item['value'] . '</item>' . PHP_EOL;
            }
            return '        <argument name="' . $argument['name'] . '" xsi:type="' . $argument['type'] . '">' . PHP_EOL . $value . '        </argument>' . PHP_EOL;
        }
        return '        <argument name="' . $argument['name'] . '" xsi:type="' . $argument['type'] . '">' . $argument['value'] . '</argument>' . PHP_EOL;
    }

    private function addUiComponent()
    {
        $headTemplatePath = $this->reader->getModuleDir(null, Generator::OPENGENTO_MAKEGENTO_CLI)
            . '/Generator/templates/view/adminhtml/ui_component/listing.xml.tpl';
        $template = $this->ioFile->read($headTemplatePath);

        // Let's replace listing by columns in the ui component name to get the columns name
        $columnsName = str_replace('listing', 'columns', $this->listingLayoutUiComponent);

        $fieldsToUpdate = [
            '{{ui_component_name}}',
            '{{buttons}}',
            '{{columns}}',
            '{{primary_field_name}}',
            '{{data_provider}}',
            '{{columns_name}}'
        ];

        $fieldsReplacement = [
            $this->listingLayoutUiComponent,
            $this->getButtons(),
            $this->getColumns(),
            $this->resource->getIdFieldName(),
            $this->dataProviderName,
            $columnsName
        ];

        $newFileContent = str_replace(
            $fieldsToUpdate,
            $fieldsReplacement,
            $template
        );

        $newFilePath = $this->modulePath . '/view/adminhtml/ui_component/' . $this->listingLayoutUiComponent . '.xml';
        if (!$this->ioFile->fileExists($newFilePath, false)) {
            $this->ioFile->mkdir($this->modulePath . '/view/adminhtml/ui_component', 0755);
        }
        $this->ioFile->write($newFilePath, $newFileContent);
    }

    /**
     * Get the buttons for the ui component It checks for existence of form related controller and adds the buttons
     *
     * @return string
     */
    private function getButtons(): string
    {
        $buttons = '';
        $controllerFolder = $this->modulePath . '/Controller/Adminhtml/' . $this->entityName;
        if ($this->ioFile->fileExists($controllerFolder . '/Create.php', false)) {
            $buttons = '
                <item name="buttons" xsi:type="array">
                    <item name="add" xsi:type="array">
                        <item name="name" xsi:type="string">add</item>
                        <item name="label" xsi:type="string" translate="true">Create new ' . strtolower($this->entityName) . '</item>
                        <item name="class" xsi:type="string">primary</item>
                        <item name="url" xsi:type="string">*/' . $this->route . '/create</item>
                    </item>
                </item>';
        }
        return $buttons;
    }

    /**
     * Get the columns for the ui component
     *
     * @return string
     * @throws LocalizedException
     */
    private function getColumns(): string
    {
        $mainTable = $this->getResource()->getMainTable();
        $tableColumns = $this->getResource()->getConnection()->describeTable($mainTable);
        $columns = '';
        foreach ($tableColumns as $column) {
            $dataType = $column['DATA_TYPE'];
            $class = '';
            $component = '';
            $columnType = 'text';
            if ($dataType === 'datetime' || $dataType === 'date') {
                $class = 'class="' . \Magento\Ui\Component\Listing\Columns\Date::class . '"';
                $component = 'component="Magento_Ui/js/grid/columns/date"';
                $columnType = 'date';
            }
            $columns .= '<column name="' . $column['COLUMN_NAME'] . '" ' . $class . ' ' . $component . '>
                <argument name="data" xsi:type="array">
                    <item name="config" xsi:type="array">
                        <item name="filter" xsi:type="string">text</item>
                        <item name="label" xsi:type="string" translate="true">' . $column['COLUMN_NAME'] . '</item>
                        <item name="dataType" xsi:type="string">' . $columnType . '</item>
                    </item>
                </argument>
            </column>' . PHP_EOL;
        }
        return $columns;
    }

}
