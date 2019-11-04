<?php
/**
 * ExceptionFactory.php
 *
 * Factory for generating various exception types.
 * Requires ObjectManager for instance generation.
 *
 * @link https://devdocs.magento.com/guides/v2.3/extension-dev-guide/factories.html
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

namespace AuroraExtensions\SimpleReturnsSampleData\Exception;

use Exception;
use Magento\Framework\{
    ObjectManagerInterface,
    Phrase
};
use Throwable;

final class ExceptionFactory
{
    /** @constant string BASE_TYPE */
    public const BASE_TYPE = Exception::class;

    /** @constant string DEFAULT_MSG */
    public const DEFAULT_MSG = 'Unable to process this request.';

    /** @constant string INVALID_TYPE */
    public const INVALID_TYPE = '%1 is not an instance of Throwable and cannot be thrown.';

    /** @property ObjectManagerInterface $objectManager */
    protected $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     * @return void
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create exception from type given.
     *
     * @param string|null $type
     * @param Phrase|null $message
     * @return Throwable
     * @throws Exception
     */
    public function create(
        ?string $type = self::BASE_TYPE,
        ?Phrase $message = null
    ) {
        /** @var array $arguments */
        $arguments = [];

        /* If no message was given, set default message. */
        $message = $message ?? __(self::DEFAULT_MSG);

        if (!is_subclass_of($type, Throwable::class)) {
            throw new Exception(
                __(
                    self::INVALID_TYPE,
                    $type
                )->__toString()
            );
        }

        if ($type !== self::BASE_TYPE) {
            $arguments['phrase'] = $message;
        } else {
            $arguments['message'] = $message->__toString();
        }

        return $this->objectManager->create($type, $arguments);
    }
}
