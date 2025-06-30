<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Webservice;

use GlsGroup\Sdk\ParcelProcessing\Api\CancellationServiceInterface;
use GlsGroup\Sdk\ParcelProcessing\Service\ServiceFactory;
use GlsGroup\Sdk\ParcelProcessing\Api\ShipmentServiceInterface;
use GlsGroup\Sdk\ParcelProcessing\Exception\ServiceException;
use GlsGroup\Shipping\Model\Config\ModuleConfig;
use Psr\Log\LoggerInterface;

class ParcelProcessingServiceFactory
{
    /**
     * @var ServiceFactory
     */
    private $serviceFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ServiceFactory $serviceFactory,
        ModuleConfig $moduleConfig,
        LoggerInterface $logger
    ) {
        $this->serviceFactory = $serviceFactory;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
    }

    /**
     * Create a pre-configured instance of the shipment service.
     *
     * @param null $store
     * @return ShipmentServiceInterface
     * @throws ServiceException
     */
    public function createShipmentService($store = null): ShipmentServiceInterface
    {
        return $this->serviceFactory->createShipmentService(
            $this->moduleConfig->getUserName($store),
            $this->moduleConfig->getPassword($store),
            $this->logger,
            $this->moduleConfig->isSandboxMode($store)
        );
    }

    /**
     * Create a pre-configured instance of the parcel cancellation service.
     *
     * @param null $store
     * @return CancellationServiceInterface
     * @throws ServiceException
     */
    public function createCancellationService($store = null): CancellationServiceInterface
    {
        return $this->serviceFactory->createCancellationService(
            $this->moduleConfig->getUserName($store),
            $this->moduleConfig->getPassword($store),
            $this->logger,
            $this->moduleConfig->isSandboxMode($store)
        );
    }
}
