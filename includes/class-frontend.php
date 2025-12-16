<?php
/**
 * Frontend functionality.
 *
 * @package Kindlinks_Glossary
 * @subpackage Kindlinks_Glossary/includes
 */

declare(strict_types=1);

/**
 * Handles frontend script enqueuing and data localization.
 */
class Kindlinks_Glossary_Frontend {

    /**
     * Enqueue frontend assets.
     *
     * Only loads on single posts and pages.
     */
    public function enqueue_assets(): void {
        // Debug info
        $debug_info = [];
        
        // Get enabled post types
        $enabled_post_types = explode(',', get_option('kindlinks_glossary_enabled_post_types', 'post,page'));
        $enabled_post_types = array_map('trim', $enabled_post_types);
        
        // Check if current post type is enabled
        $current_post_type = get_post_type();
        $debug_info['current_post_type'] = $current_post_type;
        $debug_info['enabled_post_types'] = $enabled_post_types;
        $debug_info['post_type_check'] = in_array($current_post_type, $enabled_post_types);
        
        if (!in_array($current_post_type, $enabled_post_types)) {
            $this->output_debug_info($debug_info, 'Post type not enabled');
            return;
        }

        // Check if glossary is disabled for this specific post
        $is_disabled = is_singular() && get_post_meta(get_the_ID(), '_kindlinks_glossary_disabled', true) === '1';
        $debug_info['post_disabled'] = $is_disabled;
        
        if ($is_disabled) {
            $this->output_debug_info($debug_info, 'Glossary disabled for this post');
            return;
        }

        // Check if post category is enabled (for posts only)
        if (is_singular('post')) {
            $enabled_categories = get_option('kindlinks_glossary_enabled_categories', ['all']);
            $post_categories = wp_get_post_categories(get_the_ID());
            
            $debug_info['enabled_categories'] = $enabled_categories;
            $debug_info['post_categories'] = $post_categories;
            $debug_info['is_all_categories'] = in_array('all', $enabled_categories);
            
            // If not set to 'all', check if post's categories match
            if (!in_array('all', $enabled_categories)) {
                // Check if any of the post's categories are in the enabled list
                $has_enabled_category = false;
                foreach ($post_categories as $cat_id) {
                    if (in_array($cat_id, $enabled_categories)) {
                        $has_enabled_category = true;
                        break;
                    }
                }
                
                $debug_info['has_enabled_category'] = $has_enabled_category;
                
                // If post doesn't have any enabled categories, don't load glossary
                if (!$has_enabled_category) {
                    $this->output_debug_info($debug_info, 'Post category not in enabled list');
                    return;
                }
            }
        }

        // Get glossary data from database
        $glossary_data = $this->get_glossary_data();
        
        // Apply filter for extensibility
        $glossary_data = apply_filters('kindlinks_glossary_terms', $glossary_data);

        $debug_info['terms_count'] = count($glossary_data);
        
        // Only enqueue if we have terms (lazy loading)
        if (empty($glossary_data)) {
            $this->output_debug_info($debug_info, 'No glossary terms found');
            return;
        }
        
        $debug_info['status'] = 'Loading glossary';

        // Enqueue Popper.js (required by Tippy.js) - Local bundle for reliability and performance
        wp_enqueue_script(
            'popperjs',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/vendor/popper.min.js',
            [],
            '2.11.8',
            true
        );

        // Enqueue Tippy.js - Local bundle for reliability and performance
        wp_enqueue_script(
            'tippyjs',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/vendor/tippy-bundle.min.js',
            ['popperjs'],
            '6.3.7',
            true
        );

        // Enqueue custom glossary script
        wp_enqueue_script(
            'kindlinks-glossary-js',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/glossary.js',
            ['tippyjs'],
            KINDLINKS_GLOSSARY_VERSION,
            true
        );

        // Enqueue custom CSS
        wp_enqueue_style(
            'kindlinks-glossary-css',
            KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/css/glossary.css',
            [],
            KINDLINKS_GLOSSARY_VERSION
        );

        // Get settings
        $max_limit = absint(get_option('kindlinks_glossary_max_limit', 2));
        $content_selectors = get_option('kindlinks_glossary_content_selectors', '.entry-content,.breakdance-post-content');
        $underline_color = get_option('kindlinks_glossary_underline_color', '#F26C26');
        $hover_bg_color = get_option('kindlinks_glossary_hover_bg_color', '#fff3cd');
        $tooltip_keyword_color = get_option('kindlinks_glossary_tooltip_keyword_color', '#8B3A3A');
        $read_more_text = get_option('kindlinks_glossary_read_more_text', 'Xem thêm');

        // Apply filters for extensibility
        $max_limit = apply_filters('kindlinks_glossary_max_limit', $max_limit);
        $content_selectors = apply_filters('kindlinks_glossary_content_selectors', $content_selectors);

        // Localize script with glossary data
        wp_localize_script(
            'kindlinks-glossary-js',
            'KindlinksData',
            [
                'terms' => $glossary_data,
                'max_limit' => $max_limit,
                'content_selectors' => $content_selectors,
                'read_more_text' => $read_more_text,
                'colors' => [
                    'underline' => $underline_color,
                    'hover_bg' => $hover_bg_color,
                    'tooltip_keyword' => $tooltip_keyword_color,
                ],
                'ajax_url' => admin_url('admin-ajax.php'),
                'track_clicks' => apply_filters('kindlinks_glossary_track_clicks', true),
            ]
        );

        // Add inline CSS for dynamic colors
        $custom_css = "
            .kindlinks-term {
                text-decoration-color: {$underline_color} !important;
            }
            .kindlinks-term:hover {
                background-color: {$hover_bg_color} !important;
            }
            .kindlinks-tooltip-keyword {
                color: {$tooltip_keyword_color} !important;
            }
            .kindlinks-tooltip-link {
                color: {$tooltip_keyword_color} !important;
            }
        ";
        wp_add_inline_style('kindlinks-glossary-css', $custom_css);
        
        // Output debug info if WP_DEBUG is enabled
        $this->output_debug_info($debug_info, 'Glossary loaded successfully');
    }

