<div class="wrap kindlinks-glossary-admin">
    <h1><?php esc_html_e('Glossary Settings', 'kindlinks-glossary'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('kindlinks_glossary_settings'); ?>

        <h2><?php esc_html_e('General Settings', 'kindlinks-glossary'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="max_limit"><?php esc_html_e('Max Occurrences', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="number" name="max_limit" id="max_limit" min="1" max="10" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_max_limit', 2)); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('Maximum number of times to highlight each keyword per page.', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="content_selectors"><?php esc_html_e('Content Selectors', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="content_selectors" id="content_selectors" class="regular-text" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_content_selectors', '.entry-content,.breakdance-post-content')); ?>">
                    <p class="description">
                        <?php esc_html_e('CSS selectors where glossary should run (comma-separated). Common: .entry-content, .elementor-widget-theme-post-content, .et_pb_post_content', 'kindlinks-glossary'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enabled_post_types"><?php esc_html_e('Enabled Post Types', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="enabled_post_types" id="enabled_post_types" class="regular-text" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_enabled_post_types', 'post,page')); ?>">
                    <p class="description"><?php esc_html_e('Post types to enable glossary on (comma-separated). Default: post,page', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Enabled Post Categories', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <?php
                    $enabled_categories = get_option('kindlinks_glossary_enabled_categories', []);
                    $categories = get_categories(['hide_empty' => false]);
                    
                    if (empty($categories)) {
                        echo '<p class="description">' . esc_html__('No categories found. Create some post categories first.', 'kindlinks-glossary') . '</p>';
                    } else {
                        echo '<fieldset>';
                        echo '<legend class="screen-reader-text"><span>' . esc_html__('Select categories', 'kindlinks-glossary') . '</span></legend>';
                        
                        // "All categories" option
                        $all_selected = empty($enabled_categories) || in_array('all', $enabled_categories);
                        echo '<label style="display: block; margin-bottom: 8px;">';
                        echo '<input type="checkbox" name="enabled_categories[]" value="all" ' . checked($all_selected, true, false) . '> ';
                        echo '<strong>' . esc_html__('All Categories (No Filtering)', 'kindlinks-glossary') . '</strong>';
                        echo '</label>';
                        
                        echo '<div style="margin-left: 24px; margin-top: 12px;">';
                        echo '<p class="description" style="margin-bottom: 8px;">' . esc_html__('Or select specific categories:', 'kindlinks-glossary') . '</p>';
                        
                        foreach ($categories as $category) {
                            $checked = !$all_selected && in_array($category->term_id, $enabled_categories);
                            echo '<label style="display: block; margin-bottom: 6px;">';
                            echo '<input type="checkbox" name="enabled_categories[]" value="' . esc_attr($category->term_id) . '" ' . checked($checked, true, false) . '> ';
                            echo esc_html($category->name) . ' <span style="color: #666;">(' . $category->count . ')</span>';
                            echo '</label>';
                        }
                        echo '</div>';
                        
                        echo '</fieldset>';
                        echo '<p class="description" style="margin-top: 12px;">';
                        echo esc_html__('Leave "All Categories" checked to enable on all posts, or select specific categories to enable glossary only on posts in those categories.', 'kindlinks-glossary');
                        echo '</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Appearance', 'kindlinks-glossary'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="underline_color"><?php esc_html_e('Underline Color', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="underline_color" id="underline_color" class="color-picker" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_underline_color', '#F26C26')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="hover_bg_color"><?php esc_html_e('Hover Background Color', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="hover_bg_color" id="hover_bg_color" class="color-picker" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_hover_bg_color', '#fff3cd')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tooltip_keyword_color"><?php esc_html_e('Tooltip Keyword Color', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="tooltip_keyword_color" id="tooltip_keyword_color" class="color-picker" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_tooltip_keyword_color', '#8B3A3A')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="read_more_text"><?php esc_html_e('"Read More" Link Text', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="read_more_text" id="read_more_text" class="regular-text" 
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_read_more_text', 'Xem thêm')); ?>"
                           placeholder="Xem thêm">
                    <p class="description">
                        <?php esc_html_e('Text displayed for the "Read More" link in tooltips. Examples: "Xem thêm", "Đọc thêm", "Chi tiết"', 'kindlinks-glossary'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('API Settings', 'kindlinks-glossary'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_key"><?php esc_html_e('API Key', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="api_key" id="api_key" class="regular-text" readonly
                           value="<?php echo esc_attr(get_option('kindlinks_glossary_api_key')); ?>">
                    <button type="button" class="button" id="regenerate_api_key"><?php esc_html_e('Regenerate', 'kindlinks-glossary'); ?></button>
                    <p class="description">
                        <?php esc_html_e('Use this key in X-API-Key header for REST API authentication.', 'kindlinks-glossary'); ?>
                        <br><?php esc_html_e('Endpoint: ', 'kindlinks-glossary'); ?><code><?php echo esc_url(rest_url('kindlinks/v1/sync')); ?></code>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="kindlinks_glossary_settings_submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'kindlinks-glossary'); ?>">
        </p>
    </form>
</div>

