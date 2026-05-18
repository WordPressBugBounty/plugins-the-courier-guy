<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>
<textarea id="<?= esc_attr($identifier); ?>" name="<?= esc_attr($identifier); ?>" class="widefat"
          rows="10"<?= esc_attr($readonly); ?>><?= esc_html(
            htmlentities(
                    $value
            )
    ); ?></textarea>
