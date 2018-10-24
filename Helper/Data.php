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

namespace Fontis\Australia\Helper;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const AUSTRALIA_COUNTRY_CODE = 'AU';

    const MAX_AUTOCOMPLETE_RESULTS_DEFAULT = 20;

    const XML_PATH_POSTCODE_AUTOCOMPLETE_ENABLED = 'fontis_australia/postcode_autocomplete/active';

    /**
     * Checks whether postcode autocomplete is enabled.
     *
     * @param mixed $scope
     * @return bool
     */
    public function isPostcodeAutocompleteEnabled($scope = null)
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_POSTCODE_AUTOCOMPLETE_ENABLED, ScopeInterface::SCOPE_STORE, $scope);
    }

    /**
     * @param mixed $scope
     * @return int
     */
    public function getPostcodeAutocompleteMaxResultCount($scope = null)
    {
        $max = $this->scopeConfig->getValue("fontis_australia/postcode_autocomplete/max_results", ScopeInterface::SCOPE_STORE, $scope);

        if (!is_numeric($max)) {
            return self::MAX_AUTOCOMPLETE_RESULTS_DEFAULT;
        }

        $max = (int) $max;

        if ($max > 0) {
            return $max;
        } else {
            return self::MAX_AUTOCOMPLETE_RESULTS_DEFAULT;
        }
    }

    /**
     * @param mixed $store
     * @return string
     */
    public function getAPIKey($store = null)
    {
        return $this->scopeConfig->getValue("fontis_australia/australia_post/api_key", ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param mixed $store
     * @return bool
     */
    public function shouldRemovePostOfficeBoxes($store = null)
    {
        return $this->scopeConfig->isSetFlag("fontis_australia/postcode_autocomplete/remove_post_office_boxes", ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Get all the simple items in an order.
     *
     * @param RateRequest|OrderInterface $request Request to retrieve items for
     * @return array List of simple products from order
     */
    public function getAllSimpleItems($request)
    {
        $items = array();

        foreach ($request->getAllItems() as $item) {
            if ($item->getProductType() === ProductType::TYPE_SIMPLE) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
