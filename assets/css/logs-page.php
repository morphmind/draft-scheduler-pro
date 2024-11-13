<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap draft-scheduler-pro">
    <h1 class="wp-heading-inline">
        <?php _e('Scheduler Activity Logs', 'draft-scheduler-pro'); ?>
    </h1>

    <!-- Stats Dashboard -->
    <div class="dsp-dashboard">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Success Rate', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value"><?php echo $stats['success_rate']; ?>%</div>
                    <div class="stat-label"><?php _e('Overall', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Total Scheduled', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value"><?php echo $stats['total_scheduled']; ?></div>
                    <div class="stat-label"><?php _e('Posts scheduled', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Errors', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value"><?php echo $stats['total_errors']; ?></div>
                    <div class="stat-label"><?php _e('Total errors', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Recent Activity', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value"><?php echo $stats['recent_activity']; ?></div>
                    <div class="stat-label"><?php _e('Last 24 hours', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dsp-filters">
        <form method="get" class="logs-filter-form">
            <input type="hidden" name="page" value="draft-scheduler-logs">
            
            <div class="filter-grid">
                <div class="filter-item">
                    <label for="log_action"><?php _e('Action', 'draft-scheduler-pro'); ?></label>
                    <select name="log_action" id="log_action">
                        <option value=""><?php _e('All Actions', 'draft-scheduler-pro'); ?></option>
                        <?php foreach ($this->get_log_actions() as $action): ?>
                            <option value="<?php echo esc_attr($action); ?>" <?php selected($filters['action'], $action); ?>>
                                <?php echo esc_html($this->get_action_label($action)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="log_status"><?php _e('Status', 'draft-scheduler-pro'); ?></label>
                    <select name="log_status" id="log_status">
                        <option value=""><?php _e('All Statuses', 'draft-scheduler-pro'); ?></option>
                        <option value="success" <?php selected($filters['status'], 'success'); ?>>
                            <?php _e('Success', 'draft-scheduler-pro'); ?>
                        </option>
                        <option value="error" <?php selected($filters['status'], 'error'); ?>>
                            <?php _e('Error', 'draft-scheduler-pro'); ?>
                        </option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="user_id"><?php _e('User', 'draft-scheduler-pro'); ?></label>
                    <select name="user_id" id="user_id">
                        <option value=""><?php _e('All Users', 'draft-scheduler-pro'); ?></option>
                        <?php foreach ($this->get_log_users() as $user): ?>
                            <option value="<?php echo esc_attr($user['id']); ?>" <?php selected($filters['user_id'], $user['id']); ?>>
                                <?php echo esc_html($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="date_from"><?php _e('Date From', 'draft-scheduler-pro'); ?></label>
                    <input type="text" name="date_from" id="date_from" class="flatpickr" 
                           value="<?php echo esc_attr($filters['date_from']); ?>">
                </div>

                <div class="filter-item">
                    <label for="date_to"><?php _e('Date To', 'draft-scheduler-pro'); ?></label>
                    <input type="text" name="date_to" id="date_to" class="flatpickr" 
                           value="<?php echo esc_attr($filters['date_to']); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e('Apply Filters', 'draft-scheduler-pro'); ?>
                    </button>
                    <a href="<?php echo admin_url('edit.php?page=draft-scheduler-logs'); ?>" class="button">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Reset', 'draft-scheduler-pro'); ?>
                    </a>
                    <a href="<?php echo add_query_arg('action', 'export_logs'); ?>" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export CSV', 'draft-scheduler-pro'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="dsp-logs-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-date"><?php _e('Date', 'draft-scheduler-pro'); ?></th>
                    <th scope="col" class="column-user"><?php _e('User', 'draft-scheduler-pro'); ?></th>
                    <th scope="col" class="column-action"><?php _e('Action', 'draft-scheduler-pro'); ?></th>
                    <th scope="col" class="column-type"><?php _e('Schedule Type', 'draft-scheduler-pro'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'draft-scheduler-pro'); ?></th>
                    <th scope="col" class="column-details"><?php _e('Details', 'draft-scheduler-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs['items'])): ?>
                    <?php foreach ($logs['items'] as $log): ?>
                        <tr data-log-id="<?php echo esc_attr($log->id); ?>">
                            <td>
                                <?php echo esc_html(
                                    wp_date(
                                        get_option('date_format') . ' ' . get_option('time_format'), 
                                        strtotime($log->created_at)
                                    )
                                ); ?>
                            </td>
                            <td>
                                <?php 
                                $user = get_userdata($log->user_id);
                                echo $user ? esc_html($user->display_name) : __('Unknown', 'draft-scheduler-pro');
                                ?>
                            </td>
                            <td><?php echo esc_html($this->get_action_label($log->action)); ?></td>
                            <td><?php echo esc_html($log->schedule_type); ?></td>
                            <td>
                                <span class="log-status status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo esc_html(ucfirst($log->status)); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small view-details" 
                                        data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php _e('View Details', 'draft-scheduler-pro'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">📝</div>
                                <h3><?php _e('No Logs Found', 'draft-scheduler-pro'); ?></h3>
                                <p><?php _e('No activity logs match your current filters.', 'draft-scheduler-pro'); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($logs['pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $logs['pages'],
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="log-details-modal" class="dsp-modal">
    <div class="dsp-modal-content">
        <span class="dsp-modal-close">&times;</span>
        <h2><?php _e('Log Details', 'draft-scheduler-pro'); ?></h2>
        <div class="log-details-content">
            <!-- Will be populated by JavaScript -->
            <div class="loading">
                <span class="spinner is-active"></span>
                <?php _e('Loading...', 'draft-scheduler-pro'); ?>
            </div>
        </div>
    </div>
</div>