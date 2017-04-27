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

class CubicWeightRecord extends ArticleRecord
{
    /**
     * The cubic weight is the parcel's volume in cubic metres multiplied by 250.
     * @see http://auspost.com.au/personal/parcel-dimensions.html
     * @var float
     */
    public $weight;

    /**
     * Calculate Weight
     *
     * @return bool
     */
    public function calculateWeight()
    {
        // Convert [cm] to [m]
        $l = (float)$this->length / 100;
        $w = (float)$this->width / 100;
        $h = (float)$this->height / 100;

        $this->weight = round(($l * $w * $h) * 250, 2);

        return true;
    }
}
