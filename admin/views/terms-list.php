<div class="wrap kindlinks-glossary-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('Glossary Terms', 'kindlinks-glossary'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=kindlinks-glossary-add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'kindlinks-glossary'); ?>
    </a>
    <hr class="wp-header-end">

    <?php 
    // Debug: Show what we got
    if (defined('WP_DEBUG') && WP_DEBUG && isset($terms)) {
        echo '<div class="notice notice-info"><p>';
        echo 'DEBUG - Terms variable type: ' . gettype($terms) . '<br>';
        echo 'DEBUG - Terms count: ' . (is_array($terms) || is_object($terms) ? count($terms) : 'N/A') . '<br>';
        echo 'DEBUG - Is empty: ' . (empty($terms) ? 'YES' : 'NO');
        if (!empty($terms) && is_array($terms)) {
            echo '<br>DEBUG - First term: ' . print_r($terms[0], true);
        }
        echo '</p></div>';
    }
    ?>

    <?php if (empty($terms)): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('No terms found. Add your first glossary term to get started!', 'kindlinks-glossary'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Keyword', 'kindlinks-glossary'); ?></th>
                    <th><?php esc_html_e('Definition', 'kindlinks-glossary'); ?></th>
                    <th><?php esc_html_e('Category', 'kindlinks-glossary'); ?></th>
                    <th><?php esc_html_e('Clicks', 'kindlinks-glossary'); ?></th>
                    <th><?php esc_html_e('Actions', 'kindlinks-glossary'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($terms as $term): ?>
                    <tr>
                        <td><strong><?php echo esc_html($term->keyword); ?></strong></td>
                        <td><?php echo wp_kses_post(wp_trim_words($term->definition, 15)); ?></td>
                        <td><?php echo esc_html($term->category ?? 'general'); ?></td>
                        <td><?php echo esc_html($term->click_count ?? 0); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=kindlinks-glossary-add&term_id=' . $term->id)); ?>">
                                <?php esc_html_e('Edit', 'kindlinks-glossary'); ?>
                            </a> |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=kindlinks-glossary&action=delete&term_id=' . $term->id), 'kindlinks_glossary_action')); ?>" 
                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this term?', 'kindlinks-glossary'); ?>');"
                               class="delete">
                                <?php esc_html_e('Delete', 'kindlinks-glossary'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

