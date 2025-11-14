<?php

namespace Webmecanik\Connector\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\MysqlMq\Model\QueueManagement;

class MessageStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => QueueManagement::MESSAGE_STATUS_NEW, 'label' => __('New message')],
            ['value' => QueueManagement::MESSAGE_STATUS_IN_PROGRESS, 'label' => __('In progress')],
            ['value' => QueueManagement::MESSAGE_STATUS_COMPLETE, 'label' => __('Message complete')],
            ['value' => QueueManagement::MESSAGE_STATUS_RETRY_REQUIRED, 'label' => __('Retry required')],
            ['value' => QueueManagement::MESSAGE_STATUS_ERROR, 'label' => __('In error')],
            ['value' => QueueManagement::MESSAGE_STATUS_TO_BE_DELETED, 'label' => __('To be deleted')],
        ];
    }
}
