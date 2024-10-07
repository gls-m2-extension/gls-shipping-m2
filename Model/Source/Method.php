<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Method implements OptionSourceInterface
{
    protected $_code = 'method';


    public function toOptionArray()
    {
        return [
            ['value' => 'standard', 'label' => 'Standard'],
            ['value' => 'parcelshop', 'label' => 'Parcel Shop']
        ];
    }
}
