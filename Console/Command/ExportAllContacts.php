<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Console\Command;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Console\Cli;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmecanik\Connector\Model\PublisherContact;

class ExportAllContacts extends Command
{
    /**
     * bin/magento webmecanik:export-all-contacts && bin/magento queue:consumers:start contact.updated
     */
    private const DEFAULT_MAX_COLLECTION_SIZE = 1000;
    private const COMMAND_NAME = 'webmecanik:export-all-contacts';
    private const DESCRIPTION = 'Export all contacts (customer and guest optin)';

    public function __construct(
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly PublisherContact $publisher,
        private readonly SubscriberCollectionFactory $subscriberCollectionFactory,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription(self::DESCRIPTION);
        $this->addOption('full-export', null, null, 'Full export');

        parent::configure();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fullExport = $input->getOption('full-export');

        $customerCollection = $this->customerCollectionFactory->create();
        $customerCollection->setOrder(CustomerInterface::CREATED_AT, $customerCollection::SORT_ORDER_DESC);

        if (!$fullExport) {
            $customerCollection->setPageSize(self::DEFAULT_MAX_COLLECTION_SIZE);
        }

        foreach ($customerCollection as $customer) {
            $email = $customer->getEmail();
            $output->writeln('Exporting customer: ' . $email);
            $this->publisher->publish($email);
        }

        $guestSubscriberCollection = $this->subscriberCollectionFactory->create();
        $guestSubscriberCollection->addFieldToFilter('customer_id', 0);
        $guestSubscriberCollection->setOrder('change_status_at');

        if (!$fullExport) {
            $guestSubscriberCollection->setPageSize(self::DEFAULT_MAX_COLLECTION_SIZE);
        }

        /** @var Subscriber $guestSubscriber */
        foreach ($guestSubscriberCollection as $guestSubscriber) {
            $email = $guestSubscriber->getEmail();
            $output->writeln('Exporting guest optin: ' . $email);
            $this->publisher->publish($email);
        }

        $output->writeln(__('%1 customers exported', $customerCollection->count()));
        $output->writeln(__('%1 guests optin exported', $guestSubscriberCollection->count()));

        return Cli::RETURN_SUCCESS;
    }
}
