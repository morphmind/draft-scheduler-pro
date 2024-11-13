<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSP_Logger {
    /**
     * Class instance.
     *
     * @var DSP_Logger
     */
    private static $instance = null;

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dsp_activity_logs';
        
        // Create table if not exists
        $this->create_tables();
    }

    /**
     * Get singleton instance.
     *
     * @return DSP_Logger
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance.
     */
    private function __wakeup() {}

    /**
     * Initialize the logger.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_logs_page'));
    }

    /**
     * Create required database tables.
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            post_ids text,
            schedule_type varchar(20),
            schedule_settings text,
            status varchar(20) NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add logs admin page.
     */
    public function add_logs_page() {
        add_submenu_page(
            'edit.php',
            __('Scheduler Logs', 'draft-scheduler-pro'),
            __('Scheduler Logs', 'draft-scheduler-pro'),
            'manage_options',
            'draft-scheduler-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Log an action.
     *
     * @param string $action Action name
     * @param array $data Additional data
     * @param string $status Status (success/error)
     * @param string $message Optional message
     * @return bool|int
     */
    public function log($action, $data = array(), $status = 'success', $message = '') {
        global $wpdb;
        
        $log_data = array(
            'user_id' => get_current_user_id(),
            'action' => $action,
            'post_ids' => is_array($data['post_ids']) ? json_encode($data['post_ids']) : 
                         (isset($data['post_ids']) ? $data['post_ids'] : ''),
            'schedule_type' => isset($data['schedule_type']) ? $data['schedule_type'] : '',
            'schedule_settings' => isset($data['settings']) ? json_encode($data['settings']) : '',
            'status' => $status,
            'message' => $message,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($this->table_name, $log_data);
        
        if ($result === false) {
            error_log('Draft Scheduler Pro: Failed to insert log entry - ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Get logs with filters.
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'action' => '',
            'status' => '',
            'user_id' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $values[] = $args['action'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = $args['per_page'];
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
        $total = $wpdb->get_var($wpdb->prepare($total_query, $values));
        
        // Get logs
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE {$where} 
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d",
            array_merge($values, array($limit, $offset))
        );
        
        return array(
            'items' => $wpdb->get_results($query),
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }

    /**
     * Clean old logs.
     *
     * @param int $days Number of days to keep logs
     * @return int|false Number of rows deleted or false on error
     */
    public function clean_old_logs($days = 30) {
        global $wpdb;
        
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $date
        ));
    }

    /**
     * Get log statistics.
     *
     * @return array
     */
    public function get_log_stats() {
        global $wpdb;
        
        return array(
            'total_logs' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"),
            'success_count' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'success'"),
            'error_count' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'error'"),
            'recent_count' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ))
        );
    }
}
