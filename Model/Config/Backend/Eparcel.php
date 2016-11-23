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

namespace Fontis\Australia\Model\Config\Backend;

use Fontis\Australia\Model\ResourceModel\Eparcel as EparcelResource;
use Magento\Config\Model\Config\Backend\File;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;

/**
 * Backend model for import parcel
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class Eparcel extends File
{
    /** @var EparcelResource */
    protected $eparcelResource;

    /**
     * @param Registry $registry
     * @param Context $context
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param UploaderFactory $uploaderFactory
     * @param RequestDataInterface $requestData
     * @param Filesystem $filesystem
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param EparcelResource $eparcelResource
     * @param array $data
     */
    public function __construct(
        Registry $registry,
        Context $context,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        EparcelResource $eparcelResource,
        array $data = []
    )
    {
        $this->eparcelResource = $eparcelResource;
        parent::__construct($context, $registry, $config, $cacheTypeList, $uploaderFactory, $requestData, $filesystem, $resource, $resourceCollection, $data);
    }

    /**
     * Return path to directory for upload file
     * @return string
     * @throws LocalizedException
     */
    public function getUploadDir()
    {
        return parent::_getUploadDir();
    }

    /**
     * Override after save event handler
     * to proceed uploaded file and delete file after import
     */
    public function afterSave()
    {
        $this->eparcelResource->uploadAndImport($this);
        $this->delete();

        return parent::afterSave();
    }
}
