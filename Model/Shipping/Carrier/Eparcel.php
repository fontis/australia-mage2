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

namespace Fontis\Australia\Model\Shipping\Carrier;

use Fontis\Australia\Helper\ClickAndSend;
use Fontis\Australia\Helper\Eparcel as Helper;
use Fontis\Australia\Model\ResourceModel\Eparcel as EparcelResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result as RateResult;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\Result as TrackingResult;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Eparcel extends AbstractCarrier implements CarrierInterface
{
    const CARRIER_CODE = 'eparcel';

    /** @var string */
    protected $_code = self::CARRIER_CODE;

    /** @var string */
    protected $defaultConditionName = 'package_weight';

    /** @var array */
    protected $conditionNames = array();

    /** @var RateResultFactory */
    protected $rateResultFactory;

    /** @var ClickAndSend  */
    protected $clickandsendHelper;

    /** @var MethodFactory */
    protected $rateMethodFactory;

    /** @var StatusFactory */
    protected $statusFactory;

    /** @var Helper  */
    protected $helper;

    /** @var CustomerSession */
    protected $customerSession;

    /** @var EparcelResource  */
    protected $eparcelResource;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Helper $helper,
        ClickAndSend $clickandsendHelper,
        RateResultFactory $rateResultFactory,
        TrackingResultFactory $trackingResultFactory,
        MethodFactory $rateMethodFactory,
        CustomerSession $customerSession,
        EparcelResource $eparcelResource,
        StatusFactory $statusFactory
    ) {
        $this->helper = $helper;
        $this->clickandsendHelper = $clickandsendHelper;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->customerSession = $customerSession;
        $this->eparcelResource = $eparcelResource;
        $this->statusFactory = $statusFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger);

        foreach ($this->helper->getCode('condition_name') as $k => $v) {
            $this->conditionNames[] = $k;
        }
    }

    /**
     * Get Rates from resource model
     *
     * @param RateRequest $request the order detail
     * @return array
     */
    public function getRate(RateRequest $request)
    {
        return $this->eparcelResource->getRate($request);
    }

    /**
     * Collects the shipping rates for Eparcel shipping from the REST API.
     *
     * @param RateRequest $request the order detail
     * @return RateResult|null shipping rate
     */
    public function collectRates(RateRequest $request)
    {
        // Make sure that Shipping method is enabled
        if (!$this->isActive()) {
            return false;
        }

        $request->setConditionName($this->getConfigData('condition_name') ? $this->getConfigData('condition_name') : $this->defaultConditionName);

        $result = $this->rateResultFactory->create();
        $rates = $this->getRate($request);

        if (is_array($rates) and !empty($rates)) {
            $carrierTitle = $this->getConfigData('title');
            foreach ($rates as $rate) {
                if (!empty($rate) && $rate['price'] >= 0) {
                    $method = $this->rateMethodFactory->create();
                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle($carrierTitle);

                    if ($chargeCode = $this->_getChargeCode($rate)) {
                        $_method = strtolower(str_replace(' ', '_', $chargeCode));
                    } else {
                        $_method = strtolower(str_replace(' ', '_', $rate['delivery_type']));
                    }

                    $method->setMethod($_method);

                    if ($this->getConfigData('name')) {
                        $method->setMethodTitle($this->getConfigData('name'));
                    } else {
                        $method->setMethodTitle($rate['delivery_type']);
                    }

                    $method->setMethodChargeCodeIndividual($rate['charge_code_individual']);
                    $method->setMethodChargeCodeBusiness($rate['charge_code_business']);
                    $shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price']);
                    $method->setPrice($shippingPrice);
                    $method->setCost($rate['cost']);
                    $method->setDeliveryType($rate['delivery_type']);
                    $result->append($method);
                }
            }
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Get correct code from rate result
     *
     * @param array $rate
     * @return mixed
     */
    protected function _getChargeCode($rate)
    {
        // Is this customer is in a ~business~ group ?
        $businessGroups = $this->_scopeConfig->getValue('fontis_eparcelexport/charge_codes/business_groups', ScopeInterface::SCOPE_STORE);
        $isBusinessCustomer = (
            $this->customerSession->isLoggedIn()
            &&
            in_array($this->customerSession->getCustomerGroupId(),
                explode(',', $businessGroups)
            )
        );

        if ($isBusinessCustomer) {
            if (isset($rate['charge_code_business']) && $rate['charge_code_business']) {
                return $rate['charge_code_business'];
            }

            return $this->_scopeConfig->getValue('fontis_eparcelexport/charge_codes/default_charge_code_business', ScopeInterface::SCOPE_STORE);
        } else {
            if (isset($rate['charge_code_individual']) && $rate['charge_code_individual']) {
                return $rate['charge_code_individual'];
            }

            return $this->_scopeConfig->getValue('fontis_eparcelexport/charge_codes/default_charge_code_individual', ScopeInterface::SCOPE_STORE);
        }
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('bestway' => $this->getConfigData('name'));
    }

    /**
     * If method support tracking code
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get info from tracking number
     *
     * @param string $tracking
     * @return null|TrackingResult
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);

        if ($result instanceof TrackingResult) {
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Get tracking
     *
     * @param array $trackings
     * @return TrackingResultFactory
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = array($trackings);
        }

        return $this->privGetTracking($trackings);
    }

    /**
     * Get tracking info detail
     *
     * @param array $trackings
     * @return TrackingResultFactory
     */
    protected function privGetTracking($trackings)
    {
        $result = $this->trackingResultFactory->create();

        foreach ($trackings as $t) {
            $tracking = $this->statusFactory->create();
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->setTracking($t);
            $tracking->setUrl('https://auspost.com.au/track/');
            $result->append($tracking);
        }

        return $result;
    }
}
