<?php
/**
 * Plugin Name: The Courier Guy Shipping for WooCommerce
 * Description: The Courier Guy WP & Woocommerce Shipping functionality.
 * Author: The Courier Guy
 * Author URI: https://www.thecourierguy.co.za/
 * Version: 5.4.0
 * Plugin Slug: wp-plugin-the-courier-guy
 * Text Domain: the-courier-guy
 * WC requires at least: 7.0.0
 * WC tested up to: 10.2.2
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

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

// Load TCG Integration regardless of WooCommerce status for testing
if (file_exists(__DIR__ . '/Core/TCG_Shipping_Integration.php')) {
    // Load the integration class
    require_once __DIR__ . '/Core/TCG_Shipping_Integration.php';

    // Register REST API routes immediately and independently
    add_action('rest_api_init', function() {
        // Create a temporary instance just for REST API registration
        $temp_integration = new TCG_Shipping_Integration();
        $temp_integration->register_rest_api();
    });

    // WooCommerce Blocks integration (if available)
    add_action('woocommerce_blocks_loaded', function () {

        if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
            return;
        }

        add_action('woocommerce_blocks_integrations', function($integrationRegistry) {
            $integration = new TCG_Shipping_Integration();
            $integrationRegistry->register($integration);
        });
    });
}

function htaccess_protect()
{
    $plugin_dir = dirname(__FILE__);
    $htaccess   = $plugin_dir . '/.htaccess.setup';
    $target     = dirname(__DIR__, 2) . '/Uploads/the-courier-guy/.htaccess';
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
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'woocommerce_tcg_declare_hpos_compatibility');

// Enqueue frontend scripts for checkout
add_action('enqueue_block_assets', function() {
    if (function_exists('is_checkout') && is_checkout()) {
        // Check if the script is already registered by the integration
        if (!wp_script_is('tcg-blocks-frontend', 'registered')) {
            wp_enqueue_script(
                'tcg-blocks-frontend',
                plugins_url('dist/js/frontend/blocks.js', __FILE__),
                ['wp-i18n', 'wp-element', 'wp-html-entities', 'wc-blocks-checkout'],
                filemtime(plugin_dir_path(__FILE__) . 'dist/js/frontend/blocks.js'),
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
        } else {
            // Just enqueue if already registered
            wp_enqueue_script('tcg-blocks-frontend');
        }
    }
}, 10, 0);

