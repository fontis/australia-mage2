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

namespace Fontis\Australia\Model\ResourceModel;

use Exception;
use Fontis\Australia\Helper\Eparcel as EparcelHelper;
use Fontis\Australia\Model\Config\Backend\Eparcel as BackendEparcel;
use Fontis\Australia\Model\Shipping\Carrier\Eparcel as EparcelShipping;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Region\Collection as RegionCollection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\Http\Proxy as HttpProxy;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Eparcel
{
    const MIN_CSV_COLUMN_COUNT = 8;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /** @var StoreManagerInterface  */
    protected $storeManager;

    /** @var CountryCollection */
    protected $countryCollection;

    /** @var RegionCollection */
    protected $regionCollection;

    /** @var Eparcel */
    protected $eparcelHelper;

    /** @var ResourceConnection */
    protected $coreResource;

    /** @var array */
    private $exceptions;

    /** @var Http */
    protected $request;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CountryCollection $countryCollection
     * @param RegionCollection $regionCollection
     * @param EparcelHelper $eparcelHelper
     * @param ResourceConnection $coreResource
     * @param HttpProxy $request
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CountryCollection $countryCollection,
        RegionCollection $regionCollection,
        EparcelHelper $eparcelHelper,
        ResourceConnection $coreResource,
        HttpProxy $request
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->countryCollection = $countryCollection;
        $this->regionCollection = $regionCollection;
        $this->eparcelHelper = $eparcelHelper;
        $this->coreResource = $coreResource;
        $this->request = $request;
    }

    /**
     * @return string
     */
    private function getMainTable()
    {
        return $this->coreResource->getTableName('australia_eparcel');
    }

    /**
     * Get eparcel rates
     *
     * @param RateRequest $request
     * @return array shipping rates
     */
    public function getRate(RateRequest $request)
    {
        $read = $this->coreResource->getConnection();

        $postcode = $request->getDestPostcode();
        $table = $this->getMainTable();

        $signatureRequired = $this->scopeConfig->isSetFlag('carriers/eparcel/signature_required', ScopeInterface::SCOPE_STORE, $request->getStoreId());

        if ($signatureRequired) {
            $signatureCost = (float) $this->scopeConfig->getValue('carriers/eparcel/signature_cost', ScopeInterface::SCOPE_STORE, $request->getStoreId());
        } else {
            $signatureCost = 0;
        }

        $newdata = array();

        // Query 5 times, each time we will query with different conditions
        // which specified in switch case  : switch ($j)
        for ($j = 0; $j < 5; $j++) {
            $select = $read->select()->from($table);

            switch ($j) {
                case 0:
                    $select->where(
                        $read->quoteInto(" (dest_country_id=? ", $request->getDestCountryId()) .
                        $read->quoteInto(" AND dest_region_id=? ", $request->getDestRegionId()) .
                        $read->quoteInto(" AND dest_zip=?) ", $postcode)
                    );
                    break;
                case 1:
                    $select->where(
                        $read->quoteInto("  (dest_country_id=? ", $request->getDestCountryId()) .
                        $read->quoteInto(" AND dest_region_id=? AND dest_zip='0000') ", $request->getDestRegionId())
                    );
                    break;
                case 2:
                    $select->where(
                        $read->quoteInto("  (dest_country_id=? AND dest_region_id='0' AND dest_zip='0000') ", $request->getDestCountryId())
                    );
                    break;
                case 3:
                    $select->where(
                        $read->quoteInto("  (dest_country_id=? AND dest_region_id='0' ", $request->getDestCountryId()) .
                        $read->quoteInto("  AND dest_zip=?) ", $postcode)
                    );
                    break;
                case 4:
                    $select->where(
                        "  (dest_country_id='0' AND dest_region_id='0' AND dest_zip='0000')"
                    );
                    break;
            }


            if (is_array($request->getConditionName())) {
                $i = 0;
                foreach ($request->getConditionName() as $conditionName) {
                    if ($i == 0) {
                        $select->where('condition_name=?', $conditionName);
                    } else {
                        $select->orWhere('condition_name=?', $conditionName);
                    }

                    $select->where('condition_from_value<=?', $request->getData($conditionName));
                    $select->where('condition_to_value>=?', $request->getData($conditionName));

                    $i++;
                }
            } else {
                $select->where('condition_name=?', $request->getConditionName());
                $select->where('condition_from_value<=?', $request->getData($request->getConditionName()));
                $select->where('condition_to_value>=?', $request->getData($request->getConditionName()));
            }

            $select->where('website_id=?', $request->getWebsiteId());

            $select->order('dest_country_id DESC');
            $select->order('dest_region_id DESC');
            $select->order('dest_zip DESC');
            $select->order('condition_from_value DESC');

            $newdata = array();
            $row = $read->fetchAll($select);

            if (!empty($row) && ($j < 5)) {
                // have found a result or found nothing and at end of list!
                foreach ($row as $data) {

                    $price = (float)($data['price']);

                    // add per-Kg cost
                    $conditionValue = (float)($request->getData($request->getConditionName()));
                    $price += (float)($data['price_per_kg']) * $conditionValue;

                    // add signature cost
                    $price += $signatureCost;

                    // add version without insurance
                    $data['price'] = (string)$price;
                    $newdata[] = $data;

                    if ($this->scopeConfig->isSetFlag('carriers/eparcel/insurance_enable', ScopeInterface::SCOPE_STORE, $request->getStoreId())) {
                        // add version with insurance
                        $insuranceStep = (float) $this->scopeConfig->getValue('carriers/eparcel/insurance_step', ScopeInterface::SCOPE_STORE, $request->getStoreId());
                        $insuranceCostPerStep = (float) $this->scopeConfig->getValue('carriers/eparcel/insurance_cost_per_step', ScopeInterface::SCOPE_STORE, $request->getStoreId());

                        // work out how many insurance 'steps' we have
                        $steps = ceil($request->getPackageValue() / $insuranceStep);

                        // add on number of 'steps' multiplied by the
                        // insurance cost per step
                        $insuranceCost = $insuranceCostPerStep * $steps;
                        $price += $insuranceCost;

                        $data['price'] = (string)$price;
                        $data['delivery_type'] .= " with TransitCover";
                        $newdata[] = $data;
                    }
                }

                break;
            }
        }

        return $newdata;
    }

    /**
     * @param array $csvLine
     * @param string $rowNumber
     * @param string $conditionFullName
     * @param array $countryCodesToIds
     * @param array $regionCodesToIds
     * @return array
     */
    private function formatCsvLine($csvLine, $rowNumber, $conditionFullName, $countryCodesToIds, $regionCodesToIds)
    {
        if (empty($countryCodesToIds) || !array_key_exists($csvLine[0], $countryCodesToIds)) {
            $csvLine[0] = '0';

            if ($csvLine[0] != '*' && $csvLine[0] != '') {
                $this->exceptions[] = __('Invalid country "%1" on row #%2', $csvLine[0], ($rowNumber + 1));
            }
        } else {
            $csvLine[0] = $countryCodesToIds[$csvLine[0]];
        }

        if (empty($regionCodesToIds) || !array_key_exists($csvLine[1], $regionCodesToIds)) {
            $csvLine[1] = '0';

            if ($csvLine[1] != '*' && $csvLine[1] != '') {
                $this->exceptions[] = __('Invalid region/state "%1" on row #%2', $csvLine[1], ($rowNumber + 1));
            }
        } else {
            $csvLine[1] = $regionCodesToIds[$csvLine[1]];
        }

        if ($csvLine[2] == '*' || $csvLine[2] == '') {
            $csvLine[2] = '';
        }

        if (!$this->isPositiveDecimalNumber($csvLine[3]) || $csvLine[3] == '*' || $csvLine[3] == '') {
            $this->exceptions[] = __('Invalid %1 "%2" on row #%3', $conditionFullName, $csvLine[3], ($rowNumber + 1));
        } else {
            $csvLine[3] = (float) $csvLine[3];
        }

        if (!$this->isPositiveDecimalNumber($csvLine[4]) || $csvLine[4] == '*' || $csvLine[4] == '') {
            $this->exceptions[] = __('Invalid %1 "%2" on row #%3', $conditionFullName, $csvLine[4], ($rowNumber + 1));
        } else {
            $csvLine[4] = (float) $csvLine[4];
        }

        if (!$this->isPositiveDecimalNumber($csvLine[5])) {
            $this->exceptions[] = __('Invalid shipping price "%1" on row #%2', $csvLine[5], ($rowNumber + 1));
        } else {
            $csvLine[5] = (float) $csvLine[5];
        }

        if (!$this->isPositiveDecimalNumber($csvLine[6])) {
            $this->exceptions[] = __('Invalid shipping price per kg "%1" on row #%2', $csvLine[6], ($rowNumber + 1));
        } else {
            $csvLine[6] = (float) $csvLine[6];
        }

        /** @var EparcelHelper $helper */
        $helper = $this->eparcelHelper;

        if (isset($csvLine[8]) && $csvLine[8] != '' && !$helper->isValidChargeCode($csvLine[8])) {
            $this->exceptions[] = __('Invalid charge code "%1" on row #%2', $csvLine[8], ($rowNumber + 1));
        } else {
            $csvLine[8] = isset($csvLine[8]) ? (string) $csvLine[8] : null;
        }

        if (isset($csvLine[9]) && $csvLine[9] != '' && !$helper->isValidChargeCode($csvLine[9])) {
            $this->exceptions[] = __('Invalid charge code "%1" on row #%2', $csvLine[9], ($rowNumber + 1));
        } else {
            $csvLine[9] = isset($csvLine[9]) ? (string) $csvLine[9] : null;
        }

        $csvLine[10] = isset($csvLine[10]) ? (int) $csvLine[10] : 0;

        return $csvLine;
    }

    /**
     * Import to australia_eparcel table
     *
     * @param BackendEparcel $object
     * @throws Exception
     */
    public function uploadAndImport(BackendEparcel $object)
    {
        if (empty($object->getValue())) {
            return;
        }

        $csvFile = $object->getUploadDir() . DIRECTORY_SEPARATOR . $object->getFieldsetDataValue('import')['name'];

        if (!empty($csvFile)) {
            if (!is_readable($csvFile)) {
                throw new Exception("Eparcel import file is not readable");
            }

            $postedData = $this->request->getPostValue();
            $csv = trim(file_get_contents($csvFile));
            $table = $this->getMainTable();
            $websiteId = $object->getScopeId();

            if (isset($postedData['groups']['eparcel']['fields']['condition_name']['inherit'])) {
                $conditionName = (string) $this->scopeConfig->getValue('carriers/eparcel/condition_name', ScopeInterface::SCOPE_STORE, $this->storeManager->getStore()->getId());
            } else {
                $conditionName = $postedData['groups']['eparcel']['fields']['condition_name']['value'];
            }
        }

        $conditionFullName = $this->eparcelHelper->getCode('condition_name_short', $conditionName);

        if (empty($csv)) {
            throw new LocalizedException(__('File not found'));
        }

        $this->exceptions = array();
        $csvLines = explode("\n", $csv);
        $csvLine = array_shift($csvLines);
        $csvLine = $this->getCsvValues($csvLine);

        if (count($csvLine) < self::MIN_CSV_COLUMN_COUNT) {
            $this->exceptions[0] = __('Less than ' . self::MIN_CSV_COLUMN_COUNT . ' columns in the CSV header.');
        }

        $countryCodes = array();
        $regionCodes = array();

        foreach ($csvLines as $k => $csvLine) {
            $csvLine = $this->getCsvValues($csvLine);
            $count = count($csvLine);

            if ($count > 0 && $count < self::MIN_CSV_COLUMN_COUNT) {
                $this->exceptions[0] = __('Less than ' . self::MIN_CSV_COLUMN_COUNT . ' columns in row ' . ($k + 1) . '.');
            } else {
                $countryCodes[] = $csvLine[0];
                $regionCodes[] = $csvLine[1];
            }
        }

        if (empty($this->exceptions)) {
            $data = array();
            $countryCodesToIds = array();
            $regionCodesToIds = array();
            $countryCodesIso2 = array();

            $countryCollection = $this->countryCollection->addCountryCodeFilter($countryCodes)->load();

            foreach ($countryCollection->getItems() as $country) {
                $countryCodesToIds[$country->getData('iso3_code')] = $country->getData('country_id');
                $countryCodesToIds[$country->getData('iso2_code')] = $country->getData('country_id');
                $countryCodesIso2[] = $country->getData('iso2_code');
            }

            $regionCollection = $this->regionCollection
                ->addRegionCodeFilter($regionCodes)
                ->addCountryFilter($countryCodesIso2)
                ->load();

            foreach ($regionCollection->getItems() as $region) {
                $regionCodesToIds[$region->getData('code')] = $region->getData('region_id');
            }

            foreach ($csvLines as $k => $csvLine) {
                $csvLine = $this->getCsvValues($csvLine);
                $csvLine = $this->formatCsvLine($csvLine, $k, $conditionFullName, $countryCodesToIds, $regionCodesToIds);

                $data[] = array(
                    'website_id' => $websiteId,
                    'dest_country_id' => $csvLine[0],
                    'dest_region_id' => $csvLine[1],
                    'dest_zip' => $csvLine[2],
                    'condition_name' => $conditionName,
                    'condition_from_value' => $csvLine[3],
                    'condition_to_value' => $csvLine[4],
                    'price' => $csvLine[5],
                    'price_per_kg' => $csvLine[6],
                    'delivery_type' => $csvLine[7],
                    'charge_code_individual' => $csvLine[8],
                    'charge_code_business' => $csvLine[9],
                    'stock_id' => $csvLine[10],
                );

                $dataDetails[] = array(
                    'country' => $csvLine[0],
                    'region' => $csvLine[1]
                );
            }
        }

        if (empty($this->exceptions)) {
            $connection = $this->coreResource->getConnection();

            $condition = array(
                $connection->quoteInto('website_id = ?', $websiteId),
                $connection->quoteInto('condition_name = ?', $conditionName),
            );

            $connection->delete($table, $condition);

            foreach ($data as $k => $dataLine) {
                try {
                    // convert comma-seperated postcode/postcode range
                    // string into an array
                    $postcodes = array();

                    foreach (explode(',', $dataLine['dest_zip']) as $postcodeEntry) {
                        $postcodeEntry = explode("-", trim($postcodeEntry));

                        if (count($postcodeEntry) == 1) {
                            // if the postcode entry is length 1, it's
                            // just a single postcode
                            $postcodes[] = $postcodeEntry[0];
                        } else {
                            // otherwise it's a range, so convert that
                            // to a sequence of numbers
                            $pcode1 = (int) $postcodeEntry[0];
                            $pcode2 = (int) $postcodeEntry[1];
                            $postcodes = array_merge($postcodes, range(min($pcode1, $pcode2), max($pcode1, $pcode2)));
                        }
                    }

                    foreach ($postcodes as $postcode) {
                        $dataLine['dest_zip'] = str_pad($postcode, 4, "0", STR_PAD_LEFT);
                        $connection->insert($table, $dataLine);
                    }
                } catch (Exception $e) {
                    $this->exceptions[] = $e->getMessage();
                }
            }
        }

        if (!empty($this->exceptions)) {
            throw new Exception("\n" . implode("\n", $this->exceptions));
        }
    }

    /**
     * Due to bugs in fgetcsv(), this extension is using tips from php.net.
     * We could potentially swap this out for Zend's CSV parsers after testing for bugs in that.
     * Note: I've updated this code the latest version in the comments on php.net (Jonathan Melnick)
     *
     * @param string $string
     * @param string $separator
     * @return array
     */
    protected function getCsvValues($string, $separator = ",")
    {
        $elements = explode($separator, trim($string));

        for ($i = 0; $i < count($elements); $i++) {
            $nquotes = substr_count($elements[$i], '"');

            if ($nquotes % 2 == 1) {
                for ($j = $i + 1; $j < count($elements); $j++) {
                    if (substr_count($elements[$j], '"') % 2 == 1) { // Look for an odd-number of quotes
                        // Put the quoted string's pieces back together again
                        array_splice($elements, $i, $j - $i + 1, implode($separator, array_slice($elements, $i, $j - $i + 1)));
                        break;
                    }
                }
            }

            if ($nquotes > 0) {
                // Remove first and last quotes, then merge pairs of quotes
                $qstr = & $elements[$i];
                $qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
                $qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
                $qstr = str_replace('""', '"', $qstr);
            }

            $elements[$i] = trim($elements[$i]);
        }

        return $elements;
    }

    /**
     * @param string $n
     * @return bool
     */
    protected function isPositiveDecimalNumber($n)
    {
        return (bool) preg_match("/^[0-9]+(\.[0-9]*)?$/", $n);
    }
}
