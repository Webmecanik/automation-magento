<?php
declare(strict_types=1);

namespace Webmecanik\Connector\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\MysqlMq\Model\MessageStatus;

class SendErrorNotification
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(MessageStatus $message): void
    {
        $recipientEmail = trim((string)$this->scopeConfig->getValue(
            'webmecanik_connector/general/error_recipient_email'
        ));

        if (!$recipientEmail) {
            return;
        }

        $templateOptions = [
            'area' => Area::AREA_FRONTEND,
            'store' => 1
        ];

        $from = [
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email'),
            'name' => 'Webmecanik connector'
        ];

        $this->inlineTranslation->suspend();

        $rejectionMessageJson = $message->getData('rejection_message');

        if (!$rejectionMessageJson) {
            return;
        }

        try {
            $this->transportBuilder->setTemplateIdentifier('webmecanik_consumer_error')
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars([
                    'rejection_message' => $rejectionMessageJson,
                    'updated_at' => $message->getData('updated_at')
                ])
                ->setFromByScope($from)
                ->addTo($recipientEmail);
            $transport = $this->transportBuilder->getTransport();

            $transport->sendMessage();

            $this->inlineTranslation->resume();
        } catch (MailException|LocalizedException) {
        }
    }
}
