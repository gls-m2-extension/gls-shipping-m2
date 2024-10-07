<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Block\Adminhtml\System\Config\Form\Field;
use Magento\Backend\Block\Widget\Grid\Extended;
/**
 * Shipping carrier table rate grid block
 * WARNING: This grid used for export table rates
 */
class Grid extends Extended
{

    private $dataObjectFactory;
    private $collectionFactory;

    /**
     * @param \Magento\Backend\Block\Template\Context                                          $context
     * @param \Magento\Backend\Helper\Data                                                     $backendHelper
     * @param \Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory $collectionFactory
     * @param \Magento\OfflineShipping\Model\Carrier\Tablerate                                 $tablerate
     * @param array                                                                            $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Framework\Data\CollectionFactory $collectionFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\OfflineShipping\Model\Carrier\Tablerate $tablerate,
        array $data = []
    ) {

        $this->collectionFactory = $collectionFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->_tablerate = $tablerate;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Define grid properties
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('shippingTablerateGrid');
        $this->_exportPageSize = 10000;
    }

    /**
     * Prepare shipping table rate collection
     *
     * @return \Magento\OfflineShipping\Block\Adminhtml\Carrier\Tablerate\Grid
     */
    protected function _prepareCollection()
    {
        $path = 'carriers/glsgroup/parcelshop_price';

        $value = $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );

        $gridData = \json_decode($value, 1);

        $collection = $this->collectionFactory->create();
        if (is_array($gridData)) {
            foreach ($gridData as $data) {
                $object = $this->dataObjectFactory->create();
                $object->setData($data);
                $collection->addItem($object);
            }
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare table columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'subtotal',
            ['header' => __('Subtotal (and above)'), 'index' => 'subtotal', 'default' => '*']
        );
        $this->addColumn(
            'dest_country',
            ['header' => __('Country'), 'index' => 'dest_country', 'default' => '*']
        );

        $this->addColumn(
            'dest_region',
            ['header' => __('Region/State'), 'index' => 'dest_region', 'default' => '*']
        );

        $this->addColumn(
            'dest_zip',
            ['header' => __('Zip/Postal Code'), 'index' => 'dest_zip', 'default' => '*']
        );
        $this->addColumn(
            'weight',
            ['header' => __('Weight (and above)'), 'index' => 'weight', 'default' => '0']
        );

        $this->addColumn(
            'price',
            ['header' => __('Shipping Price'), 'index' => 'price']
        );

        return parent::_prepareColumns();
    }
}
