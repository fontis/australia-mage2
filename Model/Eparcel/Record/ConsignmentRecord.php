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

namespace Fontis\Australia\Model\Eparcel\Record;

class ConsignmentRecord extends AbstractRecord
{
    public $type = 'C';
    public $consignmentId = '';
    public $postChargeToAccount;
    public $chargeCode;
    public $merchantConsigneeCode;
    public $consigneeName;
    public $consigneeBusinessName;
    public $consigneeAddressLine1;
    public $consigneeAddressLine2;
    public $consigneeAddressLine3;
    public $consigneeAddressLine4;
    public $consigneeSuburb;
    public $consigneeStateCode;
    public $consigneePostcode;
    public $consigneeCountryCode;
    public $consigneePhoneNumber;
    public $isPhonePrintRequired = false;
    public $consigneeFaxNumber;
    public $deliveryInstructions;
    public $isSignatureRequired;
    public $isPartDelivery = false;
    public $comments;
    public $addToAddressBook = false;
    public $cashToCollectAmount;
    public $ref;
    public $isRefPrintRequired = false;
    public $ref2;
    public $isRef2PrintRequired = false;
    public $chargebackAccount;
    public $isRecurringConsignment = false;
    public $returnName;
    public $returnAddress1;
    public $returnAddress2;
    public $returnAddress3;
    public $returnAddress4;
    public $returnSuburb;
    public $returnState;
    public $returnPostcode;
    public $returnCountryCode;
    public $redirectionCompanyName;
    public $redirectionName;
    public $redirectionAddress1;
    public $redirectionAddress2;
    public $redirectionAddress3;
    public $redirectionAddress4;
    public $redirectSuburb;
    public $redirectState;
    public $redirectionPostcode;
    public $redirectionCountryCode;
    public $manifestIdentifier;
    public $consigneeEmailAddress;
    public $emailNotification;
    public $businessPartnerNumber;
    public $surveyId;
    public $deliverySubscription;
    public $embargoDate;
    public $specifiedDeliveryDate;
    public $specifiedDeliveryDay;
    public $specifiedNoDeliveryDay;
    public $customerCollectLocation;
}
