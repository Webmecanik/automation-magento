<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Model\ResourceModel\Queue\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\MysqlMq\Model\QueueManagement;

class Collection extends SearchResult
{
    public const MESSAGE_STATUSES_TO_FILTER = [
        QueueManagement::MESSAGE_STATUS_NEW,
        QueueManagement::MESSAGE_STATUS_IN_PROGRESS,
        QueueManagement::MESSAGE_STATUS_ERROR,
        QueueManagement::MESSAGE_STATUS_RETRY_REQUIRED
    ];
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->join(
            ['message' => $this->getTable('queue_message')],
            'main_table.message_id = message.id',
            ['message_body' => 'body']
        );

        $this->addFieldToFilter(QueueManagement::MESSAGE_STATUS, ['in' => self::MESSAGE_STATUSES_TO_FILTER]);
        $this->addQueueToFilter($this);

        return $this;
    }

    public function addQueueToFilter($collection): void
    {
        $collection->join(
            ['queue' => $this->getTable('queue')],
            'main_table.queue_id = queue.id AND queue.name = "webmecanik_contact_updated"',
            []
        );
    }
}
