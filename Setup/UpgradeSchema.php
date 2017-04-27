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

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var InstallSchema
     */
    protected $installSchema;

    /**
     * @param InstallSchema $installSchema
     */
    public function __construct(InstallSchema $installSchema)
    {
        $this->installSchema = $installSchema;
    }

    /**
     * Upgrade event
     *
     * @param SchemaSetupInterface $installer
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $installer, ModuleContextInterface $context)
    {
        $installer->startSetup();

        // Don't run the upgrade process if the extension isn't already installed.
        if (!$context->getVersion()) {
            return;
        }

        if (version_compare($context->getVersion(), '0.3.0') < 0) {
            $this->installSchema->createParcelTable($installer);
        }

        $installer->endSetup();
    }
}
