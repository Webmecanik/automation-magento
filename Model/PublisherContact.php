<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Model;

use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Webmecanik\Connector\Model\ResourceModel\Queue\Grid\CollectionFactory;

class PublisherContact
{
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly MutableScopeConfigInterface $scopeConfig,
        private readonly CollectionFactory $queueCollectionFactory
    ) {
    }

    public function publish(string $email): void
    {
        $moduleEnabled = $this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_publisher');

        if ($moduleEnabled) {
            if (!$this->isCustomerEligibleToExport($email)) {
                return;
            }

            $this->publisher->publish('webmecanik.contact.updated', $email);
        }
    }

    public function isCustomerEligibleToExport(string $email): bool
    {
        $currentQueueCollection = $this->queueCollectionFactory->create();
        $currentQueueCollection->addFieldToFilter('message.body', '"' . $email . '"');

        if ($currentQueueCollection->count() > 0) {
            return false;
        }

        return true;
    }
}
