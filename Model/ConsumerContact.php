<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Webmecanik\Connector\Model;

use Exception;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Logger as CustomerLogger;
use Magento\Directory\Model\Country;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\JsonValidator;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResourceModel;
use Magento\Newsletter\Model\Subscriber;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Webmecanik\Connector\Api\Data\AddressInterface as CustomAddressInterface;
use Webmecanik\Connector\Api\Data\CustomerInterface as CustomCustomerInterface;
use Webmecanik\Connector\Api\Data\OrderInterface as CustomOrderInterface;

class ConsumerContact
{
    public const CONSUMER_WEBSITE_ID = 1;
    private const PAYMENT_METHOD_KEY = 'payment_method';
    private const CUSTOM_OBJECTS_KEY = 'customObjects';
    private const MAGENTO_FIELD_PREFIX = 'magento_';
    private const CUSTOM_COUNTRY_NAMES = [
        'CD' => 'Democratic Republic of the Congo',
        'HK' => 'Hong Kong',
        'CZ' => 'Czech Republic',
        'PM' => 'Saint Pierre and Miquelon',
        'CI' => 'Ivory Coast',
        'MF' => 'Saint Martin',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConsoleOutput $output,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SubscriberResourceModel $subscriberResourceModel,
        private readonly ApiClient $apiClient,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly SerializerInterface $serializer,
        private readonly ReinitableConfigInterface $reinitableConfig,
        private readonly WritePayload $writePayload,
        private readonly Country $country,
        private readonly CustomerLogger $customerLogger,
        private readonly JsonValidator $jsonValidator,
        private readonly CustomerFactory $customerFactory
    ) {
    }

