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

class ArticleRecord extends AbstractRecord
{
    /**
     * The cubic weight is the parcel's volume in cubic metres multiplied by 250.
     * @see http://auspost.com.au/personal/parcel-dimensions.html
     * @var float
     */
    public $weight;
    public $length;
    public $width;
    public $height;
    public $type = 'A';
    public $numberIdenticalItems = 1;
    public $description;
    public $isDangerousGoods = false;
    public $isInsuranceRequired = false;
    public $insuranceAmount;
    public $valueForCustoms;
    public $exportReason;
    public $exportClearanceNumber;

    public function calculateWeight()
    {
        /*
         * Everything is already calculated.
         * This method only exists for consistency with
         * CubicWeightRecord
         */
        return true;
    }
}
