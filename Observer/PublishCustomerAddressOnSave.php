<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Observer;

use Magento\Customer\Model\Address;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Webmecanik\Connector\Model\PublisherContact;

class PublishCustomerAddressOnSave implements ObserverInterface
{
    public function __construct(
        private readonly PublisherContact $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Address $address */
        $address = $observer->getEvent()->getData('customer_address');

        if ($address->getData('is_customer_save_transaction')) {
            return;
        }

        $filteredDiff = array_intersect_key(
            array_diff_assoc($address->getData(), $address->getOrigData() ?? []),
            array_flip([
                'firstname',
                'lastname',
                'street',
                'city',
                'postcode',
                'telephone',
                'country_id'
            ])
        );

        if ($filteredDiff) {
            $customer = $address->getCustomer();

            $this->publisher->publish($customer->getEmail());
        }
    }
}
