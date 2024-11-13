(function($) {
    'use strict';

    // Toast Notification System
    const Toast = {
        types: {
            SUCCESS: 'success',
            ERROR: 'error',
            WARNING: 'warning',
            INFO: 'info'
        },
        
        show: function(message, type = 'info', duration = 3000) {
            const toast = $(`
                <div class="toast toast-${type}">
                    <span class="dashicons ${this.getIcon(type)}"></span>
                    <div class="toast-content">${message}</div>
                    <button class="toast-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `).appendTo('#toast-container');

            setTimeout(() => {
                toast.fadeOut(() => toast.remove());
            }, duration);

            toast.find('.toast-close').on('click', function() {
                toast.fadeOut(() => toast.remove());
            });
        },

        getIcon: function(type) {
            const icons = {
                success: 'dashicons-yes',
                error: 'dashicons-no',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };
            return icons[type] || icons.info;
        }
    };

    // Initialize Components
    function initComponents() {
        // Select2
        $('.modern-select').select2({
            theme: 'default',
            width: '100%',
            dropdownParent: $('.draft-scheduler-pro'),
            minimumResultsForSearch: 10
        });

        // Flatpickr
        $('.flatpickr').flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true
        });

        // Initialize tabs
        initTabs();

        // Load draft posts
        loadDraftPosts();

        // Initialize drag and drop
        initDragAndDrop();

        // Bind events
        bindEvents();
    }

    // Tab Navigation
    function initTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const target = $(this).data('tab');
            
            // Update active states
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show content
            $('.tab-content').hide().removeClass('active');
            $(`#${target}`).fadeIn().addClass('active');

            // Initialize calendar if needed
            if (target === 'calendar') {
                initCalendarView();
            }
        });
    }

    // Language Switcher
    function initLanguageSwitcher() {
        $('#language-switch').on('change', function() {
            const newLocale = $(this).val();
            
            $.ajax({
                url: draftSchedulerPro.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'switch_language',
                    nonce: draftSchedulerPro.nonce,
                    locale: newLocale
                },
                success: function(response) {
                    if (response.success) {
                        Toast.show(draftSchedulerPro.strings.languageSwitch, Toast.types.INFO);
                        setTimeout(() => window.location.reload(), 1500);
                    }
                }
            });
        });
    }

    // Post Count Selection
    function initPostCountSelection() {
        $('input[name="post_count_type"]').on('change', function() {
            const isCustom = $(this).val() === 'custom';
            $('#custom-count-input').slideToggle(isCustom);
            if (!isCustom) {
                $('#custom-count-input input').val('');
            }
            updateSchedulePreview();
        });
    }

    // Draft Posts Loading
    function loadDraftPosts() {
        const $container = $('#draft-posts');
        const filters = {
            post_type: $('#post-type').val(),
            categories: $('#categories').val(),
            authors: $('#authors').val()
        };

        $container.html(`
            <div class="loading-state">
                <span class="spinner is-active"></span>
                <p>${draftSchedulerPro.strings.loadingPosts}</p>
            </div>
        `);

        $.ajax({
            url: draftSchedulerPro.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_draft_posts',
                nonce: draftSchedulerPro.nonce,
                ...filters
            },
            success: function(response) {
                if (response.success) {
                    renderDraftPosts(response.data);
                } else {
                    Toast.show(response.data, Toast.types.ERROR);
                    showEmptyState($container, draftSchedulerPro.strings.error);
                }
            },
            error: function() {
                Toast.show(draftSchedulerPro.strings.error, Toast.types.ERROR);
                showEmptyState($container, draftSchedulerPro.strings.error);
            }
        });
    }

    function renderDraftPosts(posts) {
        const $container = $('#draft-posts');
        $container.empty();

        if (posts.length === 0) {
            showEmptyState($container, draftSchedulerPro.strings.noPosts);
            return;
        }

        posts.forEach(post => {
            $container.append(`
                <div class="draggable-item" data-post-id="${post.id}">
                    <div class="post-title">${post.title}</div>
                    <div class="post-meta">
                        <span class="author">${post.author}</span>
                        <span class="modified">${post.modified}</span>
                    </div>
                </div>
            `);
        });
    }

    // Drag and Drop
    function initDragAndDrop() {
        new Sortable(document.getElementById('draft-posts'), {
            group: {
                name: 'posts',
                pull: 'clone',
                put: false
            },
            sort: false,
            animation: 150,
            onClone: function(evt) {
                $(evt.clone).addClass('dragging');
            }
        });

        new Sortable(document.getElementById('selected-draft-posts'), {
            group: 'posts',
            animation: 150,
            onAdd: function() {
                updateSchedulePreview();
                checkEmptyState();
            },
            onRemove: function() {
                updateSchedulePreview();
                checkEmptyState();
            }
        });
    }

    function checkEmptyState() {
        const $selectedContainer = $('#selected-draft-posts');
        const hasItems = $selectedContainer.children('.draggable-item').length > 0;

        if (!hasItems) {
            showEmptyState($selectedContainer, draftSchedulerPro.strings.dragHere);
        } else {
            $selectedContainer.find('.empty-state').remove();
        }
    }

    function showEmptyState($container, message) {
        $container.html(`
            <div class="empty-state">
                <span class="dashicons dashicons-move"></span>
                <p>${message}</p>
            </div>
        `);
    }

    // Schedule Settings
    function initScheduleSettings() {
        $('input[name="schedule_type"]').on('change', function() {
            updateTimingOptions($(this).val());
            updateSchedulePreview();
        });

        // Initialize with default option
        updateTimingOptions($('input[name="schedule_type"]:checked').val());
    }

    function updateTimingOptions(scheduleType) {
        const $timingOptions = $('#timing-options');
        let html = '';

        switch(scheduleType) {
            case 'sequential':
            case 'random':
                html = `
                    <div class="interval-settings">
                        <h3>${scheduleType === 'sequential' ? 'Sequential' : 'Random'} Interval</h3>
                        <div class="interval-inputs">
                            <div class="input-group">
                                <label>Hours</label>
                                <input type="number" name="interval_hours" min="0" max="23" value="1" class="modern-input" />
                            </div>
                            <div class="input-group">
                                <label>Minutes</label>
                                <input type="number" name="interval_minutes" min="0" max="59" value="0" class="modern-input" />
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'custom':
                html = `
                    <div class="custom-window-settings">
                        <h3>Daily Time Window</h3>
                        <div class="time-window-inputs">
                            <div class="input-group">
                                <label>Start Time</label>
                                <input type="text" name="window_start" class="modern-input flatpickr" data-enable-time="true" data-no-calendar="true" data-time_24hr="true" />
                            </div>
                            <div class="input-group">
                                <label>End Time</label>
                                <input type="text" name="window_end" class="modern-input flatpickr" data-enable-time="true" data-no-calendar="true" data-time_24hr="true" />
                            </div>
                        </div>
                        <div class="posts-per-day">
                            <label>Posts per Day</label>
                            <input type="number" name="posts_per_day" min="1" value="1" class="modern-input" />
                        </div>
                    </div>
                `;
                break;
        }

        $timingOptions.html(html);

        // Reinitialize Flatpickr
        $timingOptions.find('.flatpickr').flatpickr({
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });

        // Bind change events for new inputs
        $timingOptions.find('input').on('change', updateSchedulePreview);
    }

    // Schedule Preview
    function updateSchedulePreview() {
        const $preview = $('#schedule-preview');
        const selectedPosts = getSelectedPosts();
        const settings = getScheduleSettings();

        if (!settings.startDate || selectedPosts.length === 0) {
            showEmptyState($preview, draftSchedulerPro.strings.previewEmpty);
            return;
        }

        const schedules = calculateScheduleTimes(selectedPosts, settings);
        renderPreview(schedules);
    }

    function getSelectedPosts() {
        let posts = $('#selected-draft-posts .draggable-item').map(function() {
            return {
                id: $(this).data('post-id'),
                title: $(this).find('.post-title').text()
            };
        }).get();

        const postCountType = $('input[name="post_count_type"]:checked').val();
        if (postCountType === 'custom') {
            const count = parseInt($('#custom-count-input input').val());
            if (count && count < posts.length) {
                posts = posts.slice(0, count);
            }
        }

        return posts;
    }

    function getScheduleSettings() {
        const settings = {
            startDate: $('#start-date').val(),
            scheduleType: $('input[name="schedule_type"]:checked').val(),
            postCountType: $('input[name="post_count_type"]:checked').val(),
            customPostCount: $('#custom-count-input input').val(),
            timing: {}
        };

        if (settings.scheduleType === 'custom') {
            settings.timing = {
                windowStart: $('input[name="window_start"]').val(),
                windowEnd: $('input[name="window_end"]').val(),
                postsPerDay: $('input[name="posts_per_day"]').val()
            };
        } else {
            settings.timing = {
                intervalHours: $('input[name="interval_hours"]').val(),
                intervalMinutes: $('input[name="interval_minutes"]').val()
            };
        }

        return settings;
    }

    function calculateScheduleTimes(posts, settings) {
        const schedules = [];
        const startDate = new Date(settings.startDate);
        let postsList = [...posts];

        if (settings.scheduleType === 'random') {
            postsList = shuffleArray(postsList);
        }

        postsList.forEach((post, index) => {
            const date = new Date(startDate);
            
            if (settings.scheduleType === 'custom') {
                const schedule = calculateCustomWindowTime(date, index, settings.timing);
                schedules.push({ post, date: schedule });
            } else {
                const schedule = calculateIntervalTime(date, index, settings.timing);
                schedules.push({ post, date: schedule });
            }
        });

        return schedules;
    }

    function calculateCustomWindowTime(baseDate, index, timing) {
        const postsPerDay = parseInt(timing.postsPerDay);
        const dayIndex = Math.floor(index / postsPerDay);
        const postIndex = index % postsPerDay;

        const [startHour, startMinute] = timing.windowStart.split(':').map(Number);
        const [endHour, endMinute] = timing.windowEnd.split(':').map(Number);

        const windowStartMinutes = startHour * 60 + startMinute;
        const windowEndMinutes = endHour * 60 + endMinute;
        
        const windowDuration = windowEndMinutes - windowStartMinutes;
        const interval = windowDuration / (postsPerDay + 1);
        
        const scheduleMinutes = windowStartMinutes + (interval * (postIndex + 1));
        
        const date = new Date(baseDate);
        date.setDate(date.getDate() + dayIndex);
        date.setHours(Math.floor(scheduleMinutes / 60));
        date.setMinutes(scheduleMinutes % 60);
        
        return date;
    }

    function calculateIntervalTime(baseDate, index, timing) {
        const intervalHours = parseInt(timing.intervalHours) || 0;
        const intervalMinutes = parseInt(timing.intervalMinutes) || 0;
        const totalMinutes = (intervalHours * 60 + intervalMinutes) * index;
        
        const date = new Date(baseDate);
        date.setMinutes(date.getMinutes() + totalMinutes);
        
        return date;
    }

    function renderPreview(schedules) {
        const $preview = $('#schedule-preview');
        $preview.empty();

        const $timeline = $('<div class="preview-timeline"></div>');
        
        schedules.forEach(schedule => {
            const timeString = schedule.date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            
            const dateString = schedule.date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });

            $timeline.append(`
                <div class="preview-item">
                    <span class="preview-time">${timeString}</span>
                    <span class="preview-date">${dateString}</span>
                    <span class="preview-post">${schedule.post.title}</span>
                </div>
            `);
        });

        $preview.append($timeline);
    }

    // Calendar View
    function initCalendarView() {
        const calendarEl = document.getElementById('calendar-view');
        if (!calendarEl._calendar) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: function(info, successCallback, failureCallback) {
                    $.ajax({
                        url: draftSchedulerPro.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'get_scheduled_posts',
                            nonce: draftSchedulerPro.nonce,
                            start: info.startStr,
                            end: info.endStr
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data);
                            } else {
                                failureCallback(response.data);
                            }
                        },
                        error: failureCallback
                    });
                }
            });
            
            calendar.render();
            calendarEl._calendar = calendar;
        }
    }

    // Form Submission
    function handleFormSubmit(e) {
        e.preventDefault();

        if (!confirm(draftSchedulerPro.strings.confirmSchedule)) {
            return;
        }

        const settings = getScheduleSettings();
        settings.selectedPosts = getSelectedPosts();

        if (settings.selectedPosts.length === 0) {
            Toast.show(draftSchedulerPro.strings.noPostsSelected, Toast.types.WARNING);
            return;
        }

        const $submitButton = $(this).find('button[type="submit"]');
        const originalText = $submitButton.html();
        
        $submitButton.prop('disabled', true).html(`
            <span class="dashicons dashicons-update spinning"></span>
            ${draftSchedulerPro.strings.processing}
        `);

        $.ajax({
            url: draftSchedulerPro.ajaxUrl,
            method: 'POST',
            data: {
                action: 'schedule_drafts',
                nonce: draftSchedulerPro.nonce,
                settings: JSON.stringify(settings)
            },
            success: function(response) {
                if (response.success) {
                    Toast.show(response.data.message, Toast.types.SUCCESS);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Toast.show(response.data, Toast.types.ERROR);
                    $submitButton.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                Toast.show(draftSchedulerPro.strings.error, Toast.types.ERROR);
                $submitButton.prop('disabled', false).html(originalText);
            }
        });
    }

    // Utility Functions
    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }

    // Bind Events
    function bindEvents() {
        $('#draft-scheduler-form').on('submit', handleFormSubmit);
        $('#undo-schedule').on('click', handleUndo);
        $('.filter-group select').on('change', loadDraftPosts);
        initPostCountSelection();
        initScheduleSettings();
        initLanguageSwitcher();
    }

    // Handle Undo
    function handleUndo() {
        if (!confirm(draftSchedulerPro.strings.confirmUndo)) {
            return;
        }

        const $button = $(this);
        const originalText = $button.html();
        
        $button.prop('disabled', true).html(`
            <span class="dashicons dashicons-update spinning"></span>
            ${draftSchedulerPro.strings.processing}
        `);

        $.ajax({
            url: draftSchedulerPro.ajaxUrl,
            method: 'POST',
            data: {
                action: 'undo_schedule',
                nonce: draftSchedulerPro.nonce
            },
            success: function(response) {
                if (response.success) {
                    Toast.show(response.data, Toast.types.SUCCESS);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Toast.show(response.data, Toast.types.ERROR);
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                Toast.show(draftSchedulerPro.strings.error, Toast.types.ERROR);
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    // Initialize everything when document is ready
    $(document).ready(function() {
        initComponents();
    });

})(jQuery);
