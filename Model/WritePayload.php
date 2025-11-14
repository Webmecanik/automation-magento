<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Webmecanik\Connector\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

class WritePayload
{
    private const EXPORT_DIRECTORY = 'webmecanik';

    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly DirectoryList $directoryList,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function writeFile($data): void
    {
        if ($this->scopeConfig->isSetFlag('webmecanik_connector/general/enable_debug') === false) {
            return;
        }

        try {
            $varPath = $this->directoryList->getPath(DirectoryList::VAR_DIR);
            $exportDirectoryPath = $varPath . DIRECTORY_SEPARATOR . self::EXPORT_DIRECTORY;

            $this->fileDriver->createDirectory($exportDirectoryPath);

            $filename = $exportDirectoryPath . DIRECTORY_SEPARATOR . time() . '.json';
            $fileResource = $this->fileDriver->fileOpen($filename, 'w');
            $jsonContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $this->fileDriver->fileWrite(
                $fileResource,
                $jsonContent
            );

        } catch (FileSystemException) {
        }
    }
}
