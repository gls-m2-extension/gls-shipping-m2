<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Carrier;

use GlsGroup\Shipping\Model\BulkShipment\ShipmentManagement;
use GlsGroup\Shipping\Model\Config\ModuleConfig;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory as RateErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result as TrackingResult;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackResultFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackRequest\TrackRequestInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackErrorResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackResponseInterface;
use Netresearch\ShippingCore\Model\Rate\Emulation\ProxyCarrierFactory;
use Netresearch\ShippingCore\Model\Rate\Emulation\RatesManagement;
use Psr\Log\LoggerInterface;

class GlsGroup extends AbstractCarrierOnline implements CarrierInterface
{
    public const CARRIER_CODE = 'glsgroup';

    public const TRACKING_URL_TEMPLATE = 'https://gls-group.eu/track/%s';

    /**
     * @var string
     */
    protected $_code = self::CARRIER_CODE;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var RatesManagement
     */
    private $ratesManagement;

    /**
     * @var ShipmentManagement
     */
    private $shipmentManagement;

    /**
     * @var TrackRequestInterfaceFactory
     */
    private $trackRequestFactory;

    /**
     * @var ProxyCarrierFactory
     */
    private $proxyCarrierFactory;

    /**
     * @var AbstractCarrierInterface
     */
    private $proxyCarrier;

    /**
     * @var ShippingRouteValidator
     */
    private $shippingRouteValidator;

