<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Plugin;

use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\MysqlMq\Model\Driver\Queue;
use Magento\MysqlMq\Model\MessageStatusFactory;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\MysqlMq\Model\ResourceModel\MessageStatus as MessageStatusResourceModel;
use Webmecanik\Connector\Model\SendErrorNotification;

class SendEmailOnRejection
{
    public function __construct(
        private readonly MessageStatusResourceModel $messageStatusResourceModel,
        private readonly MessageStatusFactory $messageStatusFactory,
        private readonly SendErrorNotification $sendErrorNotification
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
        $properties = $envelope->getProperties();
        $relationId = $properties[QueueManagement::MESSAGE_QUEUE_RELATION_ID];
        $messageQueueName = $properties[QueueManagement::MESSAGE_QUEUE_NAME];

        if ($messageQueueName === 'webmecanik_contact_updated') {
            $messageStatus = $this->messageStatusFactory->create();
            $this->messageStatusResourceModel->load($messageStatus, $relationId, 'id');

            if ($messageStatus->getId()) {
                $this->sendErrorNotification->execute($messageStatus);
            }
        }
    }
}
