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

namespace Fontis\Australia\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [];

    /**
     * @var CheckoutConfigInterface[]|MethodInterface[]
     */
    protected $methods = [];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param string[] $methodCodes
     * @throws LocalizedException
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        array $methodCodes = array()
    ) {
        $this->escaper = $escaper;
        $this->methodCodes = $methodCodes;

        foreach ($this->methodCodes as $code) {
            $method = $paymentHelper->getMethodInstance($code);
            if (!$method instanceof CheckoutConfigInterface) {
                throw new LocalizedException(
                    __("%1 class doesn't implement \\Fontis\\Australia\\Model\\Payment\\CheckoutConfigInterface", $method)
                );
            }
            $this->methods[$code] = $method;
        }
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = [];
        $escaper = $this->escaper;
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $methodConfig = $this->methods[$code]->getCheckoutConfig();
                if (is_array($methodConfig)) {
                    array_walk($methodConfig, function (&$value, $key) use ($escaper) {
                        $value = $escaper->escapeHtml($value);
                    });
                } else {
                    $methodConfig = $escaper->escapeHtml($methodConfig);
                }
                $config["payment"][$code] = $methodConfig;
            }
        }
        return $config;
    }
}
