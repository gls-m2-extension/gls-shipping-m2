<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Block\Adminhtml\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
/**
 * Export CSV button for shipping table rates
 */
class Export extends AbstractElement
{

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $backendUrl;

    /**
     * @param \Magento\Framework\Data\Form\Element\Factory           $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper                             $escaper
     * @param array                                                  $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        array $data = []
    ) {

        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->backendUrl = $backendUrl;
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        /**
 * @var \Magento\Backend\Block\Widget\Button $buttonBlock
*/
        $buttonBlock = $this->getForm()->getParent()->getLayout()->createBlock('Magento\Backend\Block\Widget\Button');

        $params = ['website' => $buttonBlock->getRequest()->getParam('website')];

        $url = $this->backendUrl->getUrl("*/*/csvExportTablerates", $params);
        $data = [
            'label' => __('Export CSV'),
            'onclick' => "setLocation('" .
            $url .
            "gls_parcelshop_tablerates.csv' )",
            'class' => '',
        ];

        $html = $buttonBlock->setData($data)->toHtml();
        return $html;
    }
}
