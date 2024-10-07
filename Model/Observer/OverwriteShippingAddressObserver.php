<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Model\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;

/**
 * Class OverwriteShippingAddressObserver
 */
class OverwriteShippingAddressObserver implements ObserverInterface
{

    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }
    public function execute(EventObserver $observer)
    {
        $quoteAddress = $observer->getQuote()->getShippingAddress();
        $orderAddress = $observer->getOrder()->getShippingAddress();
        if ($quoteAddress->getGlsRelayPointId()) {
            $orderAddress->setGlsRelayPointId($quoteAddress->getGlsRelayPointId());

            $orderAddress->setCity($this->session->getGlsRelayPoint()->getCity());
            $orderAddress->setPostcode($this->session->getGlsRelayPoint()->getZipcode());
            $orderAddress->setCity($this->session->getGlsRelayPoint()->getCity());
            $orderAddress->setCompany($this->session->getGlsRelayPoint()->getName());
            $orderAddress->setStreet([$this->session->getGlsRelayPoint()->getAddress()]);
        }
        return $this;
    }
}
