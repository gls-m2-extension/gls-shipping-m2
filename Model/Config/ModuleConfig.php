<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Netresearch\ShippingCore\Api\InfoBox\VersionInterface;
use Netresearch\ShippingCore\Api\Rate\ProxyCarrierConfigInterface;

class ModuleConfig implements VersionInterface, ProxyCarrierConfigInterface
{
    // Defaults
    private const CONFIG_PATH_VERSION = 'carriers/glsgroup/version';

    // 100_general_settings.xml
    private const CONFIG_PATH_CUT_OFF_TIMES = 'carriers/glsgroup/general/cut_off_times';

    public const CONFIG_PATH_ENABLE_LOGGING = 'carriers/glsgroup/general/logging';
    public const CONFIG_PATH_LOGLEVEL = 'carriers/glsgroup/general/logging_group/loglevel';

    // 200_account_settings.xml
    private const CONFIG_PATH_SANDBOX_MODE = 'carriers/glsgroup/account/sandboxmode';
    private const CONFIG_PATH_USER_NAME = 'carriers/glsgroup/account/api_username';
    private const CONFIG_PATH_PASSWORD = 'carriers/glsgroup/account/api_password';
    private const CONFIG_PATH_CUSTOMER_ID = 'carriers/glsgroup/account/customer_id';
    private const CONFIG_PATH_CONTACT_ID = 'carriers/glsgroup/account/contact_id';
    private const CONFIG_PATH_BROKER_REFERENCE = 'carriers/glsgroup/account/broker_reference';

    // 250_shipit_settings.xml
    private const CONFIG_PATH_SHIPIT_ENABLED     = 'carriers/glsgroup/shipit/enabled';
    private const CONFIG_PATH_SHIPIT_SANDBOX_MODE = 'carriers/glsgroup/shipit/sandbox_mode';
    private const CONFIG_PATH_SHIPIT_CLIENT_ID   = 'carriers/glsgroup/shipit/client_id';
    private const CONFIG_PATH_SHIPIT_CLIENT_SECRET = 'carriers/glsgroup/shipit/client_secret';
    private const CONFIG_PATH_SHIPIT_CONTACT_ID  = 'carriers/glsgroup/shipit/contact_id';

    // 400_checkout_settings.xml
    private const CONFIG_PATH_PROXY_CARRIER = 'carriers/glsgroup/checkout/emulated_carrier';
    private const CONFIG_PATH_SHIPPING_METHOD_TITLE = 'carriers/glsgroup/checkout/method_title';

    // 500_shipment_defaults.xml
    private const CONFIG_PATH_LABEL_SIZE = 'carriers/glsgroup/shipment_defaults/label_size';
    private const CONFIG_PATH_PACKAGE_DEFAULT_WEIGHT = 'carriers/glsgroup/shipment_defaults/package_default_weight';
    private const CONFIG_PATH_TERMS_OF_TRADE = 'carriers/glsgroup/shipment_defaults/terms_of_trade';
    private const CONFIG_PATH_SEND_SHIPPER = 'carriers/glsgroup/shipment_defaults/send_shipper';
    private const CONFIG_PATH_USE_SHOPRETURN = 'carriers/glsgroup/shipment_defaults/shopreturn';
    private const CONFIG_PATH_USE_LETTERBOX = 'carriers/glsgroup/shipment_defaults/letterbox';
    private const CONFIG_PATH_ENABLE_ALT_RETURN_ADDRESS = 'carriers/glsgroup/shipment_defaults/return_address';
    private const CONFIG_PATH_ALT_RETURN_ADDRESS = 'carriers/glsgroup/shipment_defaults/alt_return_address';

