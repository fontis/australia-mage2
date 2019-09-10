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

namespace Fontis\Australia\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->addAustralianRegions($setup);
        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    protected function addAustralianRegions(ModuleDataSetupInterface $setup)
    {
        $countryRegionTable = $setup->getTable("directory_country_region");
        $countryRegionNameTable = $setup->getTable("directory_country_region_name");

        $states = [
            ["ACT", "Australian Capital Territory"],
            ["NSW", "New South Wales"],
            ["NT", "Northern Territory"],
            ["QLD", "Queensland"],
            ["SA", "South Australia"],
            ["TAS", "Tasmania"],
            ["VIC", "Victoria"],
            ["WA", "Western Australia"],
        ];

        $connection = $setup->getConnection();
        foreach ($states as $state) {
            // Check if this region has already been added
            $result = $connection->fetchOne("SELECT code FROM "
                . $countryRegionTable
                . " WHERE `country_id` = 'AU' AND `code` = '"
                . $state[0] . "'"
            );

            if ($result === $state[0]) {
                continue; // State exists. Skip it.
            }

            $bind = ["country_id" => "AU", "code" => $state[0], "default_name" => $state[1]];
            $connection->insert($countryRegionTable, $bind);
            $regionId = $setup->getConnection()->lastInsertId($countryRegionTable);

            $columns = ["locale", "region_id", "name"];
            $values = [
                ["en_AU", $regionId, $state[1]],
                ["en_US", $regionId, $state[1]],
            ];
            $connection->insertArray($countryRegionNameTable, $columns, $values);
        }
    }
}
