<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class TCG_Shipping_Integration implements IntegrationInterface
{
    private $settings;

    public function get_name()
    {
        return 'the-courier-guy';
    }

    public function __construct()
    {
        $this->settings = TCG_Plugin::getShippingMethodSettings();
    }

    public function initialize()
    {
        if (empty($this->settings)) {
            $this->settings = TCG_Plugin::getShippingMethodSettings();
        }

        $this->register_frontend_scripts();
        $this->register_checkout_hooks();

        // Register REST API routes immediately
        $this->register_rest_api();

        // Also hook into the proper WordPress action as backup
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }


    public function register_checkout_hooks()
    {
        // Hook into WooCommerce blocks checkout process
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'update_order_meta_from_blocks']);
        add_action('woocommerce_blocks_checkout_order_processed', [$this, 'save_shipping_options_to_order']);

        // Add filter to modify shipping calculation data for blocks
        add_filter('woocommerce_shipping_packages', [$this, 'add_shipping_options_to_packages']);

        // Hook into the REST API to capture shipping options
        add_action('woocommerce_store_api_checkout_update_customer_from_request', [$this, 'capture_shipping_options']);

        // Add AJAX handler for updating shipping options
        add_action('wp_ajax_tcg_update_shipping_options', [$this, 'handle_shipping_options_update']);
        add_action('wp_ajax_nopriv_tcg_update_shipping_options', [$this, 'handle_shipping_options_update']);

        // Hook to force shipping recalculation when packages are being prepared
        add_filter('woocommerce_cart_shipping_packages', [$this, 'force_shipping_recalculation'], 10, 1);
    }

    public function handle_shipping_options_update()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (WC()->session) {
            if (isset($_POST['tcg_ship_logic_optins'])) {
                WC()->session->set('tcg_selected_optins', $_POST['tcg_ship_logic_optins']);
            } else {
                WC()->session->set('tcg_selected_optins', []);
            }

            if (isset($_POST['tcg_ship_logic_time_based_optins'])) {
                WC()->session->set('tcg_selected_time_based_optins', $_POST['tcg_ship_logic_time_based_optins']);
            } else {
                WC()->session->set('tcg_selected_time_based_optins', []);
            }

            // Clear shipping cache to force recalculation
            WC()->session->set('shipping_for_package_0', null);

            // Clear any cached shipping rates
            $packages = WC()->cart->get_shipping_packages();
            foreach ($packages as $package_key => $package) {
                WC()->session->set('shipping_for_package_' . $package_key, null);
            }

            // Force shipping calculation refresh
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
        }

        wp_send_json_success([
                                 'message'             => 'Shipping options updated',
                                 'selected_regular'    => $_POST['tcg_ship_logic_optins'] ?? [],
                                 'selected_time_based' => $_POST['tcg_ship_logic_time_based_optins'] ?? []
                             ]);
    }

    public function capture_shipping_options($customer)
    {
        // Set blocks flag in session for future reference
        if (WC()->session) {
            WC()->session->set('is_blocks', 1);
        }

        // Get request data from global request if available
        if (isset($_REQUEST)) {
            $request_data = $_REQUEST;
        } else {
            $request_data = [];
        }

        if (isset($request_data['tcg_ship_logic_optins'])) {
            WC()->session->set('tcg_selected_optins', $request_data['tcg_ship_logic_optins']);
        }

        if (isset($request_data['tcg_ship_logic_time_based_optins'])) {
            WC()->session->set('tcg_selected_time_based_optins', $request_data['tcg_ship_logic_time_based_optins']);
        }

        // Also check for JavaScript-passed data
        if (isset($_REQUEST['tcg_ship_logic_optins'])) {
            WC()->session->set('tcg_selected_optins', $_REQUEST['tcg_ship_logic_optins']);
        }

        if (isset($_REQUEST['tcg_ship_logic_time_based_optins'])) {
            WC()->session->set('tcg_selected_time_based_optins', $_REQUEST['tcg_ship_logic_time_based_optins']);
        }
    }

    public function add_shipping_options_to_packages($packages)
    {
        if (!WC()->session) {
            return $packages;
        }

        $selected_optins     = WC()->session->get('tcg_selected_optins');
        $selected_time_based = WC()->session->get('tcg_selected_time_based_optins');

        if ($selected_optins || $selected_time_based) {
            foreach ($packages as &$package) {
                if ($selected_optins) {
                    $package['ship_logic_optins'] = array_map('intval', $selected_optins);
                }

                if ($selected_time_based) {
                    $package['ship_logic_time_based_optins'] = array_map('intval', $selected_time_based);
                }
            }
        }

        return $packages;
    }

    public function force_shipping_recalculation($packages)
    {
        // This method ensures that when shipping packages are being calculated,
        // we force a fresh calculation if TCG options have changed
        if (WC()->session) {
            $selected_optins     = WC()->session->get('tcg_selected_optins');
            $selected_time_based = WC()->session->get('tcg_selected_time_based_optins');

            // If we have selected options, ensure packages include them for fresh calculation
            if ($selected_optins || $selected_time_based) {
                foreach ($packages as &$package) {
                    if ($selected_optins) {
                        $package['ship_logic_optins'] = array_map('intval', $selected_optins);
                    }

                    if ($selected_time_based) {
                        $package['ship_logic_time_based_optins'] = array_map('intval', $selected_time_based);
                    }

                    // Add a unique hash to force recalculation
                    $package['tcg_options_hash'] = md5(serialize($selected_optins) . serialize($selected_time_based));
                }
            }
        }

        return $packages;
    }

    public function update_order_meta_from_blocks($order)
    {
        // This will be called during the checkout process
        if (isset($_REQUEST['tcg_ship_logic_optins'])) {
            $order->update_meta_data('_tcg_ship_logic_optins', $_REQUEST['tcg_ship_logic_optins']);
        }

        if (isset($_REQUEST['tcg_ship_logic_time_based_optins'])) {
            $order->update_meta_data(
                '_tcg_ship_logic_time_based_optins',
                $_REQUEST['tcg_ship_logic_time_based_optins']
            );
        }

        // Save insurance selection from blocks
        if (isset($_REQUEST['tcg_billing_insurance'])) {
            $order->update_meta_data('_tcg_billing_insurance', $_REQUEST['tcg_billing_insurance'] ? '1' : '0');
        }
        $order->save();
    }

    public function save_shipping_options_to_order($order)
    {
        // Additional order processing if needed
        $regular_options    = $order->get_meta('_tcg_ship_logic_optins');
        $time_based_options = $order->get_meta('_tcg_ship_logic_time_based_optins');

        if ($regular_options || $time_based_options) {
            $order->add_order_note(
                'TCG Shipping options selected: ' .
                (!empty($regular_options) ? 'Regular: ' . implode(', ', $regular_options) . ' ' : '') .
                (!empty($time_based_options) ? 'Time-based: ' . implode(', ', $time_based_options) : '')
            );
        }
    }

    public function register_rest_api()
    {
        try {
            // Add a simple test endpoint first
            register_rest_route('the-courier-guy/v1', 'test', [
                'methods'             => 'GET',
                'callback'            => function() {
                    return rest_ensure_response([
                        'status' => 'success',
                        'message' => 'TCG REST API is working',
                        'timestamp' => current_time('c'),
                        'wp_version' => get_bloginfo('version'),
                        'wc_active' => function_exists('WC')
                    ]);
                },
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('the-courier-guy/v1', 'shipping-options', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_shipping_options'],
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('the-courier-guy/v1', 'update-shipping-options', [
                'methods'             => 'POST',
                'callback'            => [$this, 'update_shipping_options_rest'],
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('the-courier-guy/v1', 'insurance', [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_insurance_status'],
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('the-courier-guy/v1', 'insurance', [
                'methods'             => 'POST',
                'callback'            => [$this, 'set_insurance_status'],
                'permission_callback' => '__return_true'
            ]);
        } catch (Exception $e) {}
    }

    public function update_shipping_options_rest($request)
    {
        $params = $request->get_params();

        if (WC()->session) {
            if (isset($params['tcg_ship_logic_optins'])) {
                WC()->session->set('tcg_selected_optins', $params['tcg_ship_logic_optins']);
            } else {
                WC()->session->set('tcg_selected_optins', []);
            }

            if (isset($params['tcg_ship_logic_time_based_optins'])) {
                WC()->session->set('tcg_selected_time_based_optins', $params['tcg_ship_logic_time_based_optins']);
            } else {
                WC()->session->set('tcg_selected_time_based_optins', []);
            }

            // Clear shipping cache to force recalculation
            WC()->session->set('shipping_for_package_0', null);

            // Clear any cached shipping rates
            $packages = WC()->cart->get_shipping_packages();
            foreach ($packages as $package_key => $package) {
                WC()->session->set('shipping_for_package_' . $package_key, null);
            }

            // Force shipping calculation refresh
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();

            return rest_ensure_response([
                                            'success'             => true,
                                            'message'             => 'Shipping options updated',
                                            'selected_regular'    => $params['tcg_ship_logic_optins'] ?? [],
                                            'selected_time_based' => $params['tcg_ship_logic_time_based_optins'] ?? []
                                        ]);
        }

        return rest_ensure_response([
                                        'success' => false,
                                        'message' => 'Session not available'
                                    ]);
    }

    public function get_insurance_status($request)
    {
        $enabled    = false;
        $checked    = false;
        $cart_total = 0;

        if (WC()->cart) {
            $cart_total = WC()->cart->subtotal;
        }

        if (($this->settings['billing_insurance'] ?? 'no') === 'yes' && $cart_total >= 1500) {
            $enabled = true;
        }

        if (WC()->session) {
            $checked = WC()->session->get('tcg_billing_insurance') == '1';
        }
        return rest_ensure_response([
                                        'enabled'    => $enabled,
                                        'checked'    => $checked,
                                        'cart_total' => $cart_total
                                    ]);
    }

    public function set_insurance_status($request)
    {
        $params  = $request->get_params();
        $checked = !empty($params['checked']) ? '1' : '0';
        if (WC()->session) {
            WC()->session->set('tcg_billing_insurance', $checked);
            // Clear shipping cache to force recalculation
            WC()->session->set('shipping_for_package_0', null);
            $packages = WC()->cart->get_shipping_packages();
            foreach ($packages as $package_key => $package) {
                WC()->session->set('shipping_for_package_' . $package_key, null);
            }
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
        }
        return rest_ensure_response(['success' => true, 'checked' => $checked]);
    }

    public function get_shipping_options($request)
    {
        // Initialize session if needed
        if (!WC()->session && is_user_logged_in()) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        $rates = null;
        if (WC()->session) {
            $rates = WC()->session->get(TCG_Shipping_Method::TCG_SHIP_LOGIC_RESULT);
        }

        $shipping_options = [];

        // If no rates in session, return some default options or trigger a calculation
        if (empty($rates) || !isset($rates['opt_in_rates'])) {
            return rest_ensure_response($shipping_options);
        }

        if (!empty($rates) && isset($rates['opt_in_rates'])) {
            $rate_adjustment_ids            = [];
            $time_based_rate_adjustment_ids = [];

            if (isset($rates['rates']['rates'][0])) {
                if (!empty($rates['rates']['rates'][0]['rate_adjustments'])) {
                    foreach ($rates['rates']['rates'][0]['rate_adjustments'] as $rate_adjustment) {
                        if (isset($rate_adjustment['id'])) {
                            $rate_adjustment_ids[] = $rate_adjustment['id'];
                        }
                    }
                }

                if (!empty($rates['rates']['rates'][0]['time_based_rate_adjustments'])) {
                    foreach ($rates['rates']['rates'][0]['time_based_rate_adjustments'] as $time_based_rate_adjustment) {
                        if (isset($time_based_rate_adjustment['id'])) {
                            $time_based_rate_adjustment_ids[] = $time_based_rate_adjustment['id'];
                        }
                    }
                }
            }

            $disable_specific_options = json_decode(
                WC()->session->get('disable_specific_shipping_options'),
                true
            ) ?? [];
            $optinRates               = $rates['opt_in_rates'];

            if (!empty($optinRates['opt_in_rates'])) {
                foreach ($optinRates['opt_in_rates'] as $optin_rate) {
                    $optin_name = strtolower($optin_rate['name']);
                    $optin_name = str_replace("/", "", $optin_name);
                    $optin_name = str_replace("  ", " ", $optin_name);
                    $optin_name = str_replace(" ", "_", $optin_name);

                    if (in_array($optin_name, $disable_specific_options)) {
                        $shipping_options[] = [
                            'id'              => $optin_rate['id'],
                            'name'            => $optin_rate['name'],
                            'price'           => $optin_rate['charge_value'],
                            'price_formatted' => wc_price($optin_rate['charge_value']),
                            'type'            => 'regular',
                            'checked'         => in_array($optin_rate['id'], $rate_adjustment_ids)
                        ];
                    }
                }
            }

            if (!empty($optinRates['opt_in_time_based_rates'])) {
                foreach ($optinRates['opt_in_time_based_rates'] as $optin_rate) {
                    $optin_name = strtolower($optin_rate['name']);
                    $optin_name = str_replace("/", "", $optin_name);
                    $optin_name = str_replace("  ", " ", $optin_name);
                    $optin_name = str_replace(" ", "_", $optin_name);

                    if (in_array($optin_name, $disable_specific_options)) {
                        $shipping_options[] = [
                            'id'              => $optin_rate['id'],
                            'name'            => $optin_rate['name'],
                            'price'           => $optin_rate['charge_value'],
                            'price_formatted' => wc_price($optin_rate['charge_value']),
                            'type'            => 'time_based',
                            'checked'         => in_array($optin_rate['id'], $time_based_rate_adjustment_ids)
                        ];
                    }
                }
            }
        }

        return rest_ensure_response($shipping_options);
    }

    // REST API and shipping options functionality is working
    public function register_frontend_scripts()
    {
        wp_register_script(
            'tcg-blocks-frontend',
            plugins_url('../dist/js/frontend/blocks.js', __FILE__),
            ['wp-i18n', 'wp-element', 'wc-blocks-checkout'],
            filemtime(plugin_dir_path(__FILE__) . '../dist/js/frontend/blocks.js'),
            true
        );

        wp_localize_script(
            'tcg-blocks-frontend',
            'tcg_data',
            [
                'description' => __('TCG Shipping Info', 'the-courier-guy'),
                'api_url'     => home_url('/?rest_route=/the-courier-guy/v1/'),
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('wp_rest')
            ]
        );
    }

    public function register_editor_scripts()
    {
        // No editor scripts needed for this block
    }

    public function get_script_handles()
    {
        return ['tcg-blocks-frontend'];
    }

    public function get_editor_script_handles()
    {
        return [];
    }

    public function get_script_data()
    {
        return [
            'tcg_enabled' => $this->settings['enabled'] ?? 'no',
            'tcg_title'   => $this->settings['title'] ?? __('The Courier Guy', 'the-courier-guy')
        ];
    }
}
