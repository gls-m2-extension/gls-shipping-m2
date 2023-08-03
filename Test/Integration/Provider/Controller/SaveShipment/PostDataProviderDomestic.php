<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Test\Integration\Provider\Controller\SaveShipment;

use Magento\Sales\Api\Data\OrderInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes;

/**
 * Prepare POST data as sent to the `admin/order_shipment/save` controller
 */
class PostDataProviderDomestic
{
    private static function getProductCode(OrderInterface $order): string
    {
        $carrierCode = strtok((string)$order->getShippingMethod(), '_');
        return str_replace("{$carrierCode}_", '', (string)$order->getShippingMethod());
    }

    /**
     * Pack all order items into one package. Cross-border data is omitted.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function singlePackageDomestic(OrderInterface $order): array
    {
        $package = [
            'packageId' => '1',
            'items' => [],
            'package' => [
                'packageDetails' => [
                    'productCode' => self::getProductCode($order),
                    'packagingWeight' => '0.33',
                    'weight' => '0',
                    'weightUnit' => 'KILOGRAM',
                ]
            ]
        ];

        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $package['items'][$orderItem->getItemId()]['details'] = $itemDetails;

            $rowWeight = $orderItem->getWeight() * $orderItem->getQtyOrdered();
            $package['package']['packageDetails']['weight'] += $rowWeight;
        }

        $package['package']['packageDetails']['weight'] += $package['package']['packageDetails']['packagingWeight'];

        return ['packages' => [$package]];
    }

    public static function singlePackageDomesticWithCod(OrderInterface $order): array
    {
        $codAmount = $order->getBaseShippingAmount();
        $package = [
            'packageId' => '1',
            'items' => [],
            'package' => [
                'packageDetails' => [
                    'productCode' => self::getProductCode($order),
                    'packagingWeight' => '0.33',
                    'weight' => '0',
                    'weightUnit' => 'KILOGRAM',
                ]
            ]
        ];

        foreach ($order->getItems() as $orderItem) {
            $codAmount += $orderItem->getBasePrice();
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $package['items'][$orderItem->getItemId()]['details'] = $itemDetails;

            $rowWeight = $orderItem->getWeight() * $orderItem->getQtyOrdered();
            $package['package']['packageDetails']['weight'] += $rowWeight;
        }

        $package['package']['packageDetails']['weight'] += $package['package']['packageDetails']['packagingWeight'];
        $package['service'][Codes::SERVICE_OPTION_CASH_ON_DELIVERY] = [
            'enabled' => true,
            'reasonForPayment' => sprintf('Foo Bar #%s', $order->getIncrementId()),
        ];

        return ['packages' => [$package]];
    }

    /**
     * Pack each order item into an individual package. Cross-border data is omitted.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function multiPackageDomestic(OrderInterface $order): array
    {
        $packages = [];

        $packageId = 1;
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $packagingWeight = '0.33';
            $packageDetails = [
                'productCode' => self::getProductCode($order),
                'packagingWeight' => $packagingWeight,
                'weight' => $orderItem->getWeight() * $orderItem->getQtyOrdered() + (float)$packagingWeight,
                'weightUnit' => 'KILOGRAM',
            ];

            $packages[] = [
                'packageId' => $packageId,
                'items' => [
                    $orderItem->getItemId() => ['details' => $itemDetails]
                ],
                'package' => [
                    'packageDetails' => $packageDetails,
                ]
            ];

            $packageId++;
        }

        return ['packages' => $packages];
    }

    /**
     * Pack each order item into an individual package and use cod service.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function multiPackageDomesticWithCod(OrderInterface $order): array
    {
        $packages = [];

        $packageId = 1;
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $packagingWeight = '0.33';
            $packageDetails = [
                'productCode' => self::getProductCode($order),
                'packagingWeight' => $packagingWeight,
                'weight' => $orderItem->getWeight() * $orderItem->getQtyOrdered() + (float)$packagingWeight,
                'weightUnit' => 'KILOGRAM',
            ];

            $services = [
                Codes::SERVICE_OPTION_CASH_ON_DELIVERY => [
                    'enabled' => true,
                    'reasonForPayment' => sprintf('Foo Bar #%s', $order->getIncrementId()),
                ]
            ];

            $packages[] = [
                'packageId' => $packageId,
                'items' => [
                    $orderItem->getItemId() => ['details' => $itemDetails]
                ],
                'package' => [
                    'packageDetails' => $packageDetails,
                ],
                'service' => $services
            ];

            $packageId++;
        }

        return ['packages' => $packages];
    }
}
