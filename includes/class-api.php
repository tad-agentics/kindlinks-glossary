<?php
/**
 * REST API functionality.
 *
 * @package Kindlinks_Glossary
 * @subpackage Kindlinks_Glossary/includes
 */

declare(strict_types=1);

/**
 * Handles REST API endpoints for glossary management.
 */
class Kindlinks_Glossary_API {

    /**
     * Register REST API routes.
     */
    public static function register_routes(): void {
        register_rest_route('kindlinks/v1', '/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'sync_glossary_terms'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => self::get_sync_args(),
        ]);

        register_rest_route('kindlinks/v1', '/delete', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'delete_glossary_terms'],
            'permission_callback' => [self::class, 'check_permission'],
            'args'                => [
                'keywords' => [
                    'required'          => false,
                    'type'              => 'array',
                    'description'       => __('Array of keywords to delete. If empty, deletes all terms.', 'kindlinks-glossary'),
                    'sanitize_callback' => function ($value) {
                        return array_map('sanitize_text_field', $value);
                    },
                ],
            ],
        ]);
    }

    /**
     * Check permission for API access.
     *
     * @return bool|WP_Error True if user has permission, error otherwise.
     */
    public static function check_permission() {
        // Rate limiting check
        $rate_limit_check = self::check_rate_limit();
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }

        // Option 1: Require user to be logged in with manage_options capability
        // Uncomment this for production use:
        /*
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage glossary terms.', 'kindlinks-glossary'),
                ['status' => 403]
            );
        }
        return true;
        */

        // Option 2: API Key authentication (more flexible for external services)
        // Uncomment and set up API key authentication:
        /*
        $api_key = get_option('kindlinks_glossary_api_key');
        $provided_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
        
        if (empty($api_key) || $provided_key !== $api_key) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid API key.', 'kindlinks-glossary'),
                ['status' => 403]
            );
        }
        return true;
        */

        // WARNING: Current setting allows public access
        // Change this in production!
        return true;
    }

    /**
     * Check rate limit for API requests.
     * 
     * Limits to 60 requests per hour per IP address.
     *
     * @return bool|WP_Error True if under limit, error otherwise.
     */
    private static function check_rate_limit() {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'kindlinks_rate_limit_' . md5($ip_address);
        
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in this hour
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        // Check if limit exceeded (60 requests per hour)
        $rate_limit = apply_filters('kindlinks_glossary_rate_limit', 60);
        
        if ($requests >= $rate_limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: %d: rate limit number */
                    __('Rate limit exceeded. Maximum %d requests per hour allowed.', 'kindlinks-glossary'),
                    $rate_limit
                ),
                ['status' => 429]
            );
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
        
        return true;
    }

    /**
     * Define arguments for the sync endpoint.
     *
     * @return array Endpoint arguments.
     */
    private static function get_sync_args(): array {
        return [
            'terms' => [
                'required'          => true,
                'type'              => 'array',
                'description'       => __('Array of glossary terms to sync.', 'kindlinks-glossary'),
                'validate_callback' => [self::class, 'validate_terms_array'],
                'sanitize_callback' => [self::class, 'sanitize_terms_array'],
            ],
        ];
    }

    /**
     * Validate the terms array structure.
     *
     * @param mixed $value The value to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $param The parameter name.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_terms_array($value, $request, $param): bool {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $term) {
            if (!is_array($term) || !isset($term['keyword']) || !isset($term['definition'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the terms array.
     *
     * @param mixed $value The value to sanitize.
     * @return array Sanitized array of terms.
     */
    public static function sanitize_terms_array($value): array {
        $sanitized = [];

        foreach ($value as $term) {
            $sanitized[] = [
                'keyword'    => sanitize_text_field($term['keyword']),
                'definition' => wp_kses_post($term['definition']),
                'url'        => isset($term['url']) ? esc_url_raw($term['url']) : '',
            ];
        }

        return $sanitized;
    }

    /**
     * Sync glossary terms endpoint callback.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function sync_glossary_terms(WP_REST_Request $request) {
        global $wpdb;

        $terms = $request->get_param('terms');
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return new WP_Error(
                'table_not_found',
                __('Glossary table does not exist. Please reactivate the plugin.', 'kindlinks-glossary'),
                ['status' => 500]
            );
        }

        $inserted = 0;
        $updated = 0;
        $errors = [];

        foreach ($terms as $term) {
            // Skip empty keywords
            if (empty(trim($term['keyword']))) {
                $errors[] = __('Skipped empty keyword.', 'kindlinks-glossary');
                continue;
            }

            // Check if keyword already exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE keyword = %s",
                    $term['keyword']
                )
            );

            if ($existing) {
                // Update existing term
                $result = $wpdb->update(
                    $table_name,
                    [
                        'definition' => $term['definition'],
                        'url'        => $term['url'],
                    ],
                    ['keyword' => $term['keyword']],
                    ['%s', '%s'],
                    ['%s']
                );

                // Note: wpdb::update returns 0 if no rows changed (values are identical)
                // This is not an error, so we check for false specifically
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors[] = sprintf(
                        /* translators: %1$s: keyword, %2$s: error message */
                        __('Failed to update keyword "%1$s": %2$s', 'kindlinks-glossary'),
                        $term['keyword'],
                        $wpdb->last_error
                    );
                }
            } else {
                // Insert new term
                $result = $wpdb->insert(
                    $table_name,
                    [
                        'keyword'    => $term['keyword'],
                        'definition' => $term['definition'],
                        'url'        => $term['url'],
                    ],
                    ['%s', '%s', '%s']
                );

                if ($result) {
                    $inserted++;
                } else {
                    $errors[] = sprintf(
                        /* translators: %1$s: keyword, %2$s: error message */
                        __('Failed to insert keyword "%1$s": %2$s', 'kindlinks-glossary'),
                        $term['keyword'],
                        $wpdb->last_error
                    );
                }
            }
        }

        // Clear the cached glossary data
        delete_transient('kindlinks_glossary_terms');

        $total = $inserted + $updated;

        $response = [
            'success'  => $total > 0 || empty($errors),
            'inserted' => $inserted,
            'updated'  => $updated,
            'total'    => $total,
            'message'  => sprintf(
                /* translators: %1$d: total synced, %2$d: inserted, %3$d: updated */
                __('Synced %1$d terms: %2$d inserted, %3$d updated.', 'kindlinks-glossary'),
                $total,
                $inserted,
                $updated
            ),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
            $response['error_count'] = count($errors);
        }

        return rest_ensure_response($response);
    }

    /**
     * Delete glossary terms endpoint callback.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function delete_glossary_terms(WP_REST_Request $request) {
        global $wpdb;

        $keywords = $request->get_param('keywords');
        $table_name = $wpdb->prefix . 'kindlinks_glossary';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return new WP_Error(
                'table_not_found',
                __('Glossary table does not exist. Please reactivate the plugin.', 'kindlinks-glossary'),
                ['status' => 500]
            );
        }

        $deleted = 0;
        $errors = [];

        // If no keywords specified, delete all terms
        if (empty($keywords)) {
            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
            
            if ($result !== false) {
                $deleted = $wpdb->num_rows;
                $message = __('All glossary terms have been deleted.', 'kindlinks-glossary');
            } else {
                return new WP_Error(
                    'delete_failed',
                    __('Failed to delete all terms.', 'kindlinks-glossary'),
                    ['status' => 500]
                );
            }
        } else {
            // Delete specific keywords
            foreach ($keywords as $keyword) {
                if (empty(trim($keyword))) {
                    continue;
                }

                $result = $wpdb->delete(
                    $table_name,
                    ['keyword' => $keyword],
                    ['%s']
                );

                if ($result) {
                    $deleted++;
                } else {
                    $errors[] = sprintf(
                        /* translators: %s: keyword */
                        __('Failed to delete keyword: %s', 'kindlinks-glossary'),
                        $keyword
                    );
                }
            }

            $message = sprintf(
                /* translators: %d: number of deleted terms */
                __('Deleted %d terms.', 'kindlinks-glossary'),
                $deleted
            );
        }

        // Clear the cached glossary data
        delete_transient('kindlinks_glossary_terms');

        $response = [
            'success' => $deleted > 0,
            'deleted' => $deleted,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
            $response['error_count'] = count($errors);
        }

        return rest_ensure_response($response);
    }
}

