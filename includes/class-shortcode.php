<?php
/**
 * Shortcode functionality.
 *
 * @package Kindlinks_Glossary
 * @subpackage Kindlinks_Glossary/includes
 */

declare(strict_types=1);

/**
 * Handles shortcode rendering.
 */
class Kindlinks_Glossary_Shortcode {

    /**
     * Initialize shortcode.
     */
    public function __construct() {
        add_shortcode('glossary', [$this, 'render_shortcode']);
        add_shortcode('glossary_list', [$this, 'render_list_shortcode']);
    }

    /**
     * Render individual term shortcode.
     * 
     * Usage: [glossary keyword="WordPress"]
     * Usage: [glossary keyword="WordPress"]Custom text[/glossary]
     *
     * @param array $atts Shortcode attributes.
     * @param string|null $content Shortcode content.
     * @return string HTML output.
     */
    public function render_shortcode($atts, $content = null): string {
        $atts = shortcode_atts([
            'keyword' => '',
        ], $atts);

        if (empty($atts['keyword'])) {
            return '';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';
        
        $term = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE keyword = %s",
            $atts['keyword']
        ));

        if (!$term) {
            return $content ?? $atts['keyword'];
        }

        // Load Tippy.js if not already loaded (local bundle)
        if (!wp_script_is('popperjs', 'enqueued')) {
            wp_enqueue_script(
                'popperjs',
                KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/vendor/popper.min.js',
                [],
                '2.11.8',
                true
            );
        }
        
        if (!wp_script_is('tippyjs', 'enqueued')) {
            wp_enqueue_script(
                'tippyjs',
                KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/js/vendor/tippy-bundle.min.js',
                ['popperjs'],
                '6.3.7',
                true
            );
        }
        
        if (!wp_style_is('kindlinks-glossary-css', 'enqueued')) {
            wp_enqueue_style(
                'kindlinks-glossary-css',
                KINDLINKS_GLOSSARY_PLUGIN_URL . 'assets/css/glossary.css',
                [],
                KINDLINKS_GLOSSARY_VERSION
            );
        }

        $tooltip_content = $this->create_tooltip_content($term);
        $display_text = $content ?? $term->keyword;

        return sprintf(
            '<span class="kindlinks-term" data-tippy-content=\'%s\'>%s</span>',
            esc_attr($tooltip_content),
            esc_html($display_text)
        );
    }

    /**
     * Render glossary list shortcode.
     * 
     * Usage: [glossary_list]
     * Usage: [glossary_list category="technical" orderby="keyword" order="ASC"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_list_shortcode($atts): string {
        $atts = shortcode_atts([
            'category' => '',
            'orderby' => 'keyword',
            'order' => 'ASC',
            'columns' => 2,
        ], $atts);

        global $wpdb;
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        $where = '';
        if (!empty($atts['category'])) {
            $where = $wpdb->prepare(" WHERE category = %s", $atts['category']);
        }

        $orderby = in_array($atts['orderby'], ['keyword', 'category', 'click_count']) ? $atts['orderby'] : 'keyword';
        $order = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';

        $terms = $wpdb->get_results("SELECT * FROM {$table_name}{$where} ORDER BY {$orderby} {$order}");

        if (empty($terms)) {
            return '<p>' . esc_html__('No glossary terms found.', 'kindlinks-glossary') . '</p>';
        }

        $columns = max(1, min(4, intval($atts['columns'])));
        $column_class = 'glossary-column-' . $columns;

        $output = '<div class="kindlinks-glossary-list ' . esc_attr($column_class) . '">';
        
        foreach ($terms as $term) {
            $read_more_text = get_option('kindlinks_glossary_read_more_text', 'Xem thêm');
            $output .= sprintf(
                '<div class="glossary-term-item"><strong>%s</strong>: %s %s</div>',
                esc_html($term->keyword),
                wp_kses_post($term->definition),
                !empty($term->url) ? sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($term->url), esc_html($read_more_text)) : ''
            );
        }
        
        $output .= '</div>';

        return $output;
    }

    /**
     * Create tooltip content HTML.
     *
     * @param object $term Term data.
     * @return string HTML content.
     */
    private function create_tooltip_content($term): string {
        $read_more_text = get_option('kindlinks_glossary_read_more_text', 'Xem thêm');
        $content = '<div class="kindlinks-tooltip-content">';
        $content .= '<strong class="kindlinks-tooltip-keyword">' . esc_html($term->keyword) . '</strong>';
        $content .= '<p class="kindlinks-tooltip-definition">' . wp_kses_post($term->definition) . '</p>';
        
        if (!empty($term->url)) {
            $content .= sprintf(
                '<a href="%s" class="kindlinks-tooltip-link" target="_blank" rel="noopener noreferrer">%s →</a>',
                esc_url($term->url),
                esc_html($read_more_text)
            );
        }
        
        $content .= '</div>';
        
        return str_replace("'", '&#39;', $content);
    }
}

