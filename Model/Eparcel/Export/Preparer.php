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

namespace Fontis\Australia\Model\Eparcel\Export;

use Fontis\Australia\Helper\Eparcel as EparcelHelper;
use Fontis\Australia\Model\Eparcel\Parcel\Carton;
use Fontis\Australia\Model\Eparcel\Parcel\CartonFactory;
use Fontis\Australia\Model\Eparcel\Parcel\Parcel;
use Fontis\Australia\Model\Eparcel\Record\ArticleRecordFactory;
use Fontis\Australia\Model\Eparcel\Record\ConsignmentRecord;
use Fontis\Australia\Model\Eparcel\Record\ConsignmentRecordFactory;
use Fontis\Australia\Model\Eparcel\Record\CubicWeightRecordFactory;
use Fontis\Australia\Model\Eparcel\Record\GoodRecordFactory;
use Fontis\Australia\Model\Eparcel\Record\RecordContainer;
use Fontis\Australia\Model\Eparcel\Record\RecordContainerFactory;
use Fontis\Australia\Model\Shipping\Carrier\Eparcel as EparcelCarrier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Store\Model\ScopeInterface;

class Preparer
{
    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /** @var EparcelHelper */
    protected $eparcelHelper;

    /** @var File */
    protected $fileDriver;

    /** @var GoodRecordFactory */
    protected $goodRecordFactory;

    /** @var CartonFactory */
    protected $cartonFactory;

    /** @var CubicWeightRecordFactory */
    protected $cubicWeightRecordFactory;

