<?php

$pluginpath = plugin_dir_path(__DIR__);

/**
 * @author The Courier Guy
 * @package tcg/shipping
 */
class TCG_Shipping_Method extends WC_Shipping_Method
{
    const TCG_SHIP_LOGIC_RESULT = 'tcg_ship_logic_result';
    /**
     * @var WC_Logger
     */
    private static $log;
    private $parameters;
    private $logging = false;
    private $wclog;
    private $disable_specific_shipping_options = "";

    /**
     * TCG_Shipping_Method constructor.
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
        /*
         * These variables must be overridden on classes that extend WC_Shipping_Method.
         */
        $this->id    = 'the_courier_guy';
        $this->wclog = wc_get_logger();
        $title       = 'The Courier Guy';

        $form_fields = $this->get_instance_form_fields();

        $tcg_config = $this->getTCGShippingSettings($instance_id);

        if (is_checkout() && !self::is_woocommerce_blocks_checkout(
            ) && isset($tcg_config['disable_specific_shipping_options'])) {
            $this->disable_specific_shipping_options = json_encode($tcg_config['disable_specific_shipping_options']);
        }

        if ($wc_session = WC()->session) {
            $wc_session->set('disable_specific_shipping_options', $this->disable_specific_shipping_options);
        }

        $this->supports           = [
            'shipping-zones',
            'instance-settings',
        ];
        $this->tax_status         = false;
        $this->method_title       = __('The Courier Guy');
        $this->method_description = __('The Official Courier Guy shipping method.');
        $this->overrideFormFieldsVariable();

        if (!empty($instance_id)) {
            $title = $this->get_instance_option('title', 'The Courier Guy');
        }
        $this->title = $title;

        //This action hook must be added to trigger the 'process_admin_options' method on parent class WC_Shipping_Method.
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        add_filter(
            'woocommerce_shipping_' . $this->id . '_instance_settings_values',
            [$this, 'setShipLogicApiCredentials'],
            10,
            2
        );

        $this->parameters = $this->getShippingProperties();
        if (is_array($this->parameters) && count($this->parameters) > 0) {
            $this->logging = isset($this->parameters['usemonolog']) ? $this->parameters['usemonolog'] === 'yes' : false;
            if ($this->logging && self::$log === null) {
                self::$log = wc_get_logger();
            }
        }

