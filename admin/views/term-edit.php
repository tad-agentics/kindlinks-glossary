<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__('Edit Term', 'kindlinks-glossary') : esc_html__('Add New Term', 'kindlinks-glossary'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('kindlinks_glossary_term'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="keyword"><?php esc_html_e('Keyword', 'kindlinks-glossary'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="keyword" id="keyword" class="regular-text" 
                           value="<?php echo $term ? esc_attr($term->keyword) : ''; ?>" required>
                    <p class="description"><?php esc_html_e('The word or phrase to highlight (case insensitive).', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="definition"><?php esc_html_e('Definition', 'kindlinks-glossary'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <?php 
                    wp_editor(
                        $term ? $term->definition : '',
                        'definition',
                        [
                            'textarea_name' => 'definition',
                            'textarea_rows' => 5,
                            'media_buttons' => false,
                            'teeny' => true,
                        ]
                    ); 
                    ?>
                    <p class="description"><?php esc_html_e('The tooltip content. HTML is allowed.', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="url"><?php esc_html_e('Read More URL', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="url" name="url" id="url" class="regular-text" 
                           value="<?php echo $term ? esc_url($term->url) : ''; ?>">
                    <p class="description"><?php esc_html_e('Optional link to more information.', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="category"><?php esc_html_e('Category', 'kindlinks-glossary'); ?></label>
                </th>
                <td>
                    <input type="text" name="category" id="category" class="regular-text" 
                           value="<?php echo $term ? esc_attr($term->category ?? 'general') : 'general'; ?>">
                    <p class="description"><?php esc_html_e('Category for organizing terms (e.g., "technical", "business").', 'kindlinks-glossary'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="kindlinks_glossary_submit" class="button button-primary" 
                   value="<?php echo $is_edit ? esc_attr__('Update Term', 'kindlinks-glossary') : esc_attr__('Add Term', 'kindlinks-glossary'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=kindlinks-glossary')); ?>" class="button">
                <?php esc_html_e('Cancel', 'kindlinks-glossary'); ?>
            </a>
        </p>
    </form>
</div>

