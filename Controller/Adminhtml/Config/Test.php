<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Controller\Adminhtml\Config;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Webmecanik\Connector\Model\ApiClient;

class Test implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Webmecanik_Connector::all';

    public function __construct(
        private readonly RawFactory $resultRawFactory,
        private readonly ApiClient $apiClient
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(): Raw
    {
        $data = [
            'lastname' => 'test',
            'firstname' => 'test',
            'email' => 'test@test.com'
        ];

        $result = '<pre>' . $this->apiClient->createContact($data) . '</pre>';

        return $this->resultRawFactory->create()->setContents($result);
    }
}
