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

use ArrayIterator;
use IteratorAggregate;

class RecordContainer implements IteratorAggregate
{
    /**
     * @var AbstractRecord[]
     */
    protected $records = array();

    /**
     * @param AbstractRecord $record
     * @return int
     */
    public function addRecord(AbstractRecord $record)
    {
        $record->setIsAddedToEparcel(true);

        return array_push($this->records, $record);
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->records);
    }
}
