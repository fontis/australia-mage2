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

class CategoriesItem implements OptionSourceInterface
{
    const RETURNED_GOODS = 21;
    const GIFT = 31;
    const COMMERCIAL_SAMPLE = 32;
    const DOCUMENT = 91;
    const OTHER = 991;
    const PLANT_ANIMAL_OR_FOOD_PRODUCT = 999;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::RETURNED_GOODS, 'label' => __('Returned Goods')),
            array('value' => self::GIFT, 'label' => __('Gift')),
            array('value' => self::COMMERCIAL_SAMPLE, 'label' => __('Commercial Sample')),
            array('value' => self::DOCUMENT, 'label' => __('Document')),
            array('value' => self::PLANT_ANIMAL_OR_FOOD_PRODUCT, 'label' => __('Plant, Animal or Food Product')),
            array('value' => self::OTHER, 'label' => __('Other')),
        );
    }
}
