<?php
/**
 * Opengento_MakegentoCli.
 *
 * @package   Opengento_MakegentoCli
 */

namespace Opengento\MakegentoCli\Helper;

use Magento\Framework\ObjectManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use phpDocumentor\Reflection\Types\ClassString;
use Symfony\Component\Console\Exception\LogicException;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Helper\Context;

/**
 * Helper class.
 */
class ScriptAskAnswer
{
    private array $_listActions = [
        'rewrite',
        'new-module',
        'controller',
        'helper',
        'model',
        'repository',
        'dbschema',
        'command',
        'plugin',
        'observer',
        'cron',
        'logger',
        'webapi',
        'create-view',
        'email',
        'unit-test',
    ];

    private string $_action = 'module';
    private int $_step = 0;
    private array $_patterns = [
        'module' => '~[A-Z][a-zA-Z]*_[A-Z][a-zA-Z]*~',
    ];

    private array $_stepList = [
        'module' => [
            'Which action name do you want to perform?',
            'Which module (Name) do you want to work on?' . PHP_EOL,
        ],
        'new-module' => [
            'What\'s the new module name?' . PHP_EOL,
        ],
        'controller' => [
            'What\'s the controller name?' . PHP_EOL,
            'Which area (frontend|adminhtml)?' . PHP_EOL,
            'What\'s the path for the controller (relative to controller folder)?' . PHP_EOL,
            'What\'s the route name?'
        ],
        'helper' => [
            'What\'s the helper name?' . PHP_EOL,
        ],
        'model' => [
            'What\'s the model classname?' . PHP_EOL,
            'On which table the model is based on?' . PHP_EOL,
        ],
        'repository' => [
            'What\'s the repository classname?' . PHP_EOL,
            'What\'s the model class namespace?' . PHP_EOL,
            'What\'s the collection class namespace?' . PHP_EOL,
        ],
        'dbschema' => [
            'What\'s the table name?' . PHP_EOL,
            'What\'s name of the futur or link model?' . PHP_EOL,
            'What\'s the table\'s primary key name?' . PHP_EOL,
            'Which columns are on the table ? <comment>(you can use the command myam:gc_tools -c)</comment>?' . PHP_EOL,
            'Which index are on the table ? <comment>(you can use the command myam:gc_tools -i)</comment>' . PHP_EOL,
            'Which constraints unique are on the table ? <comment>(you can use the command myam:gc_tools -ct)</comment>' . PHP_EOL,
            'Witch foreign keys are on the table ? <comment>(you can use the command myam:gc_tools -f)</comment>' . PHP_EOL,
        ],
        'command' => [
            'What\'s the command classname?' . PHP_EOL,
            'What\'s the command name(eg. module:action)?' . PHP_EOL,
        ],
        'plugin' => [
            'Vendor OR App/code ?' . PHP_EOL,
            'What\'s the plugin name?' . PHP_EOL,
            'In witch module can i find the class to override' . PHP_EOL,
            'Which kind of class are you rewriting ?' . PHP_EOL,
            'Which class do you want to plug (namespace)?' . PHP_EOL,
            'Which area (frontend|adminhtml|all)' . PHP_EOL,
        ],
        'observer' => [
            'What\'s the observer name?' . PHP_EOL,
            'What\s the event name?' . PHP_EOL,
            'Which area (frontend|adminhtml|all)' . PHP_EOL,
        ],
        'cron' => [
            'What\'s the cron classname?' . PHP_EOL,
            'What\s the schedule(default0 1 * * *)?' . PHP_EOL,
        ],
        'logger' => [
            'What\'s the loggerfile name(default name is vendorname_modulename.log)?' . PHP_EOL,
        ],
        'rewrite' => [
            'Vendor OR App/code ?' . PHP_EOL,
            'What\'s the classname?' . PHP_EOL,
            'In witch module can i find the class to override' . PHP_EOL,
            'Which kind of class are you rewriting ?' . PHP_EOL,
            'What\'s namespace of overriden class?' . PHP_EOL,
            'Where do you want place the override (relative to module folder)' . PHP_EOL,
        ],
        'webapi' => [
            'What\'s the namespace of the repoInterface?' . PHP_EOL,
            'What\'s the root url for the API?' . PHP_EOL,
        ],
        'create-view' => [
            'What\'s the route controller name action?' . PHP_EOL,
            'What\'s area (frontend|adminhtml?' . PHP_EOL,
            'What\'s the block classname (default Main)?' . PHP_EOL,
            'What\'s the template name (default content.phtml)?' . PHP_EOL,
            'What\'s the layout type (optionnal, possible value : empty|1column|2columns-left|2columns-right|...)?' . PHP_EOL,
        ],
        'email' => [
            'What\'s the email name' . PHP_EOL,
            'What\'s email id (default module_name_email_label)' . PHP_EOL,
            'What\'s the template file name' . PHP_EOL,
        ],
        'unit-test' => [],
    ];

