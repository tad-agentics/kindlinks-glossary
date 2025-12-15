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
        add_action('admin_init', [$this, 'check_database_table']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box']);
    }

    /**
     * Sanitize color value (supports hex, rgb, rgba, transparent)
     *
     * @param string $color Color value to sanitize
     * @return string Sanitized color value
     */
    private function sanitize_color_value($color) {
        $color = trim($color);
        
        // Allow transparent
        if (strtolower($color) === 'transparent') {
            return 'transparent';
        }
        
        // Allow rgb/rgba
        if (preg_match('/^rgba?\s*\([^)]+\)$/i', $color)) {
            return sanitize_text_field($color);
        }
        
        // Allow hex colors
        if (preg_match('/^#[a-f0-9]{3,8}$/i', $color)) {
            return sanitize_hex_color($color);
        }
        
        // Default fallback
        return '#fff3cd';
    }

    /**
     * Check if database table exists and create if needed.
     * This is a safety fallback in case activation hook didn't run.
     */
    public function check_database_table(): void {
        // Only check on glossary admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'kindlinks-glossary') === false) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists) {
            // Table doesn't exist, create it now
            require_once KINDLINKS_GLOSSARY_PLUGIN_DIR . 'includes/class-activator.php';
            Kindlinks_Glossary_Activator::activate();
            
            // Show admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('Database table created successfully!', 'kindlinks-glossary') . '</p>';
                echo '</div>';
            });
        }
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
            'sanitize_callback' => [$this, 'sanitize_color_value'],
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

        register_setting('kindlinks_glossary_settings', 'kindlinks_glossary_read_more_text', [
            'type' => 'string',
            'default' => 'Xem thêm',
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

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Database table not found. Please deactivate and reactivate the plugin.', 'kindlinks-glossary') . '</p></div>';
            $terms = [];
        } else {
            // Get all terms
            $terms = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY keyword ASC");
            
            // Check for database errors
            if ($wpdb->last_error) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Database error: ', 'kindlinks-glossary') . esc_html($wpdb->last_error) . '</p></div>';
            }
            
            // Debug info (comment out in production)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<div class="notice notice-info"><p>';
                echo 'Table: ' . esc_html($table_name) . '<br>';
                echo 'Terms found: ' . count($terms) . '<br>';
                echo 'Last query: ' . esc_html($wpdb->last_query);
                echo '</p></div>';
            }
        }

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
                $result = $wpdb->update(
                    $table_name,
                    ['keyword' => $keyword, 'definition' => $definition, 'url' => $url, 'category' => $category],
                    ['id' => $term->id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
                
                if ($result !== false) {
                    $message = __('Term updated successfully.', 'kindlinks-glossary');
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update term: ', 'kindlinks-glossary') . esc_html($wpdb->last_error) . '</p></div>';
                }
            } else {
                $result = $wpdb->insert(
                    $table_name,
                    ['keyword' => $keyword, 'definition' => $definition, 'url' => $url, 'category' => $category],
                    ['%s', '%s', '%s', '%s']
                );
                
                if ($result) {
                    $message = __('Term added successfully.', 'kindlinks-glossary');
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                    
                    // Debug info
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo '<div class="notice notice-info"><p>';
                        echo 'Inserted ID: ' . $wpdb->insert_id . '<br>';
                        echo 'Table: ' . esc_html($table_name) . '<br>';
                        echo 'Keyword: ' . esc_html($keyword);
                        echo '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to add term: ', 'kindlinks-glossary') . esc_html($wpdb->last_error) . '</p></div>';
                }
            }

            delete_transient('kindlinks_glossary_terms');
            
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
            update_option('kindlinks_glossary_read_more_text', sanitize_text_field($_POST['read_more_text']));
            
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

                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid JSON file: ', 'kindlinks-glossary') . esc_html(json_last_error_msg()) . '</p></div>';
                } elseif (!is_array($terms)) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid format: JSON must be an array of terms', 'kindlinks-glossary') . '</p></div>';
                } elseif (empty($terms)) {
                    echo '<div class="notice notice-warning"><p>' . esc_html__('No terms found in the file', 'kindlinks-glossary') . '</p></div>';
                } else {
                    $imported = 0;
                    $errors = [];
                    
                    foreach ($terms as $index => $term) {
                        // Validate required fields
                        if (empty($term['keyword']) || empty($term['definition'])) {
                            $errors[] = sprintf(__('Term #%d: Missing keyword or definition', 'kindlinks-glossary'), $index + 1);
                            continue;
                        }
                        
                        $result = $wpdb->replace(
                            $table_name,
                            [
                                'keyword' => sanitize_text_field($term['keyword']),
                                'definition' => wp_kses_post($term['definition']),
                                'url' => esc_url_raw($term['url'] ?? ''),
                                'category' => sanitize_text_field($term['category'] ?? 'general'),
                            ],
                            ['%s', '%s', '%s', '%s']
                        );
                        
                        if ($result) {
                            $imported++;
                        } else {
                            $errors[] = sprintf(__('Term #%d (%s): Database error', 'kindlinks-glossary'), $index + 1, $term['keyword']);
                        }
                    }
                    
                    delete_transient('kindlinks_glossary_terms');
                    
                    if ($imported > 0) {
                        echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Imported %d terms successfully.', 'kindlinks-glossary'), $imported) . '</p></div>';
                    }
                    
                    if (!empty($errors)) {
                        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Some terms could not be imported:', 'kindlinks-glossary') . '</strong><br>';
                        foreach ($errors as $error) {
                            echo esc_html($error) . '<br>';
                        }
                        echo '</p></div>';
                    }
                }
            } else {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => __('File is too large (exceeds upload_max_filesize)', 'kindlinks-glossary'),
                    UPLOAD_ERR_FORM_SIZE => __('File is too large', 'kindlinks-glossary'),
                    UPLOAD_ERR_PARTIAL => __('File was only partially uploaded', 'kindlinks-glossary'),
                    UPLOAD_ERR_NO_FILE => __('No file was uploaded', 'kindlinks-glossary'),
                    UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder', 'kindlinks-glossary'),
                    UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk', 'kindlinks-glossary'),
                    UPLOAD_ERR_EXTENSION => __('File upload stopped by extension', 'kindlinks-glossary'),
                ];
                
                $error_code = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $error_message = $error_messages[$error_code] ?? __('Unknown upload error', 'kindlinks-glossary');
                
                echo '<div class="notice notice-error"><p>' . esc_html__('Upload failed: ', 'kindlinks-glossary') . esc_html($error_message) . '</p></div>';
            }
        }

        include KINDLINKS_GLOSSARY_PLUGIN_DIR . 'admin/views/import-export.php';
    }
}

