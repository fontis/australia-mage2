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

namespace Fontis\Australia\Model\ResourceModel\Eparcel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Psr\Log\LoggerInterface;

class Collection extends AbstractDb
{
    /**
     * @var DummyResource
     */
    protected $dummyResource;

    /** @var string */
    protected $_shipTable;

    /** @var string */
    protected $_countryTable;

    /** @var string */
    protected $_regionTable;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ResourceConnection $coreResource
     * @param DummyResource $dummyResource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ResourceConnection $coreResource,
        DummyResource $dummyResource
    ) {
        $this->dummyResource = $dummyResource;
        $connection = $coreResource->getConnection();
        parent::__construct($entityFactory, $logger, $fetchStrategy, $connection);
        $this->_shipTable = $coreResource->getTableName('australia_eparcel');
        $this->_countryTable = $coreResource->getTableName('directory_country');
        $this->_regionTable = $coreResource->getTableName('directory_country_region');
        $this->_initSelect();
        $this->_setIdFieldName('pk');
    }

    /**
     * Init select
     *
     * @return void
     */
    protected function _initSelect()
    {
        $this->getSelect()->from(array("s" => $this->_shipTable))
            ->joinLeft(array("c" => $this->_countryTable), 'c.country_id = s.dest_country_id', 'iso3_code AS dest_country')
            ->joinLeft(array("r" => $this->_regionTable), 'r.region_id = s.dest_region_id', 'code AS dest_region')
            ->order(array("dest_country", "dest_region", "dest_zip"));
    }

    /**
     * @return DummyResource
     */
    public function getResource()
    {
        $this->dummyResource;
    }

    /**
     * @param int $websiteId
     * @return Collection
     */
    public function setWebsiteFilter($websiteId)
    {
        $this->addFieldToFilter("website_id", array("eq" => $websiteId));

        return $this;
    }

    /**
     * @param string $conditionName
     * @return Collection
     */
    public function setConditionFilter($conditionName)
    {
        $this->addFieldToFilter("condition_name", array("eq" => $conditionName));

        return $this;
    }

    /**
     * @param string $countryId
     * @return Collection
     */
    public function setCountryFilter($countryId)
    {
        $this->addFieldToFilter("dest_country_id", array("eq" => $countryId));

        return $this;
    }
}
