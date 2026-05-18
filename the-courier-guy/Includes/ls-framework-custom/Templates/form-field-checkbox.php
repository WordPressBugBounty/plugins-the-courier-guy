<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
$checked = (!empty($value) ? ' checked' : '');
?>
<input type="checkbox" id="<?= esc_attr($identifier); ?>" name="<?= esc_attr($identifier); ?>"<?= esc_attr(
        $checked
); ?><?= esc_attr($readonly); ?> />
