<?php
/**
 * Plugin Name: The Courier Guy Shipping for WooCommerce
 * Description: The Courier Guy WP & Woocommerce Shipping functionality.
 * Author: The Courier Guy
 * Author URI: https://www.thecourierguy.co.za/
 * Version: 5.5.4
 * Plugin Slug: wp-plugin-the-courier-guy
 * Text Domain: the-courier-guy
 * WC requires at least: 9.0
 * WC tested up to: 10.7
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
require_once('Core/TCGRateCache.php');
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
    add_action('woocommerce_settings_saved', 'clear_tcg_caches');
    add_action('init', function () {
        if (!wp_next_scheduled('tcg_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'tcg_cleanup_transients');
        }
    });
} else {
    deactivate_plugins(plugin_basename(__FILE__));
    unset($_GET['activate']);
}

add_action('tcg_cleanup_transients', function () {
    global $wpdb;

    // Delete expired transients only
    $time = time();

    // Get expired transient timeout keys (limit to avoid heavy queries)
    $expired = $wpdb->get_col(
        $wpdb->prepare(
            "
        SELECT option_name
        FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_timeout_tcg_rate_cache%'
        AND option_value < %d
        LIMIT 500
    ",
            $time
        )
    );

    foreach ($expired as $timeout_key) {
        $transient_key = str_replace('_transient_timeout_', '', $timeout_key);

        delete_transient($transient_key);
    }

    if (count($expired) === 500) {
        // If we hit the limit, there may be more expired transients. Schedule another cleanup soon.
        wp_schedule_single_event(time() + 600, 'tcg_cleanup_transients');
    }
});
function clear_tcg_caches()
{
    // Clear TCG rate cache on settings save to ensure new settings take effect immediately
    (new TCGRateCache())->clear_tcg_cache();
}

// Load TCG Integration regardless of WooCommerce status for testing
if (file_exists(__DIR__ . '/Core/TCG_Shipping_Integration.php')) {
    // Load the integration class
    require_once __DIR__ . '/Core/TCG_Shipping_Integration.php';

    // Register REST API routes immediately and independently
    add_action('rest_api_init', function () {
        // Create a temporary instance just for REST API registration
        $temp_integration = new TCG_Shipping_Integration();
        $temp_integration->register_rest_api();
    });

    // WooCommerce Blocks integration (if available)
    add_action('woocommerce_blocks_loaded', function () {
        if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
            return;
        }

        add_action('woocommerce_blocks_integrations', function ($integrationRegistry) {
            $integration = new TCG_Shipping_Integration();
            $integrationRegistry->register($integration);
        });
    });
}

function htaccess_protect()
{
    $plugin_dir = dirname(__FILE__);
    $htaccess   = $plugin_dir . '/.htaccess.setup';
    $upload_dir = wp_upload_dir();

    $directory = $upload_dir['basedir'] . '/the-courier-guy';
    $target    = $directory . '/.htaccess';

    if (!is_dir($directory) && !wp_mkdir_p($directory)) {
        return new WP_Error(
            'mkdir_failed',
            'Could not create upload directory.'
        );
    }

    if (!file_exists($target) && file_put_contents($target, "") === false) {
        return new WP_Error(
            'file_failed',
            'Could not create .htaccess file.'
        );
    }
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

add_filter('allowed_redirect_hosts', function ($hosts) {
    $hosts[] = 'shiplogic-backend-prod-infra-label-pdfs.s3.af-south-1.amazonaws.com';
    $hosts[] = 'labels.shiplogic.com';

    return $hosts;
});

add_action('before_woocommerce_init', 'woocommerce_tcg_declare_hpos_compatibility');

// Enqueue frontend scripts for checkout
add_action('enqueue_block_assets', function () {
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
                    'batch_url'   => home_url('/wp-json/wc/store/v1/batch'),
                    'nonce'       => wp_create_nonce('wp_rest'),
                    'tcg_enabled' => TCG_Plugin::getShippingMethodSettings()['enabled'] ?? 'no'
                ]
            );
        } else {
            // Just enqueue if already registered
            wp_enqueue_script('tcg-blocks-frontend');
        }
    }
},         10, 0);