    private array $_isCacheList = [
        'dbschema' => [
            3 => 'column',
            4 => 'index',
            5 => 'constraint_u',
            6 => 'constraint_fk'
        ]
    ];

    private array $_choiceList = [
        'controller' => [
            1 => ['frontend', 'adminhtml'],
        ],
        'plugin' => [
            0 => ['vendor', 'app/code'],
            2 => '_contextModuleList',
            3 => ['Controller', 'Interface', 'Repository', 'DataProvider', 'Collection', 'Model', 'Others'],
            4 => 'classPath',
            5 => ['frontend', 'adminhtml', 'all'],
        ],
        'rewrite' => [
            0 => ['vendor', 'app/code'],
            2 => '_contextModuleList',
            3 => ['Controller', 'Interface', 'Repository', 'DataProvider', 'Collection', 'Model', 'Others'],
            4 => 'classPath',
        ],
        'observer' => [
            2 => ['frontend', 'adminhtml', 'all'],
        ],
        'create-view' => [
            1 => ['frontend', 'adminhtml',],
            4 => ['empty', '1column', '2columns-left', '2columns-right', '3columns',
                'admin-empty', 'admin-1column', 'admin-2columns-left'],
        ],
        'repository' => [
            1 => 'modelPath',
            2 => 'collectionPath',
        ],
        'webapi' => [
            0 => 'repositoryInterfacePath',
        ],
    ];

