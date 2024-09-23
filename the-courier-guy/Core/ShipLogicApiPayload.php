<?php

/**
 * @author The Courier Guy
 * @package tcg/core
 */

require_once 'ShipLogicContentPayload.php';

class ShipLogicApiPayload
{
    public static $r1;
    public static $j;
    protected static $log;
    public $globalFactor = 50;

    /**
     * ShipLogicApiPayload constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param int $globalFactor
     */
    public function set_global_factor(int $globalFactor): void
    {
        $this->globalFactor = $globalFactor;
    }

    /**
     * @param array $parameters
     * @param array $items
     *
     * @return array
     */
    public function getContentsPayload($parameters, $items)
    {
        $logging = $parameters['usemonolog'] === 'yes';
        if ($logging && !self::$log) {
            self::$log = wc_get_logger();
        }

        $useAdvancedAlgorithm = isset($parameters['enablenonstandardpackingbox']) && $parameters['enablenonstandardpackingbox'] === 'yes';

        $waybillDescriptionOverride = isset($parameters['remove_waybill_description']) && $parameters['remove_waybill_description'] === 'yes';
        self::$r1                   = $r2 = [];

        /** Get the standard parcel sizes
         * At least one must be set or default to standard size
         */
        list($globalParcels, $defaultProduct, $globalFlyer) = $this->getGlobalParcels($parameters);

        /**
         * Get products per item and store for efficiency
         */
        $all_items = $this->getAllItems($items, $defaultProduct);
        unset($items);

        /**
         * Check for any single-packaging product items
         * They will be packaged individually using their own dimensions
         * Global parcels don't apply
         */
        list($singleItems, $all_items) = $this->getSingleItems($all_items);

        /**
         * Items that don't fit into any of the defined parcel sizes
         * are each passed as a lumped item with their own dimension and mass
         *
         * Now check if there are items that don't fit into any box
         */
        $i = 0;

        list($tooBigItems, $fittingItems, $fitsFlyer) = $this->getFittingItems(
            $all_items,
            $globalParcels,
            $globalFlyer
        );

        // Up to here we have three arrays of products - single pack items, too big items and fitting items. No longer need all_items
        unset($all_items);

        // Handle the single parcel items first
        self::$j = $this->fitSingleItems($singleItems, $globalFlyer, $fitsFlyer, $waybillDescriptionOverride);
        unset($singleItems);

        // Handle the non-fitting items next
        // Single pack sizes
        self::$j = $this->fitToobigItems($tooBigItems, $waybillDescriptionOverride, self::$j);
        unset($tooBigItems);

        $this->poolIfPossible($fittingItems);

        /** Now the fitting items
         * We have to fit them into parcels
         * The idea is to minimise the total number of parcels - cf Talent 2020-09-09
         *
         */
        $conLoad = new ShipLogicContentPayload($parameters, $fittingItems, $globalParcels, $logging, self::$log);

        if (count($fittingItems) === 1) {
            if (!$useAdvancedAlgorithm) {
                $conLoad->calculate_single_fitting_items_packing(self::$r1, self::$j);
            } else {
                $r2 = $conLoad->calculate_multi_fitting_items_advanced();
            }
        } elseif (count($fittingItems) > 1) {
            if (!$useAdvancedAlgorithm) {
                $r2 = $conLoad->calculate_multi_fitting_items_basic();
            } else {
                $r2 = $conLoad->calculate_multi_fitting_items_advanced();
            }
        }

        unset($fittingItems);

        foreach ($r2 as $itemm) {
            self::$r1[] = $itemm;
        }

        self::$r1['fitsFlyer'] = $fitsFlyer;

        return self::$r1;
    }

    /**
     * @return array
     */
    private function getInsurancePayloadForQuote()
    {
        global $TCG_Plugin;
        $result                   = [];
        $customShippingProperties = $TCG_Plugin->getShippingCustomProperties();
        $insurance                = $customShippingProperties['tcg_insurance'];
        if ($insurance) {
            $result = [
                'insuranceflag' => 1,
                'declaredvalue' => WC()->cart->get_displayed_subtotal(),
            ];
        }

        return $result;
    }

    /**
     * @param WC_Order $order
     *
     * @return array
     */
    private function getInsurancePayloadForCollection($order)
    {
        $result = [];
        if ($order->get_meta('_billing_insurance', true) || $order->get_meta(
                '_shipping_insurance',
                true
            )) {
            $result = [
                'insuranceflag' => 1,
            ];
        }

        return $result;
    }

