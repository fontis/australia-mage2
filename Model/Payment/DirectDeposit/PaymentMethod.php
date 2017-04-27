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

namespace Fontis\Australia\Model\Payment\DirectDeposit;

use Fontis\Australia\Block\Payment\DirectDeposit as DirectDepositBlocks;
use Fontis\Australia\Model\Payment\CheckoutConfigInterface;
use Fontis\Australia\Model\Payment\CustomerGroupAccessChecker as AusCustomerGroupAccessChecker;
use Fontis\CustomerGroupAccess\AccessCheckFactory as CustomerGroupAccessCheckFactory;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data as PaymentHelperData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger as PaymentMethodLogger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb as AbstractDbCollection;

class PaymentMethod extends AbstractMethod implements CheckoutConfigInterface
{
    use AusCustomerGroupAccessChecker;

    const METHOD_CODE = "fontis_australia_directdeposit";

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = DirectDepositBlocks\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = DirectDepositBlocks\Info::class;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var CustomerGroupAccessCheckFactory
     */
    protected $accessCheckFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param PaymentHelperData $paymentHelperData
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentMethodLogger $paymentLogger
     * @param CustomerGroupAccessCheckFactory $accessCheckFactory
     * @param AbstractResource $abstractResource
     * @param AbstractDbCollection $abstractDbCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelperData $paymentHelperData,
        ScopeConfigInterface $scopeConfig,
        PaymentMethodLogger $paymentLogger,
        CustomerGroupAccessCheckFactory $accessCheckFactory,
        AbstractResource $abstractResource = null,
        AbstractDbCollection $abstractDbCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentHelperData, $scopeConfig, $paymentLogger, $abstractResource, $abstractDbCollection);

        $this->accessCheckFactory = $accessCheckFactory;
    }

    /**
     * @return array
     */
    public function getCheckoutConfig()
    {
        return array(
            "account_name" => $this->getConfigData("account_name"),
            "account_bsb" => $this->getConfigData("account_bsb"),
            "account_number" => $this->getConfigData("account_number"),
            "message" => $this->getConfigData("message"),
        );
    }

    /**
     * Overridden with checks to ensure certain necessary config settings are set.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        if (!$this->getConfigData("account_name", $storeId)) {
            return false;
        }
        if (!$this->getConfigData("account_bsb", $storeId)) {
            return false;
        }
        if (!$this->getConfigData("account_number", $storeId)) {
            return false;
        }
        return parent::isActive($storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param array|DataObject $data
     * @return $this
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        $details = array_filter($this->getCheckoutConfig());
        $infoInstance = $this->getInfoInstance();
        foreach ($details as $key => $value) {
            $infoInstance->setAdditionalInformation($key, $value);
        }

        return $this;
    }
}
