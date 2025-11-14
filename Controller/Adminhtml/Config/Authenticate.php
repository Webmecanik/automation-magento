<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Controller\Adminhtml\Config;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Webmecanik\Connector\Model\ApiClient;

class Authenticate implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Webmecanik_Connector::all';

    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly ApiClient $apiClient
    ) {
    }

    public function execute(): Redirect
    {
        $this->apiClient->authenticate();

        return $this->redirectFactory->create()->setRefererUrl();
    }
}