    /**
     * Get the standard parcel sizes
     * At least one must be set or default to standard size
     *
     * @param $parameters
     *
     * @return array
     */
    private function getGlobalParcels($parameters)
    {
        $globalParcells = [];
        $defaultProduct = [];
        for ($i = 1; $i < 7; $i++) {
            $globalParcel              = [];
            $product_length_per_parcel = $parameters['product_length_per_parcel_' . $i] ?? '';
            $product_width_per_parcel  = $parameters['product_width_per_parcel_' . $i] ?? '';
            $product_height_per_parcel = $parameters['product_height_per_parcel_' . $i] ?? '';
            if ($i === 1) {
                $globalParcel[0] = $product_length_per_parcel !== '' ? (int)$product_length_per_parcel : 50;
                $globalParcel[1] = $product_width_per_parcel !== '' ? (int)$product_width_per_parcel : 50;
                $globalParcel[2] = $product_height_per_parcel !== '' ? (int)$product_height_per_parcel : 50;
                rsort($globalParcel);
                $globalParcel['volume'] = $globalParcel[0] * $globalParcel[1] * $globalParcel[2];
                $globalParcells[0]      = $globalParcel;
            } else {
                $skip = false;
                if ($product_length_per_parcel === '') {
                    $skip = true;
                }
                if ($product_width_per_parcel === '') {
                    $skip = true;
                }
                if ($product_height_per_parcel === '') {
                    $skip = true;
                }
                if (!$skip) {
                    $globalParcel[0] = (int)$product_length_per_parcel;
                    $globalParcel[1] = (int)$product_width_per_parcel;
                    $globalParcel[2] = (int)$product_height_per_parcel;
                    rsort($globalParcel);
                    $globalParcel['volume'] = $globalParcel[0] * $globalParcel[1] * $globalParcel[2];
                    $globalParcells[$i - 1] = $globalParcel;
                }
            }
        }

        // Get a default product size to use where dimensions are not configured
        $globalParcelCount = count($globalParcells);
        if ($globalParcelCount == 1) {
            $defaultProduct = $globalParcells[0];
        } elseif (isset($globalParcells[1])) {
            $defaultProduct = $globalParcells[1];
        }

        $globalFlyer = $globalParcells[0];

        // Order the global parcels by largest dimension ascending order
        if (count($globalParcells) > 1) {
            usort(
                $globalParcells,
                function ($a, $b) {
                    if ($a[0] === $b[0]) {
                        return 0;
                    }

                    return ($a[0] < $b[0]) ? -1 : 1;
                }
            );
        }

        return [
            $globalParcells,
            $defaultProduct,
            $globalFlyer,
        ];
    }

    private function getAllItems($items, $defaultProduct)
    {
        $all_itemms = [];
        foreach ($items as $item) {
            $itm               = [];
            $item_variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;
            $item_product_id   = isset($item['product_id']) ? $item['product_id'] : 0;
            if ($item_variation_id !== 0) {
                $product       = new WC_Product_Variation($item_variation_id);
                $itm['single'] = $this->isSingleProductItem($product, $item_product_id);
            } else {
                $product       = new WC_Product($item_product_id);
                $itm['single'] = $this->isSingleProductItem($product);
            }
            $itm['item']               = $item;
            $itm['product']            = $product;
            $itm['dimensions']         = [];
            $itm['dimensions']['mass'] = $product->has_weight() ? wc_get_weight($product->get_weight(), 'kg') : 1.0;
            if ($product->has_dimensions()) {
                $itm['has_dimensions']       = true;
                $itm['toobig']               = false;
                $itm['dimensions']['height'] = $product->get_height();
                $itm['dimensions']['width']  = $product->get_width();
                $itm['dimensions']['length'] = $product->get_length();
            } else {
                $itm['has_dimensions'] = true;
                // Set as too-big item by default
                $itm['dimensions']['height'] = 1;
                $itm['dimensions']['width']  = 1;
                $itm['dimensions']['length'] = 1;
            }
            $itmdimensionsheight = $itm['dimensions']['height'];
            $itmdimensionswidth  = $itm['dimensions']['width'];
            $itmdimensionslength = $itm['dimensions']['length'];
            $itm['volume']       = 0;
            if ($itmdimensionsheight != 0 && $itmdimensionswidth != 0 && $itmdimensionslength != 0) {
                $itm['volume'] = intval($itmdimensionsheight) * intval($itmdimensionswidth) * intval(
                        $itmdimensionslength
                    );
            }
            $itm['slug']              = get_post($item['product_id'])->post_title;
            $all_itemms[$item['key']] = $itm;
        }

        return $all_itemms;
    }

