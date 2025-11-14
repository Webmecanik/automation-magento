<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Controller\Adminhtml\Queue;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\MessageQueue\PoisonPill\PoisonPillPutInterface;
use Magento\MysqlMq\Model\ResourceModel\Queue as QueueResourceModel;

class Retry implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Webmecanik_Connector::all';

    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly RequestInterface $request,
        private readonly QueueResourceModel $queueResourceModel,
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
        $messageId = (int)$this->request->getParam('id');

        $this->queueResourceModel->pushBackForRetry($messageId);

        $this->configWriter->save('webmecanik_connector/general/enable_export', 1);
        $this->reinitableConfig->reinit();
        $this->poisonPillPut->put();

        $this->messageManager->addSuccessMessage(__('Messages ID "%1" have been requeued.', $messageId));

        return $this->redirectFactory->create()->setPath('*/*');
    }
}
