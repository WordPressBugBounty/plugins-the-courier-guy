<?php

$pluginpath = plugin_dir_path(__DIR__);

/**
 * @author The Courier Guy
 * @package tcg/core
 */
class TCG_Plugin extends CustomPlugin
{
    public const TCG_SHIP_LOGIC_SECRET_ACCESS_TOKEN  = 'tcg_ship_logic_secret_access_token';
    public const TCG_SHIP_LOGIC_SECRET_ACCESS_KEY    = 'tcg_ship_logic_secret_access_key';
    public const TCG_SHIP_LOGIC_ACCESS_KEY_ID        = 'tcg_ship_logic_access_key_id';
    public const TCG_LOGGING                         = 'tcg_logging';
    public const SHIP_LOGIC_SHORT_TRACKING_REFERENCE = 'ship_logic_short_tracking_reference';

    /**
     * @var WC_Logger
     */
    private static $log;
    private $shipLogicApi;
    private $parcelPerfectApiPayload;
    /**
     * @var array
     */
    private $parameters;

    /**
     * TCG_Plugin constructor.
     *
     * @param $file
     */
    public function __construct($file)
    {
        parent::__construct($file);
        $this->initializeShipLogicApi();
        $this->initializeShipLogicApiPayload();
        $this->registerShippingMethod();

        add_action('wp_enqueue_scripts', [$this, 'registerJavascriptResources']);
        add_action('wp_enqueue_scripts', [$this, 'registerCSSResources']);
        add_action('wp_enqueue_scripts', [$this, 'localizeJSVariables']);
        add_action('admin_enqueue_scripts', [$this, 'registerJavascriptResources']);
        add_action('admin_enqueue_scripts', [$this, 'localizeJSVariables']);
        add_action('login_enqueue_scripts', [$this, 'localizeJSVariables']);
        add_action('woocommerce_checkout_update_order_review', [$this, 'updateShippingPropertiesFromCheckout']);
        add_filter('woocommerce_checkout_fields', [$this, 'addIihtcgFields'], 10, 1);
        add_filter('woocommerce_form_field_tcg_place_lookup', [$this, 'getSuburbFormFieldMarkUp'], 1, 4);
        add_filter('woocommerce_email_before_order_table', [$this, 'addExtraEmailFields'], 10, 3);

        add_action('woocommerce_order_actions', [$this, 'addSendCollectionActionToOrderMetaBox'], 10, 2);
        add_action('woocommerce_order_actions', [$this, 'addPrintWayBillActionToOrderMetaBox'], 10, 2);
        add_action('admin_post_print_waybill', [$this, 'redirectToPrintWaybillUrl'], 10, 0);
        add_action('woocommerce_order_action_tcg_print_waybill', [$this, 'redirectToPrintWaybillUrl'], 10, 1);
        add_filter('woocommerce_admin_shipping_fields', [$this, 'addShippingMetaToOrder'], 10, 1);

        add_action('woocommerce_order_action_tcg_send_collection', [$this, 'createShipmentFromOrder'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'createShipmentOnOrderProcessing'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'updateShippingPropertiesOnOrder'], 10, 2);

        add_action('woocommerce_shipping_packages', [$this, 'updateShippingPackages'], 20, 1);
        add_action('woocommerce_after_calculate_totals', [$this, 'getCartTotalCost'], 20, 1);

        add_action('woocommerce_checkout_billing', [$this, 'add_shipping_selector']);

        /* Add  Admin Disclaimer notice */
        add_action('admin_notices', [$this, 'addDisclaimer']);
        add_action('wp_ajax_dismissed_notice_handler', [$this, 'ajax_notice_handler']);

        add_filter('thecourierguy_flyer_fits_filter', [$this, 'flyer_fits_flyer_filter'], 10, 3);

        add_action(
            'woocommerce_review_order_before_order_total',
            [TCG_Shipping_Method::class, 'shipLogicRateOptins'],
            10,
            2
        );

        add_action('wc_ajax_update_order_review', [$this, 'test_ajax']);

        add_action('woocommerce_checkout_process', [$this, 'courier_my_order_to_me_validation']);

        add_action('woocommerce_order_item_add_action_buttons', [$this, 'addTCGReturnButton'], 10, 1);

        add_action('woocommerce_admin_order_items_after_fees', [$this, 'recalculateShippingOnAdminRecalculate'], 10, 1);
    }

    public function recalculateShippingOnadminRecalculate(int $orderId)
    {
        if (!isset($_POST['action']) || 'woocommerce_calc_line_taxes' !== sanitize_text_field($_POST['action'])) {
            return;
        }
        $order        = wc_get_order($orderId);
        $getRatesBody = $order->get_meta(ShipLogicApi::TCG_SHIP_LOGIC_GETRATES_BODY, true);
        $this->initializeShipLogicApi();
        $response       = $this->shipLogicApi->makeAPIRequest(
            'getRates',
            ['body' => json_encode($getRatesBody)]
        );
        $getRatesResult = json_decode($response, true);
        $order->update_meta_data(TCG_Shipping_Method::TCG_SHIP_LOGIC_RESULT, ['rates' => $getRatesResult]);
        $order->save();
    }

    public function courier_my_order_to_me_validation()
    {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $current_shipping_method = $chosen_shipping_methods[0] ?? null;
        $courier_guy_word        = "the_courier_guy";
        $flatRateWord            = "flat_rate";
        $pudo_word               = "pickup_dropoff";

        if (($_POST['iihtcg_selector_input'] ?? '') == 'tcg' && strpos(
                                                                    $current_shipping_method,
                                                                    $courier_guy_word
                                                                ) === false && strpos(
                                                                                   $current_shipping_method,
                                                                                   $flatRateWord
                                                                               ) === false
            && strpos($current_shipping_method, $pudo_word) === false) {
            wc_add_notice(__('Please select a shipping method.'), 'error');
        }
    }

    public function test_ajax($fields)
    {
        $fields['ship_logic_opt_ins'] = [
            'label'    => 'Opt Ins',
            'required' => false,
            'type'     => 'text',
        ];

        return $fields;
    }


    public function ajax_notice_handler()
    {
        if (isset($_POST['type'])) {
            $type = sanitize_html_class($_POST['type']);
            update_option('dismissed-' . $type, true);
        }
    }

