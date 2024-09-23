<?php


class ShipLogicContentPayload
{

    private $parameters;
    private $fittingItems;
    private $globalParcels;
    private $logging;
    private $log;

    private $r1;
    private $j;

    public function __construct($parameters, $fittingItems, $globalParcels, $logging, $log)
    {
        $this->parameters    = $parameters;
        $this->fittingItems  = $fittingItems;
        $this->globalParcels = $globalParcels;
        $this->logging       = $logging;
        $this->log           = $log;
        $this->r1            = ShipLogicApiPayload::$r1;
        $this->j             = ShipLogicApiPayload::$j;
    }

    /**
     * @param $parcel
     * @param $package
     *
     * @return mixed
     */
    private
    static function getMaxPackingConfiguration(
        $parcel,
        $package
    ) {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        $maxItems = 0;
        foreach ($boxPermutations as $key => $permutation) {
            $boxItems = (int)($parcel[0] / $package[$permutation[0]]);
            $boxItems *= (int)($parcel[1] / $package[$permutation[1]]);
            $boxItems *= (int)($parcel[2] / $package[$permutation[2]]);
            $maxItems = max($maxItems, $boxItems);
        }

        return $maxItems;
    }

    /**
     * @param $parcel
     * @param $package
     * @param $count
     *
     * @return float|int|mixed
     */
    private
    static function getActualPackingConfiguration(
        $parcel,
        $package,
        $count
    ) {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        $usedHeight = $parcel[2];
        foreach ($boxPermutations as $permutation) {
            $nl = (int)($parcel[0] / $package[$permutation[0]]);
            $nw = (int)($parcel[1] / $package[$permutation[1]]);
            $na = $nl * $nw;
            if ($na !== 0) {
                $h = ceil($count / ($nl * $nw)) * $package[$permutation[2]];
                if ($h < $usedHeight) {
                    $usedHeight = $h;
                }
            }
        }

        return $usedHeight;
    }

    private
    static function getActualPackingConfigurationAdvanced(
        $parcel,
        $package,
        $count
    ) {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        $usedHeight = $parcel[2];
        $useds      = [];
        foreach ($boxPermutations as $permutation) {
            $nl = (int)($parcel[0] / $package[$permutation[0]]);
            $nw = (int)($parcel[1] / $package[$permutation[1]]);
            $na = $nl * $nw;
            $h  = 0;
            if ($na !== 0) {
                $h = ceil($count / ($nl * $nw)) * $package[$permutation[2]];
                if ($h <= $usedHeight) {
                    $usedHeight = $h;
                }
            }
            $useds[] = [$nl * $package[$permutation[0]], $nw * $package[$permutation[1]], $h];
        }

        $used = [];
        foreach ($useds as $u) {
            if ($u[2] == $usedHeight) {
                $used = $u;
                break;
            }
        }

        $remainingBoxes = [];

        $vb1 = [$used[0], $used[1], $parcel[2] - $used[2]];
        rsort($vb1);
        $vb1['volume'] = $vb1[0] * $vb1[1] * $vb1[2];
        if ($vb1['volume'] > 0) {
            $remainingBoxes[] = $vb1;
        }

        $vb2 = [$parcel[0] - $used[0], $used[1], $parcel[2]];
        rsort($vb2);
        $vb2['volume'] = $vb2[0] * $vb2[1] * $vb2[2];
        if ($vb2['volume'] > 0) {
            $remainingBoxes[] = $vb2;
        }

        $vb3 = [$parcel[0], $parcel[1] - $used[1], $parcel[2]];
        rsort($vb3);
        $vb3['volume'] = $vb3[0] * $vb3[1] * $vb3[2];
        if ($vb3['volume'] > 0) {
            $remainingBoxes[] = $vb3;
        }

        return $remainingBoxes;
    }

