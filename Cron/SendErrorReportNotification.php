<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Cron;

use Magento\MysqlMq\Model\MessageStatus;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\MysqlMq\Model\ResourceModel\MessageStatusCollectionFactory;
use Webmecanik\Connector\Model\ResourceModel\Queue\Grid\Collection;
use Webmecanik\Connector\Model\SendErrorNotification;

class SendErrorReportNotification
{
    public function __construct(
        private readonly MessageStatusCollectionFactory $messageStatusCollectionFactory,
        private readonly Collection $queueCollection,
        private readonly SendErrorNotification $sendErrorNotification
    ) {
    }

    public function execute(): void
    {
        $messageStatusCollection = $this->messageStatusCollectionFactory->create();
        $messageStatusCollection->setMainTable('queue_message_status');
        $messageStatusCollection->addFieldToFilter(
            QueueManagement::MESSAGE_STATUS,
            QueueManagement::MESSAGE_STATUS_ERROR
        );
        $messageStatusCollection->addFieldToFilter('updated_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $messageStatusCollection->setOrder('updated_at', 'DESC');

        $this->queueCollection->addQueueToFilter($messageStatusCollection);

        if (!$messageStatusCollection->count()) {
            return;
        }

        /** @var MessageStatus $lastMessage */
        $lastMessage = $messageStatusCollection->getFirstItem();

        $this->sendErrorNotification->execute($lastMessage);
    }
}
