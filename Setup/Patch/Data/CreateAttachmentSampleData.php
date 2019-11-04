<?php
/**
 * CreateAttachmentSampleData.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/sampledata/LICENSE.txt
 *
 * @package       AuroraExtensions_SimpleReturnsSampleData
 * @copyright     Copyright (C) 2019 Aurora Extensions <support@auroraextensions.com>
 * @license       MIT License
 */
declare(strict_types=1);

namespace AuroraExtensions\SimpleReturnsSampleData\Setup\Patch\Data;

use Exception;
use AuroraExtensions\SimpleReturns\{
    Api\AttachmentRepositoryInterface,
    Api\SimpleReturnRepositoryInterface,
    Api\Data\AttachmentInterface,
    Api\Data\AttachmentInterfaceFactory,
    Api\Data\SimpleReturnInterface,
    Model\ResourceModel\SimpleReturn\Collection,
    Model\ResourceModel\SimpleReturn\CollectionFactory,
    Model\Security\Token as Tokenizer
};
use AuroraExtensions\SimpleReturnsSampleData\{
    Component\DataContainerTrait,
    Component\LoggerTrait
};
use Magento\Framework\{
    App\Filesystem\DirectoryList,
    DataObject,
    DataObject\Factory as DataObjectFactory,
    Filesystem,
    Setup\ModuleDataSetupInterface,
    Setup\Patch\DataPatchInterface
};
use Magento\MediaStorage\{
    Model\File\Uploader,
    Model\File\UploaderFactory
};
use Psr\Log\LoggerInterface;

class CreateAttachmentSampleData implements DataPatchInterface
{
    use DataContainerTrait, LoggerTrait;

    /** @constant string SAVE_PATH */
    public const SAVE_PATH = '/simplereturns/';

    /** @property ModuleDataSetupInterface $moduleDataSetup */
    private $moduleDataSetup;

    /** @property AttachmentInterfaceFactory $attachmentFactory */
    private $attachmentFactory;

    /** @property AttachmentRepositoryInterface $attachmentRepository */
    private $attachmentRepository;

    /** @property CollectionFactory $collectionFactory */
    private $collectionFactory;

    /** @property Filesystem $filesystem */
    private $filesystem;

    /** @property UploaderFactory $fileUploaderFactory */
    private $fileUploaderFactory;

    /** @property SimpleReturnRepositoryInterface $rmaRepository */
    private $rmaRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param AttachmentInterfaceFactory $attachmentFactory
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param CollectionFactory $collectionFactory
     * @param DataObjectFactory $dataObjectFactory
     * @param Filesystem $filesystem
     * @param UploaderFactory $fileUploaderFactory
     * @param LoggerInterface $logger
     * @param SimpleReturnRepositoryInterface $rmaRepository
     * @param array $data
     * @return void
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        AttachmentInterfaceFactory $attachmentFactory,
        AttachmentRepositoryInterface $attachmentRepository,
        CollectionFactory $collectionFactory,
        DataObjectFactory $dataObjectFactory,
        Filesystem $filesystem,
        UploaderFactory $fileUploaderFactory,
        LoggerInterface $logger,
        SimpleReturnRepositoryInterface $rmaRepository,
        array $data = []
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attachmentFactory = $attachmentFactory;
        $this->attachmentRepository = $attachmentRepository;
        $this->collectionFactory = $collectionFactory;
        $this->container = $dataObjectFactory->create($data);
        $this->filesystem = $filesystem;
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->logger = $logger;
        $this->rmaRepository = $rmaRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            CreateRmaSampleData::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup
            ->startSetup();

        /** @var int $index */
        $index = 0;

        /** @var array $data */
        $data = $this->getContainer()
            ->toArray();

        /** @var SimpleReturnInterface[] $items */
        $items = $this->collectionFactory
            ->create()
            ->addFieldToSelect('*');

        /** @var SimpleReturnInterface $rma */
        foreach ($items as $rma) {
            try {
                /** @var array $config */
                $config = $data[$index++] + [
                    'rma_id' => $rma->getId(),
                ];

                $this->addMediaFiles($config);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $this->moduleDataSetup
            ->endSetup();
    }

    /**
     * @param array $data
     * @return DataPatchInterface
     */
    private function addMediaFiles(array $data = []): DataPatchInterface
    {
        /** @var array $file */
        foreach ($data as $file) {
            /** @var string $thumbnail */
            $thumbnail = $file['thumbnail'];

            /** @var string $tmpFile */
            $tmpFile = $this->createThumbTmpFile($thumbnail);

            /** @var array $info */
            $info = [
                'name' => $file['filename'],
                'path' => $file['filepath'],
                'size' => $file['filesize'],
                'type' => $file['mimetype'],
                'tmp_name' => $tmpFile,
            ];

            /** @var Uploader $uploader */
            $uploader = $this->fileUploaderFactory
                ->create(['fileId' => $info])
                ->setAllowedExtensions($this->getFileExtensions())
                ->setAllowCreateFolders(true)
                ->setAllowRenameFiles(true)
                ->setFilesDispersion(true);

            /** @var string $mediaPath */
            $mediaPath = $this->getMediaAbsolutePath();

            /** @var string $savePath */
            $savePath = $mediaPath . static::SAVE_PATH;

            /** @var array $result */
            $result = $uploader->save($savePath, $file['filename']);

            /** @var array $attachData */
            $attachData = [
                'filename' => $result['name'],
                'filepath' => $result['file'],
                'filesize' => $result['size'],
                'mimetype' => $result['type'],
                'thumbnail' => $thumbnail,
                'token' => Tokenizer::createToken(),
            ];

            /** @var AttachmentInterface $attachment */
            $attachment = $this->attachmentFactory
                ->create()
                ->addData($attachData);
            $this->attachmentRepository->save($attachment);

        }

        return $this;
    }

    /**
     * @param string $path
     * @return string
     */
    private function createThumbTmpFile(string $path): string
    {
        /** @var resource $tmpFile */
        $tmpFile = tmpfile();

        /** @var string $filePath */
        $filePath = $this->getLocalMediaAbsPath() . $path;

        /** @var resource $handle */
        $handle = fopen($filePath, 'r');

        /** @var string $content */
        $content = '';

        while (!feof($handle)) {
            $content .= fread($handle, 4096);
        }

        fwrite($tmpFile, $content);
        fclose($handle);

        return stream_get_meta_data($tmpFile)['uri'];
    }

    /**
     * @return string
     */
    private function getMediaAbsolutePath(): string
    {
        /** @var string $mediaPath */
        $mediaPath = $this->filesystem
            ->getDirectoryRead(DirectoryList::MEDIA)
            ->getAbsolutePath();

        return rtrim($mediaPath, DS);
    }

    /**
     * @return string
     */
    private function getLocalMediaAbsPath(): string
    {
        return SIMPLERETURNS_SAMPLEDATA_MEDIA_DIR;
    }

    /**
     * @return array
     */
    private function getFileExtensions(): array
    {
        return ['jpg', 'jpeg', 'gif', 'png'];
    }
}