    public function calculate_single_fitting_items_packing(&$r1, &$j)
    {
        $parameters_in    = $this->parameters;
        $fittingItems_in  = $this->fittingItems;
        $globalParcels_in = $this->globalParcels;

        $waybillDescriptionOverride = isset($parameters_in['remove_waybill_description']) && $parameters_in['remove_waybill_description'] === 'yes';

        // Handle with existing code which works
        foreach ($fittingItems_in as $fittingItem) {
            $item     = $fittingItem['item'];
            $quantity = $item['item']['quantity'];
            // Fit them into boxes
            $pdims = [
                $item['dimensions']['length'],
                $item['dimensions']['width'],
                $item['dimensions']['height']
            ];

            $nitems = $fittingItem['item']['item']['quantity'];

            // Calculate how many items will fit into each parcel
            $fits = [];
            foreach ($globalParcels_in as $k => $global_parcel) {
                $fits[$k] = self::getMaxPackingConfiguration($global_parcel, $pdims);
            }
            asort($fits);

            $bestFitIndex = 0;

            foreach ($fits as $k => $fit) {
                if ($fit >= $nitems) {
                    $bestFitIndex = $k;
                    break;
                }
            }

            $itemsPerBox = $fits[$bestFitIndex];

            if ($itemsPerBox === 0) {
                $itemsPerBox = max($fits);
            }

            $nboxes = (int)ceil($nitems / $itemsPerBox);

            if ($bestFitIndex != 0) {
                $fitsFlyer = false;
            }

            for ($box = 1; $box <= $nboxes; $box++) {
                $j++;
                $entry                = [];
                $slug                 = $fittingItem['item']['slug'];
                $entry['item']        = $j;
                $entry['description'] = !$waybillDescriptionOverride ? $slug : 'Item';
                $entry['itemCount']   = $quantity;
                if ($quantity >= $itemsPerBox) {
                    $entry['actmass'] = $itemsPerBox * $item['dimensions']['mass'];
                    $quantity         -= $itemsPerBox;
                } else {
                    $entry['actmass'] = $quantity * $item['dimensions']['mass'];
                }
                $entry['pieces'] = 1; // Each box counts as one piece
                $entry['dim1']   = $globalParcels_in[$bestFitIndex][0];
                $entry['dim2']   = $globalParcels_in[$bestFitIndex][1];
                $entry['dim3']   = $globalParcels_in[$bestFitIndex][2];
                $r1[]            = $entry;
            }
        }
    }

    public function calculate_multi_fitting_items_basic()
    {
        global $j;
        $parameters_in    = $this->parameters;
        $fittingItems_in  = $this->fittingItems;
        $globalParcels_in = $this->globalParcels;
        $logging_in       = $this->logging;
        $log_in           = $this->log;

        // Have more than one size items to try and pack
        // Start with the smallest box that will fit all products
        // and the largest product
        $initialBoxIndex = 0;

        $waybillDescriptionOverride = isset($parameters_in['remove_waybill_description']) && $parameters_in['remove_waybill_description'] === 'yes';

        $fits = [];

        foreach ($fittingItems_in as $fittingItem) {
            $pdims = [
                $fittingItem['item']['dimensions']['length'],
                $fittingItem['item']['dimensions']['width'],
                $fittingItem['item']['dimensions']['height']
            ];
            foreach ($globalParcels_in as $k => $global_parcel) {
                $fits[$k][$fittingItem['item']['slug']] = self::getMaxPackingConfiguration($global_parcel, $pdims);
            }

            foreach ($fits as $k => $fit) {
                foreach ($fit as $prod) {
                    if ($prod == 0) {
                        unset($fits[$k]);
                        unset($globalParcels_in[$k]);
                    }
                }
            }

            $globalParcels_in = array_values($globalParcels_in);

            $initialBoxIndex = 0;
        }

        $bestFit = false;
        $k       = $j;

        $fittingItemsFlat = array_values($fittingItems_in);

        while (!$bestFit) {
            $itemIndex           = 0;
            $anyItemsLeft        = true;
            $anyItemsLeftForItem = false;
            $nboxes              = 1;
            $r2                  = [];
            $j                   = $k;
            $j++;
            $entry                = [];
            $entry['description'] = '';
            $entry['actmass']     = 0;
            $entry['itemCount']   = 0;
            $boxIsFull            = false;
            $boxRemaining         = $globalParcels_in[$initialBoxIndex];
            while ($anyItemsLeft && $itemIndex !== count($fittingItems_in)) {
                if ($boxIsFull) {
                    $nboxes++;
                    $j++;
                    $entry                = [];
                    $entry['actmass']     = 0;
                    $entry['itemCount']   = 0;
                    $entry['description'] = '';
                    $boxIsFull            = false;
                    $boxRemaining         = $globalParcels_in[$initialBoxIndex];
                }
                if (!$anyItemsLeftForItem) {
                    $item = $fittingItemsFlat[$itemIndex];
                }

                // Calculate how many can be added
                $pdims = [
                    $item['item']['dimensions']['length'],
                    $item['item']['dimensions']['width'],
                    $item['item']['dimensions']['height'],
                ];
                $logging_in ? $log_in->add('thecourierguy', 'pdims: ' . json_encode($pdims)) : '';
                $logging_in ? $log_in->add('thecourierguy', 'item: ' . json_encode($item)) : '';
                // Calculate max that can be filled
                $logging_in ? $log_in->add('thecourierguy', 'boxremaining: ' . json_encode($boxRemaining)) : '';

                $maxItems = self::getMaxPackingConfiguration($boxRemaining, $pdims);
                $logging_in ? $log_in->add('thecourierguy', 'maxitem: ' . json_encode($maxItems)) : '';

                $slug                 = $item['item']['slug'];
                $entry['item']        = $j;
                $entry['description'] .= (!$waybillDescriptionOverride ? $slug : 'Item') . ', ';
                $entry['pieces']      = 1;
                $entry['dim1']        = $globalParcels_in[$initialBoxIndex][0];
                $entry['dim2']        = $globalParcels_in[$initialBoxIndex][1];
                $entry['dim3']        = $globalParcels_in[$initialBoxIndex][2];
                if ($maxItems == 0) {
                    $boxIsFull = true;
                    if ($item['item']['item']['quantity'] > 0) {
                        $anyItemsLeftForItem = true;
                    }
                    $r2[] = $entry;
                } elseif ($maxItems >= $item['item']['item']['quantity']) {
                    // Put them all in
                    $entry['actmass']   += $item['item']['item']['quantity'] * $item['item']['dimensions']['mass'];
                    $entry['itemCount'] += $item['item']['item']['quantity'];
                    $itemIndex++;
                    if ($itemIndex == count($fittingItems_in)) {
                        $anyItemsLeft = false;
                    }
                    // Calculate the remaining box content
                    $used                = self::getActualPackingConfiguration(
                        $boxRemaining,
                        $pdims,
                        $item['item']['item']['quantity']
                    );
                    $boxRemaining[2]     -= $used;
                    $anyItemsLeftForItem = false;
                    unset($item);
                } else {
                    // Fill the box and calculate remainder
                    $entry['actmass']                 += $maxItems * $item['item']['dimensions']['mass'];
                    $boxIsFull                        = true;
                    $r2[]                             = $entry;
                    $item['item']['item']['quantity'] -= $maxItems;
                    if ($item['item']['item']['quantity'] > 0) {
                        $anyItemsLeftForItem = true;
                    }
                }
            }
            $r2[] = $entry;
            if ($nboxes === 1 || ($nboxes > 1 && $initialBoxIndex == count($globalParcels_in) - 1)) {
                $bestFit = true;
            }
            $initialBoxIndex++;
        }

        return $r2;
    }

