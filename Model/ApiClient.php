<?php

namespace Webmecanik\Connector\Model;

use Exception;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Mautic\Auth\ApiAuth;
use Mautic\Auth\TwoLeggedOAuth2;
use Mautic\MauticApi;
use Psr\Log\LoggerInterface;

class ApiClient
{
    /**
     * @param ApiAuth $apiAuth
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param ReinitableConfigInterface $reinitableConfig
     */
    public function __construct(
        private readonly ApiAuth                   $apiAuth,
        private readonly ScopeConfigInterface      $scopeConfig,
        private readonly WriterInterface           $configWriter,
        private readonly LoggerInterface           $logger,
        private readonly SerializerInterface       $serializer,
        private readonly ReinitableConfigInterface $reinitableConfig
    ) {
    }

    /**
     * @return void
     */
    public function authenticate(): void
    {
        $this->logger->info('Authenticating with Mautic');

        $oauthSettings = $this->getOauthSettings();

        /** @var TwoLeggedOAuth2 $auth */
        $auth = $this->apiAuth->newAuth($oauthSettings, $oauthSettings['AuthMethod']);
        $newAccessToken = $auth->getAccessToken();

        $this->configWriter->save('webmecanik_connector/general/oauth2_access_token', $newAccessToken);
        $this->reinitableConfig->reinit();

        $this->logger->info('Authentication successful');
    }

    /**
     * @throws Exception
     */
    public function createContact(array $data, bool $secondPass = false): string
    {
        $accessToken = $this->scopeConfig->getValue('webmecanik_connector/general/oauth2_access_token');

        if (!$accessToken) {
            $this->triggerException('Authentication required');
        }

        $settings = $this->getOauthSettings($accessToken);

        $api = new MauticApi();
        /** @var TwoLeggedOAuth2 $auth */
        $auth = $this->apiAuth->newAuth($settings, $settings['AuthMethod']);

        $contactApi = $api->newApi('contacts', $auth, $settings['baseUrl']);

        if ($this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_debug')) {
            $contactApi->setLogger($this->logger);
        }

        $result = $contactApi->create($data);
        $errors = $result['errors'] ?? null;

        if ($errors) {
            $errorCode = $errors[0]['code'];

            if ($errorCode === 401 && $secondPass === false) {
                $this->authenticate();

                return $this->createContact($data, true);
            } elseif ($errorCode === 0 && $secondPass === false) {
                $this->logger->info('Mautic Timeout ? Retrying');
                // @phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                sleep(10);
                return $this->createContact($data, true);
            } else {
                $this->triggerException($result);
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function getOauthSettings(string $currentAccessToken = null): array
    {
        $settings = [
            'AuthMethod' => 'TwoLeggedOAuth2',
            'clientKey' => $this->scopeConfig->getValue('webmecanik_connector/general/client_id'),
            'clientSecret' => $this->scopeConfig->getValue('webmecanik_connector/general/client_secret'),
            'baseUrl' => $this->scopeConfig->getValue('webmecanik_connector/general/mautic_url'),
        ];

        if ($currentAccessToken) {
            $settings['accessToken'] = $currentAccessToken;
        }

        return $settings;
    }

    /**
     * @throws Exception
     */
    private function triggerException(array|string $message): void
    {
        $serializedMessage = $this->serializer->serialize($message);
        $this->logger->info($serializedMessage);

        throw new LocalizedException(__($serializedMessage));
    }
}
