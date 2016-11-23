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

use Fontis\Australia\Model\Eparcel\Record\ArticleRecord;
use Fontis\Australia\Model\Eparcel\Record\GoodRecord;

abstract class Parcel
{
    /**
     * Calculated in kilograms
     *
     * @var float
     */
    public $weight = 0;

    /**
     * Calculated in kilograms
     *
     * @var float
     */
    public $weightMax = 0;

    /**
     * Calculated in centimetres
     *
     * @var float
     */
    public $width = 0;

    /**
     * Calculated in centimetres
     *
     * @var float
     */
    public $height = 0;

    /**
     * Calculated in centimetres
     *
     * @var float
     */
    public $length = 0;

    /** @var array */
    protected $_goodRecords = array();

    /** @var bool */
    protected $isInsuranceRequired = false;

    /**
     * @return bool
     */
    public function isInsuranceRequired()
    {
        return $this->isInsuranceRequired;
    }

    /**
     * @param bool $isInsuranceRequired
     */
    public function setInsuranceRequired($isInsuranceRequired)
    {
        $this->isInsuranceRequired = (bool) $isInsuranceRequired;
    }

    /**
     * @param ArticleRecord $record
     * @return ArticleRecord
     */
    public function processArticleRecord(ArticleRecord $record)
    {
        $totalValue = $this->getTotalValue();

        $record->length = $this->length;
        $record->width = $this->width;
        $record->height = $this->height;

        $record->weight = $this->weight;

        $record->numberIdenticalItems = 1;
        $record->description = "";
        $record->valueForCustoms = $totalValue;

        $record->calculateWeight();

        if ($this->isInsuranceRequired()) {
            $record->isInsuranceRequired = true;
            $record->insuranceAmount = $totalValue;
        }

        return $record;
    }

    /**
     * @return int
     */
    protected function getTotalValue()
    {
        $totalValue = 0;

        foreach ($this->getGoodRecords() as $_goodRecord) {
            $totalValue += $_goodRecord->totalValue;
        }

        return $totalValue;
    }

    /**
     * @param GoodRecord $goodRecord
     * @return bool
     */
    public function addGoodRecord(GoodRecord $goodRecord)
    {
        $this->_goodRecords[] = $goodRecord;

        return true;
    }

    /**
     * @return array
     */
    public function getGoodRecords()
    {
        return $this->_goodRecords;
    }

    /**
     * @param GoodRecord $goodRecord
     * @return mixed
     */
    abstract public function canAddGoodRecord(GoodRecord $goodRecord);
}
