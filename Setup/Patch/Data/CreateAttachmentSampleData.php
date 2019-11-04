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
    Component\LoggerTrait,
    Exception\ExceptionFactory
};
use Magento\Framework\{
    App\Filesystem\DirectoryList,
    DataObject,
    DataObject\Factory as DataObjectFactory,
    Exception\LocalizedException,
    Filesystem,
    Image\AdapterFactory,
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

    /** @property ExceptionFactory $exceptionFactory */
    private $exceptionFactory;

    /** @property Filesystem $filesystem */
    private $filesystem;

    /** @property UploaderFactory $fileUploaderFactory */
    private $fileUploaderFactory;

    /** @property AdapterFactory $imageFactory */
    private $imageFactory;

    /** @property SimpleReturnRepositoryInterface $rmaRepository */
    private $rmaRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param AttachmentInterfaceFactory $attachmentFactory
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param CollectionFactory $collectionFactory
     * @param DataObjectFactory $dataObjectFactory
     * @param ExceptionFactory $exceptionFactory
     * @param Filesystem $filesystem
     * @param UploaderFactory $fileUploaderFactory
     * @param AdapterFactory $imageFactory
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
        ExceptionFactory $exceptionFactory,
        Filesystem $filesystem,
        UploaderFactory $fileUploaderFactory,
        AdapterFactory $imageFactory,
        LoggerInterface $logger,
        SimpleReturnRepositoryInterface $rmaRepository,
        array $data = []
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attachmentFactory = $attachmentFactory;
        $this->attachmentRepository = $attachmentRepository;
        $this->collectionFactory = $collectionFactory;
        $this->container = $dataObjectFactory->create($data);
        $this->exceptionFactory = $exceptionFactory;
        $this->filesystem = $filesystem;
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->imageFactory = $imageFactory;
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
                $this->addMediaFiles($rma, $data[$index++]);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $this->moduleDataSetup
            ->endSetup();
    }

    /**
     * @param SimpleReturnInterface $rma
     * @param array $data
     * @return DataPatchInterface
     */
    private function addMediaFiles(
        SimpleReturnInterface $rma,
        array $data = []
    ): DataPatchInterface
    {
        /** @var array $file */
        foreach ($data as $file) {
            /** @var string $thumbnail */
            $thumbnail = $file['thumbnail'];

            /** @var resource $tmpFile */
            $tmpFile = $this->createMediaTmpFile($thumbnail);

            /** @var string $tmpPath */
            $tmpPath = $this->getStreamUri($tmpFile);

            /** @var array $info */
            $info = [
                'name' => $file['filename'],
                'path' => $file['filepath'],
                'size' => $file['filesize'],
                'type' => $file['mimetype'],
                'tmp_name' => $tmpPath,
            ];

            /** @var Uploader $uploader */
            $uploader = $this->fileUploaderFactory
                ->create(['fileId' => $info])
                ->setAllowedExtensions($this->getFileExtensions())
                ->setAllowCreateFolders(true)
                ->setAllowRenameFiles(true)
                ->setFilesDispersion(true);

            /** @var string $mediaPath */
            $mediaPath = $this->getStoreMediaAbsPath();

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
                'rma_id' => $rma->getId(),
                'thumbnail' => $thumbnail,
                'token' => Tokenizer::createToken(),
            ];

            /** @var AttachmentInterface $attachment */
            $attachment = $this->attachmentFactory
                ->create()
                ->addData($attachData);

            $this->attachmentRepository->save($attachment);

            /** @var Magento\Framework\Image\Adapter\AdapterInterface $image */
            $image = $this->imageFactory->create();
            $image->open($result['path']);
            $image->save($thumbnail);
        }

        return $this;
    }

    /**
     * @param string $path
     * @return resource
     */
    private function createMediaTmpFile(string $path)
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

        return $tmpFile;
    }

    /**
     * @param resource $resource
     * @return string
     */
    private function getStreamUri($resource): string
    {
        if (!is_resource($resource)) {
            /** @var LocalizedException $exception */
            $exception = $this->exceptionFactory->create(
                LocalizedException::class,
                __('Not a valid resource handle.')
            );

            throw $exception;
        }

        return stream_get_meta_data($resource)['uri'];
    }

    /**
     * @return string
     */
    private function getStoreMediaAbsPath(): string
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