    private array $_answer = [
        'module' => [
            ['key' => 'type', 'val' => ''],
            ['key' => 'module_name', 'val' => ''],
        ],
        'new-module' => [
            ['key' => 'name', 'val' => ''],
        ],
        'controller' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'area', 'val' => ''],
            ['key' => 'path', 'val' => ''],
            ['key' => 'router', 'val' => ''],
        ],
        'helper' => [
            ['key' => 'name', 'val' => ''],
        ],
        'model' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'table', 'val' => ''],
        ],
        'repository' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'model-class', 'val' => ''],
            ['key' => 'collection-class', 'val' => ''],
        ],
        'dbschema' => [
            ['key' => 'table-name', 'val' => ''],
            ['key' => 'name', 'val' => ''],
            ['key' => 'pk-name', 'val' => ''],
            ['key' => 'columns', 'val' => ''],
            ['key' => 'indexes', 'val' => ''],
            ['key' => 'unique', 'val' => ''],
            ['key' => 'foreign-key', 'val' => ''],
        ],
        'command' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'command', 'val' => ''],
        ],
        'plugin' => [
            ['key' => 'location', 'val' => '', 'exclude' => true],
            ['key' => 'name', 'val' => ''],
            ['key' => 'module_name', 'val' => '', 'exclude' => true],
            ['key' => 'type', 'val' => '', 'exclude' => true],
            ['key' => 'plugin', 'val' => ''],
            ['key' => 'area', 'val' => ''],
        ],
        'observer' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'event', 'val' => ''],
            ['key' => 'area', 'val' => ''],
        ],
        'cron' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'schedule', 'val' => ''],
        ],
        'logger' => [
            ['key' => 'name', 'val' => ''],
        ],
        'rewrite' => [
            ['key' => 'location', 'val' => '', 'exclude' => true],
            ['key' => 'name', 'val' => ''],
            ['key' => 'module_name', 'val' => '', 'exclude' => true],
            ['key' => 'type', 'val' => '', 'exclude' => true],
            ['key' => 'rewrite', 'val' => ''],
            ['key' => 'path', 'val' => ''],
        ],
        'webapi' => [
            ['key' => 'class', 'val' => ''],
            ['key' => 'url', 'val' => ''],
        ],
        'create-view' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'area', 'val' => ''],
            ['key' => 'block-class', 'val' => ''],
            ['key' => 'template', 'val' => ''],
            ['key' => 'layout-type', 'val' => ''],
        ],
        'email' => [
            ['key' => 'name', 'val' => ''],
            ['key' => 'id', 'val' => ''],
            ['key' => 'template', 'val' => ''],
        ],
        'unit-test' => [],
    ];

    public array $modelPath = [];
    public array $collectionPath = [];
    public array $repositoryInterfacePath = [];
    public array $classPath = [];
    public array $_contextModuleList = [];
    private array $_vendorModules = [];
    private array $_appcodeModules = [];

    public function __construct(
        public CustomerSession $_customerSession,
        public Context $_context,
        public \Magento\Store\Model\StoreManagerInterface $_storeManager,
        public ObjectManagerInterface $_objectManager,
        public ModuleListInterface $_moduleList,
        public Reader $_moduleDir
    ) {

        parent::__construct($_context);
    }

    public function loadNamespaces() {
        if ((isset($this->getAnswer()[2]) && $this->getAnswer()[2]['key'] == 'module_name') || (isset($this->getAnswer()[0]) && $this->getAnswer()[0]['key'] == 'location')) {
            switch (($this->getAnswer()[2]['key'] == 'module_name' || $this->getAnswer()[0]['key'] == 'location') ? $this->getAnswer()[0]['val'] : '') {
                case 'vendor':
                    $this->loadNamespacesVendor();
                    break;
                case 'app/code':
                    $this->loadNamespacesAppCode();
                    break;
            }
        }
    }

    private function loadNamespacesAppCode() {
        $this->_contextModuleList = $this->_appcodeModules;
        if ($this->getAnswer()[2]['key'] == 'module_name' && $this->getAnswer()[2]['val'] != '') {
            $this->folderMap($this->_moduleDir->getModuleDir(null, $this->getAnswer()[2]['val']));
            $this->classPath[] = 'Not Found';
        }
        $namespace = str_replace('_', '\\', $this->getAnswer('module')[1]['val']);
        $storeManager = $this->_objectManager->get(\Magento\Framework\Filesystem\DirectoryList::class);

        $modelPath = scandir($storeManager->getRoot() . '/app/code/' . str_replace('_', '/', $this->getAnswer('module')[1]['val']) . '/Model/');
        $modelNamespace = $namespace . '\\Model';
        foreach ($modelPath as $path) {
            if (stripos($path, '.php') && stripos($path, 'repository') === false) {
                $this->modelPath[] = $modelNamespace . '\\' . str_replace('.php', '', $path);
            }
        }

        $collectionNamespace = $namespace . '\\Model\\ResourceModel\\';
        $modelPath = scandir($storeManager->getRoot() . '/app/code/' . str_replace('_', '/', $this->getAnswer('module')[1]['val']) . '/Model/ResourceModel/');
        foreach ($modelPath as $path) {
            if (stripos($path, '.') === false) {
                $this->collectionPath[] = $collectionNamespace . $path . '\\Collection';
            }
        }

        $repositoryInterfaceNamespace = $namespace . '\\Api\\';
        $modelPath = scandir($storeManager->getRoot() . '/app/code/' . str_replace('_', '/', $this->getAnswer('module')[1]['val']) . '/Api/');
        foreach ($modelPath as $path) {
            if (stripos($path, '.php') && stripos($path, 'repository')) {
                $this->repositoryInterfacePath[] = $repositoryInterfaceNamespace . $path . '';
            }
        }
    }

    private function loadNamespacesVendor() {
        $this->_contextModuleList = $this->_vendorModules;
        if ($this->getAnswer()[2]['key'] == 'module_name' && $this->getAnswer()[2]['val'] != '') {
            $this->folderMap($this->_modulesDir->getModuleDir(null, $this->getAnswer()[2]['val']));
            if (count($this->classPath) <= 0) {
                $this->classPath[] = 'Not Found';
            }
        }
    }

    private function folderMap($path) {
        $directories = scandir($path);
        $storeManager = $this->_objectManager->get(\Magento\Framework\Filesystem\DirectoryList::class);
        $rootpath = $storeManager->getRoot() . '/vendor/';
        foreach ($directories as $file) {
            if (is_dir($path . '/' . $file) && stripos($file, '.') === false) {
                $this->folderMap($path . '/' . $file);
            } elseif ($file != '.' && $file != '..' && stripos($file, '.php') !== false) {
                if ($this->getAnswer()[3]['val'] != 'Others') {
                    if (stripos($file, $this->getAnswer()[3]['val']) !== false)
                        $this->classPath[] = ucwords(str_replace([$rootpath, '-', '/', '.php'], ['', '\\', '\\', ''], $path . '/' . $file), '\\');
                } else {
                    $this->classPath[] = 'Not implemented yet, maybe for the next version';
                }
            }
        }
    }

    private function resetStep(): int {
        return $this->_step = 0;
    }

    public function getListAction(): array {
        return $this->_listActions;
    }

    public function getListStepsAction(): array {
        return $this->_stepList[$this->_action];
    }

    public function getChoicesList(): bool|array {
        if (isset($this->_choiceList[$this->_action]))
            if (isset($this->_choiceList[$this->_action][$this->_step]))
                return $this->_choiceList[$this->_action][$this->_step];
        return false;
    }

    public function getIsCached(): bool|array {
        if (isset($this->_isCacheList[$this->_action]))
            if (isset($this->_isCacheList[$this->_action][$this->_step]))
                return $this->_isCacheList[$this->_action][$this->_step];
        return false;
    }

    public function validatePattern(string $type, string $value): bool {
        return preg_match($this->_patterns[$type], $value) == true;
    }

    public function setStep(int $step): int {
        return $this->_step = $step;
    }

    public function getStep(): int {
        return $this->_step;
    }

    public function getAction(): string {
        return $this->_action;
    }

    public function setAction(string $action): string {
        $this->resetStep();
        if ($action == 'module' || in_array($action, $this->getListAction()))
            return $this->_action = $action;
        else
            throw new LogicException($action . ' is not implemented');
    }

    public function setAnswer(string $answer) {
        if (isset($this->_answer[$this->_action]))
            if (isset($this->_answer[$this->_action][$this->_step]))
                $this->_answer[$this->_action][$this->_step]['val'] = $answer;
        $this->_step++;
    }

    /**
     * @param string $action
     * @return array
     */
    public function getAnswer(string $action = null, bool $excluded = true): array {
        if ($action === null) {
            $action = $this->_action;
        }
        $tmpAnswer = $this->_answer[$action] ?? [];

        if (!$excluded) {
            $tmpAnswer = array_filter($tmpAnswer, fn($a) => !($a['exclude'] ?? false));
        }

        return $tmpAnswer;
    }

    /**
     * @return array|bool
     */
    public function getNextStep(): array|bool {
        if (!isset($this->_stepList[$this->_action])) {
            return false;
        }
        if (!isset($this->_stepList[$this->_action][$this->_step])) {
            $this->_step = 0;
            return false;
        }
        return [
            'question' => $this->_stepList[$this->_action][$this->_step],
            'choices' => $this->getChoicesList(),
            'isCached' => $this->getIsCached()
        ];
    }

    /**
     * @return array
     */
    public function getCustomModules(): array {
        $result = ['New_Module'];
        $modules = $this->_moduleList->getNames();
        foreach ($modules as $_module) {
            if ($this->isModuleOutputEnabled()) {
                $dir = $this->_moduleDir->getModuleDir(null, $_module);
                if (strpos($dir, 'app/code') !== false) {
                    $result[] = $_module;
                    $this->_appcodeModules[] = $_module;
                } else {
                    $this->_vendorModules[] = $_module;
                }
            }
        }

        return $result;
    }
}
