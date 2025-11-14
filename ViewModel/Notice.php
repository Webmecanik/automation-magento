<?php

namespace Webmecanik\Connector\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Notice implements ArgumentInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isExportEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_export');
    }
}
