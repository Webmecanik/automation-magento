<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Plugin;

use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\MysqlMq\Model\Driver\Queue;
use Magento\MysqlMq\Model\MessageStatusFactory;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\MysqlMq\Model\ResourceModel\MessageStatus as MessageStatusResourceModel;

class SaveRejectionMessage
{
    public function __construct(
        private readonly MessageStatusResourceModel $messageStatusResourceModel,
        private readonly MessageStatusFactory $messageStatusFactory
    ) {
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function afterReject(
        Queue $subject,
        $result,
        EnvelopeInterface $envelope,
        bool $requeue = true,
        string $rejectionMessage = null
    ): void {
        if ($rejectionMessage !== null) {
            $properties = $envelope->getProperties();
            $relationId = $properties[QueueManagement::MESSAGE_QUEUE_RELATION_ID];

            $messageStatus = $this->messageStatusFactory->create();
            $this->messageStatusResourceModel->load($messageStatus, $relationId, 'id');

            if ($messageStatus->getId()) {
                $messageStatus->setData('rejection_message', $rejectionMessage);
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->messageStatusResourceModel->save($messageStatus);
            }
        }
    }
}
