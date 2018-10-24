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

namespace Fontis\Australia\Model\ClickAndSend;

use Fontis\Australia\Helper\ClickAndSend;
use Fontis\Australia\Helper\Data;
use Fontis\Australia\Model\Shipping\Carrier\AustraliaPost;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

class Export
{
    // The number of items in an order that click and send csv specification allows
    const MAX_ITEM_IN_AN_ORDER = 4;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var ClickAndSend
     */
    protected $clickAndSendHelper;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var AustraliaPost
     */
    protected $australiaPostCarrier;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $dataHelper
     * @param ClickAndSend $clickAndSendHelper
     * @param Filesystem $filesystem
     * @param DirectoryList $directoryList
     * @param AustraliaPost $australiaPostCarrier
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig,
        Data $dataHelper,
        ClickAndSend $clickAndSendHelper,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        AustraliaPost $australiaPostCarrier
    ) {
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->dataHelper = $dataHelper;
        $this->clickAndSendHelper = $clickAndSendHelper;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->australiaPostCarrier = $australiaPostCarrier;
    }

    /**
     * @param array $orderIds
     * @return string
     * @throws LocalizedException
     */
    public function exportOrders($orderIds)
    {
        $formattedOrders = array();

        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);
            $shippingMethod = $order->getShippingMethod();

            if (strpos($shippingMethod, AustraliaPost::CARRIER_CODE) !== 0) {
                throw new LocalizedException(__("Order #" . $order->getIncrementId() . " doesn't use Australia Post as its carrier!"));
            }

            $formattedOrders[] = $this->formatOrderData($order);
        }

        return $this->makeCsv($formattedOrders);
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    protected function formatOrderData($order)
    {
        $orderStoreId = $order->getStoreId();
        $shippingAddress = $order->getShippingAddress();

        $item = array(
            'addressCode' => '',
            'deliveryCompanyName' => $shippingAddress->getCompany(),
            'deliveryName' => $shippingAddress->getName(),
            'deliveryTelephone' => $shippingAddress->getTelephone(),
            'deliveryEmail' => $shippingAddress->getEmail(),
            'deliveryAddressLine1' => $shippingAddress->getStreet1(),
            'deliveryAddressLine2' => $shippingAddress->getStreet2(),
            'deliveryAddressLine3' => $shippingAddress->getStreet3(),
            'deliveryCity' => $shippingAddress->getCity(),
            'deliveryState' => $shippingAddress->getRegionCode(),
            'deliveryPostcode' => $shippingAddress->getPostcode(),
            'deliveryCountryCode' => $shippingAddress->getCountry(),
            'length' => $this->australiaPostCarrier->getAttribute($order, 'length'),
            'width' => $this->australiaPostCarrier->getAttribute($order, 'width'),
            'height' => $this->australiaPostCarrier->getAttribute($order, 'height'),
            'declaredWeight' => sprintf('%0.3f', $order->getWeight()),

            // Extra Cover doesn't work with Click & Send
            'extraCover' => '',

            'insuranceValue' => '',
            'descriptionOfGoods' => '',
            'categoryOfItems' => $this->scopeConfig->getValue('fontis_australia/clickandsend/category_of_items', ScopeInterface::SCOPE_STORE, $orderStoreId),

            // This number is sometimes needed when you're exporting a package to a foreign country.
            'exportDeclarationNumber' => '',

            'categoryOfItemsExplanation' => $this->scopeConfig->getValue('fontis_australia/clickandsend/category_of_items_explanation', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'articleLodgerName' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_name', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'nonDeliveryInstructions' => $this->scopeConfig->getValue('fontis_australia/clickandsend/nondelivery_instructions', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'returnAddress' => $this->scopeConfig->getValue('fontis_australia/clickandsend/return_address', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromName' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_name', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromCompanyName' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_company_name', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromPhone' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_phone', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromFax' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_fax', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromEmail' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_email', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromAbn' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_abn', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromAddressLine1' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_address_line_1', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromAddressLine2' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_address_line_2', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromAddressLine3' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_address_line_3', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromCity' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_city', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromState' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_state', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromPostcode' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_postcode', ScopeInterface::SCOPE_STORE, $orderStoreId),
            'fromCountry' => $this->scopeConfig->getValue('fontis_australia/clickandsend/from_country', ScopeInterface::SCOPE_STORE, $orderStoreId),

            // A value that can used for reconciliations with shipments, e.g. order number,
            // invoice number, recipient name, etc.
            'yourReference' => $order->getIncrementId(),

            'deliveryInstructions' => '',
            'additionalServices' => '',
            'boxOrIrregularShapedItem' => '',
            'sendersCustomReference' => '',
            'importersReferenceNumber' => '',
            'hasCommercialValue' => ''
        );

        // The Click & Send CSV specification only allows up to four items to be listed.
        $maxItems = self::MAX_ITEM_IN_AN_ORDER;

        // Initialise the items
        for ($i = 0; $i < $maxItems; $i++) {
            $item['itemCode' . $i] = '';
            $item['itemDescription' . $i] = '';
            $item['itemHsTariffNumber' . $i] = '';
            $item['itemCountryOfOrigin' . $i] = '';
            $item['itemQuantity' . $i] = '';
            $item['itemUnitPrice' . $i] = '';
            $item['itemUnitWeight' . $i] = '';
        }

        $allSimpleItems = $this->dataHelper->getAllSimpleItems($order);

        for ($i = 0; $i < $maxItems; $i++) {
            if (isset($allSimpleItems[$i])) {
                $simpleItem = $allSimpleItems[$i];
                $item['itemCode' . $i] = $simpleItem->getId();
                $item['itemDescription' . $i] = $this->cleanItemDescription($simpleItem->getName());
                $item['itemHsTariffNumber' . $i] = '';
                $item['itemCountryOfOrigin' . $i] = $simpleItem->getData('country_of_manufacture');
                $item['itemQuantity' . $i] = (int)$simpleItem->getQtyOrdered();
                $item['itemUnitPrice' . $i] = sprintf('%0.2f', $simpleItem->getPrice());
                $item['itemUnitWeight' . $i] = sprintf('%0.3f', $simpleItem->getWeight());
            }
        }

        // Include ATL options column to satisfy new specification
        $item['atlOptions'] = '';

        return $item;
    }

    /**
     * Make CSV file ready for downloading
     *
     * @param array $formattedOrders
     * @return string The filename of the generated CSV file
     */
    protected function makeCsv($formattedOrders)
    {
        $filename = sprintf("order_export_%s_clickandsend.csv", date("Ymd_His"));
        $tmpDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $file = $tmpDirectory->openFile($filename);

        foreach ($formattedOrders as $item) {
            $file->writeCsv($item);
        }

        $file->close();

        return $this->directoryList->getPath(DirectoryList::TMP) . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Remove character that is not word
     *
     * @param string $itemDescription
     * @return string
     */
    private function cleanItemDescription($itemDescription)
    {
        return preg_replace("/[^ \w]+/", "", $itemDescription);
    }
}