    /**
     * @throws Exception
     */
    public function process(string $email): void
    {
        $contactData = [
            CustomerInterface::EMAIL => $email
        ];

        try {
            $contactData['doNotContact'] = [
                [
                    'reason' => $this->isOptout($email) ? 1 : 0,
                    'comments' => 'Non abonné web',
                    'channel' => 'email'
                ]
            ];

            try {
                $customer = $this->getCustomer($email, self::CONSUMER_WEBSITE_ID);

                $billingAddress = $this->addressRepository->getById($customer->getDefaultBilling());
                $phone = $billingAddress->getTelephone();
                $countryCode = $billingAddress->getCountryId() ?? 'FR';
                $countryName = self::CUSTOM_COUNTRY_NAMES[$countryCode]
                    ?? $this->country->loadByCode($countryCode)->getName('en_US');

                $customerData = [
                    'overwriteWithBlank' => true,
                    CustomCustomerInterface::CUSTOMER_ID => (string)$customer->getId(),
                    CustomerInterface::EMAIL => strtolower($customer->getEmail()),
                    CustomerInterface::PREFIX => $customer->getPrefix(),
                    CustomerInterface::FIRSTNAME => ucwords(strtolower($customer->getFirstname())),
                    CustomerInterface::LASTNAME => ucwords(strtolower($customer->getLastname())),
                    CustomAddressInterface::PHONE_CODE => $phone
                    && !$this->isFrenchMobileNumber($phone) ? $phone : null,
                    CustomAddressInterface::MOBILE_CODE => $phone
                    && $this->isFrenchMobileNumber($phone) ? $phone : null,
                    CustomAddressInterface::STREET_1_CODE => $billingAddress->getStreet()[0] ?? null,
                    CustomAddressInterface::STREET_2_CODE => $billingAddress->getStreet()[1] ?? null,
                    CustomAddressInterface::POSTCODE_CODE => $billingAddress->getPostcode(),
                    AddressInterface::CITY => ucwords(strtolower($billingAddress->getCity())),
                    CustomAddressInterface::COUNTRY_CODE => $countryName,
                    CustomerInterface::GROUP_ID => (string)$customer->getGroupId(),
                    CustomerInterface::DOB => $customer->getDob(),
                    CustomCustomerInterface::LAST_ACTIVE_DATE_CODE => $this->customerLogger->get(
                        (int)$customer->getId()
                    )->getLastLoginAt(),
                    self::MAGENTO_FIELD_PREFIX . CustomerInterface::CREATED_AT => $customer->getCreatedAt(),
                    self::CUSTOM_OBJECTS_KEY => [
                        'data' => [
                            [
                                'alias' => 'orders',
                                'data' => $this->getOrdersData($customer)
                            ]
                        ]
                    ]
                ];

                $contactData = array_merge($contactData, $customerData);
            } catch (NoSuchEntityException) {
                // No customer found, only export contact data
            }

            if ($this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_export') === false) {
                $message = __('Export is disabled for %1', $email);
                $this->log($message);

                throw new Exception($this->serializer->serialize($message));
            }

            $this->log(__('Exporting contact %1', $email));
            $this->writePayload->writeFile($contactData);
            $this->apiClient->createContact($contactData);
        } catch (Exception $e) {
            $this->log(__('Error for %1', $email));

            if ($this->scopeConfig->isSetFlag('webmecanik_connector/general/stop_export_on_error')) {
                $this->configWriter->save('webmecanik_connector/general/enable_export', 0);
                $this->reinitableConfig->reinit();
            }

            $message = $this->jsonValidator->isValid($e->getMessage()) ? json_decode($e->getMessage())
                : $e->getMessage();

            $rejectionMessage = [
                'response' => $message,
                'request' => $contactData
            ];

            throw new Exception(json_encode($rejectionMessage, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    private function getOrdersData(CustomerInterface $customer): array
    {
        $ordersData = [];
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter(OrderInterface::STORE_ID, $customer->getStoreId());
        $orderCollection->addFieldToFilter(OrderInterface::CUSTOMER_ID, $customer->getId());
        $orderCollection->addFieldToFilter(OrderInterface::STATUS, ['neq' => 'pending']);

        /** @var OrderInterface $order */
        foreach ($orderCollection as $order) {
            $orderData = [
                'name' => (int)$order->getId(),
                'attributes' => [
                    $this->cleanKey(CustomOrderInterface::ID) => (string)$order->getId(),
                    $this->cleanKey(OrderInterface::EXT_ORDER_ID) => $order->getExtOrderId(),
                    $this->cleanKey(OrderInterface::STATUS) => $order->getStatus(),
                    $this->cleanKey(OrderInterface::INCREMENT_ID) => $order->getIncrementId(),
                    $this->cleanKey(OrderInterface::BASE_DISCOUNT_AMOUNT) => (float)$order->getBaseDiscountAmount(),
                    $this->cleanKey(OrderInterface::BASE_SHIPPING_AMOUNT) => (float)$order->getBaseShippingAmount(),
                    $this->cleanKey(OrderInterface::BASE_SHIPPING_TAX_AMOUNT) => (float)$order->getBaseShippingTaxAmount(),
                    $this->cleanKey(OrderInterface::BASE_TAX_AMOUNT) => (float)$order->getBaseTaxAmount(),
                    $this->cleanKey(OrderInterface::BASE_GRAND_TOTAL) => (float)$order->getBaseGrandTotal(),
                    $this->cleanKey(OrderInterface::COUPON_CODE) => $order->getCouponCode(),
                    $this->cleanKey(OrderInterface::SHIPPING_DESCRIPTION) => $order->getShippingDescription(),
                    $this->cleanKey(self::PAYMENT_METHOD_KEY) => $order->getPayment()->getMethod(),
                    $this->cleanKey(OrderInterface::CREATED_AT) => $order->getCreatedAt(),
                    $this->cleanKey(OrderInterface::TOTAL_ITEM_COUNT) => (int)$order->getTotalItemCount()
                ],
                'linkedCustomObjects' => [
                    [
                        'alias' => 'items',
                        'data' => $this->getItemsData($order)
                    ]
                ]
            ];

            $ordersData[] = $orderData;
        }

        return $ordersData;
    }

    private function cleanKey(string $key): string
    {
        return str_replace('_', '', $key);
    }

    private function getItemsData(OrderInterface $order): array
    {
        $itemsData = [];
        foreach ($order->getItems() as $item) {
            $itemData = [
                'name' => $item->getName(),
                'attributes' => [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'categories' => $this->getCategoriesData($item),
                ],
            ];

            $itemsData[] = $itemData;
        }

        return $itemsData;
    }

    private function getCategoriesData(OrderItemInterface $item): array
    {
        $categories = [];

        try {
            $product = $this->productRepository->getById($item->getProductId());
        } catch (NoSuchEntityException) {
            return [];
        }

        /** @var CategoryCollection $categoryCollection */
        $categoryCollection = $product->getCategoryCollection();
        $categoryCollection->addIsActiveFilter();
        $categoryCollection->addPathFilter('1/2/');
        $categoryCollection->addFieldToFilter('children_count', 0);

        /** @noinspection PhpUnhandledExceptionInspection */
        $categoryCollection->addAttributeToSelect(CategoryInterface::KEY_NAME);

        if ($categoryCollection->count()) {
            /** @var Category $category */
            foreach ($categoryCollection as $category) {
                $categoryId = (int)$category->getId();
                $categories[$categoryId] = (string)$categoryId;
            }
        }

        return array_values($categories);
    }

    private function log(Phrase $msg): void
    {
        $this->output->writeln($msg);
        $this->logger->info($msg);
    }

    private function isOptout(string $email): bool
    {
        try {
            $subscriptionData = $this->subscriberResourceModel->loadBySubscriberEmail(
                $email,
                ConsumerContact::CONSUMER_WEBSITE_ID
            );

            if (!$subscriptionData || $subscriptionData['subscriber_status'] != Subscriber::STATUS_SUBSCRIBED) {
                return true;
            }

            return false;
        } catch (Exception) {
        }

        return true;
    }

    private function isFrenchMobileNumber(string $phoneNumber): bool
    {
        // Regular expression for French mobile numbers
        $pattern = '/^(\+33|0)([67])\d{8}$/';

        // Check if the number matches the pattern
        return preg_match($pattern, $phoneNumber) === 1;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getCustomer($email, $websiteId = null): CustomerInterface
    {
        $customer = $this->customerFactory->create();

        if (isset($websiteId)) {
            $customer->setWebsiteId($websiteId);
        }

        $customer->loadByEmail($email);

        if (!$customer->getEmail()) {
            throw new NoSuchEntityException(
                __(
                    'No such entity with %fieldName = %fieldValue, %field2Name = %field2Value',
                    [
                        'fieldName' => 'email',
                        'fieldValue' => $email,
                        'field2Name' => 'websiteId',
                        'field2Value' => $websiteId
                    ]
                )
            );
        }

        return $customer->getDataModel();
    }
}
