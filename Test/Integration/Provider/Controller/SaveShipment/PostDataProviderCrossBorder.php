<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Test\Integration\Provider\Controller\SaveShipment;

use Magento\Sales\Api\Data\OrderInterface;

class PostDataProviderCrossBorder
{
    private static function getProductCode(OrderInterface $order): string
    {
        $carrierCode = strtok((string)$order->getShippingMethod(), '_');
        return str_replace("{$carrierCode}_", '', (string)$order->getShippingMethod());
    }

    /**
     * Pack all order items into one package.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function singlePackageCrossBorder(OrderInterface $order): array
    {
        $package = [
            'packageId' => '1',
            'items' => [],
            'package' => [
                'packageDetails' => [
                    'productCode' => self::getProductCode($order),
                    'packagingWeight' => '0.33',
                    'weight' => '0',
                    'weightUnit' => 'POUND',
                ],
                'packageCustoms' => [
                    'customsValue' => $order->getGrandTotal(),
                    'termsOfTrade' => 50,
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

            $itemCustoms = [
                'customsValue' => $orderItem->getPrice(),
            ];

            $package['items'][$orderItem->getItemId()]['itemCustoms'] = $itemCustoms;
            $rowWeight = $orderItem->getWeight() * $orderItem->getQtyOrdered();
            $package['package']['packageDetails']['weight'] += $rowWeight;
        }

        return ['packages' => [$package]];
    }

    /**
     * Pack each order item into an individual package.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function multiPackageCrossBorder(OrderInterface $order): array
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

            $itemCustoms = [
                'customsValue' => $orderItem->getPrice(),
            ];

            $packageDetails =  [
                'productCode' => self::getProductCode($order),
                'packagingWeight' => '0.33',
                'weight' => $orderItem->getWeight() * $orderItem->getQtyOrdered(),
                'weightUnit' => 'POUND',
            ];

            $packageCustoms = [
                'customsValue' => $order->getGrandTotal(),
                'termsOfTrade' => 50,
            ];

            $packages[] = [
                'packageId' => $packageId,
                'items' => [
                    $orderItem->getItemId() => [
                        'details' => $itemDetails,
                        'itemCustoms' => $itemCustoms
                    ]
                ],
                'package' => [
                    'packageDetails' => $packageDetails,
                    'packageCustoms' => $packageCustoms
                ]
            ];

            $packageId++;
        }

        return ['packages' => $packages];
    }
}
