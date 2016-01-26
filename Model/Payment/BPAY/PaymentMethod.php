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
 * @copyright  Copyright (c) 2016 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Fontis\Australia\Model\Payment\BPAY;

use Fontis\Australia\Block\Payment\BPAY as BPAYBlocks;
use Fontis\Australia\Model\Payment\CheckoutConfigInterface;
use Fontis\Australia\Model\Payment\CustomerGroupAccessChecker as AusCustomerGroupAccessChecker;
use Fontis\CustomerGroupAccess\AccessCheckFactory as CustomerGroupAccessCheckFactory;
use Fontis\BpayRefGenerator\Generator as BpayRefGenerator;
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
use Magento\Checkout\Model\Session\Proxy as CheckoutSessionProxy;
use Magento\Backend\Model\Session\Quote\Proxy as BackendQuoteSessionProxy;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Sales\Model\Order\Payment as OrderPayment;

class PaymentMethod extends AbstractMethod implements CheckoutConfigInterface
{
    use AusCustomerGroupAccessChecker;

    const METHOD_CODE = "fontis_australia_bpay";

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = BPAYBlocks\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = BPAYBlocks\Info::class;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var CustomerGroupAccessCheckFactory
     */
    protected $accessCheckFactory;

    /**
     * @var BpayRefGenerator
     */
    protected $refGenerator;

    /**
     * @var CheckoutSessionProxy
     */
    protected $checkoutSession;

    /**
     * @var BackendQuoteSessionProxy
     */
    protected $backendQuoteSession;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param PaymentHelperData $paymentHelperData
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentMethodLogger $paymentLogger
     * @param CustomerGroupAccessCheckFactory $accessCheckFactory
     * @param BpayRefGenerator $refGenerator
     * @param CheckoutSessionProxy $checkoutSession
     * @param BackendQuoteSessionProxy $backendQuoteSession
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
        BpayRefGenerator $refGenerator,
        CheckoutSessionProxy $checkoutSession,
        BackendQuoteSessionProxy $backendQuoteSession,
        AbstractResource $abstractResource = null,
        AbstractDbCollection $abstractDbCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentHelperData, $scopeConfig, $paymentLogger, $abstractResource, $abstractDbCollection);

        $this->accessCheckFactory = $accessCheckFactory;
        $this->refGenerator = $refGenerator;
        $this->checkoutSession = $checkoutSession;
        $this->backendQuoteSession = $backendQuoteSession;
    }

    /**
     * @return array
     */
    public function getCheckoutConfig()
    {
        return array(
            "biller_code" => $this->getConfigData("biller_code"),
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
        if (!$this->getConfigData("biller_code", $storeId)) {
            return false;
        }
        return parent::isActive($storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param array|DataObject $data
     * @return PaymentMethod
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $infoInstance->setAdditionalInformation("biller_code", $this->getConfigData("biller_code"));
        $infoInstance->setAdditionalInformation("customer_ref", $this->getCustomerRef());
        $infoInstance->setAdditionalInformation("accepts_credit_cards", $this->getConfigData("accepts_credit_cards"));

        return $this;
    }

    /**
     * This is the neatest entry point for calculating the customer ref once an order ID has been
     * generated. There doesn't appear to be any other way that we can hook into the order placement
     * process for this specific payment method after the order ID has been created.
     *
     * @return PaymentMethod
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        $this->getInfoInstance()->setAdditionalInformation("customer_ref", $this->getCustomerRef());
        return parent::validate();
    }

    /**
     * @return string|null
     */
    protected function getStoredCustomerRef()
    {
        return $this->getInfoInstance()->getAdditionalInformation("customer_ref");
    }

    /**
     * @return string|null
     */
    protected function getCustomerRef()
    {
        $customerRef = $this->getStoredCustomerRef();
        if ($customerRef !== null) {
            return $customerRef;
        }

        $useCustomerId = (bool) (int) $this->getConfigData("calculate_using_customerid");
        if ($useCustomerId === true) {
            if ($this->_appState->getAreaCode() === FrontNameResolver::AREA_CODE) {
                $customer = $this->backendQuoteSession->getQuote()->getCustomer();
            } else {
                $customer = $this->checkoutSession->getQuote()->getCustomer();
            }
            if ($customerId = $customer->getId()) {
                $number = $customerId;
            } else {
                return null;
            }
        } else {
            $info = $this->getInfoInstance();
            // We can't use the interface here because for some reason it doesn't include the getOrder() method.
            if ($info instanceof OrderPayment) {
                $order = $info->getOrder();
                // Magento2 order increment IDs aren't prefixed with the store ID, so we need to do it ourselves.
                $number = $this->getStore() . $order->getRealOrderId();
            } else {
                return null;
            }
        }

        $useMod10V5 = (bool) (int) $this->getConfigData("generate_using_mod10V5");
        if ($useMod10V5 === true) {
            return $this->refGenerator->calcMod10V5($number);
        } else {
            return $this->refGenerator->calcMod10V1($number);
        }
    }
}
