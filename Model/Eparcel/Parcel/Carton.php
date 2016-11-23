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

namespace Fontis\Australia\Model\Eparcel\Parcel;

use Fontis\Australia\Model\Eparcel\Record\GoodRecord;

class Carton extends Parcel
{
    /**
     * @param GoodRecord $goodRecord
     * @return bool
     */
    public function canAddGoodRecord(GoodRecord $goodRecord)
    {
        // Check that adding this good to parcel will not make
        // total parcel weight go over parcel's maxWeight
        if (($goodRecord->weight * $goodRecord->quantity) + $this->weight >= $this->weightMax) {
            return false;
        }

        return true;
    }

    /**
     * @param GoodRecord $goodRecord
     * @return GoodRecord
     */
    public function addGoodRecord(GoodRecord $goodRecord)
    {
        $this->weight += ($goodRecord->weight * $goodRecord->quantity);

        return parent::addGoodRecord($goodRecord);
    }
}