    private function getSingleItems($all_items)
    {
        $singleItems = [];

        foreach ($all_items as $key => $item) {
            if ($item['single']) {
                $singleItems[$key] = $item;
                unset($all_items[$item['item']['key']]);
            }
        }

        return [$singleItems, $all_items];
    }

    private function getFittingItems($all_items, $globalParcels, $globalFlyer)
    {
        $tooBigItems  = [];
        $fittingItems = [];
        $fitsFlyer    = true;
        foreach ($all_items as $key => $item) {
            $fits      = $this->doesFitGlobalParcels($item, $globalParcels);
            $fitsFlyer = $fitsFlyer && $this->doesFitParcel($item, $globalFlyer);
            if (empty($item['toobig'])) {
                $item['toobig'] = false;
            }
            if (!$fits['fits'] || $item['toobig']) {
                $fitsFlyer         = false;
                $tooBigItems[$key] = $item;
            } else {
                $fittingItems[$key] = ['item' => $item, 'index' => $fits['fitsIndex']];
            }
        }

        // Order the fitting items with the biggest dimension first
        usort(
            $fittingItems,
            function ($a, $b) use ($all_items, $fittingItems) {
                $itema         = $a['item'];
                $itemb         = $b['item'];
                $producta_size = max(
                    (int)$itema['dimensions']['length'],
                    (int)$itema['dimensions']['width'],
                    (int)$itema['dimensions']['height']
                );
                $productb_size = max(
                    (int)$itemb['dimensions']['length'],
                    (int)$itemb['dimensions']['width'],
                    (int)$itemb['dimensions']['height']
                );
                if ($producta_size === $productb_size) {
                    return 0;
                }

                return ($producta_size < $productb_size) ? 1 : -1;
            }
        );

        $f = [];
        foreach ($fittingItems as $fitting_item) {
            $f[$fitting_item['item']['item']['key']] = [
                'item'  => $fitting_item['item'],
                'index' => $fitting_item['index']
            ];
        }
        $fittingItems = $f;
        unset($f);

        return [
            $tooBigItems,
            $fittingItems,
            $fitsFlyer,
        ];
    }

    private function fitSingleItems($singleItems, $globalFlyer, &$fitsFlyer, $waybillDescriptionOverride)
    {
        $j = 0;

        foreach ($singleItems as $singleItem) {
            $fitsFlyer = $fitsFlyer && $this->doesFitParcel($singleItem, $globalFlyer);
            $j++;
            $slug        = $singleItem['slug'];
            $entry       = [];
            $dim         = [];
            $dim['dim1'] = (int)$singleItem['dimensions']['width'];
            $dim['dim2'] = (int)$singleItem['dimensions']['height'];
            $dim['dim3'] = (int)$singleItem['dimensions']['length'];
            sort($dim);
            $entry['dim1']    = $dim[0];
            $entry['dim2']    = $dim[1];
            $entry['dim3']    = $dim[2];
            $entry['actmass'] = $singleItem['dimensions']['mass'];

            for ($i = 0; $i < $singleItem['item']['quantity']; $i++) {
                $entry['item']        = $j;
                $entry['description'] = !$waybillDescriptionOverride ? $slug : 'Item';
                $entry['itemCount']   = 1;
                $entry['pieces']      = 1;
                self::$r1[]           = $entry;
                $j++;
            }
            $j--;
        }

        return $j;
    }

    private function fitToobigItems($tooBigItems, $waybillDescriptionOverride, $j)
    {
        foreach ($tooBigItems as $tooBigItem) {
            $j++;
            $item = $tooBigItem;

            $slug                 = $item['slug'];
            $entry                = [];
            $entry['item']        = $j;
            $entry['description'] = !$waybillDescriptionOverride ? $slug : 'Item';
            $entry['pieces']      = $item['item']['quantity'];

            $dim         = [];
            $dim['dim1'] = (int)$item['dimensions']['length'];
            $dim['dim2'] = (int)$item['dimensions']['width'];
            $dim['dim3'] = (int)$item['dimensions']['height'];
            sort($dim);

            $entry['dim1']    = $dim[0];
            $entry['dim2']    = $dim[1];
            $entry['dim3']    = $dim[2];
            $entry['actmass'] = $item['dimensions']['mass'];

            for ($i = 0; $i < $tooBigItem['item']['quantity']; $i++) {
                $entry['item']        = $j;
                $entry['description'] = !$waybillDescriptionOverride ? $slug : 'Item';
                $entry['itemCount']   = 1;
                $entry['pieces']      = 1;
                self::$r1[]           = $entry;
                $j++;
            }

            self::$r1[] = $entry;
        }

        return $j;
    }

