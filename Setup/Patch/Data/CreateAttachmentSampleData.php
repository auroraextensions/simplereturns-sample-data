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
    DataObject,
    DataObject\Factory as DataObjectFactory,
    Setup\ModuleDataSetupInterface,
    Setup\Patch\DataPatchInterface
};
use Psr\Log\LoggerInterface;

class CreateAttachmentSampleData implements DataPatchInterface
{
    use DataContainerTrait, LoggerTrait;

    /** @property ModuleDataSetupInterface $moduleDataSetup */
    private $moduleDataSetup;

    /** @property AttachmentInterfaceFactory $attachmentFactory */
    private $attachmentFactory;

    /** @property AttachmentRepositoryInterface $attachmentRepository */
    private $attachmentRepository;

    /** @property CollectionFactory $collectionFactory */
    private $collectionFactory;

    /** @property SimpleReturnRepositoryInterface $rmaRepository */
    private $rmaRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param AttachmentInterfaceFactory $attachmentFactory
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param CollectionFactory $collectionFactory
     * @param DataObjectFactory $dataObjectFactory
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
        LoggerInterface $logger,
        SimpleReturnRepositoryInterface $rmaRepository,
        array $data = []
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attachmentFactory = $attachmentFactory;
        $this->attachmentRepository = $attachmentRepository;
        $this->collectionFactory = $collectionFactory;
        $this->container = $dataObjectFactory->create($data);
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
                $this->addAttachments($rma, $data[$index++]);
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
     * @return SimpleReturnInterface
     */
    private function addAttachments(
        SimpleReturnInterface $rma,
        array $data = []
    ): void
    {
        /** @var array $file */
        foreach ($data as $file) {
            try {
                /** @var AttachmentInterface $attachment */
                $attachment = $this->attachmentFactory
                    ->create()
                    ->addData($file)
                    ->setRmaId((int) $rma->getId())
                    ->setToken(Tokenizer::createToken());

                $this->attachmentRepository->save($attachment);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
