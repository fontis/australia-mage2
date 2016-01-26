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
 * @copyright  Copyright (c) 2016 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Fontis\Australia\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $countryRegionTable = $setup->getTable("directory_country_region");
        $countryRegionNameTable = $setup->getTable("directory_country_region_name");

        $data = [
            ["ACT", "Australian Capital Territory"],
            ["NSW", "New South Wales"],
            ["NT", "Northern Territory"],
            ["QLD", "Queensland"],
            ["SA", "South Australia"],
            ["TAS", "Tasmania"],
            ["VIC", "Victoria"],
            ["WA", "Western Australia"],
        ];

        foreach ($data as $row) {
            $bind = ["country_id" => "AU", "code" => $row[0], "default_name" => $row[1]];
            $setup->getConnection()->insert($countryRegionTable, $bind);
            $regionId = $setup->getConnection()->lastInsertId($countryRegionTable);

            $columns = ["locale", "region_id", "name"];
            $values = [
                ["en_AU", $regionId, $row[1]],
                ["en_US", $regionId, $row[1]],
            ];
            $setup->getConnection()->insertArray($countryRegionNameTable, $columns, $values);
        }
    }
}
