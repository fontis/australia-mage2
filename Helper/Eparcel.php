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

namespace Fontis\Australia\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Api\Data\StoreInterface;

class Eparcel extends AbstractHelper
{
    const XML_PATH_EMAIL_NOTIFICATION_ENABLED = 'fontis_eparcelexport/email_notification/enabled';

    const XML_PATH_EMAIL_NOTIFICATION_LEVEL = 'fontis_eparcelexport/email_notification/level';

    // AUSTRALIA POST CHARGE CODES
    private $standardChargeCodes = array(
        // Domestic / Standard / Individual
        'S1', // EPARCEL 1       Domestic
        'S2', // EPARCEL 2       Domestic
        'S3', // EPARCEL 3       Domestic
        'S4', // EPARCEL 4       Domestic
        'S5', // EPARCEL 5       Domestic
        'S6', // EPARCEL 6       Domestic
        'S7', // EPARCEL 7       Domestic
        'S8', // EPARCEL 8       Domestic

        // Domestic / Standard / Business
        'B1', // B TO B EPARCEL 1        Domestic
        'B2', // B TO B EPARCEL 2        Domestic
        'B5', // B TO B EPARCEL 5        Domestic

        // Domestic / Express / Individual
        'X1', // EXPRESS POST EPARCEL    Domestic
        'X2', // EXPRESS POST EPARCEL 2  Domestic

        // Domestic / Express / Business
        'XB1', // EXPRESS POST EPARCEL B2B        Domestic
        'XB2', // EXPRESS POST EPARCEL B2B 2      Domestic

        // International / Standard
        'AIR1', // INTERNATIONAL Airmail 1 International
        'AIR2', // INTERNATIONAL Airmail 2 International
        'AIR3', // INTERNATIONAL Airmail - 8 Zones International

        // International / Express
        'EPI1', // Express Post International      International
        'EPI2', // Express Post International      International
        'EPI3', // Express Post International – 8 zones    International
        'ECM1', // Express Courier Int'l Merchandise 1      International
        'ECM2', // Express Courier Int'l Merchandise 2     International
        'ECM3', // Express Courier Int'l Merch 8Zone       International
        'ECD1', // EXPRESS COURIER INT'L DOC 1     International
        'ECD2', // EXPRESS COURIER INT'L DOC 2     International
        'ECD3', // Express Courier Int'l Doc – 8 zones     International

        // Other
        'CFR', // eParcel Call For Return Domestic
        'PR', // eParcel Post Returns Service    Domestic
        'CS1', // CTC EPARCEL     Domestic
        'CS4', // CTC EPARCEL     Domestic
        'CS5', // CTC EPARCEL 5   Domestic
        'CS6', // CTC EPARCEL 6   Domestic
        'CS7', // CTC EPARCEL 7   Domestic
        'CS8', // CTC EPARCEL 8   Domestic
        'CX1', // CTC EXPRESS POST 500G BRK       Domestic
        'CX2', // CTC EXPRESS POST MULTI BRK      Domestic
        'RPI1', // Registered Post International   International
    );

    /**
     * Returns whether email notifications are enabled.
     *
     * @param mixed $scope
     * @return bool
     */
    public function isEmailNotificationEnabled($scope = null)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_EMAIL_NOTIFICATION_ENABLED, ScopeInterface::SCOPE_STORE, $scope);
    }

    /**
     * Returns the email notification level, i.e. none, notify when despatched,
     * or complete tracking.
     *
     * @param mixed $scope
     * @return string
     */
    public function getEmailNotificationLevel($scope = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_EMAIL_NOTIFICATION_LEVEL, ScopeInterface::SCOPE_STORE, $scope);
    }

    /**
     * Determines whether a given string is a valid eParcel charge code.
     *
     * @param string $chargeCode
     * @param mixed $scope
     * @return bool
     */
    public function isValidChargeCode($chargeCode, $scope = null)
    {
        $isStandard = in_array($chargeCode, $this->standardChargeCodes);

        if ($isStandard || $this->scopeConfig->getValue('fontis_eparcelexport/charge_codes/allow_custom_charge_codes', ScopeInterface::SCOPE_STORE, $scope)) {
            // Charge code not found in the standard list of codes, but system config tells us this is OK
            // @see https://github.com/fontis/fontis_australia/issues/39
            return true;
        }

        return false;
    }

    /**
     * Get shipping codes
     *
     * @param string $type
     * @param string $code
     * @return array|string shipping codes
     */
    public function getCode($type, $code = '')
    {
        $codes = array(
            'condition_name' => array(
                'package_weight' => __('Weight vs. Destination'),
                'package_value' => __('Price vs. Destination'),
                'package_qty' => __('# of Items vs. Destination'),
            ),
            'condition_name_short' => array(
                'package_weight' => __('Weight (and above)'),
                'package_value' => __('Order Subtotal (and above)'),
                'package_qty' => __('# of Items (and above)'),
            ),
        );

        if (!isset($codes[$type])) {
            throw new LocalizedException(__('Invalid Table Rate code type: %s', $type));
        }

        if ($code === '') {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw new LocalizedException(__('Invalid Table Rate code for type %s: %s', $type, $code));
        }

        return $codes[$type][$code];
    }
}
