<?php
/**
 * Fired during plugin activation.
 *
 * @package Kindlinks_Glossary
 * @subpackage Kindlinks_Glossary/includes
 */

declare(strict_types=1);

/**
 * Handles plugin activation tasks.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Kindlinks_Glossary_Activator {

    /**
     * Activate the plugin.
     *
     * Creates the custom database table for storing glossary terms.
     */
    public static function activate(): void {
        self::create_glossary_table();
    }

    /**
     * Create the custom glossary table.
     *
     * Uses dbDelta to ensure proper table creation/update.
     */
    private static function create_glossary_table(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kindlinks_glossary';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            definition text NOT NULL,
            url varchar(255) DEFAULT '' NOT NULL,
            category varchar(100) DEFAULT 'general' NOT NULL,
            click_count int DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY keyword (keyword),
            KEY category (category),
            KEY keyword_length ((LENGTH(keyword)))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create default options
        $defaults = [
            'kindlinks_glossary_db_version' => KINDLINKS_GLOSSARY_VERSION,
            'kindlinks_glossary_max_limit' => 2,
            'kindlinks_glossary_content_selectors' => '.entry-content,.breakdance-post-content',
            'kindlinks_glossary_underline_color' => '#F26C26',
            'kindlinks_glossary_hover_bg_color' => '#fff3cd',
            'kindlinks_glossary_tooltip_keyword_color' => '#8B3A3A',
            'kindlinks_glossary_enabled_post_types' => 'post,page',
            'kindlinks_glossary_enabled_categories' => ['all'],
            'kindlinks_glossary_api_key' => wp_generate_password(32, false),
        ];

        foreach ($defaults as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
    }
}

