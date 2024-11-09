<?php

declare(strict_types=1);

namespace {{vendor\module}}\Controller\Adminhtml\{{controllerPath}};

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class {{controllerName}} extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = '{{moduleName}}::listing';

    /**
     * Construct
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Page
     */
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb(__('Reports'), __('Reports'));
        $resultPage->addBreadcrumb(__('{{module_title}}'), __('{{module_title}}'));
        $resultPage->getConfig()->getTitle()->prepend(__('{{module_title}} Listing'));

        return $resultPage;
    }
}

