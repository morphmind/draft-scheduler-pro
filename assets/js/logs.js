jQuery(document).ready(function($) {
    // Initialize Flatpickr for date inputs
    $('.flatpickr').flatpickr({
        dateFormat: "Y-m-d",
        maxDate: "today"
    });

    // Log Details Modal Handling
    function initLogDetailsModal() {
        const $modal = $('#log-details-modal');
        const $close = $('.dsp-modal-close');
        const $content = $('.log-details-content');

        // Close modal on X click
        $close.on('click', function() {
            $modal.fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).is($modal)) {
                $modal.fadeOut();
            }
        });

        // View Details button click
        $('.view-details').on('click', function() {
            const logId = $(this).data('log-id');
            showLogDetails(logId);
        });

        function showLogDetails(logId) {
            $modal.fadeIn();
            $content.html('<div class="loading"><span class="spinner is-active"></span> Loading...</div>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_log_details',
                    nonce: draftSchedulerPro.nonce,
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        renderLogDetails(response.data);
                    } else {
                        $content.html(`<div class="error">${response.data}</div>`);
                    }
                },
                error: function() {
                    $content.html('<div class="error">Failed to load log details.</div>');
                }
            });
        }

        function renderLogDetails(data) {
            const postLinks = data.post_ids.map(post => 
                `<a href="${post.edit_url}" target="_blank">${post.title}</a>`
            ).join(', ');

            const scheduleSettings = JSON.parse(data.schedule_settings);
            let settingsHtml = '<ul class="settings-list">';
            
            for (const [key, value] of Object.entries(scheduleSettings)) {
                settingsHtml += `<li><strong>${formatKey(key)}:</strong> ${formatValue(value)}</li>`;
            }
            settingsHtml += '</ul>';

            const html = `
                <div class="log-details">
                    <div class="detail-group">
                        <h3>Basic Information</h3>
                        <p><strong>Date:</strong> ${data.formatted_date}</p>
                        <p><strong>User:</strong> ${data.user_name}</p>
                        <p><strong>Action:</strong> ${data.action_label}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${data.status}">${data.status}</span></p>
                    </div>

                    <div class="detail-group">
                        <h3>Affected Posts</h3>
                        <div class="posts-list">${postLinks}</div>
                    </div>

                    <div class="detail-group">
                        <h3>Schedule Settings</h3>
                        ${settingsHtml}
                    </div>

                    ${data.message ? `
                    <div class="detail-group">
                        <h3>Additional Information</h3>
                        <div class="message-box ${data.status}">${data.message}</div>
                    </div>
                    ` : ''}
                </div>
            `;

            $content.html(html);
        }
    }

    // Helper Functions
    function formatKey(key) {
        return key.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    function formatValue(value) {
        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }
        if (Array.isArray(value)) {
            return value.join(', ');
        }
        if (typeof value === 'object') {
            return JSON.stringify(value, null, 2);
        }
        return value;
    }

    // Export Functionality
    $('.export-logs').on('click', function(e) {
        e.preventDefault();
        const filters = $('.logs-filter-form').serialize();
        window.location.href = `${ajaxurl}?action=export_logs&${filters}&nonce=${draftSchedulerPro.nonce}`;
    });

    // Real-time Log Updates
    let logUpdateInterval;
    
    function startLogUpdates() {
        logUpdateInterval = setInterval(updateLatestLogs, 30000); // 30 seconds
    }

    function stopLogUpdates() {
        clearInterval(logUpdateInterval);
    }

    function updateLatestLogs() {
        const lastLogId = $('.wp-list-table tr:first-child').data('log-id');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'get_new_logs',
                nonce: draftSchedulerPro.nonce,
                last_id: lastLogId,
                filters: $('.logs-filter-form').serialize()
            },
            success: function(response) {
                if (response.success && response.data.logs.length > 0) {
                    insertNewLogs(response.data.logs);
                    updateStats(response.data.stats);
                }
            }
        });
    }

    function insertNewLogs(logs) {
        logs.reverse().forEach(log => {
            const newRow = createLogRow(log);
            $('.wp-list-table tbody').prepend(newRow);
            newRow.addClass('highlight-new').delay(2000).queue(function(next) {
                $(this).removeClass('highlight-new');
                next();
            });
        });
    }

    function createLogRow(log) {
        return $(`
            <tr data-log-id="${log.id}">
                <td>${log.formatted_date}</td>
                <td>${log.user_name}</td>
                <td>${log.action_label}</td>
                <td>${log.schedule_type}</td>
                <td><span class="log-status status-${log.status}">${log.status}</span></td>
                <td>
                    <button type="button" class="button button-small view-details" data-log-id="${log.id}">
                        View Details
                    </button>
                </td>
            </tr>
        `);
    }

    function updateStats(stats) {
        Object.entries(stats).forEach(([key, value]) => {
            $(`.stat-value[data-stat="${key}"]`).text(value);
        });
    }

    // Initialize components
    initLogDetailsModal();
    
    // Start real-time updates if we're on the logs page
    if ($('.wp-list-table').length) {
        startLogUpdates();
    }

    // Stop updates when leaving the page
    $(window).on('unload', stopLogUpdates);
});