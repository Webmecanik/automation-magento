<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Controller\Adminhtml\Queue;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\MessageQueue\PoisonPill\PoisonPillPutInterface;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\MysqlMq\Model\ResourceModel\MessageStatusCollectionFactory;
use Magento\MysqlMq\Model\ResourceModel\Queue as QueueResourceModel;
use Webmecanik\Connector\Model\ResourceModel\Queue\Grid\Collection;

class RetryAll implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Webmecanik_Connector::all';

    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly QueueResourceModel $queueResourceModel,
        private readonly MessageStatusCollectionFactory $messageStatusCollectionFactory,
        private readonly Collection $queueCollection,
        private readonly PoisonPillPutInterface $poisonPillPut,
        private readonly Writer $configWriter,
        private readonly ReinitableConfigInterface $reinitableConfig
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(): ResultInterface
    {
        $this->retryAll();

        $this->messageManager->addSuccessMessage(__('All messages in error have been requeued.'));

        return $this->redirectFactory->create()->setPath('*/*');
    }

    /**
     * @throws Exception
     */
    public function retryAll(): void
    {
        $messageStatusCollection = $this->messageStatusCollectionFactory->create();
        $messageStatusCollection->addFieldToFilter(
            QueueManagement::MESSAGE_STATUS,
            ['in' => [QueueManagement::MESSAGE_STATUS_ERROR, QueueManagement::MESSAGE_STATUS_IN_PROGRESS]]
        );

        $this->queueCollection->addQueueToFilter($messageStatusCollection);

        foreach ($messageStatusCollection as $messageStatus) {
            $this->queueResourceModel->pushBackForRetry($messageStatus->getId());
        }

        $this->configWriter->save('webmecanik_connector/general/enable_export', 1);
        $this->reinitableConfig->reinit();
        $this->poisonPillPut->put();
    }
}
