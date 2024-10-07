<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Plugin;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;

/**
 * Class ShippingInformationManagementPlugin
 *
 * @package Oye\Deliverydate\Model\Checkout
 */
class ShippingInformationManagementPlugin
{

    private $session;

    /**
     * @param \Magento\Checkout\Model\Session\Proxy $session
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session
    )
    {

        $this->session = $session;
    }

    /**
     * @param ShippingInformationManagement $subject
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
                                      $cartId,
        ShippingInformationInterface  $addressInformation
    )
    {
        $relayPoint = $addressInformation->getShippingAddress()->getExtensionAttributes()->getRelayPoint();
        if ($relayPoint) {
            $addressInformation->getShippingAddress()->setGlsRelayPointId($relayPoint->getId());

            $this->session->setGlsRelayPoint($relayPoint);
        }

    }
}