    /**
     * Get glossary data from database.
     *
     * @return array Array of glossary terms, sorted by keyword length (descending).
     */
    private function get_glossary_data(): array {
        // Try to get cached data first
        $cached_data = get_transient('kindlinks_glossary_terms');
        
        if (false !== $cached_data && is_array($cached_data)) {
            return $cached_data;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return [];
        }

        // Fetch all terms, ordered by keyword length (longest first)
        $results = $wpdb->get_results(
            "SELECT keyword, definition, url, aliases 
            FROM {$table_name} 
            ORDER BY LENGTH(keyword) DESC",
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        // Ensure data is properly formatted
        $formatted_data = array_map(function ($term) {
            return [
                'keyword'    => $term['keyword'],
                'definition' => $term['definition'],
                'url'        => $term['url'],
                'aliases'    => $term['aliases'] ?? '',
            ];
        }, $results);

        // Cache the data for 10 minutes
        set_transient('kindlinks_glossary_terms', $formatted_data, 10 * MINUTE_IN_SECONDS);

        return $formatted_data;
    }

    /**
     * Output debug information (only when WP_DEBUG is enabled)
     *
     * @param array $debug_info Debug information
     * @param string $reason Reason for not loading
     */
    private function output_debug_info(array $debug_info, string $reason = ''): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $debug_info['reason'] = $reason;
        
        // Output as HTML comment and console log
        add_action('wp_footer', function() use ($debug_info) {
            echo "\n<!-- Kindlinks Glossary Debug -->\n";
            echo "<!-- " . esc_html(json_encode($debug_info, JSON_PRETTY_PRINT)) . " -->\n";
            echo "<script>console.log('Kindlinks Glossary Debug:', " . wp_json_encode($debug_info) . ");</script>\n";
        }, 999);
    }
}

