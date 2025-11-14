<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestBtn extends Field
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var Button $buttonBlock  */
        $buttonBlock = $this->getData('form')->getLayout()->createBlock(Button::class);
        $data = [
            'label' => __('Test'),
            'onclick' => "setLocation('" . $this->getUrl('webmecanik/config/test') . "')",
        ];

        return $buttonBlock->setData($data)->toHtml();
    }
}