    /**
     * @return array|mixed
     */
    public function calculate_multi_fitting_items_advanced(): array
    {
        $parameters_in    = $this->parameters;
        $fittingItems_in  = $this->fittingItems;
        $globalParcels_in = $this->globalParcels;
        $logging_in       = $this->logging;
        $log_in           = $this->log;

        $fits = [];

        foreach ($fittingItems_in as $fittingItem) {
            $pdims = [
                $fittingItem['item']['dimensions']['length'],
                $fittingItem['item']['dimensions']['width'],
                $fittingItem['item']['dimensions']['height']
            ];
            foreach ($globalParcels_in as $k => $global_parcel) {
                $fits[$k][$fittingItem['item']['slug']] = self::getMaxPackingConfiguration($global_parcel, $pdims);
            }

            $globalParcels_in = array_values($globalParcels_in);
        }

        $tcgPackages = [];

        foreach ($fits as $fitIndex => $fit) {
            $remainingItems = $this->fittingItems;
            $results        = [];
            $anyItemsLeft   = true;
            while ($anyItemsLeft) {
                list($r2, $anyItemsLeft, $remainingItems) = $this->fitItemsInRealBoxes(
                    $remainingItems,
                    $fits,
                    (int)$fitIndex
                );
                if ($r2 !== null) {
                    $results[] = $r2[0];
                }
            }
            if (count($results) === 1) {
                return $results;
            }
            $tcgPackages[$fitIndex] = $results;
        }

        usort($tcgPackages, function ($a, $b) {
            if (count($a) === count($b)) {
                $avol = 0.0;
                foreach ($a as $value) {
                    $avol += $this->packVol($value);
                }
                $bvol = 0.0;
                foreach ($b as $value) {
                    $bvol += $this->packVol($value);
                }

                return $avol <=> $bvol;
            }

            return count($a) <=> count($b);
        });

        return $tcgPackages[0];
    }

    /**
     * @param array $package
     *
     * @return float
     */
    private function packVol(array $package): float
    {
        return (float)$package['dim1'] * (float)$package['dim2'] * (float)$package['dim3'];
    }

