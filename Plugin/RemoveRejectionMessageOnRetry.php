<?php

namespace Webmecanik\Connector\Plugin;

use Magento\MysqlMq\Model\ResourceModel\Queue;

class RemoveRejectionMessageOnRetry
{
    /** @noinspection PhpUnusedParameterInspection */
    public function afterPushBackForRetry(Queue $subject, $result, int $relationId): void
    {
        $subject->getConnection()->update(
            $subject->getTable('queue_message_status'),
            [
                'rejection_message' => null
            ],
            [
                'id = ?' => $relationId
            ]
        );
    }
}
