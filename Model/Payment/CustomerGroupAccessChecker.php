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

namespace Fontis\Australia\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Designed for use by payment methods performing customer group access checks.
 *
 * @property \Fontis\CustomerGroupAccess\AccessCheckFactory $accessCheckFactory
 */
trait CustomerGroupAccessChecker
{
    /**
     * Check whether payment method can be used
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote !== null) {
            $accessCheck = $this->accessCheckFactory->create(array("storeId" => $this->getStore()));
            if ($accessCheck->check($quote->getCustomer()->getGroupId()) === false) {
                return false;
            }
        }

        return parent::isAvailable($quote);
    }
}
