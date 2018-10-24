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

namespace Fontis\Australia\Model\Shipping\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InstructionsNonDelivery implements OptionSourceInterface
{
    const RETURN_BY_SURFACE = 1;
    const RETURN_BY_AIRMAIL = 2;
    const DELIVER_REDIRECT_BY_AIRMAIL = 3;
    const DELIVER_REDIRECT_BY_SURFACE = 4;
    const TREAT_AS_ABANDONED = 5;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::RETURN_BY_SURFACE,
                'label' => __('Return by Surface'),
            ),
            array(
                'value' => self::RETURN_BY_AIRMAIL,
                'label' => __('Return By Airmail'),
            ),
            array(
                'value' => self::DELIVER_REDIRECT_BY_AIRMAIL,
                'label' => __('Delivery/Redirect by Airmail'),
            ),
            array(
                'value' => self::DELIVER_REDIRECT_BY_SURFACE,
                'label' => __('Delivery/Redirect by Surface'),
            ),
            array(
                'value' => self::TREAT_AS_ABANDONED,
                'label' => __('Treat as Abandoned'),
            )
        );
    }
}
