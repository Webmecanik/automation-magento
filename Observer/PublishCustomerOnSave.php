<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Webmecanik\Connector\Model\PublisherContact;

class PublishCustomerOnSave implements ObserverInterface
{
    public function __construct(
        private readonly PublisherContact $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Customer $customer */
        $customer = $observer->getEvent()->getData('customer');

        $this->publisher->publish($customer->getEmail());
    }
}
