<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap draft-scheduler-pro">
    <!-- Header -->
    <div class="dsp-header">
        <h1 class="wp-heading-inline"><?php _e('Draft Scheduler Pro', 'draft-scheduler-pro'); ?></h1>
        <div class="header-actions">
            <div class="language-switcher">
                <select id="language-switch" class="modern-select">
                    <option value="en_US" <?php selected(get_locale(), 'en_US'); ?>>English</option>
                    <option value="tr_TR" <?php selected(get_locale(), 'tr_TR'); ?>>Türkçe</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Stats Dashboard -->
    <div class="dsp-dashboard">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Draft Posts', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value" data-stat="drafts">
                        <?php echo $this->get_drafts_count(); ?>
                    </div>
                    <div class="stat-label"><?php _e('Available', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Scheduled', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value" data-stat="scheduled">
                        <?php echo $this->get_scheduled_count(); ?>
                    </div>
                    <div class="stat-label"><?php _e('Next 30 days', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php _e('Next Publication', 'draft-scheduler-pro'); ?></h3>
                    <div class="stat-value" data-stat="next">
                        <?php echo $this->get_next_scheduled_time(); ?>
                    </div>
                    <div class="stat-label"><?php _e('Time until next post', 'draft-scheduler-pro'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="dsp-tabs">
        <nav class="nav-tab-wrapper">
            <button class="nav-tab nav-tab-active" data-tab="schedule">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php _e('Schedule Posts', 'draft-scheduler-pro'); ?>
            </button>
            <button class="nav-tab" data-tab="calendar">
                <span class="dashicons dashicons-calendar"></span>
                <?php _e('Calendar View', 'draft-scheduler-pro'); ?>
            </button>
            <button class="nav-tab" data-tab="history">
                <span class="dashicons dashicons-backup"></span>
                <?php _e('History', 'draft-scheduler-pro'); ?>
            </button>
        </nav>

        <!-- Schedule Tab -->
        <div id="schedule" class="tab-content active">
            <div class="modern-card">
                <form id="draft-scheduler-form" class="draft-scheduler-form">
                    <!-- Post Selection Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2><?php _e('Select Posts', 'draft-scheduler-pro'); ?></h2>
                        </div>

                        <!-- Post Count Selection -->
                        <div class="post-count-selection">
                            <div class="count-options">
                                <label class="radio-card">
                                    <input type="radio" name="post_count_type" value="all" checked>
                                    <span class="radio-content">
                                        <span class="radio-icon">
                                            <span class="dashicons dashicons-admin-post"></span>
                                        </span>
                                        <span class="radio-label"><?php _e('All Drafts', 'draft-scheduler-pro'); ?></span>
                                    </span>
                                </label>

                                <label class="radio-card">
                                    <input type="radio" name="post_count_type" value="custom">
                                    <span class="radio-content">
                                        <span class="radio-icon">
                                            <span class="dashicons dashicons-edit"></span>
                                        </span>
                                        <span class="radio-label"><?php _e('Custom Count', 'draft-scheduler-pro'); ?></span>
                                    </span>
                                </label>
                            </div>

                            <div id="custom-count-input" class="custom-count-input" style="display: none;">
                                <input type="number" name="custom_post_count" min="1" class="modern-input" 
                                       placeholder="<?php _e('Enter number of posts', 'draft-scheduler-pro'); ?>">
                            </div>
                        </div>

                        <!-- Posts Container -->
                        <div class="posts-container">
                            <div class="post-list">
                                <h3><?php _e('Available Drafts', 'draft-scheduler-pro'); ?></h3>
                                <div id="draft-posts" class="draggable-list">
                                    <div class="loading-state">
                                        <span class="spinner is-active"></span>
                                        <p><?php _e('Loading posts...', 'draft-scheduler-pro'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="post-list">
                                <h3><?php _e('Selected Posts', 'draft-scheduler-pro'); ?></h3>
                                <div id="selected-draft-posts" class="draggable-list">
                                    <div class="empty-state">
                                        <span class="dashicons dashicons-move"></span>
                                        <p><?php _e('Drag posts here', 'draft-scheduler-pro'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Post Filters -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2><?php _e('Filters', 'draft-scheduler-pro'); ?></h2>
                        </div>

                        <div class="filters-grid">
                            <div class="filter-group">
                                <label for="post-type">
                                    <span class="dashicons dashicons-admin-post"></span>
                                    <?php _e('Post Type', 'draft-scheduler-pro'); ?>
                                </label>
                                <select id="post-type" name="post_type" class="modern-select">
                                    <?php 
                                    $post_types = get_post_types(array('public' => true), 'objects');
                                    foreach ($post_types as $post_type) {
                                        if ($post_type->name !== 'attachment') {
                                            echo sprintf(
                                                '<option value="%s">%s</option>',
                                                esc_attr($post_type->name),
                                                esc_html($post_type->labels->name)
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="categories">
                                    <span class="dashicons dashicons-category"></span>
                                    <?php _e('Categories', 'draft-scheduler-pro'); ?>
                                </label>
                                <select id="categories" name="categories[]" multiple class="modern-select">
                                    <?php 
                                    $categories = get_categories(array('hide_empty' => false));
                                    foreach ($categories as $category) {
                                        echo sprintf(
                                            '<option value="%d">%s</option>',
                                            $category->term_id,
                                            esc_html($category->name)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="authors">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e('Authors', 'draft-scheduler-pro'); ?>
                                </label>
                                <select id="authors" name="authors[]" multiple class="modern-select">
                                    <?php 
                                    $authors = get_users(array('who' => 'authors'));
                                    foreach ($authors as $author) {
                                        echo sprintf(
                                            '<option value="%d">%s</option>',
                                            $author->ID,
                                            esc_html($author->display_name)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Settings -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2><?php _e('Schedule Settings', 'draft-scheduler-pro'); ?></h2>
                        </div>

                        <div class="schedule-options">
                            <!-- Start Date -->
                            <div class="date-time-picker">
                                <label for="start-date">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php _e('Start Date & Time', 'draft-scheduler-pro'); ?>
                                </label>
                                <input type="text" id="start-date" name="start_date" 
                                       class="modern-input flatpickr" data-min-date="today" required>
                            </div>

                            <!-- Schedule Types -->
                            <div class="schedule-types">
                                <label class="schedule-card">
                                    <input type="radio" name="schedule_type" value="sequential" checked>
                                    <div class="card-content">
                                        <span class="card-icon">📅</span>
                                        <h3><?php _e('Sequential', 'draft-scheduler-pro'); ?></h3>
                                        <p><?php _e('Schedule posts in order', 'draft-scheduler-pro'); ?></p>
                                    </div>
                                </label>

                                <label class="schedule-card">
                                    <input type="radio" name="schedule_type" value="random">
                                    <div class="card-content">
                                        <span class="card-icon">🎲</span>
                                        <h3><?php _e('Random', 'draft-scheduler-pro'); ?></h3>
                                        <p><?php _e('Schedule in random order', 'draft-scheduler-pro'); ?></p>
                                    </div>
                                </label>

                                <label class="schedule-card">
                                    <input type="radio" name="schedule_type" value="custom">
                                    <div class="card-content">
                                        <span class="card-icon">⚡</span>
                                        <h3><?php _e('Custom Window', 'draft-scheduler-pro'); ?></h3>
                                        <p><?php _e('Set specific time windows', 'draft-scheduler-pro'); ?></p>
                                    </div>
                                </label>
                            </div>

                            <!-- Timing Options -->
                            <div id="timing-options" class="timing-options">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Preview -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2><?php _e('Schedule Preview', 'draft-scheduler-pro'); ?></h2>
                        </div>
                        <div id="schedule-preview" class="schedule-preview">
                            <div class="empty-state">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <p><?php _e('Select posts and set a start date to see the schedule preview', 'draft-scheduler-pro'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <?php if (get_option('draft_scheduler_pro_undo')): ?>
                            <button type="button" id="undo-schedule" class="modern-button secondary">
                                <span class="dashicons dashicons-undo"></span>
                                <?php _e('Undo Last Schedule', 'draft-scheduler-pro'); ?>
                            </button>
                        <?php endif; ?>

                        <button type="submit" class="modern-button primary">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Schedule Posts', 'draft-scheduler-pro'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Calendar Tab -->
        <div id="calendar" class="tab-content">
            <div class="modern-card">
                <div id="calendar-view"></div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history" class="tab-content">
            <div class="modern-card">
                <div id="scheduling-history"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>