    private function array_flatten($array)
    {
        $flat = [];
        foreach ($array as $key => $value) {
            array_push($flat, $key);
            foreach ($value as $val) {
                array_push($flat, $val);
            }
        }
        $u = array_unique($flat);

        return $u;
    }

    /**
     * Will attempt to pool items of same dimensions to produce
     * better packing calculations
     *
     * Parameters are passed by reference, so modified in the function
     *
     * @param $fittingItems
     * @param $items
     */
    private function poolIfPossible(&$fittingItems)
    {
        $pools = [];

        $fittings = array_values($fittingItems);
        $nfit     = count($fittings);
        for ($i = 0; $i < $nfit; $i++) {
            $flat = $this->array_flatten($pools);
            if (!in_array($i, $flat)) {
                $pools[$i] = [];
            }
            for ($j = $i + 1; $j < $nfit; $j++) {
                if ($fittings[$i]['item']['volume'] != $fittings[$j]['item']['volume']) {
                    continue;
                }
                if (
                    $fittings[$i]['item']['dimensions']['height'] != $fittings[$j]['item']['dimensions']['height']
                    && $fittings[$i]['item']['dimensions']['width'] != $fittings[$j]['item']['dimensions']['width']
                ) {
                    continue;
                }
                $flat = $this->array_flatten($pools);
                if (!in_array($j, $flat)) {
                    $pools[$i][] = $j;
                }
            }
        }

        if (count($pools) == count($fittingItems)) {
            return;
        }

        $fitted = [];

        foreach ($pools as $k => $fit) {
            $key            = $fittings[$k]['item']['item']['key'];
            $grp_name       = $fittings[$k]['item']['slug'];
            $grp_quantity   = (float)$fittings[$k]['item']['item']['quantity'];
            $grp_mass       = $fittings[$k]['item']['dimensions']['mass'] * $grp_quantity;
            $grp_dimensions = $fittings[$k]['item']['dimensions'];
            foreach ($fit as $item) {
                $grp_name     .= '.';
                $grp_mass     += $fittings[$item]['item']['dimensions']['mass'] * (float)$fittings[$item]['item']['item']['quantity'];
                $grp_quantity += $fittings[$item]['item']['item']['quantity'];
            }
            $fitted[$key]                               = $fittings[$k];
            $fitted[$key]['item']['slug']               = $grp_name;
            $fitted[$key]['item']['dimensions']         = $grp_dimensions;
            $fitted[$key]['item']['dimensions']['mass'] = $grp_mass / $grp_quantity;
            $fitted[$key]['item']['item']['quantity']   = $grp_quantity;
        }

        $fittingItems = $fitted;
    }

    /**
     * @param $product
     *
     * @return bool
     */
    private function isSingleProductItem($product, $item_product_id = null)
    {
        if ($item_product_id !== null) {
            $psp = get_post_meta($item_product_id, 'product_single_parcel');
        } else {
            $psp = get_post_meta($product->get_id(), 'product_single_parcel');
        }

        if (is_array($psp) && count($psp) > 0) {
            return $psp[0] === 'on';
        }

        return false;
    }

    /**
     * @param $item
     * @param $globalParcels
     *
     * @return array
     */
    private function doesFitGlobalParcels($item, $globalParcels)
    {
        $globalParcelIndex = 0;
        foreach ($globalParcels as $globalParcel) {
            $fits = $this->doesFitParcel($item, $globalParcel);
            if ($fits) {
                break;
            }
            $globalParcelIndex++;
        }

        return ['fits' => $fits, 'fitsIndex' => $globalParcelIndex];
    }

    /**
     * @param $item
     * @param $parcel
     *
     * @return bool
     */
    private function doesFitParcel($item, $parcel)
    {
        // Parcel now has volume as element - need to drop before sorting
        unset($parcel['volume']);

        rsort($parcel);
        if ($item['has_dimensions']) {
            $productDims    = [];
            $productDims[0] = $item['dimensions']['length'];
            $productDims[1] = $item['dimensions']['width'];
            $productDims[2] = $item['dimensions']['height'];
            rsort($productDims);
            $fits = false;
            if (
                $productDims[0] <= $parcel[0]
                && $productDims[1] <= $parcel[1]
                && $productDims[2] <= $parcel[2]
            ) {
                $fits = true;
            }
        } else {
            $fits = true;
        }

        return $fits;
    }

    /**
     * @param array $shippingItem
     *
     * @return mixed
     */
    private function getServiceIdentifierFromShippingItem(
        $shippingItem
    ) {
        $method      = $shippingItem['method_id'];
        $methodParts = explode(':', $method);

        return $methodParts[1];
    }
}
