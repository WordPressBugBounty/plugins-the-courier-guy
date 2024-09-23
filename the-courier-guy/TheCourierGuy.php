<?php
/**
 * Plugin Name: The Courier Guy Shipping for WooCommerce
 * Description: The Courier Guy WP & Woocommerce Shipping functionality.
 * Author: The Courier Guy
 * Author URI: https://www.thecourierguy.co.za/
 * Version: 5.1.2
 * Plugin Slug: wp-plugin-the-courier-guy
 * Text Domain: the-courier-guy
 * WC requires at least: 7.0.0
 * WC tested up to: 9.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

$dependencyPlugins = [
    'woocommerce/woocommerce.php' => [
        'notice' => 'Please install Woocommerce before attempting to install the The Courier Guy plugin.'
    ],
];
require_once('Includes/ls-framework-custom/Core/CustomPluginDependencies.php');
require_once('Includes/ls-framework-custom/Core/CustomPlugin.php');
require_once('Includes/ls-framework-custom/Core/CustomPostType.php');
$dependencies      = new CustomPluginDependencies(__FILE__);
$dependenciesValid = $dependencies->checkDependencies($dependencyPlugins);
if ($dependenciesValid && class_exists('WC_Shipping_Method')) {
    require_once('Core/TCG_Plugin.php');
    global $TCG_Plugin;
    $TCG_Plugin            = new TCG_Plugin(__FILE__);
    $GLOBALS['TCG_Plugin'] = $TCG_Plugin;
    register_activation_hook(__FILE__, 'htaccess_protect');
    register_activation_hook(__FILE__, [$TCG_Plugin, 'intiatePluginActivation']);
    register_deactivation_hook(__FILE__, [$TCG_Plugin, 'deactivatePlugin']);
} else {
    deactivate_plugins(plugin_basename(__FILE__));
    unset($_GET['activate']);
}

add_action('wp_ajax_tcg_return_action', 'tcg_return_callback');
add_action('wp_ajax_nopriv_tcg_return_action', 'tcg_return_callback');

function tcg_return_callback()
{
    if (isset($_POST['order_id'])) {
        $order_id = intval($_POST['order_id']);
        header('Content-Type: application/json');
        echo $GLOBALS['TCG_Plugin']->createShipmentFromOrder(wc_get_order($order_id), true);
    }
    wp_die();
}

function htaccess_protect()
{
    $plugin_dir = dirname(__FILE__);
    $htaccess   = $plugin_dir . '/.htaccess.setup';
    $target     = dirname(__DIR__, 2) . '/uploads/the-courier-guy/.htaccess';
    copy($htaccess, $target);
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_tcg_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'woocommerce_tcg_declare_hpos_compatibility');

