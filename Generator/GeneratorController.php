<?php

namespace Opengento\MakegentoCli\Generator;

use Magento\Framework\App\Area;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Opengento\MakegentoCli\Exception\ExistingClassException;
use Opengento\MakegentoCli\Service\CurrentModule;
use Opengento\MakegentoCli\Service\Php\ClassGenerator;
use Opengento\MakegentoCli\Utils\StringTransformationTools;

class GeneratorController extends Generator
{
    private ?string $entityName = null;

    public function __construct(
        File $ioFile,
        Filesystem $filesystem,
        Reader $reader,
        StringTransformationTools $stringTransformationTools,
        CurrentModule $currentModule,
        private readonly ClassGenerator $classGenerator
    )
    {
        parent::__construct($ioFile, $filesystem, $reader, $stringTransformationTools, $currentModule);
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = $this->stringTransformationTools->getPascalCase($entityName);
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return string
     * @throws ExistingClassException
     */
    public function generateListingController(): string
    {
        $modulePath = $this->currentModule->getModulePath();
        $controllerPath = mb_ucfirst(
            mb_strtolower($this->stringTransformationTools->getCamelCase($this->getEntityName()), 'UTF-8'),
            'UTF-8'
        );

        $filePath = $modulePath . '/Controller/' . mb_ucfirst(Area::AREA_ADMINHTML . '/' . $controllerPath, 'UTF-8');
        $filePathWithName = $filePath . '/Index.php';

        if ($this->ioFile->fileExists($filePath)) {
            throw new ExistingClassException("Controller already exists", strtolower($controllerPath).'/index');
        }

        $namespace = $this->currentModule->getModuleNamespace('/Controller/Adminhtml/' . $this->getEntityName());

        $listingControllerContent = $this->classGenerator->generate(
            'Index',
            $namespace,
            [
                "ADMIN_RESOURCE" => $this->currentModule->getModuleName() . '::view'
            ],
            [],
            [
                'execute' => [
                    'visibility' => 'public',
                    'arguments' => [],
                    'returnType' => \Magento\Framework\Controller\ResultInterface::class,
                    'body' => [
                        '$resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);'. PHP_EOL .
                        '$resultPage->setActiveMenu("' . $this->currentModule->getModuleName() . '::index");'. PHP_EOL .
                        'return $resultPage;'
                    ]
                ]
            ],
            '\\'.\Magento\Backend\App\Action::class
        );

        if (!$this->ioFile->fileExists($filePathWithName, false)) {
            $this->ioFile->mkdir($filePath, 0755);
        }

        $this->ioFile->write(
            $filePathWithName,
            $listingControllerContent
        );
        return strtolower($controllerPath).'/index';
    }

    /**
     * @return array
     */
    public function generateFormControllers(): array
    {
        $modulePath = $this->currentModule->getModulePath();
        $controllerPath = mb_ucfirst(
            mb_strtolower($this->stringTransformationTools->getCamelCase($this->getEntityName()), 'UTF-8'),
            'UTF-8'
        );
        $controllers = [];

        $filePath = $modulePath . '/Controller/' . mb_ucfirst(Area::AREA_ADMINHTML . '/' . $controllerPath, 'UTF-8');
        $filePathWithName = $filePath . '/Edit.php';

        $namespace = $this->currentModule->getModuleNamespace('/Controller/Adminhtml/' . $this->getEntityName());

        $repositoryInterface = $this->currentModule->getModuleNamespace( '\Api') . '\\' . $this->getEntityName() . 'RepositoryInterface';
        $factory = $this->currentModule->getModuleNamespace( '\Model') . '\\' . $this->getEntityName() . 'Factory';

        $repositoryVariableName = $this->getEntityNameVariable() . 'Repository';
        try {
            $this->checkConstructorVariable($repositoryVariableName, \Magento\Backend\App\Action::class);
        } catch (\InvalidArgumentException $e) {
            $repositoryVariableName = mb_lcfirst($this->currentModule->getModuleName()).$this->getEntityName().'Repository';
        }

        $factoryVariableName = $this->getEntityNameVariable() . 'Factory';
        try {
            $this->checkConstructorVariable($factoryVariableName, \Magento\Backend\App\Action::class);
        } catch (\InvalidArgumentException $e) {
            $factoryVariableName = mb_lcfirst($this->currentModule->getModuleName()).$this->getEntityName().'Factory';
        }

        // Edit Controller
        if ($this->ioFile->fileExists($filePath)) {
            $controllers['edit'] = $filePathWithName;
        } else {
            $editControllerContent = $this->classGenerator->generate(
                'Edit',
                $namespace,
                [
                    "ADMIN_RESOURCE" => $this->currentModule->getModuleName() . '::manage'
                ],
                [],
                [
                    '__construct' => [
                        'visibility' => 'public',
                        'arguments' => [
                            '\Magento\Backend\App\Action\Context $context',
                            'private readonly \\'. $repositoryInterface .' $' . $repositoryVariableName,
                            'private readonly \\'. $factory .' $' . $factoryVariableName
                        ],
                        'body' => [
                            'parent::__construct($context);'
                        ]
                    ],
                    'execute' => [
                        'visibility' => 'public',
                        'arguments' => [],
                        'returnType' => \Magento\Framework\Controller\ResultInterface::class,
                        'body' => [
                            '$id = $this->getRequest()->getParam("id");' . PHP_EOL .
                            '$resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);' . PHP_EOL .
                            'if ($id) {' . PHP_EOL .
                            '    $' . $this->getEntityNameVariable() . ' = $this->' . $repositoryVariableName . '->getById($id);' . PHP_EOL .
                            '    if (!$' . $this->getEntityNameVariable() . '->getId()) {' . PHP_EOL .
                            '        $this->messageManager->addErrorMessage(__("This '. strtolower($this->getEntityName()) .' no longer exists."));' . PHP_EOL .
                            '        $resultRedirect = $this->resultRedirectFactory->create();' . PHP_EOL .
                            '        return $resultRedirect->setPath("*/*/");' . PHP_EOL .
                            '    }' . PHP_EOL .
                            '    $resultPage->getConfig()->getTitle()->prepend(__("Edit '.$this->getEntityName().'"));' . PHP_EOL .
                            '} else {' . PHP_EOL .
                            '    $' . $this->getEntityNameVariable() . ' = $this->' . $factoryVariableName . '->create();' . PHP_EOL .
                            '    $resultPage->getConfig()->getTitle()->prepend(__("New '.$this->getEntityName().'"));' . PHP_EOL .
                            '}' . PHP_EOL .
                            '$resultPage->setActiveMenu("' . $this->currentModule->getModuleName() . '::index");' . PHP_EOL .
                            PHP_EOL .
                            'return $resultPage;'

                        ]
                    ]
                ],
                '\\'.\Magento\Backend\App\Action::class
            );

            if (!$this->ioFile->fileExists($filePathWithName, false)) {
                $this->ioFile->mkdir($filePath, 0755);
            }

            $this->ioFile->write(
                $filePathWithName,
                $editControllerContent
            );

            $controllers['edit'] = $filePathWithName;
        }

        // Create Controller
        $filePathWithName = $filePath . '/Create.php';

        if ($this->ioFile->fileExists($filePath)) {
            $controllers['create'] = $filePathWithName;
        } else {
            $createControllerContent = $this->classGenerator->generate(
                'Create',
                $namespace,
                [
                    "ADMIN_RESOURCE" => $this->currentModule->getModuleName() . '::manage'
                ],
                [],
                [
                    'execute' => [
                        'visibility' => 'public',
                        'arguments' => [],
                        'returnType' => \Magento\Framework\Controller\ResultInterface::class,
                        'body' => [
                            '$resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_FORWARD);'. PHP_EOL .
                            '$resultPage->forward("edit");'. PHP_EOL .
                            'return $resultPage;'
                        ]
                    ]
                ],
                '\\'.\Magento\Backend\App\Action::class
            );

            if (!$this->ioFile->fileExists($filePathWithName, false)) {
                $this->ioFile->mkdir($filePath, 0755);
            }

            $this->ioFile->write(
                $filePathWithName,
                $createControllerContent
            );
        }

        // Save Controller
        $filePathWithName = $filePath . '/Save.php';

        if ($this->ioFile->fileExists($filePath)) {
            $controllers['save'] = $filePathWithName;
        } else {
            $saveControllerContent = $this->classGenerator->generate(
                'Save',
                $namespace,
                [
                    "ADMIN_RESOURCE" => $this->currentModule->getModuleName() . '::manage'
                ],
                [],
                [
                    '__construct' => [
                        'visibility' => 'public',
                        'arguments' => [
                            '\Magento\Backend\App\Action\Context $context',
                            'private readonly \\'. $repositoryInterface .' $' . $repositoryVariableName,
                            'private readonly \\'. $factory .' $' . $factoryVariableName
                        ],
                        'body' => [
                            'parent::__construct($context);'
                        ]
                    ],
                    'execute' => [
                        'visibility' => 'public',
                        'arguments' => [],
                        'returnType' => \Magento\Framework\Controller\ResultInterface::class,
                        'body' => [
                            'try {' . PHP_EOL .
                            '$' . $this->getEntityNameVariable() . ' = $this->' . $factoryVariableName . '->create();' . PHP_EOL .
                            '$request = $this->_request->getParams();'. PHP_EOL .
                            '$' . $this->getEntityNameVariable() . '->setData($request);'. PHP_EOL .
                            '$this->' . $repositoryVariableName . '->save($' . $this->getEntityNameVariable() . ');'. PHP_EOL .
                            '$this->messageManager->addSuccessMessage(__("'.$this->getEntityName().' have been registered."));' . PHP_EOL .
                            '} catch (\Magento\Framework\Exception\LocalizedException $e) {' . PHP_EOL .
                            '$this->messageManager->addErrorMessage($e->getMessage());' . PHP_EOL .
                            '} catch (\Exception $e) {' . PHP_EOL .
                            '$this->messageManager->addExceptionMessage($e, __("Something went wrong while saving the '.$this->getEntityName().'."));' . PHP_EOL .
                            '}' . PHP_EOL .
                            'return $this->resultRedirectFactory->create()->setPath("*/*/index");'
                        ]
                    ]
                ],
                '\\'.\Magento\Backend\App\Action::class
            );

            if (!$this->ioFile->fileExists($filePathWithName, false)) {
                $this->ioFile->mkdir($filePath, 0755);
            }

            $this->ioFile->write(
                $filePathWithName,
                $saveControllerContent
            );
        }

        // Delete Controller
        $filePathWithName = $filePath . '/Delete.php';

        if ($this->ioFile->fileExists($filePath)) {
            $controllers['delete'] = $filePathWithName;
        } else {
            $deleteControllerContent = $this->classGenerator->generate(
                'Delete',
                $namespace,
                [
                    "ADMIN_RESOURCE" => $this->currentModule->getModuleName() . '::manage'
                ],
                [],
                [
                    '__construct' => [
                        'visibility' => 'public',
                        'arguments' => [
                            '\Magento\Backend\App\Action\Context $context',
                            'private readonly \\' . $repositoryInterface . ' $' . $repositoryVariableName
                        ],
                        'body' => [
                            'parent::__construct($context);'
                        ]
                    ],
                    'execute' => [
                        'visibility' => 'public',
                        'arguments' => [],
                        'returnType' => \Magento\Framework\Controller\ResultInterface::class,
                        'body' => [
                            '$id = $this->getRequest()->getParam("id");' . PHP_EOL .
                            'if ($id) {' . PHP_EOL .
                            '    try {' . PHP_EOL .
                            '        $' . $this->getEntityNameVariable() . ' = $this->' . $repositoryVariableName . '->getById($id);' . PHP_EOL .
                            '        $this->' . $repositoryVariableName . '->delete($' . $this->getEntityNameVariable() . ');' . PHP_EOL .
                            '        $this->messageManager->addSuccessMessage(__("' . $this->getEntityName() . ' have been deleted."));' . PHP_EOL .
                            '    } catch (\Magento\Framework\Exception\LocalizedException $e) {' . PHP_EOL .
                            '        $this->messageManager->addErrorMessage($e->getMessage());' . PHP_EOL .
                            '    } catch (\Exception $e) {' . PHP_EOL .
                            '        $this->messageManager->addExceptionMessage($e, __("Something went wrong while deleting the ' . $this->getEntityName() . '."));' . PHP_EOL .
                            '    }' . PHP_EOL .
                            '} else {' . PHP_EOL .
                            '    $this->messageManager->addErrorMessage(__("We can\'t find a ' . $this->getEntityName() . ' to delete."));' . PHP_EOL .
                            '}' . PHP_EOL .
                            'return $this->resultRedirectFactory->create()->setPath("*/*/index");'
                        ]
                    ]
                ],
                '\\'.\Magento\Backend\App\Action::class
            );

            if (!$this->ioFile->fileExists($filePathWithName, false)) {
                $this->ioFile->mkdir($filePath, 0755);
            }

            $this->ioFile->write(
                $filePathWithName,
                $deleteControllerContent
            );
        }


        return $controllers;
    }

    private function checkConstructorVariable(string $variable, string $parentClass): string
    {
        $parentClassReflection = new \ReflectionClass($parentClass);
        $parentClassProperties = $parentClassReflection->getProperties();
        if (!empty($parentClassProperties)) {
            foreach ($parentClassProperties as $property) {
                if ($property->getName() === $variable) {
                    throw new \InvalidArgumentException("Variable $variable already exists in parent class $parentClass");
                }
            }
        }
        return $variable;
    }

    private function getEntityNameVariable(): string
    {
        return mb_lcfirst($this->stringTransformationTools->getCamelCase($this->getEntityName()));
    }
}
