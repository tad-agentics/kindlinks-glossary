<div class="wrap">
    <h1><?php esc_html_e('Import/Export Glossary Terms', 'kindlinks-glossary'); ?></h1>

    <div class="card">
        <h2><?php esc_html_e('Export Terms', 'kindlinks-glossary'); ?></h2>
        <p><?php esc_html_e('Download all glossary terms as a JSON file.', 'kindlinks-glossary'); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('kindlinks_glossary_import_export'); ?>
            <p>
                <input type="submit" name="kindlinks_glossary_export" class="button button-primary" 
                       value="<?php esc_attr_e('Export Terms', 'kindlinks-glossary'); ?>">
            </p>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e('Import Terms', 'kindlinks-glossary'); ?></h2>
        <p><?php esc_html_e('Upload a JSON file to import glossary terms. This will merge with existing terms (duplicate keywords will be updated).', 'kindlinks-glossary'); ?></p>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('kindlinks_glossary_import_export'); ?>
            <p>
                <input type="file" name="import_file" accept=".json" required>
            </p>
            <p>
                <input type="submit" name="kindlinks_glossary_import" class="button button-primary" 
                       value="<?php esc_attr_e('Import Terms', 'kindlinks-glossary'); ?>">
            </p>
        </form>
    </div>

    <div class="card">
        <h2><?php esc_html_e('JSON Format', 'kindlinks-glossary'); ?></h2>
        <p><?php esc_html_e('Your import file should follow this format:', 'kindlinks-glossary'); ?></p>
        <pre><code>[
  {
    "keyword": "WordPress",
    "definition": "A content management system",
    "url": "https://wordpress.org",
    "category": "technical"
  },
  {
    "keyword": "Plugin",
    "definition": "An extension for WordPress",
    "url": "",
    "category": "technical"
  }
]</code></pre>
    </div>
</div>

