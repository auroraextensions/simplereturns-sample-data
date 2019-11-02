<?php
/**
 * CreatePackageSampleData.php
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
    Api\Data\PackageInterface,
    Api\Data\PackageInterfaceFactory,
    Api\PackageRepositoryInterface,
    Api\SimpleReturnRepositoryInterface,
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

class CreatePackageSampleData implements DataPatchInterface
{
    use DataContainerTrait, LoggerTrait;

    /** @property ModuleDataSetupInterface $moduleDataSetup */
    private $moduleDataSetup;

    /** @property CollectionFactory $collectionFactory */
    private $collectionFactory;

    /** @property DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @property PackageInterfaceFactory $packageFactory */
    private $packageFactory;

    /** @property PackageRepositoryInterface $packageRepository */
    private $packageRepository;

    /** @property SimpleReturnRepositoryInterface $rmaRepository */
    private $rmaRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CollectionFactory $collectionFactory
     * @param DataObjectFactory $dataObjectFactory
     * @param LoggerInterface $logger
     * @param PackageInterfaceFactory $packageFactory
     * @param PackageRepositoryInterface $packageRepository
     * @param SimpleReturnRepositoryInterface $rmaRepository
     * @param array $data
     * @return void
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CollectionFactory $collectionFactory,
        DataObjectFactory $dataObjectFactory,
        LoggerInterface $logger,
        PackageInterfaceFactory $packageFactory,
        PackageRepositoryInterface $packageRepository,
        SimpleReturnRepositoryInterface $rmaRepository,
        array $data = []
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->collectionFactory = $collectionFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logger = $logger;
        $this->packageFactory = $packageFactory;
        $this->packageRepository = $packageRepository;
        $this->rmaRepository = $rmaRepository;
        $this->container = $this->dataObjectFactory->create($data);
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
                /** @var PackageInterface $package */
                $package = $this->packageFactory
                    ->create()
                    ->addData($data[$index++])
                    ->setRmaId((int) $rma->getId())
                    ->setToken(Tokenizer::createToken());
                $this->packageRepository->save($package);

                $rma->setPackageId((int) $package->getId());
                $this->rmaRepository->save($rma);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $this->moduleDataSetup
            ->endSetup();
    }
}
