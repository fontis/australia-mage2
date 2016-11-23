<?php
/**
 * Fontis Australia Extension for Magento 2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @copyright  Copyright (c) 2016 Fontis Pty. Ltd. (https://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Fontis\Australia\Controller\Adminhtml\Eparcel;

use Exception;
use Fontis\Australia\Model\Eparcel\Export\Exporter;
use Fontis\Australia\Model\Eparcel\Export\Preparer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;

class ExportOrders extends Action
{
    const ADMINHTML_SALES_ORDER_INDEX = 'sales/order/index';

    const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * Factory to create file model
     *
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var Exporter
     */
    protected $eparcelExporter;

    /**
     * @var Preparer
     */
    protected $eparcelPreparer;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param Exporter $eparcelExporter
     * @param Preparer $eparcelPreparer
     */
    public function __construct(Context $context, FileFactory $fileFactory, Exporter $eparcelExporter, Preparer $eparcelPreparer)
    {
        $this->fileFactory = $fileFactory;
        $this->eparcelExporter = $eparcelExporter;
        $this->eparcelPreparer = $eparcelPreparer;
        parent::__construct($context);
    }

    /**
     * Generate and export a CSV file for the given orders.
     *
     * @return ResponseInterface
     */
    public function execute()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            // No posted data, send back to order grid page
            $this->getMessageManager()->addErrorMessage(__('No orders found.'));
            return $this->_redirect(self::ADMINHTML_SALES_ORDER_INDEX);
        }

        $orderIds = $request->getPost('selected', array());
        if (empty($orderIds)) {
            $this->getMessageManager()->addErrorMessage(__('No orders found.'));
            return $this->_redirect(self::ADMINHTML_SALES_ORDER_INDEX);
        }

        try {
            $records = $this->eparcelPreparer->prepareOrderData($orderIds);
            $filePath = $this->eparcelExporter->exportCsv($records);
            return $this->fileFactory->create(basename($filePath), array("type" => "filename", "value" => basename($filePath)), DirectoryList::TMP);
        } catch (LocalizedException $e) {
            $this->getMessageManager()->addError($e->getMessage());
            return $this->_redirect(self::ADMINHTML_SALES_ORDER_INDEX);
        } catch (Exception $e) {
            $this->getMessageManager()->addError(__("An unknown error occurred while exporting eParcel data for the selected orders."));
            return $this->_redirect(self::ADMINHTML_SALES_ORDER_INDEX);
        }
    }
}
