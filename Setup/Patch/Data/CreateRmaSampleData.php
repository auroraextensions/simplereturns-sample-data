<?php
/**
 * CreateRmaSampleData.php
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
    Api\Data\SimpleReturnInterface,
    Api\Data\SimpleReturnInterfaceFactory,
    Api\SimpleReturnRepositoryInterface,
    Model\Security\Token as Tokenizer,
    Setup\Patch\Data\CreateSimpleReturnProductAttribute
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
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CreateRmaSampleData implements DataPatchInterface
{
    use DataContainerTrait, LoggerTrait;

    /** @property ModuleDataSetupInterface $moduleDataSetup */
    private $moduleDataSetup;

    /** @property DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @property SimpleReturnInterfaceFactory $rmaFactory */
    private $rmaFactory;

    /** @property SimpleReturnRepositoryInterface $rmaRepository */
    private $rmaRepository;

    /** @property StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param DataObjectFactory $dataObjectFactory
     * @param LoggerInterface $logger
     * @param SimpleReturnInterfaceFactory $rmaFactory
     * @param SimpleReturnRepositoryInterface $rmaRepository
     * @param StoreManagerInterface $storeManager
     * @param array $data
     * @return void
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        DataObjectFactory $dataObjectFactory,
        LoggerInterface $logger,
        SimpleReturnInterfaceFactory $rmaFactory,
        SimpleReturnRepositoryInterface $rmaRepository,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logger = $logger;
        $this->rmaFactory = $rmaFactory;
        $this->rmaRepository = $rmaRepository;
        $this->storeManager = $storeManager;
        $this->container = $this->dataObjectFactory->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            CreateSimpleReturnProductAttribute::class
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

        /** @var array $data */
        $data = $this->getContainer()
            ->toArray();

        /** @var array $item */
        foreach ($data as $item) {
            try {
                /** @var SimpleReturnInterface $rma */
                $rma = $this->rmaFactory
                    ->create()
                    ->addData($item)
                    ->setToken(Tokenizer::createToken());

                $this->rmaRepository->save($rma);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $this->moduleDataSetup
            ->endSetup();
    }
}
