<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Block\Adminhtml;

use Magento\Backend\Block\Template;
use MageBooster\Mcp\Model\Config;
use MageBooster\Mcp\Model\Config\Source\Role as RoleSource;
use MageBooster\Mcp\Model\Role;

class Connect extends Template
{
    protected $_template = 'MageBooster_Mcp::connect.phtml';

    public function __construct(
        Template\Context $context,
        private Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/approve', ['p' => (string)$this->getRequest()->getParam('p')]);
    }

    /** Roles the merchant may hand out: up to the highest access set in the AI connector settings. */
    public function getRoles(): array
    {
        $max = $this->config->getMaxRole();
        $list = [];
        foreach (RoleSource::OPTIONS as $role => $label) {
            if (Role::allows($max, $role)) {
                $list[$role] = __($label);
            }
        }
        return $list;
    }

    public function getStoreName(): string
    {
        return (string)$this->_scopeConfig->getValue('general/store_information/name') ?: (string)$this->_storeManager->getStore()->getBaseUrl();
    }
}