    public function __construct(
        ScopeConfigInterface         $scopeConfig,
        RateErrorFactory             $rateErrorFactory,
        LoggerInterface              $logger,
        Security                     $xmlSecurity,
        ElementFactory               $xmlElFactory,
        RateResultFactory            $rateFactory,
        MethodFactory                $rateMethodFactory,
        TrackResultFactory           $trackFactory,
        TrackErrorFactory            $trackErrorFactory,
        StatusFactory                $trackStatusFactory,
        RegionFactory                $regionFactory,
        CountryFactory               $countryFactory,
        CurrencyFactory              $currencyFactory,
        Data                         $directoryData,
        StockRegistryInterface       $stockRegistry,
        ModuleConfig                 $moduleConfig,
        RatesManagement              $ratesManagement,
        ShipmentManagement           $shipmentManagement,
        TrackRequestInterfaceFactory $trackRequestFactory,
        ProxyCarrierFactory          $proxyCarrierFactory,
        ShippingRouteValidator       $shippingRouteValidator,
        array                        $data = []
    )
    {
        $this->moduleConfig = $moduleConfig;
        $this->ratesManagement = $ratesManagement;
        $this->shipmentManagement = $shipmentManagement;
        $this->trackRequestFactory = $trackRequestFactory;
        $this->proxyCarrierFactory = $proxyCarrierFactory;
        $this->shippingRouteValidator = $shippingRouteValidator;

        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Returns the configured carrier instance.
     *
     * @return AbstractCarrierInterface
     * @throws NotFoundException
     */
    private function getProxyCarrier(): AbstractCarrierInterface
    {
        if (!$this->proxyCarrier) {
            $storeId = $this->getData('store');
            $carrierCode = $this->moduleConfig->getProxyCarrierCode($storeId);

            $this->proxyCarrier = $this->proxyCarrierFactory->create($carrierCode);
        }

        return $this->proxyCarrier;
    }

    /**
     * Check if the carrier can handle the given rate request.
     *
     * @param DataObject $request
     * @return bool|DataObject|AbstractCarrierOnline
     */
    public function processAdditionalValidation(DataObject $request)
    {

        $origin = (string)$request->getData('country_id');

        $destination = (string)$request->getData('dest_country_id');
        if (!$this->shippingRouteValidator->isValid($origin, $destination)) {
            return false;
        }

        return parent::processAdditionalValidation($request);
    }

    public function collectRates(RateRequest $request)
    {
        $result = $this->_rateFactory->create();

        if ($this->_activeFlag && !$this->getConfigFlag($this->_activeFlag)) {
            return $result;
        }

        $allowedMethods = explode(',', $this->getConfigData('general/allowed_methods'));

        if(empty($allowedMethods)) {
            return $result;
        }

        $request->setData('carrier_code', $this->getCarrierCode());
        $request->setData('carrier_title', $this->getConfigData('title'));
        if(in_array('standard', $allowedMethods)) {
            $standardRate = $this->ratesManagement->collectRates($request);
            if ($standardRate) {
                $result->append($standardRate);
            }
        }

        if(in_array('parcelshop', $allowedMethods)) {
            $parcelShopRate = $this->getParcelShopRate();
            if ($parcelShopRate) {
                $result->append($parcelShopRate);
            }
        }

        if (!$standardRate && !$parcelShopRate) {
            $result->append($this->getErrorMessage());

            return $result;
        }

        return $result;
    }

    private function getParcelShopRate()
    {

        $rate = $this->_rateMethodFactory->create();
        $rate->setCarrier($this->_code);

        $rate->setCarrierTitle($this->getConfigData('parcelshop_title'));
        $rate->setMethod('parcelshop');
        $rate->setMethodTitle($this->getConfigData('checkout_parcelshop/parcelshop_method_title'));
        $rate->setPrice("3.90");
        return $rate;
    }

    /**
     * Obtain shipping methods offered by the carrier.
     *
     * The GLS Shipping carrier does not offer own methods. The call gets
     * forwarded to another carrier as configured via module settings.
     *
     * @return string[] Associative array of method names with method code as key.
     */
    public function getAllowedMethods(): array
    {
        try {
            $carrier = $this->getProxyCarrier();
        } catch (LocalizedException $exception) {
            return [];
        }

        if (!$carrier instanceof CarrierInterface) {
            return [];
        }

        return $carrier->getAllowedMethods();
    }

    /**
     * Perform a shipment request to the GLS web service.
     *
     * Return either tracking number and label data or a shipment error.
     * Note that Magento triggers one web service request per package in multi-package shipments.
     *
     * @param DataObject|Request $request
     * @return DataObject
     * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::requestToShipment
     * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::returnOfShipment
     */
    protected function _doShipmentRequest(DataObject $request): DataObject
    {
        /** @var DataObject[] $apiResult */
        $apiResult = $this->shipmentManagement->createLabels([$request->getData('package_id') => $request]);

        // one request, one response.
        return $apiResult[0];
    }

    /**
     * Delete requested shipments if the current shipment request is failed
     *
     * In case one request succeeded and another request failed, Magento will
     * discard the successfully created label. That means, labels created through
     * GLS API must be cancelled.
     *
     * @param string[][] $data Arrays of info data with tracking_number and label_content
     * @return bool
     */
    public function rollBack($data): bool
    {
        if (!is_array($data) || empty($data)) {
            return parent::rollBack($data);
        }

        $cancelRequests = [];
        foreach ($data as $rollbackInfo) {
            $trackNumber = $rollbackInfo['tracking_number'];
            $cancelRequests[$trackNumber] = $this->trackRequestFactory->create(
                [
                    'storeId' => $this->getData('store'),
                    'trackNumber' => $trackNumber,
                ]
            );
        }

        $result = $this->shipmentManagement->cancelLabels($cancelRequests);
        $errors = array_filter(
            $result,
            static function (TrackResponseInterface $trackResponse) {
                return ($trackResponse instanceof TrackErrorResponseInterface);
            }
        );

        return (empty($errors) && parent::rollBack($data));
    }

    public function isCityRequired(): bool
    {
        try {
            return $this->getProxyCarrier()->isCityRequired();
        } catch (LocalizedException $exception) {
            return parent::isCityRequired();
        }
    }

    public function isZipCodeRequired($countryId = null): bool
    {
        try {
            return $this->getProxyCarrier()->isZipCodeRequired($countryId);
        } catch (LocalizedException $exception) {
            return parent::isZipCodeRequired($countryId);
        }
    }

    /**
     * Returns tracking information.
     *
     * @param string $shipmentNumber
     * @return TrackingResult
     *
     * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::getTrackingInfo
     */
    public function getTracking(string $shipmentNumber): TrackingResult
    {
        $result = $this->_trackFactory->create();

        $statusData = [
            'tracking' => $shipmentNumber,
            'carrier_title' => $this->getConfigData('title'),
            'url' => sprintf(self::TRACKING_URL_TEMPLATE, $shipmentNumber),
        ];

        $status = $this->_trackStatusFactory->create(['data' => $statusData]);
        $result->append($status);

        return $result;
    }
}