    /**
     * @param $items
     * @param $fits
     * @param int $boxndx
     *
     * @return array|null
     */
    private function fitItemsInRealBoxes($items, $fits, int $boxndx = 0): ?array
    {
        $items1 = array_values($items);

        $parameters_in    = $this->parameters;
        $globalParcels_in = $this->globalParcels;

        foreach ($fits as $fitKey => $fit) {
            if ((int)$fitKey < $boxndx) {
                unset($fits[$fitKey]);
            }
        }

        $waybillDescriptionOverride = isset($parameters_in['remove_waybill_description']) && $parameters_in['remove_waybill_description'] === 'yes';

        $anyItemsLeft = true;
        $j            = $this->j;
        $key          = 0;
        $j++;
        $entry  = [];
        $boxKey = null;

        for ($key = 0; $key < count($items1); $key++) {
            $item = $items1[$key];
            if ($item['item']['item']['quantity'] == 0) {
                continue;
            }
            $slug   = $item['item']['slug'];
            $boxKey = !$boxKey ? $this->getBoxKey($fits, $slug, $item['item']['item']['quantity']) : null;
            $box    = $globalParcels_in[$boxKey];

            $entry['item']        = $j;
            $entry['description'] = !$waybillDescriptionOverride ? $slug : 'Item';
            $entry['pieces']      = 1;
            $entry['dim1']        = $globalParcels_in[$boxKey][0];
            $entry['dim2']        = $globalParcels_in[$boxKey][1];
            $entry['dim3']        = $globalParcels_in[$boxKey][2];
            $entry['actmass']     = 0;

            // Calculate how many can be added
            $pdims    = [
                $item['item']['dimensions']['length'],
                $item['item']['dimensions']['width'],
                $item['item']['dimensions']['height'],
            ];
            $maxItems = self::getMaxPackingConfiguration($box, $pdims);
            if ($maxItems == 0) {
                return null;
            }
            $nItemsToAdd = min($maxItems, $item['item']['item']['quantity']);

            // Put nItemsToAdd into the box
            $entry['actmass']                         += $nItemsToAdd * $item['item']['dimensions']['mass'];
            $items1[$key]['item']['item']['quantity'] -= $nItemsToAdd;

            // Calculate the remaining boxes content
            $vboxes = self::getActualPackingConfigurationAdvanced(
                $box,
                $pdims,
                $nItemsToAdd
            );

            // These are now virtual boxes - maximum three
            for ($vboxi = 0; $vboxi < count($vboxes); $vboxi++) {
                $this->fitItemsInVbox($vboxes[$vboxi], $items1, $entry);
            }
            break;
        }
        $r2[]           = $entry;
        $itemsRemaining = 0;
        foreach ($items1 as $item1) {
            $itemsRemaining += $item1['item']['item']['quantity'];
        }
        $anyItemsLeft = $itemsRemaining > 0;
        $this->j      = $j;

        return [$r2, $anyItemsLeft, array_values($items1)];
    }


    private function fitItemsInVbox($vbox, &$items1, &$entry)
    {
        for ($itemi = 0; $itemi < count($items1); $itemi++) {
            $itemvb = $items1[$itemi];
            if ($itemvb['item']['item']['quantity'] == 0) {
                continue;
            }

            // Calculate how many can be added
            $pdims    = [
                $itemvb['item']['dimensions']['length'],
                $itemvb['item']['dimensions']['width'],
                $itemvb['item']['dimensions']['height'],
            ];
            $maxItems = self::getMaxPackingConfiguration($vbox, $pdims);
            if ($maxItems == 0) {
                continue;
            }

            // Else put the items into this virtual box
            $nitems = min(
                $maxItems,
                $itemvb['item']['item']['quantity']
            );

            $items1[$itemi]['item']['item']['quantity'] -= $nitems;
            $entry['actmass']                           += $nitems * $itemvb['item']['dimensions']['mass'];

            // Calculate the remaining vboxes content
            $vboxes = self::getActualPackingConfigurationAdvanced(
                $vbox,
                $pdims,
                $nitems
            );

            for ($vbi = 0; $vbi < count($vboxes); $vbi++) {
                $this->fitItemsInVbox($vboxes[$vbi], $items1, $entry);
            }
            break;
        }
    }

    private
    function getBoxKey(
        $fits,
        $slug,
        $itemCount
    ) {
        $fitsSlug = 0;
        foreach ($fits as $key => $fit) {
            $fitsSlug = $key;
            if ($fit[$slug] >= $itemCount) {
                break;
            }
        }

        return $fitsSlug;
    }

    private
    function getMinBoxConfigByVolume(
        $remainingItems
    ) {
        $totalVolume = 0;
        foreach ($remainingItems as $remainingItem) {
            $totalVolume += $this->getItemTotalVolume($remainingItem);
        }

        $parcels = $this->globalParcels;

        $config = [];
        foreach ($parcels as $parcel) {
            $n        = $totalVolume / (float)$parcel['volume'];
            $config[] = $n;
        }

        return $config;
    }

    private
    function getItemTotalVolume(
        $item
    ) {
        return $item['item']['item']['quantity'] * $item['item']['volume'];
    }
}
