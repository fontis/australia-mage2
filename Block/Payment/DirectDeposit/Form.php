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

namespace Fontis\Australia\Block\Payment\DirectDeposit;

use Magento\Payment\Block\Form as MagentoPaymentForm;

class Form extends MagentoPaymentForm
{
    /**
     * Bank transfer template
     *
     * @var string
     */
    protected $_template = "Fontis_Australia::payment/directdeposit/form.phtml";

    /**
     * @var array
     */
    private $checkoutConfig = null;

    /**
     * @return array
     */
    private function getCheckoutConfig()
    {
        if ($this->checkoutConfig === null) {
            $this->checkoutConfig = $this->getMethod()->getCheckoutConfig();
        }
        return $this->checkoutConfig;
    }

    /**
     * @return string
     */
    public function getAccountName()
    {
        return $this->getCheckoutConfig()["account_name"];
    }

    /**
     * @return string
     */
    public function getAccountBSB()
    {
        return $this->getCheckoutConfig()["account_bsb"];
    }

    /**
     * @return string
     */
    public function getAccountNumber()
    {
        return $this->getCheckoutConfig()["account_number"];
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->getCheckoutConfig()["message"];
    }
}
