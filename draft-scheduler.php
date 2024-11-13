<?php
/*
Plugin Name: Draft Scheduler Pro
Plugin URI: https://wordpress.org/plugins/draft-scheduler-pro
Description: Modern draft post scheduler with advanced timing options and category filtering
Version: 2.0.0
Author: Glokalizm
Author URI: https://glokalizm.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: draft-scheduler-pro
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DSP_VERSION', '2.0.0');
define('DSP_PLUGIN_FILE', __FILE__);
define('DSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DSP_INCLUDES_DIR', DSP_PLUGIN_DIR . 'includes/');

// Include required files
require_once DSP_INCLUDES_DIR . 'class-dsp-logger.php';

class DraftSchedulerPro {
    private $version;
    private $plugin_slug = 'draft-scheduler-pro';
    private $option_name = 'draft_scheduler_pro_settings';
    private $undo_option = 'draft_scheduler_pro_undo';
    private $logger;

    public function __construct() {
        $this->version = DSP_VERSION;

        // Initialize logger
        if (class_exists('DSP_Logger')) {
            $this->logger = DSP_Logger::get_instance();
            if ($this->logger) {
                $this->logger->init();
            }
        }

        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            
            // AJAX handlers
            add_action('wp_ajax_get_draft_posts', array($this, 'ajax_get_draft_posts'));
            add_action('wp_ajax_schedule_drafts', array($this, 'ajax_schedule_drafts'));
            add_action('wp_ajax_undo_schedule', array($this, 'ajax_undo_schedule'));
            add_action('wp_ajax_get_scheduled_posts', array($this, 'ajax_get_scheduled_posts'));
            add_action('wp_ajax_switch_language', array($this, 'ajax_switch_language'));
            
            add_action('admin_init', array($this, 'register_settings'));
        }

        // Schedule cleanup of old logs
        if (!wp_next_scheduled('dsp_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'dsp_cleanup_logs');
        }
        add_action('dsp_cleanup_logs', array($this, 'cleanup_old_logs'));
    }

    public function init() {
        do_action('draft_scheduler_pro_init');
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'draft-scheduler-pro',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
    }

    public function add_admin_menu() {
        // Add main menu item
        add_menu_page(
            __('Draft Scheduler Pro', 'draft-scheduler-pro'),
            __('Draft Scheduler', 'draft-scheduler-pro'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );

        // Add submenu items
        add_submenu_page(
            $this->plugin_slug,
            __('Schedule Posts', 'draft-scheduler-pro'),
            __('Schedule Posts', 'draft-scheduler-pro'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Activity Logs', 'draft-scheduler-pro'),
            __('Activity Logs', 'draft-scheduler-pro'),
            'manage_options',
            $this->plugin_slug . '-logs',
            array($this, 'render_logs_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (!in_array($hook, array(
            'toplevel_page_' . $this->plugin_slug,
            $this->plugin_slug . '_page_' . $this->plugin_slug . '-logs'
        ))) {
            return;
        }

        // WordPress core styles
        wp_enqueue_style('wp-components');
        wp_enqueue_style('dashicons');

        // Third-party styles
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13'
        );

        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0-rc.0'
        );

        // Third-party scripts
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array('jquery'),
            '4.6.13',
            true
        );

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0-rc.0',
            true
        );

        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            array(),
            '1.15.0',
            true
        );

        // Custom assets
        $css_version = filemtime(DSP_PLUGIN_DIR . 'assets/css/admin.css');
        $js_version = filemtime(DSP_PLUGIN_DIR . 'assets/js/admin.js');

        wp_enqueue_style(
            'draft-scheduler-pro-admin',
            DSP_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-components', 'select2', 'flatpickr'),
            $css_version
        );

        wp_enqueue_script(
            'draft-scheduler-pro-admin',
            DSP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'select2', 'flatpickr', 'sortablejs'),
            $js_version,
            true
        );

        // Localize script
        wp_localize_script('draft-scheduler-pro-admin', 'draftSchedulerPro', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('draft-scheduler-pro-nonce'),
            'strings' => $this->get_localized_strings()
        ));
    }

    private function get_localized_strings() {
        return array(
            'confirmSchedule' => __('Are you sure you want to schedule these drafts?', 'draft-scheduler-pro'),
            'confirmUndo' => __('Are you sure you want to undo the last schedule?', 'draft-scheduler-pro'),
            'success' => __('Operation completed successfully!', 'draft-scheduler-pro'),
            'error' => __('An error occurred. Please try again.', 'draft-scheduler-pro'),
            'languageSwitch' => __('Language changed. Page will reload...', 'draft-scheduler-pro'),
            'noPosts' => __('No draft posts found.', 'draft-scheduler-pro'),
            'loadingPosts' => __('Loading posts...', 'draft-scheduler-pro'),
            'dragHere' => __('Drag posts here', 'draft-scheduler-pro'),
            'previewEmpty' => __('Select posts and set a start date to see the schedule preview', 'draft-scheduler-pro'),
            'noPostsSelected' => __('Please select posts to schedule', 'draft-scheduler-pro'),
            'processing' => __('Processing...', 'draft-scheduler-pro')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include DSP_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include DSP_PLUGIN_DIR . 'templates/logs-page.php';
    }

    public function ajax_switch_language() {
        check_ajax_referer('draft-scheduler-pro-nonce', 'nonce');

        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : 'en_US';
        
        // Update user meta
        update_user_meta(get_current_user_id(), 'locale', $locale);
        
        // Update site language
        update_option('WPLANG', $locale);
        
        // Clean language cache
        wp_clean_language_cache();
        
        // Force reload text domain
        unload_textdomain('draft-scheduler-pro');
        load_plugin_textdomain(
            'draft-scheduler-pro',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );

        wp_send_json_success(array(
            'message' => __('Language switched successfully. Reloading...', 'draft-scheduler-pro')
        ));
    }

    public function ajax_get_draft_posts() {
        check_ajax_referer('draft-scheduler-pro-nonce', 'nonce');

        $filters = array(
            'post_type' => isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post',
            'categories' => isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array(),
            'authors' => isset($_POST['authors']) ? array_map('intval', $_POST['authors']) : array()
        );

        $args = array(
            'post_type' => $filters['post_type'],
            'post_status' => 'draft',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        if (!empty($filters['categories'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $filters['categories']
                )
            );
        }

        if (!empty($filters['authors'])) {
            $args['author__in'] = $filters['authors'];
        }

        $posts = get_posts($args);
        $formatted_posts = array_map(function($post) {
            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'modified' => get_the_modified_date('Y-m-d H:i:s', $post->ID),
                'categories' => wp_get_post_categories($post->ID, array('fields' => 'names'))
            );
        }, $posts);

        wp_send_json_success($formatted_posts);
    }

    public function ajax_schedule_drafts() {
        check_ajax_referer('draft-scheduler-pro-nonce', 'nonce');

        $settings = json_decode(stripslashes($_POST['settings']), true);
        
        if (!$settings) {
            $this->log_error('Invalid settings provided');
            wp_send_json_error(__('Invalid settings provided', 'draft-scheduler-pro'));
            return;
        }

        $result = $this->schedule_posts($settings);

        if (is_wp_error($result)) {
            $this->log_error($result->get_error_message(), $settings);
            wp_send_json_error($result->get_error_message());
        } else {
            $this->log_success('Posts scheduled successfully', $result);
            wp_send_json_success(array(
                'message' => sprintf(
                    __('%d posts have been scheduled successfully', 'draft-scheduler-pro'),
                    count($result['scheduled_posts'])
                )
            ));
        }
    }

    private function schedule_posts($settings) {
        $posts = $this->get_posts_to_schedule($settings);
        
        if (empty($posts)) {
            return new WP_Error('no_posts', __('No posts found to schedule', 'draft-scheduler-pro'));
        }

        $scheduled_posts = array();
        $start_date = strtotime($settings['startDate']);

        if ($settings['scheduleType'] === 'random') {
            shuffle($posts);
        }

        foreach ($posts as $index => $post) {
            $schedule_date = $this->calculate_post_date($start_date, $index, $settings);
            
            $update_args = array(
                'ID' => $post->ID,
                'post_status' => 'future',
                'post_date' => date('Y-m-d H:i:s', $schedule_date),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $schedule_date)
            );

            $result = wp_update_post($update_args);
            
            if (!is_wp_error($result)) {
                $scheduled_posts[] = $post->ID;
            }
        }

        if (empty($scheduled_posts)) {
            return new WP_Error('schedule_failed', __('Failed to schedule any posts', 'draft-scheduler-pro'));
        }

        update_option($this->undo_option, $scheduled_posts);

        return array(
            'scheduled_posts' => $scheduled_posts,
            'settings' => $settings
        );
    }

    private function get_posts_to_schedule($settings) {
        $args = array(
            'post_type' => $settings['postType'],
            'post_status' => 'draft',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post__in' => $settings['selectedPosts']
        );

        if (!empty($settings['categories'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $settings['categories']
                )
            );
        }

        return get_posts($args);
    }

    private function calculate_post_date($start_date, $index, $settings) {
        switch ($settings['scheduleType']) {
            case 'custom':
                return $this->calculate_custom_window_date($start_date, $index, $settings['timing']);
            default: // sequential or random
                return $this->calculate_interval_date($start_date, $index, $settings['timing']);
        }
    }

    private function calculate_custom_window_date($start_date, $index, $timing) {
        $posts_per_day = intval($timing['postsPerDay']);
        $day_index = floor($index / $posts_per_day);
        $post_index = $index % $posts_per_day;

        $window_start = $timing['windowStart'];
        $window_end = $timing['windowEnd'];

        list($start_hour, $start_minute) = array_map('intval', explode(':', $window_start));
        list($end_hour, $end_minute) = array_map('intval', explode(':', $window_end));

        $window_start_minutes = $start_hour * 60 + $start_minute;
        $window_end_minutes = $end_hour * 60 + $end_minute;
        
        if ($window_end_minutes <= $window_start_minutes) {
            $window_end_minutes += 1440; // 24 saat = 1440 dakika
        }

        $window_duration = $window_end_minutes - $window_start_minutes;
        $interval = $window_duration / ($posts_per_day + 1);
        
        $post_minutes = $window_start_minutes + ($interval * ($post_index + 1));
        
        $date = $start_date + ($day_index * 86400); // 86400 = 24 saat
        return $date + (floor($post_minutes / 60) * 3600) + (($post_minutes % 60) * 60);
    }

    private function calculate_interval_date($start_date, $index, $timing) {
        $interval_hours = intval($timing['intervalHours']);
        $interval_minutes = intval($timing['intervalMinutes']);
        $total_minutes = ($interval_hours * 60 + $interval_minutes) * $index;
        
        return $start_date + ($total_minutes * 60);
    }

    public function ajax_get_scheduled_posts() {
        check_ajax_referer('draft-scheduler-pro-nonce', 'nonce');

        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-d');
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d', strtotime('+30 days'));

        $args = array(
            'post_status' => 'future',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $start,
                    'before' => $end,
                    'inclusive' => true
                )
            )
        );

        $posts = get_posts($args);
        $events = array_map(function($post) {
            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'start' => get_the_date('Y-m-d\TH:i:s', $post->ID),
                'url' => get_edit_post_link($post->ID, 'raw'),
                'className' => 'scheduled-post'
            );
        }, $posts);

        wp_send_json_success($events);
    }

    public function ajax_undo_schedule() {
        check_ajax_referer('draft-scheduler-pro-nonce', 'nonce');

        $undo_list = get_option($this->undo_option);

        if (empty($undo_list)) {
            $this->log_error('No recent schedule found to undo');
            wp_send_json_error(__('No recent schedule found to undo', 'draft-scheduler-pro'));
            return;
        }

        foreach ($undo_list as $post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
        }

        $this->log_success('Schedule undone successfully', array('post_ids' => $undo_list));
        delete_option($this->undo_option);
        wp_send_json_success(__('Schedule has been undone successfully', 'draft-scheduler-pro'));
    }

    public function get_scheduled_count() {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts 
            WHERE post_status = 'future' 
            AND post_date >= %s 
            AND post_date <= %s",
            current_time('mysql'),
            date('Y-m-d H:i:s', strtotime('+30 days'))
        ));
    }

    public function get_drafts_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'draft'"
        );
    }

    public function get_next_scheduled_time() {
        global $wpdb;
        
        $next_post = $wpdb->get_var($wpdb->prepare(
            "SELECT post_date FROM $wpdb->posts 
            WHERE post_status = 'future' 
            AND post_date > %s 
            ORDER BY post_date ASC 
            LIMIT 1",
            current_time('mysql')
        ));

        if (!$next_post) {
            return __('No scheduled posts', 'draft-scheduler-pro');
        }

        $date = new DateTime($next_post);
        $now = new DateTime();
        $interval = $now->diff($date);

        if ($interval->days > 0) {
            return sprintf(__('In %d days', 'draft-scheduler-pro'), $interval->days);
        } else if ($interval->h > 0) {
            return sprintf(__('In %d hours', 'draft-scheduler-pro'), $interval->h);
        } else {
            return sprintf(__('In %d minutes', 'draft-scheduler-pro'), $interval->i);
        }
    }

    private function log_success($message, $data = array()) {
        if ($this->logger) {
            $this->logger->log('schedule_posts', $data, 'success', $message);
        }
    }

    private function log_error($message, $data = array()) {
        if ($this->logger) {
            $this->logger->log('schedule_posts', $data, 'error', $message);
        }
    }

    public function cleanup_old_logs() {
        if ($this->logger) {
            $this->logger->clean_old_logs(30); // 30 günden eski logları temizle
        }
    }
}

// Initialize the plugin
function draft_scheduler_pro_init() {
    global $draft_scheduler_pro;
    $draft_scheduler_pro = new DraftSchedulerPro();
}

add_action('plugins_loaded', 'draft_scheduler_pro_init');