<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageBooster\Mcp\Model\Role as RoleOrder;

class Role implements OptionSourceInterface
{
    public const OPTIONS = [
        RoleOrder::READ => 'Read only',
        RoleOrder::EDITOR => 'Editor',
        RoleOrder::FULL => 'Full',
    ];

    public function toOptionArray(): array
    {
        $list = [];
        foreach (self::OPTIONS as $value => $label) {
            $list[] = ['value' => $value, 'label' => __($label)];
        }
        return $list;
    }
}
