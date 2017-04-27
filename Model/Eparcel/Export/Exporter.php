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

namespace Fontis\Australia\Model\Eparcel\Export;

use Fontis\Australia\Model\Eparcel\Record\AbstractRecord;
use Fontis\Australia\Model\Eparcel\Record\RecordContainer;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class Exporter
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @param Filesystem $filesystem
     * @param DirectoryList $directoryList
     */
    public function __construct(Filesystem $filesystem, DirectoryList $directoryList)
    {
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
    }

    /**
     * @param AbstractRecord[]|RecordContainer $recordContainer
     * @return string The filename of the exported CSV file.
     */
    public function exportCsv(RecordContainer $recordContainer)
    {
        $filename = sprintf("order_export_%s_eparcel.csv", date("Ymd_His"));
        $tmpDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $file = $tmpDirectory->openFile($filename);

        foreach ($recordContainer as $record) {
            $file->writeCsv($record->getValues());
        }

        $file->close();

        return $this->directoryList->getPath(DirectoryList::TMP) . DIRECTORY_SEPARATOR . $filename;
    }
}
