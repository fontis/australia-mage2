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

namespace Fontis\Australia\Block\Payment\DirectDeposit;

use Magento\Payment\Block\Info as MagentoPaymentInfo;

class Info extends MagentoPaymentInfo
{
    /**
     * @param \Magento\Framework\DataObject|array|null $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if ($this->_paymentSpecificInformation !== null) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);

        $paymentInfo = $this->getInfo()->getAdditionalInformation();
        // Magento expects the keys to be in the proper case, and it's a lot easier to just not fight it
        // and provide the keys in this form and use them in this form everywhere.
        $transport->addData([
            "Account Name" => $paymentInfo["account_name"],
            "Account BSB" => $paymentInfo["account_bsb"],
            "Account Number" => $paymentInfo["account_number"],
        ]);

        return $transport;
    }
}
