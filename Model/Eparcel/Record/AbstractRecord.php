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

abstract class AbstractRecord
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var bool
     */
    protected $isAddedToEparcel = false;

    /**
     * @return bool
     */
    public function isAddedToEparcel()
    {
        return $this->isAddedToEparcel;
    }

    /**
     * @param bool $isAddedToEparcel
     */
    public function setIsAddedToEparcel($isAddedToEparcel)
    {
        $this->isAddedToEparcel = (bool) $isAddedToEparcel;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        $values = array_values(
            get_object_vars($this)
        );

        // Removes $isAddedToEparcel from array
        // TODO: Use a better data structure to generate eParcel records
        array_pop($values);

        foreach ($values as &$value) {
            if ($value === true) {
                $value = 'Y';
            }
            if ($value === false) {
                $value = 'N';
            }
        }

        return $values;
    }
}