    public function addIihtcgFields($fields)
    {
        $settings = $this->getShippingMethodSettings();

        if (isset($settings['enablemethodbox']) && $settings['enablemethodbox'] === 'yes') {
            $fields['billing']['iihtcg_method'] = [
                'label'    => 'iihtcg_method',
                'type'     => 'text',
                'required' => true,
                'default'  => 'none',
            ];
        }

        return $fields;
    }

    /**
     * Add shipping selector
     */
    public function add_shipping_selector()
    {
        $settings = $this->getShippingMethodSettings();

        if (is_checkout()
            && isset($settings['enablemethodbox'])
            && $settings['enablemethodbox'] === 'yes'
        ) {
            if ($this->is_woocommerce_blocks_checkout()) {
                echo <<<HTML
<div id="wc-blocks-notice-1" class="form-row form-row-wide" style="border: 1px solid lightgrey; padding:5px;">
<h5 style="color: red">Method Box is not currently available for WooCommerce Blocks, please disable it.</h5>
</div>
HTML;
            } else {
                echo <<<HTML
<div class="form-row form-row-wide" id="iihtcg_selector" style="border: 1px solid lightgrey; padding:5px;">
<h3 style="margin-top: 0;">Shipping/Collection</h3>
<h5 id="please_select" style="color: #ff0000;">Please select an option to proceed</h5>
<h5 id="please_complete" style="color: red" hidden>Please complete all required fields to proceed</h5>
<span class="woocommerce-input-wrapper">
<span id="couriertome"><input type="radio" name="iihtcg_selector_input" id="iihtcg_selector_input_tcg" value="tcg"><span style="display:inline;margin-left 5px !important;"><label for="iihtcg_selector_input_tcg"><strong>Courier my order to me</strong></label></span></span>
<span id="collectorder"><input type="radio" name="iihtcg_selector_input" id="iihtcg_selector_input_collect" value="collect"><span style="display:inline;"><label for="iihtcg_selector_input_collect"><strong>I will collect my order</strong></label></span></span>
</span>
</div>
HTML;
            }
        }
    }

    /**
     * Store cart total for multi-vendor in session
     * Packages are passed by vendor to shipping so cart total can't be seen
     *
     * @param $cart
     */
    public function getCartTotalCost($cart)
    {
        if ($wc_session = WC()->session) {
            $settings = $this->getShippingMethodSettings();
            if (isset($settings['multivendor_single_override']) && $settings['multivendor_single_override'] === 'yes') {
                $wc_session->set('customer_cart_subtotal', $cart->get_subtotal() + $cart->get_subtotal_tax());
            }
            $order = wc_get_order($wc_session->get("store_api_draft_order")) ?? false;
            if ($order) {
                $this->updateShippingPropertiesOnOrder($order->get_id(), []);
                $this->add_shipping_selector();
                TCG_Shipping_Method::shipLogicRateOptins();
                $this->updateShippingPropertiesFromCheckout();
            }
        }
    }