    /** @var ArticleRecordFactory */
    protected $articleRecordFactory;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var ConsignmentRecordFactory */
    protected $consignmentFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EparcelHelper $eparcelHelper
     * @param File $fileDriver
     * @param GoodRecordFactory $goodRecordFactory
     * @param CartonFactory $cartonFactory
     * @param CubicWeightRecordFactory $cubicWeightRecordFactory
     * @param ArticleRecordFactory $articleRecordFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ConsignmentRecordFactory $consignmentFactory
     * @param RecordContainerFactory $recordContainerFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EparcelHelper $eparcelHelper,
        File $fileDriver,
        GoodRecordFactory $goodRecordFactory,
        CartonFactory $cartonFactory,
        CubicWeightRecordFactory $cubicWeightRecordFactory,
        ArticleRecordFactory $articleRecordFactory,
        OrderRepositoryInterface $orderRepository,
        ConsignmentRecordFactory $consignmentFactory,
        RecordContainerFactory $recordContainerFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->eparcelHelper = $eparcelHelper;
        $this->fileDriver = $fileDriver;
        $this->goodRecordFactory = $goodRecordFactory;
        $this->cartonFactory = $cartonFactory;
        $this->cubicWeightRecordFactory = $cubicWeightRecordFactory;
        $this->articleRecordFactory = $articleRecordFactory;
        $this->orderRepository = $orderRepository;
        $this->consignmentFactory = $consignmentFactory;
        $this->recordContainerFactory = $recordContainerFactory;
    }

    /**
     * Implementation of abstract method to export given orders to csv file in var/export.
     *
     * @param int[]|OrderInterface[] $orders List of orders of type Mage_Sales_Model_Order or order IDs to export.
     * @return RecordContainer
     */
    public function prepareOrderData($orders)
    {
        $recordContainer = $this->recordContainerFactory->create();

        foreach ($orders as $order) {
            if (!$order instanceof OrderInterface) {
                $order = $this->orderRepository->get($order);
            }
            $this->addRecordsForOrder($recordContainer, $order);
        }

        return $recordContainer;
    }

    /**
     * @param RecordContainer $recordContainer
     * @param OrderInterface $order
     * @return Carton
     * @throws LocalizedException
     */
    private function addRecordsForOrder(RecordContainer $recordContainer, OrderInterface $order)
    {
        $shippingMethod = $order->getShippingMethod(true);

        if ($shippingMethod->getCarrierCode() != EparcelCarrier::CARRIER_CODE) {
            throw new LocalizedException(__("Order #" . $order->getIncrementId() . " doesn't use Australia Post eParcel as its carrier!"));
        }

        /** @var $orderItems OrderItemInterface[] */
        $orderItems = $order->getItemsCollection();
        $currentParcel = $this->getNewParcel($order);
        $consignmentRecord = $this->getConsignmentRecord($order);

        foreach ($orderItems as $item) {
            // Check item is valid
            if ($item->isDummy(true)) {
                continue;
            }

            // Calculate item quantity
            $itemQuantity = $item->getQtyOrdered() - $item->getQtyCanceled() - $item->getQtyShipped();

            // Check item quantity
            if ($itemQuantity == 0) {
                continue;
            }

            /*
             * Populate Good Record
             *
             * UPDATE 2010.06.16 : Auspost support has said that we should only have ONE good record
             * per consignment (though their documentation says otherwise)
             */
            $goodRecord = $this->goodRecordFactory->create();
            $goodRecord->originCountryCode = '';
            $goodRecord->hsTariffCode = '';
            $goodRecord->description = substr(str_replace(',', '', $item->getName()), 0, 50); // remove commas and cap at maximum length
            $goodRecord->productType = $this->getDefault('good/product_type');
            $goodRecord->productClassification = null;
            $goodRecord->quantity = $itemQuantity;
            $goodRecord->weight = max($item->getWeight(), 0);
            $goodRecord->unitvalue = max($item->getPrice() + $item->getTaxAmount(), 0);
            $goodRecord->totalValue = max($goodRecord->unitValue * $goodRecord->quantity, 0);

            // We have at least one Good, time to add the consignmentRecord if not done yet
            if (!$consignmentRecord->isAddedToEparcel()) {
                $recordContainer->addRecord($consignmentRecord);
            }

            // If current parcel can't fit extra item, close it, and open new parcel
            if (!$currentParcel->canAddGoodRecord($goodRecord)) {
                $this->closeParcel($order, $recordContainer, $currentParcel);
                $currentParcel = $this->getNewParcel($order);
            }

            // Add item to Parcel
            $currentParcel->addGoodRecord($goodRecord);
        }

        $this->closeParcel($order, $recordContainer, $currentParcel);
    }

    /**
     * @param OrderInterface $order
     * @return Carton
     */
    protected function getNewParcel(OrderInterface $order)
    {
        $storeId = $order->getStoreId();
        $parcel = $this->cartonFactory->create();
        $parcel->setInsuranceRequired($this->scopeConfig->isSetFlag('carriers/eparcel/insurance_enable', ScopeInterface::SCOPE_STORE, $storeId));
        $parcel->weightMax = $this->getDefault('parcel/weightmax', $storeId);
        $parcel->width = (int) $this->getDefault('parcel/width', $storeId);
        $parcel->height = (int) $this->getDefault('parcel/height', $storeId);
        $parcel->length = (int) $this->getDefault('parcel/length', $storeId);

        return $parcel;
    }

    /**
     * @param OrderInterface $order
     * @param RecordContainer $recordContainer
     * @param Parcel $parcel
     * @return bool
     */
    protected function closeParcel(OrderInterface $order, RecordContainer $recordContainer, Parcel $parcel)
    {
        if ($this->getDefault('parcel/use_cubicweight', $order->getStoreId(), true)) {
            $articleRecord = $this->cubicWeightRecordFactory->create();
        } else {
            $articleRecord = $this->articleRecordFactory->create();
        }

        $goodRecords = $parcel->getGoodRecords();

        if (count($goodRecords) == 0) {
            return false;
        }

        $recordContainer->addRecord(
            $parcel->processArticleRecord($articleRecord)
        );

        if ($this->getDefault('good/use_multiplegoodrecords', $order->getStoreId(), true)) {
            foreach ($parcel->getGoodRecords() as $_goodRecord) {
                $recordContainer->addRecord($_goodRecord);
            }
        } else {
            $goodRecord = $this->goodRecordFactory->create();
            $goodRecord->originCountryCode = '';
            $goodRecord->hsTariffCode = '';
            $goodRecord->productClassification = null;
            $goodRecord->quantity = 1;

            foreach ($parcel->getGoodRecords() as $_goodRecord) {
                // Set product type and description
                $goodRecord->productType = $_goodRecord->productType;
                $goodRecord->description = str_replace(',', '', $_goodRecord->description); // remove commas

                // Add weight * quantity
                $goodRecord->weight += $_goodRecord->weight * $_goodRecord->quantity;
                $goodRecord->unitValue += $_goodRecord->unitValue * $_goodRecord->quantity;
                $goodRecord->totalValue += $_goodRecord->totalValue;
            }

            $recordContainer->addRecord($goodRecord);
        }

        return true;
    }

    /**
     * @param OrderInterface $order
     * @return ConsignmentRecord
     */
    protected function getConsignmentRecord(OrderInterface $order)
    {
        $storeId = $order->getStoreId();

        $consignmentRecord = $this->consignmentFactory->create();
        $consignmentRecord->chargeCode = $this->_getChargeCode($order);

        $consignmentRecord->isSignatureRequired = $this->getDefault('consignment/is_signature_required', $storeId, true);
        $consignmentRecord->addToAddressBook = $this->getDefault('consignment/add_to_address_book', $storeId);
        $consignmentRecord->isRefPrintRequired = $this->getDefault('consignment/print_ref1', $storeId, true);
        $consignmentRecord->isRef2PrintRequired = $this->getDefault('consignment/print_ref2', $storeId, true);

        /** @var $shippingAddress Address */
        $shippingAddress = $order->getShippingAddress();
        $consignmentRecord->consigneeName = $shippingAddress->getName();
        $consignmentRecord->consigneeAddressLine1 = $shippingAddress->getStreet1();
        $consignmentRecord->consigneeAddressLine2 = $shippingAddress->getStreet2();
        $consignmentRecord->consigneeAddressLine3 = $shippingAddress->getStreet3();
        $consignmentRecord->consigneeAddressLine4 = $shippingAddress->getStreet4();
        $consignmentRecord->consigneeSuburb = $shippingAddress->getCity();
        $consignmentRecord->consigneeStateCode = $shippingAddress->getRegionCode();
        $consignmentRecord->consigneePostcode = $shippingAddress->getPostcode();
        $consignmentRecord->consigneeCountryCode = $shippingAddress->getCountryId();
        $consignmentRecord->consigneePhoneNumber = $shippingAddress->getTelephone();
        $consignmentRecord->ref = $order->hasInvoices() ? $order->getInvoiceCollection()->getLastItem()->getIncrementId() : "";
        $consignmentRecord->ref2 = $order->getRealOrderId();

        if ($this->eparcelHelper->isEmailNotificationEnabled($storeId)) {
            $consignmentRecord->consigneeEmailAddress = $order->getCustomerEmail();
            $consignmentRecord->emailNotification = $this->eparcelHelper->getEmailNotificationLevel($storeId);
        }

        return $consignmentRecord;
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    protected function _getChargeCode(OrderInterface $order)
    {
        list ($carrierCode, $chargeCode) = explode('_', $order->getData('shipping_method'));

        $chargeCode = strtoupper($chargeCode);
        if ($this->eparcelHelper->isValidChargeCode($chargeCode)) {
            return $chargeCode;
        }

        /* Is this customer is in a ~business~ group ? */
        $isBusinessCustomer = in_array($order->getCustomerGroupId(), explode(',', $this->getDefault("charge_codes/business_groups", $order->getStoreId())));

        return $isBusinessCustomer ?
            $this->getDefault("charge_codes/default_charge_code_business", $order->getStoreId()) :
            $this->getDefault("charge_codes/default_charge_code_individual", $order->getStoreId());
    }

    /**
     * @param string $key
     * @param mixed $scope
     * @param bool $isBooleanSetting
     * @return mixed
     */
    protected function getDefault($key, $scope = null, $isBooleanSetting = false)
    {
        if ($isBooleanSetting) {
            return $this->scopeConfig->isSetFlag('fontis_eparcelexport/' . $key, ScopeInterface::SCOPE_STORE, $scope);
        } else {
            return $this->scopeConfig->getValue('fontis_eparcelexport/' . $key, ScopeInterface::SCOPE_STORE, $scope);
        }
    }
}