    // 600_additional_services.xml
    private const CONFIG_PATH_FLEXDELIVERY_REVOCATION_EMAIL = 'carriers/glsgroup/additional_services/flexdelivery_identity';
    private const CONFIG_PATH_FLEXDELIVERY_ADJUSTMENT = 'carriers/glsgroup/additional_services/flexdelivery_adjustment';
    private const CONFIG_PATH_DEPOSIT_ADJUSTMENT = 'carriers/glsgroup/additional_services/deposit_adjustment';
    private const CONFIG_PATH_GUARANTEED24_ADJUSTMENT = 'carriers/glsgroup/additional_services/guaranteed24_adjustment';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TimezoneInterface $timezone
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_VERSION);
    }

    /**
     * Obtain the list of cut-off times, applied to the upcoming days (max. seven entries).
     *
     * @param mixed $store
     * @return \DateTime[]
     */
    public function getCutOffTimes($store = null): array
    {
        $cutOffTimes = $this->scopeConfig->getValue(
            self::CONFIG_PATH_CUT_OFF_TIMES,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        $cutOffTimes = array_column($cutOffTimes, 'time', 'day');

        $days = [];
        for ($i = 0; $i <= 6; $i++) {
            $day = $this->timezone->scopeDate($store)->modify("+$i day");
            $weekDay = $day->format('N');
            if (!isset($cutOffTimes[$weekDay])) {
                // no cut-off configured for the given day, next.
                continue;
            }

            $cutOffTime =  explode(':', $cutOffTimes[$weekDay]);
            list($hours, $minutes) = array_map('intval', $cutOffTime);
            $day->setTime($hours, $minutes);
            $days[$weekDay] = $day;
        }

        return $days;
    }

    /**
     * Get the logging status.
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_LOGGING);
    }

    /**
     * Get the log level.
     *
     * @return int
     */
    public function getLogLevel(): int
    {
        return (int) $this->scopeConfig->getValue(self::CONFIG_PATH_LOGLEVEL);
    }

    /**
     * Returns true if sandbox mode is enabled.
     *
     * @param mixed $store
     * @return bool
     */
    public function isSandboxMode($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_SANDBOX_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the user's name (API user credentials).
     *
     * @param mixed $store
     * @return string
     */
    public function getUserName($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_USER_NAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the password (API user credentials).
     *
     * @param mixed $store
     * @return string
     */
    public function getPassword($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the shipper id.
     *
     * @param mixed $store
     * @return string
     */
    public function getShipperId($store = null): string
    {
        $customerId = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_CUSTOMER_ID,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        $contactId = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_CONTACT_ID,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $customerId . ' ' . $contactId;
    }

    /**
     * Get the broker reference.
     *
     * @return string
     */
    public function getBrokerReference(): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_BROKER_REFERENCE);
    }

    /**
     * Returns true if the ShipIt API is enabled for the given store.
     *
     * @param mixed $store
     * @return bool
     */
    public function isShipItEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_SHIPIT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Returns true if the ShipIt sandbox environment should be used.
     *
     * @param mixed $store
     * @return bool
     */
    public function isShipItSandboxMode($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_SHIPIT_SANDBOX_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the ShipIt OAuth client ID.
     *
     * @param mixed $store
     * @return string
     */
    public function getShipItClientId($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SHIPIT_CLIENT_ID,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the ShipIt OAuth client secret.
     *
     * @param mixed $store
     * @return string
     */
    public function getShipItClientSecret($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SHIPIT_CLIENT_SECRET,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the default package weight (kg) used when the product has no weight configured.
     *
     * @param mixed $store
     * @return float
     */
    public function getPackageDefaultWeight($store = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PACKAGE_DEFAULT_WEIGHT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the ShipIt shipper contact ID (Shipper.ContactID in shipment requests).
     *
     * @param mixed $store
     * @return string
     */
    public function getShipItContactId($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SHIPIT_CONTACT_ID,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the code of the carrier to forward rate requests to.
     *
     * @param mixed $store
     * @return string
     */
    public function getProxyCarrierCode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PROXY_CARRIER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain the carrier method title for checkout presentation.
     *
     * @param mixed $store
     * @return string
     */
    public function getMethodTitle($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SHIPPING_METHOD_TITLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain the preferred document size for shipping labels.
     *
     * @param mixed $store
     * @return string
     */
    public function getLabelSize($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LABEL_SIZE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Determine whether the shipping origin setting should be used as shipper address or not.
     *
     * @param mixed $store
     * @return bool
     */
    public function isSendFromStoreShippingOrigin($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_SEND_SHIPPER, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Obtain configured alternative return address.
     *
     * - company
     * - country_id
     * - postcode
     * - city
     * - street
     *
     * @param mixed $store
     * @return string[] Empty array if alternative address should not be used, address details otherwise.
     */
    public function getAlternativeReturnAddress($store = null): array
    {
        $useAlternativeAddress = $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_ALT_RETURN_ADDRESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        if (!$useAlternativeAddress) {
            return [];
        }

        return (array) $this->scopeConfig->getValue(
            self::CONFIG_PATH_ALT_RETURN_ADDRESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain email address used for revoking consent of transmitting the consumer email.
     *
     * @param mixed $store
     * @return string
     */
    public function getFlexDeliveryRevocationEmail($store = null): string
    {
        $ident = $this->scopeConfig->getValue(
            self::CONFIG_PATH_FLEXDELIVERY_REVOCATION_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return (string) $this->scopeConfig->getValue(
            'trans_email/ident_' . $ident . '/email',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain the amount to be added to / reduced from the shipping cost if flex delivery service was chosen.
     *
     * @param mixed $store
     * @return float
     */
    public function getFlexDeliveryAdjustment($store = null): float
    {
        $amount = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_FLEXDELIVERY_ADJUSTMENT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return (float) str_replace(',', '.', $amount);
    }

    /**
     * Obtain the amount to be added to / reduced from the shipping cost if deposit service was chosen.
     *
     * @param mixed $store
     * @return float
     */
    public function getDepositAdjustment($store = null): float
    {
        $amount = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_DEPOSIT_ADJUSTMENT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return (float) str_replace(',', '.', $amount);
    }

    /**
     * Obtain the amount to be added to / reduced from the shipping cost if next day service was chosen.
     *
     * @param mixed $store
     * @return float
     */
    public function getG24Adjustment($store = null): float
    {
        $amount = (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_GUARANTEED24_ADJUSTMENT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return (float) str_replace(',', '.', $amount);
    }
}