    /**
     * @param $rates
     *
     * @return mixed
     */
    public function updateShippingPackages($rates)
    {
        $settings = $this->getShippingMethodSettings();
        $maxRates = [];
        if (isset($settings['multivendor_single_override']) && $settings['multivendor_single_override'] === 'yes') {
            foreach ($rates as $key => $vendor_rate) {
                $maxR = 0;
                foreach ($vendor_rate['rates'] as $k => $r) {
                    if (strpos($k, 'the_courier_guy') !== false) {
                        $maxR = max($maxR, (float)($r->get_cost() + $r->get_shipping_tax()));
                    }
                }
                $maxRates[] = ['key' => $key, 'val' => $maxR];
            }
        }
        usort(
            $maxRates,
            function ($a, $b) {
                if ($a['val'] === $b['val']) {
                    return 0;
                }

                return $a['val'] > $b['val'] ? -1 : 1;
            }
        );
        $cnt = 0;
        foreach ($maxRates as $maxRate) {
            if ($cnt !== 0) {
                foreach ($rates[$maxRate['key']]['rates'] as $vendor_rate) {
                    $method = $vendor_rate->get_method_id();
                    $label  = $vendor_rate->get_label();
                    if (strpos($method, 'the_courier_guy') !== false) {
                        $vendor_rate->set_cost(0);
                        $taxes = $vendor_rate->get_taxes();
                        foreach ($taxes as $key => $tax) {
                            $taxes[$key] = 0;
                        }
                        $vendor_rate->set_taxes($taxes);
                        if (strpos($label, 'Free Shipping') === false) {
                            $vendor_rate->set_label($label . ': Free Shipping');
                        }
                    }
                }
            }
            $cnt++;
        }

        $postdata = [];
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $postdata);
        }

        if (isset($postdata['iihtcg_method'])) {
            switch ($postdata['iihtcg_method']) {
                case 'none':
                    return [];
                    break;
                case 'collect':
                    foreach ($rates[0]['rates'] as $method => $rate) {
                        if (strpos($method, 'local_pickup') === false) {
                            unset($rates[0]['rates'][$method]);
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $rates;
    }

    /**
     * @param int $orderId
     * @param array $data
     */
    public function updateShippingPropertiesOnOrder($orderId, $data)
    {
        if ($wc_session = WC()->session) {
            $order = !empty($data) ? new WC_Order($orderId) : wc_get_order($orderId);

            $getRatesBody = $wc_session->get(ShipLogicApi::TCG_SHIP_LOGIC_GETRATES_BODY);
            if ($getRatesBody) {
                $order->update_meta_data(ShipLogicApi::TCG_SHIP_LOGIC_GETRATES_BODY, $getRatesBody);
            }
            $getRatesResult = $wc_session->get(TCG_Shipping_Method::TCG_SHIP_LOGIC_RESULT);
            if ($getRatesResult) {
                $order->update_meta_data(TCG_Shipping_Method::TCG_SHIP_LOGIC_RESULT, $getRatesResult);
            } else {
                if (isset($_POST['iihtcg_selector_input'])) {
                    if ($_POST['iihtcg_selector_input'] == 'tcg') {
                        $this->checkIfQuoteIsEmpty();
                    }
                } else {
                    $this->checkIfQuoteIsEmpty();
                }
            }

            $shippingMethods = $data['shipping_method'] ?? $wc_session->get("chosen_shipping_methods");

            $order->update_meta_data('_order_shipping_data', json_encode($shippingMethods));

            //Loop through order shipping items
            foreach ($order->get_items('shipping') as $item_id => $item) {
                $item->set_method_id($shippingMethods[0]);
                $item->save();
                break;
            }

            $order->add_order_note('Order shipping total on order: ' . $order->get_shipping_total());
            $order->save();
            $order->save_meta_data();
            $this->clearShippingCustomProperties();
            $wc_session->set('customer_cart_subtotal', '');
        }
    }

    /**
     * @param string $postData
     */
    public function updateShippingPropertiesFromCheckout($postData = [])
    {
        if ($wc_session = WC()->session) {
            $settings = $this->getShippingMethodSettings();

            if ($this->is_woocommerce_blocks_checkout()) {
                $wc_session->set("is_blocks", 1);
            }

            if (empty($postData)) {
                $parameters      = $wc_session->get_session_data();
                $shippingMethods = $wc_session->get('chosen_shipping_methods') ?? null;
            } else {
                parse_str($postData, $parameters);
                $shippingMethods = $parameters['shipping_method'] ?? null;
            }

            $addressPrefix = 'shipping_';
            if (!isset($parameters['ship_to_different_address']) || $parameters['ship_to_different_address'] != true) {
                $addressPrefix = 'billing_';
            }
            $insurance = false;
            if ((!empty($parameters[$addressPrefix . 'insurance']) && $parameters[$addressPrefix . 'insurance'] == '1')
                || ((int)$wc_session->get("is_blocks") === 1 && $settings['billing_insurance'] === "yes")
            ) {
                $insurance = true;
            }

            $customProperties = [
                'tcg_insurance' => $insurance,
            ];

            if (is_array($shippingMethods)) {
                foreach ($shippingMethods as $vendorId => $shippingMethod) {
                    if ($vendorId === 0) {
                        $customProperties['tcg_shipping_method'] = $shippingMethod;
                        $qn                                      = json_encode(
                            $wc_session->get('tcg_quote_response')
                        );
                        if ($qn == 'null' || strlen($qn) < 3) {
                            $qn = $wc_session->get('tcg_response');
                        }
                        if (isset($qn) && strlen($qn) > 2) {
                            $quote = json_decode($qn, true);
                            if (isset($quote[0])) {
                                $customProperties['tcg_quoteno'] = $quote[0]['quoteno'];
                                $shippingService                 = explode(':', $shippingMethod)[1];
                                $rates                           = $quote[0]['rates'];
                                foreach ($rates as $service) {
                                    if ($shippingService === $service['service']) {
                                        $customProperties['shippingCartage'] = $service['subtotal'];
                                        $customProperties['shippingVat']     = $service['vat'];
                                        $customProperties['shippingTotal']   = $service['total'];
                                    }
                                }
                            }
                        }
                    } else {
                        $customProperties['tcg_shipping_method_' . $vendorId] = $shippingMethod;
                        $vendorId                                             = $vendorId === 0 ? '' : $vendorId;
                        $qn                                                   = json_encode(
                            $wc_session->get('tcg_quote_response' . $vendorId)
                        );
                        if ($qn == 'null' || strlen($qn) < 3) {
                            $qn = $wc_session->get('tcg_response' . $vendorId);
                        }
                        if (isset($qn) && strlen($qn) > 2) {
                            $quote = json_decode($qn, true);
                            if (isset($quote[0])) {
                                $customProperties['tcg_quoteno_' . $vendorId] = $quote[0]['quoteno'];
                                $shippingService                              = explode(':', $shippingMethod)[1];
                                $rates                                        = $quote[0]['rates'];
                                foreach ($rates as $service) {
                                    if ($shippingService === $service['service']) {
                                        $customProperties['shippingCartage_' . $vendorId] = $service['subtotal'];
                                        $customProperties['shippingVat_' . $vendorId]     = $service['vat'];
                                        $customProperties['shippingTotal_' . $vendorId]   = $service['total'];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->setShippingCustomProperties($customProperties);
            $this->removeCachedShippingPackages();
        }
    }

    function is_woocommerce_blocks_checkout()
    {
        $content = get_the_content();
        if (strpos($content, 'wp-block-woocommerce-checkout') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getShippingCustomProperties($order = null)
    {
        $result = [];
        if ($wc_session = WC()->session) {
            $customProperties = $wc_session->get('custom_properties');
            if ($customProperties && is_array($customProperties)) {
                foreach ($customProperties as $customProperty) {
                    $result[$customProperty] = $wc_session->get($customProperty);
                }
            }
        }

        return $result;
    }

    /**
     * @param $order
     * @param $sent_to_admin
     * @param $plain_text
     *
     * @return mixed
     */
    public function addExtraEmailFields($order, $sent_to_admin, $plain_text)
    {
        // Check to see if this is a TCG shipping method
        if ($this->hasTcgShippingMethod($order)) {
            global $wpdb;
            $query   = "select meta_value from $wpdb->postmeta where post_id = %s and meta_key = 'dawpro_waybill'";
            $waybill = $wpdb->get_results($wpdb->prepare($query, [$order->get_id()]));
            if ($waybill && count($waybill) > 0) {
                $waybillNo = $waybill[0]->meta_value;
                echo <<<HTML
<br><br><span>Your Waybill: $waybillNo <a href="https://thecourierguy.pperfect.com/?w=$waybillNo">
  Click me to track</a></span><br><br>
HTML;
            }
        } else {
            return;
        }
    }

    /**
     * @param WC_Order $orderwoocommerce_my_account_my_orders_actions
     */
    public function createShipmentFromOrder($order, $returnShipment = false)
    {
        return $this->createShipment($order, $returnShipment);
    }

    /**
     * @param int $orderId
     */
    public function createShipmentOnOrderProcessing($orderId, $order)
    {
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        if ($this->hasTcgShippingMethod(
                $order
            ) && $shippingMethodParameters['automatically_submit_collection_order'] === 'yes') {
            $this->createShipment($order);
        }
    }

    /**
     * @param array $adminShippingFields
     *
     * @return array
     */
    public function addShippingMetaToOrder($adminShippingFields = [])
    {
        $tcgAdminShippingFields = [
            'insurance' => [
                'label'    => __('Courier Guy Insurance'),
                'class'    => 'wide',
                'show'     => true,
                'readonly' => true,
                'type',
                'checkbox'
            ],
            'area'      => [
                'label'             => __('Courier Guy Shipping Area Code'),
                'wrapper_class'     => 'form-field-wide',
                'show'              => true,
                'custom_attributes' => [
                    'disabled' => 'disabled',
                ],
            ],
            'place'     => [
                'label'             => __('Courier Guy Shipping Area Description'),
                'wrapper_class'     => 'form-field-wide',
                'show'              => true,
                'custom_attributes' => [
                    'disabled' => 'disabled',
                ],
            ],
        ];

        return array_merge($adminShippingFields, $tcgAdminShippingFields);
    }

    /**
     * @param WC_Order $order
     */
    public function redirectToPrintWaybillUrl($order)
    {
        $orderId = $order->get_id();
        $id      = $order->get_meta('ship_logic_order_id', true);

        $shipLogicApi = $this->shipLogicApi;
        try {
            $url    = $shipLogicApi->getShipmentLabel($id);
            $pdfUrl = json_decode($url)->url;
            wp_redirect($pdfUrl);
            exit;
        } catch (Exception $exception) {
        }

        exit;
    }

    /**
     * Create TCG return button
     *
     * @param $order
     *
     * @return void
     */
    function addTCGReturnButton($order): void
    {
        $shipping_method = reset($order->get_shipping_methods());

        $shipping_method_id = $shipping_method['method_id'];

        if (!str_contains($shipping_method_id, "the_courier_guy")
            || $order->get_meta("tcg_returned") === "1") {
            return;
        }

        $label = esc_html__('Return TCG Shipment', 'woocommerce');
        $slug  = 'return';
        ?>
        <button type="button" id="tcg-return-btn"
                disabled class="button <?php
        echo $slug; ?>-items">
            <span class="button-text"><?php
                echo $label; ?></span>
            <span hidden class="loading-indicator">Loading...</span>
        </button>
        <script>
          jQuery('#tcg-return-btn').on('click', function () {
            jQuery(this).find('.loading-indicator').show()
            jQuery(this).find('.button-text').hide()
            jQuery.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              data: {
                action: 'tcg_return_action',
                order_id: <?php echo $order->get_id(); ?>
              },
              success: function (response) {
                jQuery(this).find('.loading-indicator').hide()
                jQuery(this).find('.button-text').show()
                if (true === response.success) {
                  console.log(response)
                  window.location.reload()
                } else {
                  window.alert(response)
                }
              }
            })
          })
        </script>
        <?php
    }

    /**
     * @param array $actions
     * @param $order
     *
     * @return mixed
     */
    public
    function addPrintWayBillActionToOrderMetaBox(
        array $actions,
        $order
    ) {
        $hasShippingMethod = $this->hasTcgShippingMethod($order);
        $waybill           = $order->get_meta(self::SHIP_LOGIC_SHORT_TRACKING_REFERENCE, true);
        if ($hasShippingMethod && $waybill !== '') {
            $actions['tcg_print_waybill'] = __('Print Waybill', 'woocommerce');

            $this->enableReturnButton();
        }

        return $actions;
    }

    private function enableReturnButton(): void
    {
        ?>
        <script>
          jQuery(function () {
            jQuery('.return-items').prop('disabled', false)
          })
        </script>
        <?php
    }

    /**
     * @param array $actions
     * @param $order
     *
     * @return mixed
     */
    public
    function addSendCollectionActionToOrderMetaBox(
        array $actions,
        $order
    ) {
        $hasShippingMethod = $this->hasTcgShippingMethod($order);
        $waybill           = $order->get_meta(self::SHIP_LOGIC_SHORT_TRACKING_REFERENCE, true);
        if ($hasShippingMethod && $waybill === '') {
            $actions['tcg_send_collection'] = __('Send Order to Courier Guy', 'woocommerce');
        }

        return $actions;
    }

    /**
     * @param $field
     * @param $key
     * @param $args
     * @param $value
     *
     * @return string
     */
    public
    function getSuburbFormFieldMarkUp(
        $field,
        $key,
        $args,
        $value
    ) {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        if ($args['required']) {
            $args['class'][] = 'validate-required';
            $required        = ' <abbr class="required" title="' . esc_attr__(
                    'required',
                    'woocommerce'
                ) . '">*</abbr>';
        } else {
            $required = '';
        }
        $options                  = $field = '';
        $label_id                 = $args['id'];
        $sort                     = $args['priority'] ? $args['priority'] : '';
        $field_container          = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr($sort) . '">%3$s</p>';
        $customShippingProperties = $this->getShippingCustomProperties();
        $option_key               = isset($customShippingProperties['tcg_place_id']) ? $customShippingProperties['tcg_place_id'] : '';
        $option_text              = isset($customShippingProperties['tcg_place_label']) ? $customShippingProperties['tcg_place_label'] : '';
        $options                  .= '<option value="' . esc_attr($option_key) . '" ' . selected(
                $value,
                $option_key,
                false
            ) . '>' . esc_attr($option_text) . '</option>';
        $field                    .= '<input type="hidden" name="' . esc_attr(
                $key
            ) . '_place_id" value="' . $option_key . '"/>';
        $field                    .= '<input type="hidden" name="' . esc_attr(
                $key
            ) . '_place_label" value="' . $option_text . '"/>';
        $field                    .= '<select id="' . esc_attr($args['id']) . '" name="' . esc_attr(
                $args['id']
            ) . '" class="select ' . esc_attr(
                                         implode(' ', $args['input_class'])
                                     ) . '" ' . ' data-placeholder="' . esc_attr($args['placeholder']) . '">
                            ' . $options . '
                        </select>';
        if (!empty($field)) {
            $field_html = '';
            if ($args['label'] && 'checkbox' != $args['type']) {
                $field_html .= '<label for="' . esc_attr($label_id) . '" class="' . esc_attr(
                        implode(' ', $args['label_class'])
                    ) . '">' . $args['label'] . $required . '</label>';
            }
            $field_html .= $field;
            if ($args['description']) {
                $field_html .= '<span class="description">' . esc_html($args['description']) . '</span>';
            }
            $container_class = esc_attr(implode(' ', $args['class']));
            $container_id    = esc_attr($args['id']) . '_field';
            $field           = sprintf($field_container, $container_class, $container_id, $field_html);
        }

        return $field;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public
    function overrideAddressFields(
        $fields
    ) {
        return $fields;
    }

    /**
     *
     */
    public
    function removeCachedShippingPackages()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $packages = WC()->cart->get_shipping_packages();
        if ($wc_session = WC()->session) {
            foreach ($packages as $key => $value) {
                $shipping_session = "shipping_for_package_$key";
                $wc_session->set($shipping_session, '');
            }
        }
        $wc_session->set('tcg_prohibited_vendor', '');
        $this->updateCachedQuoteRequest([], '');
        $this->updateCachedQuoteResponse([], '');
    }

    /**
     *
     */
    public
    function getSuburbs()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $term        = sanitize_text_field($_GET['q']['term']);
        $dp_areas    = [];
        $payloadData = [
            'name' => $term,
        ];
        $d           = $this->getPlacesByName($payloadData);
        foreach ($d as $result) {
            $suggestion = [
                'suburb_value' => $result['town'],
                'suburb_key'   => $result['place'],
            ];
            $dp_areas[] = $suggestion;
        }
        echo json_encode($dp_areas);
        exit;
    }

    /**
     *
     */
    public
    function localizeJSVariables()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin, however slightly refactored.
        $southAfricaOnly        = false;
        $shippingMethodSettings = $this->getShippingMethodSettings();
        if (!empty($shippingMethodSettings) && !empty($shippingMethodSettings['south_africa_only']) && $shippingMethodSettings['south_africa_only'] == 'yes') {
            $southAfricaOnly = true;
        }
        $translation_array = [
            'url'             => get_admin_url(null, 'admin-ajax.php'),
            'southAfricaOnly' => ($southAfricaOnly) ? 'true' : 'false',
        ];
        wp_localize_script($this->getPluginTextDomain() . '-main510.js', 'theCourierGuy', $translation_array);
    }

    /**
     *
     */
    public
    function registerJavascriptResources()
    {
        $this->registerJavascriptResource('main510.js', ['jquery']);
        $this->registerJavascriptResource('notice.js', ['jquery']);

        $settings = $this->getShippingMethodSettings();
    }

    /**
     *
     */
    public
    function registerCSSResources()
    {
        $this->registerCSSResource('main510.css');
    }

    /**
     * Initiate plugin activation
     */
    public
    function intiatePluginActivation()
    {
        delete_option('dismissed-tcg_disclaimer');
        $this->activatePlugin();
    }

    /**
     * Admin Disclaimer notice on Activation.
     */
    function addDisclaimer()
    {
        if (!get_option('dismissed-tcg_disclaimer', false)) { ?>
            <div class="updated notice notice-the-courier-guy is-dismissible" data-notice="tcg_disclaimer">
                <p><strong>The Courier Guy</strong></p>
                <p>Parcel sizes are based on your packaging structure. The plugin will compare the cart’s total
                    dimensions against “Flyer”, “Medium” and “Large” parcel sizes to determine the best fit. The
                    resulting calculation will be submitted to The Courier Guy as using the parcel’s dimensions.
                    <strong>By downloading and using this plugin, you accept that incorrect ‘Parcel Size’ settings
                        may cause quotes to be inaccurate, and The Courier Guy will not be responsible for these
                        inaccurate quotes.</strong> Please make sure all dimensions are in CM and weight in KG.</p>
            </div>
            <?php
        }
    }

    /**
     * Remove flyer options if parcel does not fit into flyer package
     *
     * @param $result
     * @param $payload
     *
     * @return mixed
     */
    public
    function flyer_fits_flyer_filter(
        $result,
        $payload
    ) {
        $nonFlyer = ['LSF', 'LOF', 'NFS',];
        if (!$payload['contents']['fitsFlyer']) {
            foreach ($result as $j => $item) {
                foreach ($item['rates'] as $k => $rate) {
                    if (in_array($rate['service'], $nonFlyer)) {
                        unset($result[$j]['rates'][$k]);
                    }
                }
                $result[$j]['rates'] = array_values($result[$j]['rates']);
            }
            $result = array_values($result);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getShipLogicApi()
    {
        return $this->shipLogicApi;
    }

    /**
     * @param $base_rate
     *
     * @return string
     */
    public function getRateName($base_rate)
    {
        $name = 'The Courier Guy ' . $base_rate['service_level']['code'] . ': ';
        if (!empty($base_rate['time_based_rate_adjustments'] && !empty($base_rate['rate_adjustments']))) {
            $name .= $base_rate['time_based_rate_adjustments'][0]['name'] . ': ';
            $name .= $base_rate['rate_adjustments'][0]['name'];
        } elseif (!empty($base_rate['time_based_rate_adjustments'])) {
            $name .= $base_rate['time_based_rate_adjustments'][0]['name'];
        } elseif (!empty($base_rate['rate_adjustments'])) {
            $name .= $base_rate['rate_adjustments'][0]['name'];
        } else {
            $name .= 'Fuel charge';
        }

        return $name;
    }

    /**
     * @param $status
     * @param $message
     */
    public function get_message_json($status, $message)
    {
        $role = "info";
        if ($status == "error") {
            $role = "alert";
        }

        $html = '<ul class="woocommerce-' . $status . '" role="' . $role . '"><li data-id="billing_tcg_place_lookup"><strong>' . $message . '</strong></li></ul>';

        $response = array(
            'result'   => $status,
            'messages' => $html,
            'refresh'  => true,
            'reload'   => false
        );

        if ($status == "error") {
            echo json_encode($response);
            exit(0);
        }
    }

    /**
     *
     */
    protected
    function registerModel()
    {
        require_once $this->getPluginPath() . 'Model/Product.php';
    }

    /**
     * @param string $postData
     */
    private function checkIfQuoteIsEmpty()
    {
        $shippingMethodSettings = $this->getShippingMethodSettings();

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        $message_text = "";
        if (sizeof(WC()->cart->get_cart()) > 0) {
            $status = "success";
        } else {
            $status       = "error";
            $message_text = "Failed to get shipping rates, please try another address. Cart Empty";
        }

        if ($wc_session = WC()->session) {
            $tcg_prohibited_vendor = $wc_session->get('tcg_prohibited_vendor');
            if ($tcg_prohibited_vendor != '') {
                $status       = "info";
                $message_text = "Please note you have a product that can not be shipped by The Courier Guy in your cart.";
            }
        }

        $this->get_message_json($status, $message_text);
    }

    private function emptyUploadsDirectory($directory)
    {
        $handle = opendir($directory);
        while (($content = readdir($handle)) !== false) {
            $f = $directory . '/' . $content;
            if (!is_dir($f)) {
                unlink($f);
            } elseif (is_dir($f) && $content != '.' && $content != '..') {
                $this->emptyUploadsDirectory($f);
                rmdir($f);
            }
        }
    }

    /**
     *
     */
    private
    function clearShippingCustomProperties()
    {
        if ($wc_session = WC()->session) {
            $customSettings = $wc_session->get('custom_properties');
            foreach ($customSettings as $customSetting) {
                $wc_session->set($customSetting, '');
            }
        }
    }

    /**
     * @param array $customProperties
     */
    private
    function setShippingCustomProperties(
        $customProperties
    ) {
        if ($wc_session = WC()->session) {
            $properties = [];
            foreach ($customProperties as $key => $customProperty) {
                $properties[] = $key;
                $wc_session->set($key, filter_var($customProperty, FILTER_UNSAFE_RAW));
            }
            $wc_session->set('custom_properties', $properties);
        }
    }

    /**
     * @param $filePaths
     * @param null $zipfile
     */
    private
    function sendPdf(
        $filePaths,
        $zipfile = null
    ) {
        if (!$zipfile) {
            header('Content-type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($filePaths[0]) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($filePaths[0]));
            header('Accept-Ranges: bytes');
            @readfile($filePaths[0]);
            exit;
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipfile, ZipArchive::CREATE) !== true) {
                die('Could not create zip file ' . $zipfile);
            }
            foreach ($filePaths as $filePath) {
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
            if (file_exists($zipfile)) {
                header('Content-type: application/zip');
                header('Content-Disposition: inline; filename="' . basename($zipfile) . '"');
                @readfile($zipfile);
                unlink($zipfile);
                exit;
            }
        }
        exit;
    }

    private
    function getShippingMethodSettings()
    {
        $shippingMethodSettings = [];
        $existingZones          = WC_Shipping_Zones::get_zones();
        foreach ($existingZones as $zone) {
            $shippingMethods = $zone['shipping_methods'];
            foreach ($shippingMethods as $shippingMethod) {
                if ($shippingMethod->id == 'the_courier_guy') {
                    $courierGuyShippingMethod = $shippingMethod;
                }
            }
        }
        if (!empty($courierGuyShippingMethod)) {
            $shippingMethodSettings = $courierGuyShippingMethod->instance_settings;
        }

        return $shippingMethodSettings;
    }

    /**
     * @param string $addressType
     * @param array $fields
     *
     * @return array
     */
    private
    function addAddressFields(
        $addressType,
        $fields
    ) {
        $addressFields          = $fields[$addressType];
        $shippingMethodSettings = $this->getShippingMethodSettings();
        if (!empty($shippingMethodSettings) && !empty($shippingMethodSettings['south_africa_only']) && $shippingMethodSettings['south_africa_only'] == 'yes') {
            $required = false;
        }

        $addressFields = array_merge(
            $addressFields,
            [
                $addressType . '_postcode' => [
                    'type'     => 'text',
                    'label'    => 'Postcode',
                    'required' => true,
                    'class'    => ['form-row-last'],
                ],
            ]
        );
        if (isset($shippingMethodSettings['billing_insurance']) && $shippingMethodSettings['billing_insurance'] === 'yes') {
            $addressFields[$addressType . '_insurance'] = [
                'type'     => 'checkbox',
                'label'    => 'Would you like to include Shipping Insurance',
                'required' => false,
                'class'    => ['form-row-wide', 'tcg-insurance'],
                'priority' => 90,
            ];
        }
        $addressFields[$addressType . '_tcg_quoteno'] = [
            'type'     => 'text',
            'label'    => 'TCG Quote Number',
            'required' => false,
            'class'    => ['form-row-wide', 'tcg-quoteno'],
            'priority' => 90,
        ];
        $legacyFieldProperties                        = [
            'type'     => 'hidden',
            'required' => false,
        ];
        //@todo The setting of these additional billing and shipping properties is legacy from an older version of the plugin. This is to override legacy properties to invalidate cached required validation.
        $addressFields[$addressType . '_area']  = $legacyFieldProperties;
        $addressFields[$addressType . '_place'] = $legacyFieldProperties;
        $fields[$addressType]                   = $addressFields;

        return $fields;
    }

    /**
     *
     */
    private
    function initializeShipLogicApi()
    {
        $logging = $this->getLogging() === 'yes';

        require_once $this->getPluginPath() . 'Core/ShipLogicApi.php';
        $this->shipLogicApi = new ShipLogicApi(
            $this->getAccessKeyId(),
            $this->getAccessKey(),
            $this->getAccessToken(),
            $logging
        );
    }

    private function getAccessToken(): string
    {
        return get_option(self::TCG_SHIP_LOGIC_SECRET_ACCESS_TOKEN);
    }

    private function getLogging(): string
    {
        return get_option(self::TCG_LOGGING);
    }

    /**
     * @param mixed $shipLogicApiPayload
     */
    private
    function setShipLogicApiPayload(
        $shipLogicApiPayload
    ) {
        $this->parcelPerfectApiPayload = $shipLogicApiPayload;
    }

    /**
     *
     */
    private
    function initializeShipLogicApiPayload()
    {
        require_once $this->getPluginPath() . 'Core/ShipLogicApiPayload.php';
        $shipLogicApiPayload = new ShipLogicApiPayload();
        $this->setShipLogicApiPayload($shipLogicApiPayload);
    }

    /**
     *
     */
    private
    function registerShippingMethod()
    {
        require_once $this->getPluginPath() . 'Shipping/TCG_ShippingMethod.php';
        add_filter(
            'woocommerce_shipping_methods',
            function ($methods) {
                $methods['the_courier_guy'] = 'TCG_Shipping_Method';

                return $methods;
            }
        );
    }

    /**
     * @param WC_Order $order
     * @param bool $returnShipment
     *
     * @return false|string
     */
    private
    function createShipment(
        WC_Order $order,
        bool $returnShipment = false
    ) {
        if ($this->hasTcgShippingMethod($order)) {
            $shippingMethodParameters = $this->getShippingMethodParameters($order);

            $getRatesBody     = $order->get_meta(ShipLogicApi::TCG_SHIP_LOGIC_GETRATES_BODY, true);
            $grb              = json_encode($getRatesBody);
            $getRatesResult   = $order->get_meta(TCG_Shipping_Method::TCG_SHIP_LOGIC_RESULT, true);
            $grr              = json_encode($getRatesResult);
            $service_level_id = $getRatesResult['rates']['rates'][0]['service_level']['id'];

            $shipping_title = "";
            foreach ($order->get_items('shipping') as $item_id => $item) {
                $shipping_title = $item['name'];
            }
            $shippingMethodID = explode(':', $order->get_meta("_order_shipping_data", true));

            $shippingMethodCode = substr($shippingMethodID[1], -3);

            foreach ($getRatesResult['rates']['rates'] as $base_rate) {
                $rateName = $this->getRateName($base_rate);
                $rateCode = $base_rate['service_level']['code'];

                if ($rateName == $shipping_title || $shippingMethodCode == $rateCode) {
                    $service_level_id = $base_rate['service_level']['id'];
                }
            }

            $createShipmentBody = new stdClass();

            $createShipmentBody->collection_address = $getRatesBody->collection_address;
            $collection_contact                     = new stdClass();
            $collection_contact->name               = $shippingMethodParameters['shopContactName'];
            $collection_contact->mobile_number      = $shippingMethodParameters['shopPhone'];
            $collection_contact->email              = $shippingMethodParameters['shopEmail'];
            $createShipmentBody->collection_contact = $collection_contact;

            $createShipmentBody->delivery_address = $getRatesBody->delivery_address;
            $delivery_contact                     = new stdClass();
            $delivery_contact->name               = $order->get_shipping_first_name(
                ) . ' ' . $order->get_shipping_last_name();
            $delivery_contact->mobile_number      = $order->get_billing_phone();
            $delivery_contact->email              = $order->get_billing_email();
            $createShipmentBody->delivery_contact = $delivery_contact;

            if ($returnShipment) {
                $createShipmentBody = $this->invertDeliveryAddress($createShipmentBody);
            }

            $parcels = $getRatesBody->parcels;

            $waybillDescriptionOverride = isset($shippingMethodParameters['remove_waybill_description'])
                                          && $shippingMethodParameters['remove_waybill_description'] === 'yes';

            $parcels = $this->applyPackageDescriptions($parcels, $waybillDescriptionOverride);

            $createShipmentBody->parcels = $parcels;

            if (isset($getRatesBody->opt_in_rates)) {
                $createShipmentBody->opt_in_rates = $getRatesBody->opt_in_rates;
            }

            if (isset($getRatesBody->opt_in_time_based_rates)) {
                $createShipmentBody->opt_in_time_based_rates = $getRatesBody->opt_in_time_based_rates;
            }

            $createShipmentBody->special_instructions_collection = '';
            $createShipmentBody->special_instructions_delivery   = $order->data['customer_note'];
            $createShipmentBody->declared_value                  = $getRatesBody->declared_value;
            $createShipmentBody->service_level_id                = $service_level_id;

            $shipLogicApi = $this->shipLogicApi;
            try {
                $result   = $shipLogicApi->createShipment($createShipmentBody);
                $response = json_decode($result);

                $shipLogicOrderIdNote       = 'Ship Logic Order Id: ' . $response->id;
                $shipLogicTrackingOrderNote = 'Ship Logic Short Tracking Reference: '
                                              . $response->short_tracking_reference;

                if ($returnShipment) {
                    $order->update_meta_data('tcg_returned', "1");

                    $order->add_order_note(
                        "TCG Return\n ---Collection---: "
                        . json_encode($createShipmentBody->collection_address)
                        . "\n ---Destination---: " . json_encode($createShipmentBody->delivery_address)
                    );

                    $shipLogicOrderIdNote       = "Return $shipLogicOrderIdNote";
                    $shipLogicTrackingOrderNote = "Return $shipLogicTrackingOrderNote";

                    $ajaxResponse = array(
                        'success' => true,
                        'message' => 'Return order created successfully'
                    );
                }

                $order->update_meta_data('ship_logic_order_id', $response->id);
                $order->add_order_note($shipLogicOrderIdNote);
                $order->update_meta_data(
                    self::SHIP_LOGIC_SHORT_TRACKING_REFERENCE,
                    $response->short_tracking_reference
                );
                $order->add_order_note($shipLogicTrackingOrderNote);

                $order->save();
            } catch (Exception $exception) {
                $order->add_order_note('Ship Logic Order Not Created: ' . $exception->getMessage());
                $order->save();
            }
        }

        return json_encode($ajaxResponse ?? null);
    }

    /**
     * Sets package descriptions
     *
     * @param $parcels
     * @param $waybillDescriptionOverride
     *
     * @return void
     */
    public function applyPackageDescriptions($parcels, $waybillDescriptionOverride): array
    {
        if ($waybillDescriptionOverride) {
            for ($i = 0; $i < count($parcels); $i++) {
                $itemCount = $parcels[$i]->item_count;
                if ($itemCount === 1) {
                    $parcels[$i]->packaging = '1 item';
                } else {
                    $parcels[$i]->packaging = "$itemCount items";
                }
            }
        } else {
            for ($i = 0; $i < count($parcels); $i++) {
                $parcels[$i]->packaging = $parcels[$i]->submitted_description;
            }
        }

        return $parcels;
    }

    /**
     * Switches the shipment destination and collection
     *
     * @param $createShipmentBody
     */
    public function invertDeliveryAddress($createShipmentBody)
    {
        //Switch address
        $temp                                   = $createShipmentBody->collection_address;
        $createShipmentBody->collection_address = $createShipmentBody->delivery_address;
        $createShipmentBody->delivery_address   = $temp;

        //Switch Contact
        $temp                                   = $createShipmentBody->collection_contact;
        $createShipmentBody->collection_contact = $createShipmentBody->delivery_contact;
        $createShipmentBody->delivery_contact   = $temp;

        return $createShipmentBody;
    }

    private
    function savePdfWaybill(
        $result,
        $orderId
    ) {
        $collectno          = $result['collectno'];
        $base64             = $result['waybillBase64'];
        $filename           = md5($collectno . random_bytes(16) . time());
        $uploadsDirectory   = $this->getPluginUploadPath();
        $pdfFilePath        = $uploadsDirectory . '/' . $filename . '.pdf';
        $order              = new WC_Order($orderId);
        $tcg_waybill_stored = $order->get_meta('tcg_waybill_filename', true);
        if ($tcg_waybill_stored != '') {
            $tcg_waybill_filenames = json_decode($tcg_waybill_stored, true);
        } else {
            $tcg_waybill_filenames = [];
        }
        $tcg_waybill_filenames[] = $filename;
        try {
            $order->update_meta_data('tcg_waybill_filename', json_encode($tcg_waybill_filenames));
            $f = fopen($pdfFilePath, 'wb');
            fwrite($f, base64_decode($base64));
            fclose($f);
        } catch (Exception $e) {
        }
    }

    private
    function hasTcgShippingMethod(
        $order
    ) {
        $result = false;
        if (!empty($order)) {
            $shipping_data = json_decode($order->get_meta('_order_shipping_data', true), true);
            if (is_array($shipping_data)) {
                array_walk(
                    $shipping_data,
                    function ($shippingItem) use (&$result) {
                        if (is_string($shippingItem) && strstr($shippingItem, 'the_courier_guy')) {
                            $result = true;
                        }
                    }
                );
            }
        }

        return $result;
    }

    /**
     * @param WC_Order $order
     *
     * @return int
     */
    private function getShippingInstanceId(WC_Order $order): int
    {
        $shippingMethod = json_decode($order->get_meta('_order_shipping_data', true), true);
        if (is_array($shippingMethod)) {
            $shippingMethod = $shippingMethod[0];
        }

        $parts = explode(':', $shippingMethod);

        return $parts[count($parts) - 1];
    }

    /**
     * @param WC_Order $order
     *
     * @return array
     */
    private function getShippingMethodParameters(WC_Order $order): array
    {
        if ($this->hasTcgShippingMethod($order)) {
            return get_option('woocommerce_the_courier_guy_' . $this->getShippingInstanceId($order) . '_settings');
        }

        return [];
    }

    private function getAccessKeyId(): string
    {
        return get_option(self::TCG_SHIP_LOGIC_ACCESS_KEY_ID);
    }

    private function getAccessKey(): string
    {
        return get_option(self::TCG_SHIP_LOGIC_SECRET_ACCESS_KEY);
    }


    /**
     * @param $vendorId
     */
    private
    function clearCachedQuote(
        $vendorId
    ) {
        $vendorId = $vendorId === 0 ? '' : $vendorId;

        if ($wc_session = WC()->session) {
            $wc_session->set(
                'tcg_response' . $vendorId,
                ''
            );
            $wc_session->set(
                'tcg_request' . $vendorId,
                ''

            );
            $wc_session->set(
                'tcg_quote_response' . $vendorId,
                ''
            );
        }
    }

    /**
     * @param $quoteResponse
     * @param $vendorId
     */
    private
    function updateCachedQuoteResponse(
        $quoteResponse,
        $vendorId
    ) {
        $ts = time();

        if (count($quoteResponse) > 0) {
            $quoteResponse['ts'] = $ts;
        }

        $vendorId = $vendorId === 0 ? '' : $vendorId;

        if ($wc_session = WC()->session) {
            $wc_session->set(
                'tcg_response' . $vendorId,
                json_encode($quoteResponse)
            );
            $wc_session->set(
                'tcg_response' . $vendorId,
                json_encode($quoteResponse)
            );
            $wc_session->set('tcg_quote_response' . $vendorId, $quoteResponse);
        }
    }

    /**
     * @param $vendorId
     *
     * @return mixed
     */
    private
    function getCachedQuoteResponse(
        $vendorId
    ) {
        $vendorId = $vendorId === 0 ? '' : $vendorId;
        if ($wc_session = WC()->session) {
            $response = json_encode(
                $wc_session->get('tcg_quote_response' . $vendorId)
            );
            if ($response != 'null' && strlen($response) > 2) {
                $response = json_decode($response, true);
                $tsnow    = time();
                if (abs($response['ts'] - $tsnow) < 300) {
                    return json_encode($response);
                }
            }
            $r = $wc_session->get('tcg_response');
            if ($r != '') {
                return $wc_session->get('tcg_response');
            }
        }
    }

    /**
     * @param array $quoteParams
     * @param $vendorId - '' if multivendor not enabled
     */
    private
    function updateCachedQuoteRequest(
        $quoteParams,
        $vendorId
    ) {
        // Current timestamp
        $ts = time();

        $vendorId = $vendorId === 0 ? '' : $vendorId;

        if ($wc_session = WC()->session) {
            $wc_session->set(
                'tcg_quote_request' . $vendorId,
                hash('md5', json_encode($quoteParams) . $vendorId) . '||' . $ts
            );
            $wc_session->set(
                'tcg_request' . $vendorId,
                hash(
                    'md5',
                    json_encode($quoteParams) . $vendorId . $ts
                )
            );
        }
    }

    /**
     * @param $quoteParams
     *
     * @param $vendorId
     *
     * @return bool
     */
    private
    function compareCachedQuoteRequest(
        $quoteParams,
        $vendorId
    ) {
        $result = false;

        $tsnow = time();
        $hash  = '';

        if ($wc_session = WC()->session) {
            $vendorId = $vendorId === 0 ? '' : $vendorId;

            $request = $wc_session->get('tcg_quote_request' . $vendorId);
            if ($request != 'null' && strlen($request) > 2) {
                $parts = explode('||', $request);
                if (is_array($parts) && count($parts) === 2) {
                    $ts   = $parts[1];
                    $hash = $parts[0];
                }
                $compareQuoteHash = hash(
                    'md5',
                    json_encode(
                        $quoteParams,
                        true
                    ) . $vendorId
                );
                if ($compareQuoteHash === $hash && abs($tsnow - $ts) < 300) {
                    $result = true;
                } else {
                    $wc_session->set('tcg_quote_request' . $vendorId, '');
                }
            } elseif ($wc_session) {
                $cachedQuoteHash = $wc_session->get(
                    'tcg_request' . $vendorId
                );
                if (isset($cachedQuoteHash)) {
                    $parts = explode('||', $cachedQuoteHash);
                    if (is_array($parts) && count($parts) == 2) {
                        $ts   = $parts[1];
                        $hash = $parts[0];
                    }
                }
                $compareQuoteHash = hash(
                    'md5',
                    json_encode($quoteParams, true) . $vendorId
                );
                if ($compareQuoteHash == $hash && abs($tsnow - $ts) < 300) {
                    $result = true;
                }
            }
        }

        return $result;
    }
}
