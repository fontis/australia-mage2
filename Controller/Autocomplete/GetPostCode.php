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

namespace Fontis\Australia\Controller\Autocomplete;

use Auspost\Common\Auspost;
use Fontis\Australia\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class GetPostCode extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Data $helper
    )
    {
        $this->helper = $helper;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        if (!$this->helper->isPostcodeAutocompleteEnabled()) {
            $data = [];

            // Prepare output content
            $result = $this->resultJsonFactory->create();
            $result->setData($data);

            return $result;
        }

        $query = $this->getRequest()->get("term");
        $client = Auspost::factory(['auth_key' => $this->helper->getAPIKey()])->get('postage');

        if ($this->helper->shouldRemovePostOfficeBoxes()) {
            $locations = $client->searchPostcode(['q' => $query, 'excludepostboxflag' => true]);
        } else {
            $locations = $client->searchPostcode(['q' => $query]);
        }

        $locations = $locations['localities']['locality'];

        // Format data for jquery autocomplete
        $data = [];

        foreach ($locations as $location) {
            if ($this->helper->shouldRemovePostOfficeBoxes()) {
                // The Auspost API accepts an "excludepostboxflag" parameter, but it does not seem to affect
                // the results. For the time being, we need to manually filter them out ourselves.
                if ($location['category'] === 'Post Office Boxes') {
                    continue;
                }
            }

            $data[] = [
                "id" => $location['id'],
                "label" => $location['location'] . ' ' . $location['state'] . " (" . $location['postcode'] . ")",
                "value" => $location['location'],
                "postcode" => $location['postcode'],
            ];
        }

        // The order of results returned from API not really match the search keyword
        // eg: it will returns North Sydney before Sydney when we search for "Sydn".
        // So we will use the Levenshtein metric to sort the results so that
        // the best matched results will be ordered first.
        $tempArr = [];

        for ($i = 0; $i < count($data); $i++) {
            $tempArr[$i] = levenshtein($query, $data[$i]['label']);
        }

        asort($tempArr);
        $sortedLocations = [];

        foreach ($tempArr as $k => $v) {
            $sortedLocations[] = $data[$k];
        }

        // Limit results size by system configuration
        $sortedLocations = array_slice($sortedLocations, 0, $this->helper->getPostcodeAutocompleteMaxResultCount());

        // Prepare output content
        $result = $this->resultJsonFactory->create();
        $result->setData($sortedLocations);

        return $result;
    }
}
