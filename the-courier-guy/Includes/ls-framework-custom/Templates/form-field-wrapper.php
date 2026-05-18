<?php

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly
?>
<p>
    <label><?= esc_attr($properties['display_name'] . ':'); ?></label>
    <?php
    include($formFieldTemplateFile);
    ?>
</p>
