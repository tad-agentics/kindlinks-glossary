<?php
/**
 * Admin functionality.
 *
 * @package Kindlinks_Glossary
 * @subpackage Kindlinks_Glossary/includes
 */

declare(strict_types=1);

/**
 * Handles admin interface and settings.
 */
class Kindlinks_Glossary_Admin {

    /**
     * Initialize admin hooks.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box']);
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('Glossary', 'kindlinks-glossary'),
            __('Glossary', 'kindlinks-glossary'),
            'manage_options',
            'kindlinks-glossary',
            [$this, 'render_terms_page'],
            'dashicons-book-alt',
            30
        );

        add_submenu_page(
            'kindlinks-glossary',
            __('All Terms', 'kindlinks-glossary'),
            __('All Terms', 'kindlinks-glossary'),
            'manage_options',
            'kindlinks-glossary',
            [$this, 'render_terms_page']
        );

        add_submenu_page(
            'kindlinks-glossary',
            __('Add New Term', 'kindlinks-glossary'),
            __('Add New', 'kindlinks-glossary'),
            'manage_options',
            'kindlinks-glossary-add',
            [$this, 'render_add_edit_page']
        );

        add_submenu_page(
            'kindlinks-glossary',
            __('Settings', 'kindlinks-glossary'),
            __('Settings', 'kindlinks-glossary'),
            'manage_options',
            'kindlinks-glossary-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'kindlinks-glossary',
            __('Import/Export', 'kindlinks-glossary'),
            __('Import/Export', 'kindlinks-glossary'),
            'manage_options',
            'kindlinks-glossary-import-export',
            [$this, 'render_import_export_page']
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings(): void {
        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_max_limit', [
            'type' => 'integer',
            'default' => 2,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_content_selectors', [
            'type' => 'string',
            'default' => '.entry-content,.breakdance-post-content',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_underline_color', [
            'type' => 'string',
            'default' => '#F26C26',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_hover_bg_color', [
            'type' => 'string',
            'default' => '#fff3cd',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_tooltip_keyword_color', [
            'type' => 'string',
            'default' => '#8B3A3A',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_enabled_post_types', [
            'type' => 'string',
            'default' => 'post,page',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets($hook): void {
        if (strpos($hook, 'kindlinks-glossary') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_style(
            'kindlinks-glossary-admin',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            KINDLINKS_GLOSSARY_VERSION
        );

        wp_enqueue_script(
            'kindlinks-glossary-admin',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            KINDLINKS_GLOSSARY_VERSION,
            true
        );

        wp_localize_script('kindlinks-glossary-admin', 'KindlinksAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kindlinks_glossary_admin'),
        ]);
    }

    /**
     * Add meta boxes to posts/pages.
     */
    public function add_meta_boxes(): void {
        $post_types = explode(',', get_option('kindlinks_glossary_enabled_post_types', 'post,page'));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'kindlinks_glossary_meta',
                __('Glossary Settings', 'kindlinks-glossary'),
                [$this, 'render_meta_box'],
                trim($post_type),
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content.
     */
    public function render_meta_box($post): void {
        wp_nonce_field('kindlinks_glossary_meta_box', 'kindlinks_glossary_meta_nonce');
        
        $disabled = get_post_meta($post->ID, '_kindlinks_glossary_disabled', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="kindlinks_glossary_disabled" value="1" <?php checked($disabled, '1'); ?>>
                <?php esc_html_e('Disable glossary for this post', 'kindlinks-glossary'); ?>
            </label>
        </p>
        <p class="description">
            <?php esc_html_e('Check this to prevent automatic keyword highlighting on this post.', 'kindlinks-glossary'); ?>
        </p>
        <?php
    }

    /**
     * Save meta box data.
     */
    public function save_meta_box($post_id): void {
        if (!isset($_POST['kindlinks_glossary_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['kindlinks_glossary_meta_nonce'], 'kindlinks_glossary_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $disabled = isset($_POST['kindlinks_glossary_disabled']) ? '1' : '0';
        update_post_meta($post_id, '_kindlinks_glossary_disabled', $disabled);
    }

    /**
     * Render terms list page.
     */
    public function render_terms_page(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        // Handle actions
        if (isset($_GET['action']) && isset($_GET['term_id']) && check_admin_referer('kindlinks_glossary_action')) {
            $term_id = intval($_GET['term_id']);
            
            if ($_GET['action'] === 'delete') {
                $wpdb->delete($table_name, ['id' => $term_id], ['%d']);
                delete_transient('kindlinks_glossary_terms');
                echo '<div class="notice notice-success"><p>' . esc_html__('Term deleted successfully.', 'kindlinks-glossary') . '</p></div>';
            }
        }

        // Get all terms
        $terms = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY keyword ASC");

        include KINDLINKS_GLOSSARY_PLUGIN_DIR . 'admin/views/terms-list.php';
    }

    /**
     * Render add/edit term page.
     */
    public function render_add_edit_page(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        $term = null;
        $is_edit = false;

        if (isset($_GET['term_id'])) {
            $term_id = intval($_GET['term_id']);
            $term = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $term_id));
            $is_edit = true;
        }

        // Handle form submission
        if (isset($_POST['kindlinks_glossary_submit']) && check_admin_referer('kindlinks_glossary_term')) {
            $keyword = sanitize_text_field($_POST['keyword']);
            $definition = wp_kses_post($_POST['definition']);
            $url = esc_url_raw($_POST['url']);
            $category = sanitize_text_field($_POST['category']);

            if ($is_edit && $term) {
                $wpdb->update(
                    $table_name,
                    ['keyword' => $keyword, 'definition' => $definition, 'url' => $url, 'category' => $category],
                    ['id' => $term->id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
                $message = __('Term updated successfully.', 'kindlinks-glossary');
            } else {
                $wpdb->insert(
                    $table_name,
                    ['keyword' => $keyword, 'definition' => $definition, 'url' => $url, 'category' => $category],
                    ['%s', '%s', '%s', '%s']
                );
                $message = __('Term added successfully.', 'kindlinks-glossary');
            }

            delete_transient('kindlinks_glossary_terms');
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            
            if (!$is_edit) {
                $term = null; // Reset form
            }
        }

        include KINDLINKS_GLOSSARY_PLUGIN_DIR . 'admin/views/term-edit.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        if (isset($_POST['kindlinks_glossary_settings_submit']) && check_admin_referer('kindlinks_glossary_settings')) {
            update_option('kindlinks_glossary_max_limit', absint($_POST['max_limit']));
            update_option('kindlinks_glossary_content_selectors', sanitize_text_field($_POST['content_selectors']));
            update_option('kindlinks_glossary_underline_color', sanitize_hex_color($_POST['underline_color']));
            update_option('kindlinks_glossary_hover_bg_color', sanitize_hex_color($_POST['hover_bg_color']));
            update_option('kindlinks_glossary_tooltip_keyword_color', sanitize_hex_color($_POST['tooltip_keyword_color']));
            update_option('kindlinks_glossary_enabled_post_types', sanitize_text_field($_POST['enabled_post_types']));
            
            // Handle category filtering
            $enabled_categories = [];
            if (isset($_POST['enabled_categories']) && is_array($_POST['enabled_categories'])) {
                foreach ($_POST['enabled_categories'] as $cat) {
                    if ($cat === 'all') {
                        $enabled_categories = ['all'];
                        break;
                    }
                    $enabled_categories[] = absint($cat);
                }
            } else {
                // If nothing selected, default to 'all'
                $enabled_categories = ['all'];
            }
            update_option('kindlinks_glossary_enabled_categories', $enabled_categories);
            
            delete_transient('kindlinks_glossary_terms');
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'kindlinks-glossary') . '</p></div>';
        }

        include KINDLINKS_GLOSSARY_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render import/export page.
     */
    public function render_import_export_page(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        // Handle export
        if (isset($_POST['kindlinks_glossary_export']) && check_admin_referer('kindlinks_glossary_import_export')) {
            $terms = $wpdb->get_results("SELECT keyword, definition, url, category FROM {$table_name}", ARRAY_A);
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="glossary-terms-' . date('Y-m-d') . '.json"');
            echo json_encode($terms, JSON_PRETTY_PRINT);
            exit;
        }

        // Handle import
        if (isset($_POST['kindlinks_glossary_import']) && check_admin_referer('kindlinks_glossary_import_export')) {
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $json = file_get_contents($_FILES['import_file']['tmp_name']);
                $terms = json_decode($json, true);

                if (is_array($terms)) {
                    $imported = 0;
                    foreach ($terms as $term) {
                        $wpdb->replace(
                            $table_name,
                            [
                                'keyword' => sanitize_text_field($term['keyword']),
                                'definition' => wp_kses_post($term['definition']),
                                'url' => esc_url_raw($term['url'] ?? ''),
                                'category' => sanitize_text_field($term['category'] ?? 'general'),
                            ],
                            ['%s', '%s', '%s', '%s']
                        );
                        $imported++;
                    }
                    delete_transient('kindlinks_glossary_terms');
                    echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Imported %d terms successfully.', 'kindlinks-glossary'), $imported) . '</p></div>';
                }
            }
        }

        include KINDLINKS_GLOSSARY_PLUGIN_DIR . 'admin/views/import-export.php';
    }
}

