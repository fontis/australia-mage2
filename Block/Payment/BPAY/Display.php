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

namespace Fontis\Australia\Block\Payment\BPAY;

trait Display
{
    /**
     * @return \Magento\Payment\Model\InfoInterface
     */
    abstract public function getInfo();

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    abstract public function getViewFileUrl($fileId, array $params = []);

    /**
     * @return string
     */
    public function getBpayLogoUrl()
    {
        return $this->getViewFileUrl("Fontis_Australia::images/payment/bpay-logo.png");
    }

    /**
     * @return string
     */
    public function getBillerText()
    {
        if ($this->getAcceptsCreditCards() === true) {
            return __("Contact your bank or financial institution to make this payment from your cheque, savings, debit, credit card or transaction account. More info: www.bpay.com.au");
        } else {
            return __("Contact your bank or financial institution to make this payment from your cheque, savings, debit or transaction account. More info: www.bpay.com.au");
        }
    }

    /**
     * @return bool
     */
    public function getAcceptsCreditCards()
    {
        return (bool) (int) $this->getInfo()->getAdditionalInformation("accept_credit_cards");
    }
}
