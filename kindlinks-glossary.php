<?php
/**
 * Plugin Name: Kindlinks Auto Glossary
 * Plugin URI: https://kindlinks.com
 * Description: Automatically highlights keywords in blog posts with Kindle-style tooltips. Optimized for long-form content (50k+ words).
 * Version: 2.2.0
 * Author: Kindlinks
 * Author URI: https://kindlinks.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kindlinks-glossary
 * Domain Path: /languages
 *
 * @package Kindlinks_Glossary
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('KINDLINKS_GLOSSARY_VERSION', '2.2.0');
define('KINDLINKS_GLOSSARY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KINDLINKS_GLOSSARY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_kindlinks_glossary(): void {
    require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-activator.php';
    Kindlinks_Glossary_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_kindlinks_glossary(): void {
    // Clean up if needed
}

register_activation_hook(__FILE__, 'activate_kindlinks_glossary');
register_deactivation_hook(__FILE__, 'deactivate_kindlinks_glossary');

/**
 * The core plugin classes.
 */
require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-api.php';
require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-frontend.php';
require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-admin.php';
require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-shortcode.php';

/**
 * Main plugin class.
 */
class Kindlinks_Glossary {
    
    /**
     * Instance of this class.
     *
     * @var Kindlinks_Glossary
     */
    protected static $instance = null;

    /**
     * Initialize the plugin.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Return an instance of this class.
     *
     * @return Kindlinks_Glossary A single instance of this class.
     */
    public static function get_instance(): Kindlinks_Glossary {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks(): void {
        // Initialize API endpoints
        add_action('rest_api_init', [Kindlinks_Glossary_API::class, 'register_routes']);
        
        // Initialize frontend functionality
        $frontend = new Kindlinks_Glossary_Frontend();
        add_action('wp_enqueue_scripts', [$frontend, 'enqueue_assets']);
        
        // Initialize admin functionality
        if (is_admin()) {
            new Kindlinks_Glossary_Admin();
        }
        
        // Initialize shortcode
        new Kindlinks_Glossary_Shortcode();
        
        // Add AJAX handlers
        add_action('wp_ajax_kindlinks_regenerate_api_key', [$this, 'ajax_regenerate_api_key']);
        add_action('wp_ajax_kindlinks_track_click', [$this, 'ajax_track_click']);
        add_action('wp_ajax_nopriv_kindlinks_track_click', [$this, 'ajax_track_click']);
    }

    /**
     * AJAX handler for regenerating API key.
     */
    public function ajax_regenerate_api_key(): void {
        check_ajax_referer('kindlinks_glossary_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'kindlinks-glossary')]);
        }

        $new_key = wp_generate_password(32, false);
        update_option('kindlinks_glossary_api_key', $new_key);

        wp_send_json_success(['api_key' => $new_key]);
    }

    /**
     * AJAX handler for tracking term clicks (analytics).
     */
    public function ajax_track_click(): void {
        if (!isset($_POST['keyword'])) {
            wp_send_json_error();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';
        $keyword = sanitize_text_field($_POST['keyword']);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET click_count = click_count + 1 WHERE keyword = %s",
            $keyword
        ));

        wp_send_json_success();
    }
}

/**
 * Load plugin text domain for translations.
 */
function kindlinks_glossary_load_textdomain(): void {
    load_plugin_textdomain(
        'kindlinks-glossary',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'kindlinks_glossary_load_textdomain');

/**
 * Begin execution of the plugin.
 */
function run_kindlinks_glossary(): void {
    Kindlinks_Glossary::get_instance();
}

run_kindlinks_glossary();








