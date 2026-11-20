<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\View\Result\PageFactory;
use MageBooster\Mcp\Model\Config;
use MageBooster\Mcp\Model\Connect\Pending;
use MageBooster\Mcp\Model\Role;

/**
 * Admin page where the merchant approves a Claude / ChatGPT connection.
 * GET shows the request, POST (form key) picks the access and returns to mcp.magebooster.com
 * with sig = hex(hmac_sha256(secret, p . "\n" . role . "\n" . admin)).
 */
class Approve extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageBooster_Mcp::connect';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory,
        private Config $config,
        private Pending $pending
    ) {
        parent::__construct($context);
    }

    /** The link comes from outside the admin, so it has no secret key; the POST is checked by form key. */
    public function _processUrlKeys()
    {
        return $this->getRequest()->isPost() ? parent::_processUrlKeys() : true;
    }

    public function execute()
    {
        $p = (string)$this->getRequest()->getParam('p');
        $request = $this->pending->load($p);
        $secret = $this->config->isEnabled() ? $this->config->getSecret() : '';
        if ($request === null || $secret === '') {
            $this->messageManager->addErrorMessage(__('This connection request has expired. Start again from Claude or ChatGPT.'));
            return $this->resultRedirectFactory->create()->setPath('adminhtml/dashboard');
        }

        if (!$this->getRequest()->isPost()) {
            $page = $this->pageFactory->create();
            $page->getConfig()->getTitle()->prepend(__('Connect Claude or ChatGPT'));
            return $page;
        }

        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('*/*/approve', ['p' => $p]);
        }
        $this->pending->remove($p);
        if ($this->getRequest()->getParam('decision') !== 'approve') {
            $this->messageManager->addNoticeMessage(__('The connection was not approved.'));
            return $this->resultRedirectFactory->create()->setPath('adminhtml/dashboard');
        }
        $role = (string)$this->getRequest()->getParam('role');
        $role = Role::isValid($role) ? Role::lowest($role, $this->config->getMaxRole()) : Role::READ;
        $admin = (string)$this->_auth->getUser()->getUserName();
        $sig = hash_hmac('sha256', $p . "\n" . $role . "\n" . $admin, $secret);
        $url = $request['back'] . '?' . http_build_query(['p' => $p, 'role' => $role, 'admin' => $admin, 'sig' => $sig]);
        return $this->resultRedirectFactory->create()->setUrl($url);
    }
}
