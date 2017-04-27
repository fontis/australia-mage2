<?php
/**
 * Fontis Australia Extension for Magento 2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @copyright  Copyright (c) 2017 Fontis Pty. Ltd. (https://www.fontis.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Fontis\Australia\Controller\Adminhtml\Eparcel;

use Fontis\Australia\Controller\Adminhtml\Eparcel;
use Fontis\Australia\Model\EparcelFactory;
use Fontis\Australia\Model\ResourceModel\Eparcel\CollectionFactory;
use Fontis\Australia\Model\ResourceModel\Eparcel\Collection;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Response\Http\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\FileFactory as FileDriverFactory;

class ExportTableRates extends Action
{
    const ADMIN_RESOURCE = "Magento_Sales::sales_order";

    /**
     * @var CollectionFactory
     */
    protected $eparcelCollectionFactory;

    /**
     * Factory to create file model
     *
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var FileDriverFactory
     */
    protected $fileDriverFactory;

    /**
     * @param Context $context
     * @param CollectionFactory $eparcelCollectionFactory
     * @param FileFactory $fileFactory
     * @param FileDriverFactory $fileDriverFactory
     */
    public function __construct(
        Context $context,
        CollectionFactory $eparcelCollectionFactory,
        FileFactory $fileFactory,
        FileDriverFactory $fileDriverFactory
    )
    {
        $this->eparcelCollectionFactory = $eparcelCollectionFactory;
        $this->fileFactory = $fileFactory;
        $this->fileDriverFactory = $fileDriverFactory;
        parent::__construct($context);
    }

    /**
     * Excute eparcel/exportTableRates action
     *
     * @return File
     */
    public function execute()
    {
        $rates = $this->eparcelCollectionFactory->create();

        $response = array(
            array(
                'Country',
                'Region/State',
                'Postcodes',
                'Weight from',
                'Weight to',
                'Parcel Cost',
                'Cost Per Kg',
                'Delivery Type',
                'Charge Code Individual',
                'Charge Code Business'
            )
        );

        foreach ($rates as $rate) {
            $countryId = $rate->getData('dest_country_id');
            $regionId = $rate->getData('dest_region_id');

            $response[] = array(
                $rate->getData('dest_country'),
                $rate->getData('dest_region'),
                $rate->getData('dest_zip'),
                $rate->getData('condition_from_value'),
                $rate->getData('condition_to_value'),
                $rate->getData('price'),
                $rate->getData('price_per_kg'),
                $rate->getData('delivery_type'),
                $rate->getData('charge_code_individual'),
                $rate->getData('charge_code_business')
            );
        }

        $temp = tmpfile();
        $csv = $this->fileDriverFactory->create();
        foreach ($response as $responseRow) {
            $csv->filePutCsv($temp, $responseRow);
        }

        rewind($temp);
        $contents = stream_get_contents($temp);
        fclose($temp);

        // prepare download contents and set response header
        return $this->fileFactory->create('tablerates.csv', $contents, DirectoryList::VAR_DIR);
    }
}
