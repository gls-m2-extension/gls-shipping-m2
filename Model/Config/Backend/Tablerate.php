<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Model\Config\Backend;

/**
 * Backend model for shipping table rates CSV importing
 *
 * @author Magento Core Team <core@magentocommerce.com>
 */
class Tablerate extends \Magento\Config\Model\Config\Backend\File
{

    /**
     * @param string $filePath
     * @return \Magento\Framework\Filesystem\File\ReadInterface
     */
    private function getCsvFile()
    {
        $fileData = $this->getFileData();
        if (!empty($fileData['tmp_name'])) {
            $filePath = $fileData['tmp_name'];
            $tmpDirectory = $this->_filesystem->getDirectoryRead(\Magento\Framework\Filesystem\DirectoryList::SYS_TMP);
            $path = $tmpDirectory->getRelativePath($filePath);
            return $tmpDirectory->openFile($path);
        }
    }

    public function beforeSave()
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function save()
    {
        $columns = [
            'subtotal',
            'dest_country',
            'dest_region',
            'dest_zip',
            'weight',
            'price',
        ];
        $csvFile = $this->getCsvFile();
        //csv header
        if ($csvFile) {
            $csvFile->readCsv();
            $data = [];
            while ($dataLine = $csvFile->readCsv()) {
                if (empty($dataLine[0])) {
                    continue;
                }
                if (count($dataLine) != count($columns)) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Invalid Table Rates File Format')
                    );
                }
                $data[] = array_combine($columns, $dataLine);
            }
            $this->setValue(\json_encode($data));
            return parent::save();
        }
        return $this;
    }
}