        /*******************************
         * CUSTOM BILLING FIELD
         ******************************** */
        add_filter('woocommerce_billing_fields', [$this, 'add_billing_insurance_field']);
    }

    public static function shipLogicRateOptins()
    {
        if ($wc_session = WC()->session) {
            $rates               = $wc_session->get(self::TCG_SHIP_LOGIC_RESULT);
            $rate_adjustment_ids = [];

            if (!isset($rates['rates']['rates'][0])) {
                return false;
            }

            if (!empty($rates['rates']['rates'][0]['rate_adjustments'])) {
                foreach ($rates['rates']['rates'][0]['rate_adjustments'] as $rate_adjustment) {
                    if (isset($rate_adjustment['id'])) {
                        $rate_adjustment_ids[] = $rate_adjustment['id'];
                    }
                }
            }
            $time_based_rate_adjustment_ids = [];
            if (!empty($rates['rates']['rates'][0]['time_based_rate_adjustments'])) {
                foreach ($rates['rates']['rates'][0]['time_based_rate_adjustments'] as $time_based_rate_adjustment) {
                    if (isset($time_based_rate_adjustment['id'])) {
                        $time_based_rate_adjustment_ids[] = $time_based_rate_adjustment['id'];
                    }
                }
            }

            $disable_specific_options = json_decode($wc_session->get('disable_specific_shipping_options'));

            if ($disable_specific_options == null) {
                $disable_specific_options = array();
                $count                    = 0;
            } else {
                $count = count($disable_specific_options);
            }

            if (!empty($rates && isset($rates['opt_in_rates'])) && ($count > 0)) {
                $html       = '<tr><th>Shipping Options</th><td><ul>';
                $optinRates = $rates['opt_in_rates'];
                if (!empty($optinRates['opt_in_rates'])) {
                    foreach ($optinRates['opt_in_rates'] as $optin_rate) {
                        $optin_name = strtolower($optin_rate['name']);
                        $optin_name = str_replace("/", "", $optin_name);
                        $optin_name = str_replace("  ", " ", $optin_name);
                        $optin_name = str_replace(" ", "_", $optin_name);
                        if (in_array($optin_name, $disable_specific_options)) {
                            $tcg_ship_logic_optin_chosen = in_array($optin_rate['id'], $rate_adjustment_ids);
                            $price                       = wc_price($optin_rate['charge_value']);
                            $html                        .= "
<li class='update_totals_on_change'><input type='checkbox' value='$optin_rate[id]' name='tcg_ship_logic_optins[]' class='shipping-method update_totals_on_change'";
                            if ($tcg_ship_logic_optin_chosen) {
                                $html .= ' checked';
                            }
                            $html .= ">
<label>$optin_rate[name]
<span>$price
</span>
</label>
 </li>";
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
                            $tcg_ship_logic_time_based_optin_chosen = in_array(
                                $optin_rate['id'],
                                $time_based_rate_adjustment_ids
                            );
                            $price                                  = wc_price($optin_rate['charge_value']);
                            $html                                   .= "
<li class='update_totals_on_change'><input type='checkbox' value='$optin_rate[id]' name='tcg_ship_logic_time_based_optins[]' class='shipping-method'";
                            if ($tcg_ship_logic_time_based_optin_chosen) {
                                $html .= ' checked';
                            }
                            $html .= ">
<label>$optin_rate[name]
<span>$price
</span>
</label>
 </li>";
                        }
                    }
                }
                $html .= '</ul></td>';
                echo $html;
            }
        }
    }

    public function add_billing_insurance_field($fields)
    {
        $settings = $this->getShippingProperties();

        $cart_subtotal = (int)WC()->cart->subtotal;

        if ($cart_subtotal >= 1500 && ($settings['billing_insurance'] ?? 'no') === 'yes') {
            $fields['billing_insurance'] = [
                'type'     => 'checkbox',
                'label'    => 'Would you like to include Shipping Insurance',
                'required' => false,
                'class'    => ['form-row-wide', 'tcg-insurance-field'],
                'priority' => 110,
            ];
        }

        return $fields;
    }

    public function getTCGShippingSettings($instance_id)
    {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM $wpdb->options WHERE `option_name` like '%woocommerce_the_courier_guy_{$instance_id}_settings%'"
        );
        $raw     = stripslashes_deep($results);
        if (!empty($raw)) {
            return unserialize($raw[0]->option_value);
        }
    }

    /**
     * @return array
     */
    public function getShippingProperties()
    {
        return $this->instance_settings;
    }

    /**
     * @param array|null $settings
     *
     * @return void
     */
    public function setShipLogicApiCredentials(?array $settings = [])
    {
        if (!empty($settings)) {
            update_option('tcg_username', $settings['username'] ?? '');
            update_option('tcg_password', $settings['password'] ?? '');
            update_option(TCG_Plugin::TCG_SHIP_LOGIC_ACCESS_KEY_ID, $settings['ship_logic_access_key_id'] ?? '');
            update_option(
                TCG_Plugin::TCG_SHIP_LOGIC_SECRET_ACCESS_KEY,
                $settings['ship_logic_secret_access_key'] ?? ''
            );
            update_option(
                TCG_Plugin::TCG_SHIP_LOGIC_SECRET_ACCESS_TOKEN,
                $settings['ship_logic_secret_access_token'] ?? ''
            );
            update_option(TCG_Plugin::TCG_LOGGING, $settings['usemonolog'] ?? '');
        }

        return $settings;
    }

    /**
     * Called to calculate shipping rates for this shipping method. Rates can be added using the add_rate() method.
     * This method must be overridden as it is called by the parent class WC_Shipping_Method.
     *
     * @param array $package Shipping package.
     *
     * @uses WC_Shipping_Method::add_rate()
     */
    public function calculate_shipping($package = [])
    {
        $wc_session = WC()->session;
        $coupons    = $package['applied_coupons'] ?? [];

        if (!empty($coupons)) {
            $package['cart_subtotal'] -= (float)$wc_session->get('cart_totals')['discount_total'];
            $package['cart_subtotal'] = max(0.00, $package['cart_subtotal']);
        }

        global $TCG_Plugin;
        $parameters   = $this->getShippingProperties();
        $shipLogicApi = $TCG_Plugin->getShipLogicApi();

        $postdata = [];
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $postdata);
        }

        if (isset($postdata['iihtcg_method']) && $postdata['iihtcg_method'] !== 'tcg') {
            return;
        }

        if (isset($postdata['tcg_ship_logic_optins'])) {
            $package['ship_logic_optins'] = [];
            foreach ($postdata['tcg_ship_logic_optins'] as $val) {
                $package['ship_logic_optins'][] = (int)$val;
            }
        }

        if (isset($postdata['billing_insurance'])
            || (self::is_woocommerce_blocks_checkout() && $parameters['billing_insurance'] === "yes")) {
            $package['insurance'] = true;
        }

        if (isset($postdata['billing_company'])) {
            $package['billing_company'] = $postdata['billing_company'];
        }


        if (isset($postdata['tcg_ship_logic_time_based_optins'])) {
            $package['ship_logic_time_based_optins'] = [];
            foreach ($postdata['tcg_ship_logic_time_based_optins'] as $val) {
                $package['ship_logic_time_based_optins'][] = (int)$val;
            }
        }

        if (self::$log) {
            self::$log->add('thecourierguy', 'Calculate_shipping package: ' . json_encode($package));
        }

        $vendor_id = '';
        if (isset($package['vendor_id'])) {
            $vendor_id = $package['vendor_id'];
        }

        if ($wc_session) {
            if (!isset($postdata) || empty($postdata)) {
                //Grab billing company from session
                $customer                   = $wc_session->get('customer');
                $company                    = $customer['shipping_company'] ?? '';
                $package['billing_company'] = $company;

                //Add insurance from session
                $insurance_check = $wc_session->get('tcg_insurance');
                if ($insurance_check === 1 || $insurance_check === "1") {
                    $package['insurance'] = true;
                }
            }


            $wc_session->set(self::TCG_SHIP_LOGIC_RESULT, null);
            foreach ($package['contents'] as $content) {
                $product_id = $content['product_id'];

                if ($this->isTcgProhibited($product_id)) {
                    $wc_session->set('tcg_prohibited_vendor', 'yes');
                }
            }
            $cnt        = 0;
            $haveResult = false;
            while (!$haveResult && $cnt < 5 && $wc_session->get('tcg_prohibited_vendor') !== 'yes') {
                $result = $shipLogicApi->getRates($package, $parameters);
                if (empty($result['rates']['rates'])) {
                    return;
                }
                $baseRates = [];
                $rates     = [];
                if (isset($result['rates']) && $result['rates']['message'] === 'Success') {
                    $wc_session->set(self::TCG_SHIP_LOGIC_RESULT, $result);
                    $base_rates = $result['rates']['rates'];
                    foreach ($base_rates as $base_rate) {
                        $rate_adjustments_cost = 0;
                        foreach ($base_rate['rate_adjustments'] as $rate_adjustments) {
                            $rate_adjustments_cost += $rate_adjustments['charge'];
                        }
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

                        $taxes_enabled = get_option('woocommerce_calc_taxes');
                        $settings      = $this->getShippingProperties();

                        $tcg_insurance = $wc_session->get('tcg_insurance');

                        $meta_data = ['currency' => 'ZAR'];

                        if (($settings['tax_status'] == "taxable") && ($taxes_enabled == 'yes')) {
                            $ship_price = $base_rate['rate'];
                            $taxes      = $ship_price - $base_rate['rate_excluding_vat'];
                        } else {
                            $taxes      = 0;
                            $ship_price = $base_rate['rate_excluding_vat'];
                        }

                        $rate        = [
                            'name'            => $name,
                            'cost'            => $ship_price,
                            'total'           => $ship_price,
                            'total_taxes'     => $taxes,
                            'rate_adjustment' => $rate_adjustments_cost,
                            'calc_tax'        => 'per_item',
                            'service'         => $base_rate['service_level']['code'],
                            'cartage'         => $base_rate['base_rate']['charge'],
                            'meta_data'       => $meta_data,
                        ];
                        $baseRates[] = $rate;
                        $rates[]     = $rate;
                    }
                }
                if (self::$log) {
                    self::$log->add('thecourierguy', 'Calculate_shipping result: ' . json_encode($result));
                }
                if (isset($result['error']) && strpos($result['message'], 'Too Long') !== false) {
                    wc_clear_notices();
                    wc_add_notice('Too many items for TCG. Please split your order', 'error');
                    $haveResult = true;
                }
                if (!empty($rates)) {
                    $addedRates = $this->addRates($rates, $package);
                    if (!empty($addedRates)) {
                        $haveResult = true;
                    }
                    //The id variable must be changed back, as this is changed in addRate method on this class.
                    //@see TCG_Shipping_Method::addRate()
                    //@todo This logic is legacy from an older version of the plugin, there must be a better way, no time now.
                    $this->id = 'the_courier_guy';
                }
                $cnt++;
            }

            $displayErrors = $parameters['displaymessageifnorates'] ?? '';

            if ($wc_session->get('tcg_prohibited_vendor') === 'yes') {
                $displayErrors = 'no';
            }

            if (!$haveResult && $displayErrors === 'yes') {
                wc_clear_notices();
                wc_add_notice(
                    'Unfortunately, there are no shipping options for your desired package and address, please contact The Courier Guy',
                    'error'
                );
            }
        }
    }

    public static function is_woocommerce_blocks_checkout()
    {
        $content = get_the_content();
        if (strpos($content, 'wp-block-woocommerce-checkout') !== false) {
            return true;
        }

        return false;
    }

    /**
     * This method is called to build the UI for custom shipping setting of type 'tcg_override_per_service'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
    public function generate_tcg_override_per_service_html($key, $data)
    {
        $field_key      = $this->get_field_key($key);
        $defaults       = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
            'options'           => array(),
        );
        $data           = wp_parse_args($data, $defaults);
        $overrideValue  = $this->get_option($key);
        $overrideValues = json_decode($overrideValue, true);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php
                echo esc_attr($field_key); ?>_select"><?php
                    echo wp_kses_post($data['title']); ?><?php
                    echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php
                            echo wp_kses_post($data['title']); ?></span></legend>
                    <select class="select <?php
                    echo esc_attr($data['class']); ?>" style="<?php
                    echo esc_attr($data['css']); ?>" <?php
                    disabled($data['disabled'], true); ?> <?php
                    echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                    ?>>
                        <option value="">Select a Service</option>
                        <?php
                        $prefix = ' - ';
                        if ($field_key == 'woocommerce_the_courier_guy_price_rate_override_per_service') {
                            $prefix = ' - R ';
                        }
                        ?>
                        <?php
                        foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                            <option value="<?php
                            echo esc_attr($option_key); ?>" data-service-label="<?php
                            echo esc_attr($option_value); ?>"><?php
                                echo esc_attr(
                                    $option_value
                                ); ?><?= (!empty($overrideValues[$option_key])) ? $prefix . $overrideValues[$option_key] : ''; ?></option>
                        <?php
                        endforeach; ?>
                    </select>
                    <?php
                    foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                        <span style="display:none;" class="<?php
                        echo esc_attr($data['class']); ?>-span-<?= $option_key; ?>">
                            <?php
                            $class = '';
                            $style = '';
                            if ($field_key == 'woocommerce_the_courier_guy_price_rate_override_per_service') {
                                $class = 'wc_input_price ';
                                $style = ' style="width: 90px !important;" ';
                                ?>
                                <span style="position:relative; top:8px; padding:0 0 0 10px;">R </span>
                                <?php
                            }
                            ?>
                            <input data-service-id="<?php
                            echo esc_attr($option_key); ?>" class="<?= $class; ?> input-text regular-input <?php
                            echo esc_attr($data['class']); ?>-input"
                                   type="text"<?= $style; ?> value="<?= isset($overrideValues[$option_key]) ? $overrideValues[$option_key] : ''; ?>"/>
                        </span>
                    <?php
                    endforeach; ?>
                    <?php
                    echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                    <input type="hidden" name="<?php
                    echo esc_attr($field_key); ?>" value="<?= esc_attr($overrideValue); ?>"/>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * This method is called to build the UI for custom shipping setting of type 'tcg_shop_area'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
    public function generate_tcg_pdf_paper_size_html($key, $data)
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $field_key       = $this->get_field_key($key);
        $defaults        = [
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
        ];
        $data            = wp_parse_args($data, $defaults);
        $data['options'] = array_keys(CPDF::$PAPER_SIZES);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php
                echo $this->get_tooltip_html($data); ?>
                <label for="<?php
                echo esc_attr($field_key); ?>"><?php
                    echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php
                            echo wp_kses_post($data['title']); ?></span>
                    </legend>
                    <select class="select <?php
                    echo esc_attr($data['class']); ?>" name="<?php
                    echo esc_attr($field_key); ?>" id="<?php
                    echo esc_attr($field_key); ?>" style="<?php
                    echo esc_attr($data['css']); ?>" <?php
                    disabled($data['disabled'], true); ?> <?php
                    echo $this->get_custom_attribute_html($data); ?>>
                        <?php
                        foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                            <option value="<?php
                            echo esc_attr($option_value); ?>" <?php
                            selected($option_value, esc_attr($this->get_option($key))); ?>><?php
                                echo esc_attr($option_value); ?></option>
                        <?php
                        endforeach; ?>
                    </select>
                    <?php
                    echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * This method is called to build the UI for custom shipping setting of type 'tcg_shop_area'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
    public function generate_tcg_shop_area_html($key, $data)
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $field_key       = $this->get_field_key($key);
        $defaults        = [
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
            'options'           => [],
        ];
        $data            = wp_parse_args($data, $defaults);
        $name            = esc_attr($this->get_option('shopPlace'));
        $id              = esc_attr($this->get_option($key));
        $data['options'] = [
            $id => $name
        ];
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php
                echo $this->get_tooltip_html($data); ?>
                <label for="<?php
                echo esc_attr($field_key); ?>"><?php
                    echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php
                            echo wp_kses_post($data['title']); ?></span>
                    </legend>
                    <select class="select <?php
                    echo esc_attr($data['class']); ?>" name="<?php
                    echo esc_attr($field_key); ?>" id="<?php
                    echo esc_attr($field_key); ?>" style="<?php
                    echo esc_attr($data['css']); ?>" <?php
                    disabled($data['disabled'], true); ?> <?php
                    echo $this->get_custom_attribute_html($data); ?>>
                        <?php
                        foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                            <option value="<?php
                            echo esc_attr($option_key); ?>" <?php
                            selected($option_key, esc_attr($this->get_option($key))); ?>><?php
                                echo esc_attr($option_value); ?></option>
                        <?php
                        endforeach; ?>
                    </select>
                    <?php
                    echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * This method is called to build the UI for custom shipping setting of type 'tcg_percentage'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
    public function generate_tcg_percentage_html($key, $data)
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $field_key = $this->get_field_key($key);
        $defaults  = [
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
        ];
        $data      = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php
                echo esc_attr($field_key); ?>"><?php
                    echo wp_kses_post($data['title']); ?><?php
                    echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php
                            echo wp_kses_post($data['title']); ?></span>
                    </legend>
                    <input class="wc_input_decimal input-text regular-input <?php
                    echo esc_attr($data['class']); ?>" type="text" name="<?php
                    echo esc_attr($field_key); ?>" id="<?php
                    echo esc_attr($field_key); ?>" style="<?php
                    echo esc_attr($data['css']); ?> width: 50px !important;" value="<?php
                    echo esc_attr(wc_format_localized_decimal($this->get_option($key))); ?>" placeholder="<?php
                    echo esc_attr($data['placeholder']); ?>" <?php
                    disabled($data['disabled'], true); ?> <?php
                    echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.
                    ?> /><span style="vertical-align: -webkit-baseline-middle;padding: 6px;">%</span>
                    <?php
                    echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * This method is called to validate the custom shipping setting of type 'tcg_percentage'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $value
     *
     * @return string
     */
    public function validate_tcg_percentage_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;

        return ('' === $value) ? '' : wc_format_decimal(trim(stripslashes($value)));
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    private function isTcgProhibited($product_id)
    {
        $itp = get_post_meta($product_id, 'product_prohibit_tcg', true);

        return $itp === 'on';
    }

    /**
     * @param array $rates
     *
     * @return array
     */
    private function sortRatesByTotalValueAscending($rates)
    {
        if (is_array($rates)) {
            usort(
                $rates,
                function ($x, $y) {
                    if (!isset($x['total']) && !isset($y['total'])) {
                        exit;
                    }

                    $result = 0;

                    if ($x['total'] > $y['total']) {
                        $result = 1;
                    } elseif ($x['total'] < $y['total']) {
                        $result - 1;
                    }

                    return $result;
                }
            );
        }


        return $rates;
    }

    /**
     * @param array $rates
     * @param array $package
     *
     * @return array
     */
    private function addRates($rates, $package)
    {
        $addedRates         = [];
        $rates              = $this->filterRates($rates);
        $rates              = apply_filters('wcmp_tcg_rates_filter', $rates, $package);
        $rates              = $this->sortRatesByTotalValueAscending($rates);
        $percentageMarkup   = $this->get_instance_option('percentage_markup');
        $priceRateOverrides = json_decode($this->get_instance_option('price_rate_override_per_service'), true);
        $labelOverrides     = json_decode($this->get_instance_option('label_override_per_service'), true);
        if (!empty($rates) && is_array($rates)) {
            $finalRates = [];
            foreach ($rates as $rate) {
                $addedRates[] = $rate;
                $finalRates[] = $this->addRate(
                    $rate,
                    $package,
                    $percentageMarkup,
                    $priceRateOverrides,
                    $labelOverrides
                );
            }
        }

        $hasFreeShipping = false;
        foreach ($finalRates as $finalRate) {
            if ($finalRate['free']) {
                $hasFreeShipping = true;
            }
        }
        foreach ($finalRates as $finalRate) {
            if ($hasFreeShipping && $finalRate['free']) {
                $this->add_rate($finalRate['rate']);
            } elseif (!$hasFreeShipping) {
                $this->add_rate($finalRate['rate']);
            }
        }

        return $addedRates;
    }

    private function filterRates($rates)
    {
        $excludes = $this->get_instance_option('excludes');
        if (empty($excludes)) {
            $excludes = [];
        }
        $filteredRates = array_filter(
            $rates,
            function ($rate) use ($excludes) {
                return (!in_array($rate['service'], $excludes));
            }
        );

        return $filteredRates;
    }

    /**
     * @param array $rate
     * @param array $package
     * @param int $percentageMarkup
     * @param array $priceRateOverrides
     * @param array $labelOverrides
     */
    private function addRate($rate, $package, $percentageMarkup, $priceRateOverrides, $labelOverrides)
    {
        // Get tax rates
        $tax = WC_Tax::get_rates_for_tax_class('');

        $taxRate       = 0.0;
        $taxes_enabled = get_option('woocommerce_calc_taxes');
        if ($this->get_instance_option('tax_status') === 'taxable' && ($taxes_enabled == 'yes')) {
            $taxRate = $tax ? ($tax[1]->tax_rate + 100.00) : 0.00; // In South Africa, this is 15% or 115 as the taxRate
        }

        // Free shipping global settings
        $free_ship = $this->get_instance_option('free_shipping');
        // $free_ship_to_main_centres = $this->get_instance_option('free_shipping_to_main_centres');
        $amount_for_free_shipping = $this->get_instance_option('amount_for_free_shipping');
        $rates_for_free_shipping  = $this->get_instance_option('rates_for_free_shipping');
        if ($rates_for_free_shipping == '') {
            $rates_for_free_shipping = [];
        }

        $product_free_shipping = false;
        // Free shipping product settings
        if ($this->get_instance_option('product_free_shipping') === "yes") {
            foreach ($package['contents'] as $product) {
                $pfs = get_post_meta($product['product_id'], 'product_free_shipping', true);
                if ($pfs == "on") {
                    $product_free_shipping = true;
                }
            }
        }


        // Does qualify for free shipping based on total value
        $global_amount_free_shipping = false;
        if (isset($package['contents_cost']) && !isset($package['cart_subtotal'])) {
            $package['cart_subtotal'] = $package['contents_cost'];
        }
        if (isset($package['cart_subtotal']) && $package['cart_subtotal'] >= $amount_for_free_shipping) {
            $global_amount_free_shipping = true;
        }

        $rateTotal = $rate['total'];

        if ($rateTotal > 0) {
            $rateService = $rate['name'];

            $rateService1     = str_replace('The Courier Guy ', '', $rateService);
            $rateService1     = explode(":", $rateService1);
            $rateServicelabel = $rateService1[0];

            $rateLabel = $rate['name'];

            if (!empty($labelOverrides[$rateServicelabel])) {
                $rateLabel = $labelOverrides[$rateServicelabel];
            }

            $totalPrice      = $rateTotal;
            $taxes           = 0.00;
            $rate_adjustment = $rate['rate_adjustment'];

            if (!empty($priceRateOverrides[$rateServicelabel])) {
                $totalPrice = number_format($priceRateOverrides[$rateServicelabel], 2, '.', '');

                $totalPrice = $totalPrice + $rate_adjustment;
            } else {
                if (!empty($percentageMarkup)) {
                    $totalPrice = ($rate['total'] + ($rate['total'] * $percentageMarkup / 100));
                    $totalPrice = number_format($totalPrice, 2, '.', '');

                    $totalPrice = $totalPrice + $rate_adjustment;
                }
            }

            if ($taxRate != 0.00) {
                // Calculate tax if Tax rate is not 0
                $totalPriceExcl = $totalPrice / $taxRate * 100;
                $taxes          = $totalPrice - $totalPriceExcl;
                $totalPrice     = $totalPrice - $taxes;
            } elseif (empty($priceRateOverrides[$rateServicelabel])) {
                // Add tax back to shipping total price if taxable disabled an not 'special' price
                $totalPrice = $totalPrice * 1.15;
            }

            $shippingMethodId = 'the_courier_guy' . ':' . $rateService . ':' . $this->instance_id;
            $args             = [
                'id'       => $shippingMethodId,
                'label'    => $rateLabel,
                'cost'     => $totalPrice,
                'taxes'    => [1 => $taxes],
                'calc_tax' => 'per_order',
                'package'  => $package
            ];

            //Check if free shipping is required
            if ($free_ship == 'yes') {
                global $woocommerce;

                if (($product_free_shipping || $global_amount_free_shipping) && in_array(
                        $rate['service'],
                        $rates_for_free_shipping
                    )) {
                    $args['label'] = $rateLabel . ': Free Shipping';
                    $args['cost']  = 0;
                    $args['taxes'] = [1 => 0];

                    //The id variable must be changed, as this is used in the 'add_rate' method on the parent class WC_Shipping_Method.
                    //@todo This logic is legacy from an older version of the plugin, there must be a better way, no time now.
                    $this->id = $shippingMethodId;

                    $this->add_rate($args);

                    return array(
                        'id'   => $this->id,
                        'free' => true,
                        'rate' => $args,
                    );
                } elseif (($product_free_shipping || $global_amount_free_shipping) && !in_array(
                        $rate['service'],
                        $rates_for_free_shipping
                    )) {
                    $this->id = $shippingMethodId;

                    $this->add_rate($args);

                    return array(
                        'id'   => $this->id,
                        'free' => false,
                        'rate' => $args,
                    );
                } elseif (!($product_free_shipping || $global_amount_free_shipping)) {
                    $this->id = $shippingMethodId;

                    return array(
                        'id'   => $this->id,
                        'free' => false,
                        'rate' => $args,
                    );
                    $this->add_rate($args);
                }
            } else {
                $free = false;
                if (($product_free_shipping) && in_array(
                        $rate['service'],
                        $rates_for_free_shipping
                    )) {
                    $args['label'] = $rateLabel . ': Free Shipping';
                    $args['cost']  = 0;
                    $args['taxes'] = [1 => 0];
                    $free          = true;
                }

                $this->id = $shippingMethodId;

                $this->add_rate($args);

                return array(
                    'id'   => $this->id,
                    'free' => $free,
                    'rate' => $args,
                );
            }
        }
    }

    /**
     *
     */
    private function overrideFormFieldsVariable()
    {
        $fields                     = [
            'title'                                 => [
                'title'   => __('Title', 'woocommerce'),
                'type'    => 'text',
                'label'   => __('Method Title', 'woocommerce'),
                'default' => 'The Courier Guy'
            ],
            'account'                               => [
                'title'       => __('Account number', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'The account number supplied by The Courier Guy for integration purposes.',
                    'woocommerce'
                ),
                'default'     => __('', 'woocommerce')
            ],
            'ship_logic_access_key_id'              => [
                'title'             => __('Access Key ID', 'woocommerce'),
                'type'              => 'text',
                'description'       => __(
                    'The access key ID for the Ship Logic API (legacy).',
                    'woocommerce'
                ),
                'custom_attributes' => array(
                    'readonly' => 'readonly'
                ),
                'default'           => __('', 'woocommerce')
            ],
            'ship_logic_secret_access_key'          => [
                'title'             => __('Access Key', 'woocommerce'),
                'type'              => 'password',
                'description'       => __(
                    'The secret access key for the Ship Logic API (legacy).',
                    'woocommerce'
                ),
                'custom_attributes' => array(
                    'readonly' => 'readonly'
                ),
                'default'           => __('', 'woocommerce')
            ],
            'ship_logic_secret_access_token'        => [
                'title'       => __('API Key', 'woocommerce'),
                'type'        => 'password',
                'description' => __(
                    'The access token for the Ship Logic API (V2).',
                    'woocommerce'
                ),
                'default'     => __('', 'woocommerce')
            ],
            'tax_status'                            => [
                'title'       => __('Tax status', 'woocommerce'),
                'type'        => 'select',
                'options'     => ['taxable' => 'Taxable', 'none' => 'None'],
                'description' => __('VAT applies or not', 'woocommerce'),
                'default'     => __('taxable', 'woocommerce')
            ],
            'company_name'                          => [
                'title'       => __('Company Name', 'woocommerce'),
                'type'        => 'text',
                'description' => __('The name of your company.', 'woocommerce'),
                'default'     => '',
            ],
            'shopAddress1'                          => [
                'title'       => __('Shop Street Number and Name', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'The address used to calculate shipping, this is considered the collection point for the parcels
                    being shipping. e.g 12 My Road',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopSuburb'                            => [
                'title'       => __('Shop Suburb', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'Suburb forms part of the shipping address e.g Howick North',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopCity'                              => [
                'title'       => __('Shop City', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'City forms part of the shipping address e.g Howick',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopState'                             => [
                'title'       => __('Shop State or Province', 'woocommerce'),
                'type'        => 'select',
                'description' => __(
                    'State / Province forms part of the shipping address e.g KZN',
                    'woocommerce'
                ),
                'options'     => WC()->countries->get_states('ZA'),
                'default'     => '',
            ],
            'shopCountry'                           => [
                'title'       => __('Shop Country', 'woocommerce'),
                'type'        => 'select',
                'description' => __(
                    'Country forms part of the shipping address e.g South Africa',
                    'woocommerce'
                ),
                'options'     => WC()->countries->get_countries(),
                'default'     => 'ZA',
            ],
            'shopPostalCode'                        => [
                'title'       => __('Shop Postal Code', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'The address used to calculate shipping, this is considered the collection point for the parcels being shipping.',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopPhone'                             => [
                'title'       => __('Shop Phone', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'The telephone number to contact the shop, this may be used by the courier.',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopContactName'                       => [
                'title'       => __('Shop Contact Name', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'The contact name of the shop, this may be used by the courier.',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'shopEmail'                             => [
                'title'       => __('Shop Email', 'woocommerce'),
                'type'        => 'email',
                'description' => __(
                    'The email to contact the shop, this may be used by the courier.',
                    'woocommerce'
                ),
                'default'     => '',
            ],
            'disable_specific_shipping_options'     => [
                'title'             => __('Enable Specific shipping options', 'woocommerce'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __(
                    'Select the shipping options that you wish to always be included from the available shipping options on the checkout page.
                     <br>This setting is not available for WooCommerce Blocks.',
                    'woocommerce'
                ),
                'default'           => '',
                'options'           => $this->getAvailableShippingOptions(),
                'custom_attributes' => [
                    'data-placeholder' => __('Select the shipping option you would like to include', 'woocommerce')
                ]
            ],
            'excludes'                              => [
                'title'             => __('Exclude Rates', 'woocommerce'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __(
                    'Select the rates that you wish to always be excluded from the available rates on the checkout page.',
                    'woocommerce'
                ),
                'default'           => '',
                'options'           => $this->getRateOptions(),
                'custom_attributes' => [
                    'data-placeholder' => __('Select the rates you would like to exclude', 'woocommerce')
                ]
            ],
            'percentage_markup'                     => [
                'title'       => __('Percentage Markup', 'woocommerce'),
                'type'        => 'tcg_percentage',
                'description' => __('Percentage markup to be applied to each quote.', 'woocommerce'),
                'default'     => ''
            ],
            'automatically_submit_collection_order' => [
                'title'       => __('Automatically Submit Collection Order', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will determine whether or not the collection order is automatically submitted to The Courier Guy after checkout completion.',
                    'woocommerce'
                ),
                'default'     => 'no'
            ],
            'remove_waybill_description'            => [
                'title'       => __('Generic waybill description', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'When enabled, a generic product description will be shown on the waybill.',
                    'woocommerce'
                ),
                'default'     => 'no'
            ],
            'price_rate_override_per_service'       => [
                'title'       => __('Price Rate Override Per Service', 'woocommerce'),
                'type'        => 'tcg_override_per_service',
                'description' => __(
                                     'These prices will override The Courier Guy rates per service.',
                                     'woocommerce'
                                 ) . '<br />' . __(
                                     'Select a service to add or remove price rate override.',
                                     'woocommerce'
                                 ) . '<br />' . __(
                                     'Services with an overridden price will not use the \'Percentage Markup\' setting.',
                                     'woocommerce'
                                 ),
                'options'     => $this->getRateOptions(),
                'default'     => '',
                'class'       => 'tcg-override-per-service',
            ],
            'label_override_per_service'            => [
                'title'       => __('Label Override Per Service', 'woocommerce'),
                'type'        => 'tcg_override_per_service',
                'description' => __(
                                     'These labels will override The Courier Guy labels per service.',
                                     'woocommerce'
                                 ) . '<br />' . __('Select a service to add or remove label override.', 'woocommerce'),
                'options'     => $this->getRateOptions(),
                'default'     => '',
                'class'       => 'tcg-override-per-service',
            ],
            'flyer'                                 => [
                'title'   => '<h3>Parcels - Flyer Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_1'           => [
                'title'       => __('Length of Flyer (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the Flyer - required', 'woocommerce'),
                'default'     => '42',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_1'            => [
                'title'       => __('Width of Flyer (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the Flyer - required', 'woocommerce'),
                'default'     => '32',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_1'           => [
                'title'       => __('Height of Flyer (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the Flyer - required', 'woocommerce'),
                'default'     => '12',
                'placeholder' => 'none',
            ],
            'medium_parcel'                         => [
                'title'   => '<h3>Parcels - Medium Parcel Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_2'           => [
                'title'       => __('Length of Medium Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the medium parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_2'            => [
                'title'       => __('Width of Medium Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the medium parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_2'           => [
                'title'       => __('Height of Medium Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the medium parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'large_parcel'                          => [
                'title'   => '<h3>Parcels - Large Parcel Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_3'           => [
                'title'       => __('Length of Large Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the large parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_3'            => [
                'title'       => __('Width of Large Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the large parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_3'           => [
                'title'       => __('Height of Large Parcel (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the large parcel - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'custom_parcel_size_1'                  => [
                'title'   => '<h3>Custom Parcel Size 1</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_4'           => [
                'title'       => __('Length of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_4'            => [
                'title'       => __('Width of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_4'           => [
                'title'       => __('Height of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'custom_parcel_size_2'                  => [
                'title'   => '<h3>Custom Parcel Size 2</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_5'           => [
                'title'       => __('Length of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_5'            => [
                'title'       => __('Width of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_5'           => [
                'title'       => __('Height of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'custom_parcel_size_3'                  => [
                'title'   => '<h3>Custom Parcel Size 3</h3>',
                'type'    => 'hidden',
                'default' => '',
            ],
            'product_length_per_parcel_6'           => [
                'title'       => __('Length of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_width_per_parcel_6'            => [
                'title'       => __('Width of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'product_height_per_parcel_6'           => [
                'title'       => __('Height of Custom Parcel Size (cm)', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'woocommerce'),
                'default'     => '',
                'placeholder' => 'none',
            ],
            'billing_insurance'                     => [
                'title'       => __('Enable shipping insurance ', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will enable the shipping insurance field on the checkout page.<br>
                     A product subtotal of R1500 and above is required to activate TCG insurance.<br>
                     If you have WooCommerce Blocks, shipping insurance will be activated automatically<br>
                      if the subtotal is above the threshold and this setting is selected.',
                    'woocommerce'
                ),
                'default'     => 'no'
            ],
            'free_shipping'                         => [
                'title'       => __('Enable free shipping ', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __('This will enable free shipping over a specified amount', 'woocommerce'),
                'default'     => 'no'
            ],
            'rates_for_free_shipping'               => [
                'title'             => __('Rates for free Shipping', 'woocommerce'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __('Select the rates that you wish to enable for free shipping', 'woocommerce'),
                'default'           => '',
                'options'           => $this->getRateOptions(),
                'custom_attributes' => [
                    'data-placeholder' => __(
                        'Select the rates you would like to enable for free shipping',
                        'woocommerce'
                    )
                ]
            ],
            'amount_for_free_shipping'              => [
                'title'             => __('Amount for free Shipping', 'woocommerce'),
                'type'              => 'number',
                'description'       => __('Enter the amount for free shipping when enabled', 'woocommerce'),
                'default'           => '1000',
                'custom_attributes' => [
                    'min' => '0'
                ]

            ],
            'product_free_shipping'                 => [
                'title'       => __('Enable free shipping from product setting', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will enable free shipping if the product is included in the basket',
                    'woocommerce'
                ),
                'default'     => 'no'
            ],
            'usemonolog'                            => [
                'title'       => __('Enable WooCommerce Logging', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'Check this to enable WooCommerce logging for this plugin. Remember to empty out logs when done.',
                    'woocommerce'
                ),
                'default'     => __('no', 'woocommerce'),
            ],
            'enablemethodbox'                       => [
                'title'       => __('Enable Method Box on Checkout', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    '
                        Check this to enable the Method Box on checkout page.<br>
                        Method Box is not available for WooCommerce Blocks.',
                    'woocommerce'
                ),
                'default'     => 'no',
            ],
            'enablenonstandardpackingbox'           => [
                'title'       => __('Use non-standard packing algorithm', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'Check this to use the non-standard packing algorithm.<br> This is more accurate but will also use more server resources and may fail on shared servers.',
                    'woocommerce'
                ),
                'default'     => 'no',
            ],
            'displaymessageifnorates'               => [
                'title'       => __('Enable display message if no rates', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'Check this to display a message on checkout if there are no shipping options for a desired package and address.',
                    'woocommerce'
                ),
                'default'     => 'yes',
            ],
        ];
        $this->instance_form_fields = $fields;
    }

    /**
     * @return array|mixed|object
     */
    private function getAvailableShippingOptions()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $shipOptions                            = new stdClass();
        $shipOptions->power_station             = "Power station";
        $shipOptions->hospital                  = "Hospital";
        $shipOptions->plot_farm                 = "Plot / Farm";
        $shipOptions->tender                    = "Tender";
        $shipOptions->chain_stores              = "Chain stores";
        $shipOptions->manual_waybill_charge     = "Manual waybill charge";
        $shipOptions->after_hours_delivery      = "After hours delivery";
        $shipOptions->after_hours_collection    = "After hours collection";
        $shipOptions->public_holiday_collection = "Public holiday collection";
        $shipOptions->saturday_delivery         = "Saturday delivery";
        $shipOptions->earlybird                 = "Earlybird";
        $shipOptions->saturday_collection       = "Saturday collection";
        $shipOptions->public_holiday_delivery   = "Public holiday delivery";
        $shipOptions->public_holiday_collection = "Public holiday collection";

        return $shipOptions;
    }

    /**
     * @return array|mixed|object
     */
    private function getRateOptions()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $rateOptions        = new stdClass();
        $rateOptions->AIR   = "The Courier Guy AIR: Fuel charge";
        $rateOptions->ECO   = "The Courier Guy ECO: Fuel charge";
        $rateOptions->ECOR  = "The Courier Guy ECOR: Fuel charge";
        $rateOptions->ECOB  = "The Courier Guy ECOB: Fuel charge";
        $rateOptions->ECORB = "The Courier Guy ECORB: Fuel charge";
        $rateOptions->IND   = "The Courier Guy IND: Fuel charge";
        $rateOptions->INN   = "The Courier Guy INN: Fuel charge";
        $rateOptions->LLS   = "The Courier Guy LLS: Fuel charge";
        $rateOptions->LLX   = "The Courier Guy LLX: Fuel charge";
        $rateOptions->LOF   = "The Courier Guy LOF: Fuel charge";
        $rateOptions->LOX   = "The Courier Guy LOX: Fuel charge";
        $rateOptions->LSE   = "The Courier Guy LSE: Fuel charge";
        $rateOptions->LSF   = "The Courier Guy LSF: Fuel charge";
        $rateOptions->LSX   = "The Courier Guy LSX: Fuel charge";
        $rateOptions->NFS   = "The Courier Guy NFS: Fuel charge";
        $rateOptions->OVN   = "The Courier Guy OVN: Fuel charge";
        $rateOptions->OVNR  = "The Courier Guy OVNR: Fuel charge";
        $rateOptions->RIN   = "The Courier Guy RIN: Fuel charge";
        $rateOptions->SDX   = "The Courier Guy SDX: Fuel charge";
        $rateOptions->SPX   = "The Courier Guy SPX: Fuel charge";

        return $rateOptions;
    }

    /**
     *getSuburbLocationOptions() -> returns an array of locations from the checkout form
     * @return array|mixed|object
     */
    private function getSuburbLocationOptions()
    {
        return json_decode(
            '{"_country":"Country "," _state":"Province","_city":"City/Town","_address_2":"Street Address","_postcode":"Postcode/ZIP"}'
        );
    }

}
