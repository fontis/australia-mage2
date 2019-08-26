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

namespace Fontis\Australia\Model\Shipping\Carrier;

use Auspost\Common\Auspost;
use Auspost\Postage\Enum\ServiceCode;
use Auspost\Postage\Enum\ServiceOption;
use Auspost\Postage\PostageClient;
use Exception;
use Fontis\Australia\Helper\ClickAndSend;
use Fontis\Australia\Helper\Data as DataHelper;
use Fontis\Australia\Model\Shipping\Source\Visibility;
use Guzzle\Http\ClientInterface;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\Result as RateResult;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class AustraliaPost extends AbstractCarrier implements CarrierInterface
{
    const EXTRA_COVER_LIMIT = 5000;

    const CARRIER_CODE = 'australia_post';

    /** @var string */
    protected $_code = self::CARRIER_CODE;

    /** @var ResultFactory */
    protected $_rateResultFactory;

    /** @var ClickAndSend  */
    protected $_clickandsendHelper;

    /** @var MethodFactory */
    protected $_rateMethodFactory;

    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var DataHelper */
    protected $dataHelper;

    /**
     * @param CheckoutSession $checkoutSession
     * @param ClickAndSend $clickandsendHelper
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param MethodFactory $rateMethodFactory
     * @param ResultFactory $rateResultFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param DataHelper $dataHelper
     * @param array $data
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ClickAndSend $clickandsendHelper,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        MethodFactory $rateMethodFactory,
        ResultFactory $rateResultFactory,
        ScopeConfigInterface $scopeConfig,
        DataHelper $dataHelper,
        array $data = []
    ) {
        $this->_clickandsendHelper = $clickandsendHelper;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->checkoutSession = $checkoutSession;
        $this->dataHelper = $dataHelper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Enable tracking code or not
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Collects the shipping rates for Australia Post from the REST API.
     *
     * @param RateRequest $request the order detail
     * @return RateResult|bool shipping rate
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->isActive()) {
            return false;
        }

        /** @var PostageClient $client */
        $client = $this->getAuspostApiClient($request);

        // Check if this method is even applicable (shipping from Australia)
        $origCountryId = $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_COUNTRY_ID, ScopeInterface::SCOPE_STORE, $request->getStoreId());

        if ($client === null) {
            return false;
        }

        if ($origCountryId != DataHelper::AUSTRALIA_COUNTRY_CODE) {
            return false;
        }

        $fromPostcode = $this->_scopeConfig->getValue(Config::XML_PATH_ORIGIN_POSTCODE, ScopeInterface::SCOPE_STORE, $request->getStoreId());
        $toPostcode = $request->getDestPostcode();
        $destCountry = $request->getDestCountryId();

        if (!$destCountry) {
            // Default destination to AU if empty destination
            $destCountry = DataHelper::AUSTRALIA_COUNTRY_CODE;
        }

        $weight = (float) $request->getPackageWeight();
        $length = (int) $this->getAttribute($request, 'length');
        $width = (int) $this->getAttribute($request, 'width');
        $height = (int) $this->getAttribute($request, 'height');
        $extraCover = max((int) $request->getPackageValue(), self::EXTRA_COVER_LIMIT);

        $config = array(
            'from_postcode' => $fromPostcode,
            'to_postcode' => $toPostcode,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $weight,
            'country_code' => $destCountry
        );

        return $this->getQuotes($extraCover, $config, $client);
    }

    /**
     * Returns an array of shipping method options, e.g. "signature on
     * delivery", that have a certain visibility, e.g. "never"
     *
     * @param string $destCountry Destination country code
     * @param int $visibility Shipping method option visibility
     * @return array
     */
    protected function getOptionVisibilities($destCountry, $visibility)
    {
        $suboptions = [];

        if ($this->getPickUp() == $visibility && $destCountry != DataHelper::AUSTRALIA_COUNTRY_CODE) {
            $suboptions[] = 'pick up';
        }

        if ($this->getExtraCover() == $visibility) {
            $suboptions[] = 'extra cover';
        }

        if ($this->getSignatureOnDelivery() == $visibility && $destCountry == DataHelper::AUSTRALIA_COUNTRY_CODE) {
            $suboptions[] = 'signature on delivery';
        }

        return $suboptions;
    }

    /**
     * Checks whether a shipping method has the visibility "required"
     *
     * @param string $name Name of the shipping method
     * @param string $destCountry Country code
     * @return bool
     */
    protected function isOptionVisibilityRequired($name, $destCountry)
    {
        $suboptions = $this->getOptionVisibilities($destCountry, Visibility::REQUIRED);

        foreach ($suboptions as $suboption) {
            if (stripos($name, $suboption) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether a shipping method option has the visibility "never"
     *
     * @param string $name Name of the shipping method
     * @param string $destCountry Country code
     * @return bool
     */
    protected function isOptionVisibilityNever($name, $destCountry)
    {
        $suboptions = $this->getOptionVisibilities($destCountry, Visibility::NEVER);

        foreach ($suboptions as $suboption) {
            if (stripos($name, $suboption) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether a shipping method should be added to the result.
     *
     * @param string $name Name of the shipping method
     * @param string $destCountry Country code
     * @return bool
     */
    protected function isAvailableShippingMethod($name, $destCountry)
    {
        return $this->isOptionVisibilityRequired($name, $destCountry) &&
            !$this->isOptionVisibilityNever($name, $destCountry);
    }

    /**
     * Simplifies creating a new shipping method.
     *
     * @param string $code
     * @param string $title
     * @param string $price
     * @return Method
     */
    protected function createMethod($code, $title, $price)
    {
        // format the method code
        $code = strtolower($code);
        $code = str_replace("parcel", "", $code);
        $code = str_replace("packaging", "", $code);
        $code = str_replace("signature_on_delivery", "sod", $code);
        $code = str_replace("extra_cover", "exc", $code);
        $code = str_replace("sms_track_advice", "std", $code);
        $code = str_replace("_", "", $code);

        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($code);
        $method->setMethodTitle($title);
        $method->setPrice($this->getFinalPriceWithHandlingFee($price));
        $method->setCost($this->getFinalPriceWithHandlingFee($price));

        return $method;
    }

    /**
     * @param RateRequest $request
     * @return ClientInterface|null
     */
    protected function getAuspostApiClient(RateRequest $request)
    {
        $apiKey = $this->dataHelper->getAPIKey($request->getStoreId());
        $config = array();

        if ($this->isAustraliaPostDeveloperMode()) {
            $config = array(
                'developer_mode' => true,
                'auth_key' => $apiKey
            );
        } else {
            if ($apiKey) {
                $config = array('auth_key' => $apiKey);
            }
        }

        if (empty($config)) {
            return null;
        }

        return Auspost::factory($config)->get('postage');
    }

    /**
     * @param int $destCountry
     * @param array $config
     * @param string $serviceCode
     * @param string $serviceName
     * @param string $serviceOptionName
     * @param string $serviceOptionCode
     * @param ClientInterface $client
     * @return Method|null
     */
    private function createMethodSupportClickAndSend($destCountry, array $config, $serviceCode, $serviceName, $serviceOptionName, $serviceOptionCode, $client)
    {
        try {
            if ($destCountry == DataHelper::AUSTRALIA_COUNTRY_CODE) {
                $config = array_merge($config, array(
                    'suboption_code' => ServiceOption::AUS_SERVICE_OPTION_EXTRA_COVER,
                ));
                $postageWithExtraCover = $client->calculateDomesticParcelPostage($config);
            } else {
                $postageWithExtraCover = $client->calculateInternationalParcelPostage($config);
            }

            unset($config['suboption_code']);
        } catch (Exception $e) {
            return;
        }

        if ($serviceOptionName == 'Signature on Delivery') {
            $serviceOptionName = $serviceOptionName . ' + Extra Cover';
        } else {
            $serviceOptionName = 'Extra Cover';
        }

        if ($serviceOptionCode == ServiceOption::AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY) {
            $serviceOptionCode = 'FULL_PACKAGE';
        } else {
            $serviceOptionCode = 'EXTRA_COVER';
        }

        $servicePrice = $postageWithExtraCover['postage_result']['total_cost'];
        $_finalCode = $serviceCode . '_' . $serviceOptionCode;
        $_finalName = $serviceName . ' (' . $serviceOptionName . ')';

        if ($this->isAvailableShippingMethod($_finalName, $destCountry)) {
            $method = $this->createMethod($_finalCode, $_finalName, $servicePrice);
            return $method;
        }

        return null;
    }

    /**
     * Create methods from an array of options
     *
     * @param string $destCountry destination country code
     * @param int $extraCover size of extra cover
     * @param array $serviceOption list of service options
     * @param string $serviceName base name of service
     * @param float $servicePrice base price of service
     * @param array $config configurations
     * @param string $serviceCode base service code name
     * @param ClientInterface $client
     * @return Method[]
     */
    protected function createMethodVariants($destCountry, $extraCover, array $serviceOption, $serviceName, $servicePrice, array $config, $serviceCode, $client)
    {
        $methods = [];

        foreach ($serviceOption as $option) {
            $serviceOptionName = $option['name'];
            $serviceOptionCode = $option['code'];
            $config = array_merge($config, array(
                'service_code' => $serviceCode,
                'option_code' => $serviceOptionCode,
                'extra_cover' => $extraCover
            ));

            try {
                if ($destCountry == DataHelper::AUSTRALIA_COUNTRY_CODE) {
                    $postage = $client->calculateDomesticParcelPostage($config);
                } else {
                    $postage = $client->calculateInternationalParcelPostage($config);
                }
            } catch (Exception $e) {
                $this->_logger->error($e);
                continue;
            }

            $servicePrice = $postage['postage_result']['total_cost'];

            // Create a shipping method with only the top-level options
            $_finalCode = $serviceCode . '_' . $serviceOptionCode;
            $_finalName = $serviceName . ' (' . $serviceOptionName . ')';

            if (
                $this->isAvailableShippingMethod($_finalName, $destCountry) &&
                // The following shipping methods and shipping options don't work with
                // the Click & Send CSV import service.
                !(
                    in_array($serviceOptionCode, $this->_clickandsendHelper->getDisallowedServiceOptions()) &&
                    in_array($serviceCode, $this->_clickandsendHelper->getDisallowedServiceCodes()) &&
                    $this->_clickandsendHelper->isClickAndSendEnabled() &&
                    $this->_clickandsendHelper->isFilterShippingMethods()
                )
            ) {
                $method = $this->createMethod($_finalCode, $_finalName, $servicePrice);
                $methods[] = $method;
            }

            $extraCoverParent = $this->getCode('extra_cover');

            // Add the extra cover options (these are suboptions of
            // the top-level options)
            if (
                // The Click & Send CSV import doesn't work with Extra Cover so we
                // will need to disable the option if it has been activated. The
                // fields are there in the specification but I couldn't get it to
                // import at all.
                array_key_exists($serviceOptionCode, $extraCoverParent) &&
                !(
                    $this->_clickandsendHelper->isClickAndSendEnabled() &&
                    $this->_clickandsendHelper->isFilterShippingMethods()
                )
            ) {
                $method = $this->createMethodSupportClickAndSend($destCountry, $config, $serviceCode, $serviceName, $serviceOptionName, $serviceOptionCode, $client);
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * Get Quotes on configuration
     *
     * @param int $extraCover size of cover
     * @param array $config configurations
     * @param ClientInterface $client
     * @return RateResult
     */
    protected function getQuotes($extraCover, array $config, $client)
    {
        $rateResult = $this->_rateResultFactory->create();
        $methods = [];
        $destCountry = $config['country_code'];

        if ($destCountry == DataHelper::AUSTRALIA_COUNTRY_CODE) {
            $services = $client->listDomesticParcelServices($config);
        } else {
            $services = $client->listInternationalParcelServices($config);
        }

        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));

        foreach ($services['services']['service'] as $service) {
            $serviceCode = $service['code']; // e.g. AUS_PARCEL_REGULAR

            if (in_array($serviceCode, $allowedMethods)) {
                $serviceName = $service['name']; // e.g. Parcel Post
                $servicePrice = $service['price'];

                // Just add the shipping method if the call to Australia Post
                // returns no options for that method
                if (
                    !isset($service['options']['option']) &&
                    $this->isAvailableShippingMethod($serviceName, $destCountry)
                ) {
                    $method = $this->createMethod($serviceCode, $serviceName, $servicePrice);
                    $methods[] = $method;
                } else {
                    // If a shipping method has a bunch of options, we will have to
                    // create a specific method for each of the variants
                    $serviceOption = $service['options']['option'];

                    // Unlike domestic shipping methods where the "default"
                    // method is listed as simply another service option (this
                    // allows us to simply loop through each one), we have to
                    // extrapolate the default international shipping method
                    // from what we know about the service itself
                    if (
                        $destCountry !== DataHelper::AUSTRALIA_COUNTRY_CODE &&
                        $this->isAvailableShippingMethod($serviceName, $destCountry)
                    ) {
                        $method = $this->createMethod($serviceCode, $serviceName, $servicePrice);
                        $methods[] = $method;
                    }

                    // Checks to see if the API has returned either a single
                    // service option or an array of them. If it is a single
                    // option then turn it into an array.
                    if (isset($serviceOption['name'])) {
                        $serviceOption = array($serviceOption);
                    }

                    // If API return Array of methods
                    $methodVariants = $this->createMethodVariants($destCountry, $extraCover, $serviceOption, $serviceName, $servicePrice, $config, $serviceCode, $client);
                    $methods = array_merge($methods, $methodVariants);
                }
            }
        }

        foreach ($methods as $method) {
            $rateResult->append($method);
        }

        return $rateResult;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array Australia Post method names
     */
    public function getAllowedMethods()
    {
        $methods = explode(',', $this->getConfigData('allowed_methods'));
        $formatMethods = array();

        foreach ($methods as $method) {
            $formatMethods[$method] = $this->getConfigData('title');
        }

        return $formatMethods;
    }

    /**
     * Checks whether developer mode is enabled for the Australia Post shipping
     * rates API.
     *
     * @return bool
     */
    public function isAustraliaPostDeveloperMode()
    {
        return $this->getConfigData('developer_mode');
    }

    /**
     * Returns the visibility of "Extra Cover" shipping methods.
     *
     * @return string
     */
    public function getExtraCover()
    {
        return $this->getConfigData('extra_cover');
    }

    /**
     * Returns an associative array of shipping method codes.
     *
     * @param string $type
     * @param string $code
     * @return array|null
     */
    public function getCode($type, $code = '')
    {
        $codes = array(
            'services' => array(
                'AUS_LETTER_EXPRESS_SMALL' => __('Express Post Small Envelope'),
                'AUS_LETTER_REGULAR_LARGE' => __('Large Letter'),
                'AUS_PARCEL_COURIER' => __('Courier Post'),
                'AUS_PARCEL_COURIER_SATCHEL_MEDIUM' => __('Courier Post Assessed Medium Satchel'),
                'AUS_PARCEL_EXPRESS' => __('Express Post'),
                'AUS_PARCEL_REGULAR' => __('Parcel Post'),
                'INT_PARCEL_COR_OWN_PACKAGING' => __('International Courier'),
                'INT_PARCEL_EXP_OWN_PACKAGING' => __('International Express'),
                'INT_PARCEL_STD_OWN_PACKAGING' => __('International Standard'),
                'INT_PARCEL_AIR_OWN_PACKAGING' => __('International Economy Air'),
                'INT_PARCEL_SEA_OWN_PACKAGING' => __('International Economy Sea'),
            ),
            'extra_cover' => array(
                'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY' => __('Signature on Delivery'),
                'AUS_SERVICE_OPTION_COURIER_EXTRA_COVER_SERVICE' => __('Standard cover')
            )
        );

        if (!isset($codes[$type])) {
            return null;
        } elseif ($code === '') {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return null;
        } else {
            return $codes[$type][$code];
        }
    }

    /**
     * Returns the visibility the Pick Up option
     *
     * @return string
     */
    public function getPickUp()
    {
        return $this->getConfigData('pick_up');
    }

    /**
     * Determines whether "Signature on Delivery" is enabled and available for
     * the current destination. This is a domestic option.
     *
     * @return bool
     */
    public function isSignatureOnDelivery()
    {
        return $this->isOptional($this->getSignatureOnDelivery()) && $this->isAustralia();
    }

    /**
     * Determine if the shipping address country is Australia
     *
     * @return bool
     */
    protected function isAustralia()
    {
        return $this->getCountryId() === DataHelper::AUSTRALIA_COUNTRY_CODE;
    }

    /**
     * Get the country ID, e.g. "AU" or "US"
     *
     * @return string
     */
    public function getCountryId()
    {
        return $this->checkoutSession->getQuote()->getShippingAddress()->getCountryId();
    }

    /**
     * Returns the visibility of "Signature of Delivery" shipping methods.
     *
     * @return string
     */
    public function getSignatureOnDelivery()
    {
        return $this->getConfigData('signature_on_delivery');
    }

    /**
     * Determine if a provided config option is optional
     *
     * @param int $value Config value to check
     * @return bool
     */
    protected function isOptional($value)
    {
        return $value == Visibility::OPTIONAL;
    }

    /**
     * Get the attribute value for a product, e.g. its length attribute. If the
     * order only has one item and we've set which product attribute we want to
     * to get the attribute value from, use that product attribute. For all
     * other cases just use the default config setting, since we can't assume
     * the dimensions of the order.
     *
     * @param RateRequest|OrderInterface $request Request object
     * @param string $attribute Attribute code
     * @return string Attribute value
     */
    public function getAttribute($request, $attribute)
    {
        // Check if an appropriate product attribute has been assigned in the backend and, if not,
        // just return the default weight value as later code won't work
        $attributeCode = $this->getConfigData($attribute . '_attribute');

        if (!$attributeCode) {
            return $this->getConfigData('default_' . $attribute);
        }

        $items = $this->dataHelper->getAllSimpleItems($request);

        if (count($items) == 1) {
            $attributeValue = $items[0]->getData($attributeCode);
            if (empty($attributeValue)) {
                return $this->getConfigData('default_' . $attribute);
            }
            return $attributeValue;
        } else {
            return $this->getConfigData('default_' . $attribute);
        }
    }
}
