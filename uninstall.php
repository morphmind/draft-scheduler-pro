<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('draft_scheduler_pro_settings');
delete_option('draft_scheduler_pro_undo');
