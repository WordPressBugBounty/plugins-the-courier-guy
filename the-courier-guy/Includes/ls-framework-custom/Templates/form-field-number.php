<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>
<input type="text" id="<?= esc_attr($identifier); ?>" name="<?= esc_attr($identifier); ?>"
       value="<?= esc_attr(htmlentities($value)); ?>"
       class="widefat num"<?= esc_attr($readonly); ?> />
