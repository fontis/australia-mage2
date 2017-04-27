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

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Install event handler
     *
     * @param SchemaSetupInterface $installer
     * @param ModuleContextInterface $context
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->createParcelTable($setup);
        $setup->endSetup();
    }

    /**
     * Create australia_eparcel schema
     *
     * @param SchemaSetupInterface $setup
     */
    public function createParcelTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('australia_eparcel')
        )
        ->addColumn(
            'pk',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Entity Id'
        )
        ->addColumn(
            'website_id',
            Table::TYPE_SMALLINT,
            11,
            ['unsigned' => true, 'default' => '0'],
            'Website Id'
        )
        ->addColumn(
            'dest_country_id',
            Table::TYPE_TEXT,
            4,
            ['nullable' => false, 'default' => '0'],
            'Country Id'
        )
        ->addColumn(
            'dest_region_id',
            Table::TYPE_INTEGER,
            10,
            ['nullable' => false, 'default' => '0'],
            'Region Id'
        )
        ->addColumn(
            'dest_zip',
            Table::TYPE_TEXT,
            10,
            ['nullable' => false, 'default' => ''],
            'Zip Id'
        )
        ->addColumn(
            'condition_name',
            Table::TYPE_TEXT,
            20,
            ['nullable' => false, 'default' => '']
        )
        ->addColumn(
            'condition_from_value',
            Table::TYPE_DECIMAL,
            [12,4],
            ['nullable' => false, 'default' => '0.0000']
        )
        ->addColumn(
            'condition_to_value',
            Table::TYPE_DECIMAL,
            [12,4],
            ['nullable' => false, 'default' => '0.0000']
        )
        ->addColumn(
            'price',
            Table::TYPE_DECIMAL,
            [12,4],
            ['nullable' => false, 'default' => '0.0000']
        )
        ->addColumn(
            'price_per_kg',
            Table::TYPE_DECIMAL,
            [12,4],
            ['nullable' => false, 'default' => '0.0000']
        )
        ->addColumn(
            'cost',
            Table::TYPE_DECIMAL,
            [12,4],
            ['nullable' => false, 'default' => '0.0000']
        )
        ->addColumn(
            'delivery_type',
            Table::TYPE_TEXT,
            50,
            ['nullable' => false, 'default' => '']
        )
        ->addColumn(
            'charge_code_individual',
            Table::TYPE_TEXT,
            50,
            ['nullable' => false, 'default' => '']
        )
        ->addColumn(
            'charge_code_business',
            Table::TYPE_TEXT,
            50,
            ['nullable' => false, 'default' => '']
        )
        ->addIndex(
            'dest_country',
            ['website_id', 'dest_country_id', 'dest_region_id', 'dest_zip', 'condition_name', 'condition_to_value', 'delivery_type']
        );

        $setup->getConnection()->createTable($table);
    }
}
