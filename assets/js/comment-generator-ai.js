/**
 * GZ SEO Comment Generator AI JavaScript
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';

    function renderError($container, message) {
        $container.empty().append(
            $('<div>').addClass('gzseo-message gzseo-message-error').text(message || '')
        );
    }
    
    // Prevent Enter key from submitting the post form in metabox
    $(document).on('keydown', '.gzseo-comment-generator-wrapper input[type="text"], .gzseo-comment-generator-wrapper input[type="number"]', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            e.stopPropagation();
            
            // If it's the keyword field, trigger the keyword addition
            if ($(this).attr('id') === 'gzseo-cg-keywords') {
                $(this).trigger('keyup');
            }
            
            return false;
        }
    });

    const GZSEOCommentGenerator = {
        
        // Properties
        generatedComments: [],
        keywords: [],
        formSettings: {}, // ذخیره تنظیمات فرم
        
        // Initialize
        init: function() {
            // بررسی وجود متغیرهای ضروری
            if (typeof gzseo_cg_ajax === 'undefined') {
                console.error('GZSEO: gzseo_cg_ajax is not defined');
                return;
            }
            
            this.bindEvents();
            this.loadExistingComments();
        },
        
        // Bind events
        bindEvents: function() {
            // Prevent form submission on Enter key in keyword field
            $('#gzseo-cg-keywords').on('keydown', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Add keyword button
            $('#gzseo-cg-add-keyword').on('click', this.addKeywordFromInput.bind(this));

            if ($('#gzseo-search-keywords-wrapper').length > 0) {
                this.loadSearchKeywords();
            }
            
            // Generate comments button
            $('#gzseo-cg-generate').on('click', this.generateComments.bind(this));
            
            // Save all comments button
            $('#gzseo-cg-save-all').on('click', this.saveAllComments.bind(this));
            
            // Cancel all button
            $('#gzseo-cg-cancel-all').on('click', this.cancelAll.bind(this));
            
            // Keywords input
            $('#gzseo-cg-keywords').on('keyup', this.handleKeywordInput.bind(this));
            
            // Comment actions (using delegation)
            $(document).on('click', '.gzseo-cg-edit-comment', this.editComment.bind(this));
            $(document).on('click', '.gzseo-cg-save-comment', this.saveComment.bind(this));
            $(document).on('click', '.gzseo-cg-delete-comment', this.deleteComment.bind(this));
            $(document).on('click', '.gzseo-cg-change-author', this.changeAuthor.bind(this));
            $(document).on('click', '.gzseo-cg-cancel-edit', this.cancelEdit.bind(this));
            
            // Remove keyword tag
            $(document).on('click', '.gzseo-keyword-remove', this.removeKeyword.bind(this));
            
            // Publish type radio buttons
            $(document).on('change', 'input[name^="publish_type_"]', this.handlePublishTypeChange.bind(this));
        },
        
        // Handle keyword input
        handleKeywordInput: function(e) {
            if (e.which === 13) { // Only Enter key
                e.preventDefault();
                this.addKeywordFromInput();
            }
        },

        // Add keyword from input field
        addKeywordFromInput: function() {
            const $input = $('#gzseo-cg-keywords');
            const keyword = $input.val().trim();
            
            if (keyword && !this.keywords.includes(keyword)) {
                this.keywords.push(keyword);
                this.renderKeywordTags();
                $input.val('');
                $input.focus(); // بازگرداندن فوکوس به فیلد
            }
        },
        
        // Render keyword tags
        renderKeywordTags: function() {
            const container = $('.gzseo-cg-keywords-tags');
            container.empty();
            
            this.keywords.forEach((keyword, index) => {
                const tag = $(`
                    <span class="gzseo-keyword-tag">
                        ${keyword}
                        <i class="fas fa-times gzseo-keyword-remove" data-index="${index}"></i>
                    </span>
                `);
                container.append(tag);
            });
        },
        
        // Remove keyword
        removeKeyword: function(e) {
            const index = $(e.target).data('index');
            this.keywords.splice(index, 1);
            this.renderKeywordTags();
        },
        
        // Save form settings
        saveFormSettings: function() {
            this.formSettings = {
                keywords: this.keywords,
                wordCount: $('#gzseo-cg-word-count').val(),
                tone: $('#gzseo-cg-tone').val(),
                commentCount: $('#gzseo-cg-count').val(),
                randomDate: $('#gzseo-cg-random-date').is(':checked'),
                authorGender: $('#gzseo-cg-author-gender').val() // اضافه کردن جنسیت نویسندگان
            };
        },
        
        // Generate comments
        generateComments: function() {
            const $btn = $('#gzseo-cg-generate');
            
            // Validate inputs
            if (!this.validateInputs()) {
                return;
            }
            
            // Save form settings before generating
            this.saveFormSettings();
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> ' + gzseo_cg_ajax.messages.generating);
            
            // Prepare data
            const data = {
                action: 'gzseo_generate_comments',
                nonce: gzseo_cg_ajax.nonce,
                post_id: $('#gzseo-cg-post-id').val(),
                title: $('#gzseo-cg-title').val(),
                content: $('#gzseo-cg-content').val(),
                keyword: this.keywords.join('،'), // تبدیل آرایه به رشته با جداکننده کاما فارسی
                ai_model: $('#gzseo-cg-ai-model').val(),
                tone_of_content: $('#gzseo-cg-tone').val(),
                word_count: $('#gzseo-cg-word-count').val(),
                comment_count: $('#gzseo-cg-count').val(),
                use_random_date: $('#gzseo-cg-random-date').is(':checked'),
                author_gender: $('#gzseo-cg-author-gender').val() // اضافه کردن جنسیت
            };
                        
            // Make request
            $.ajax({
                url: gzseo_cg_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.generatedComments = response.data.comments;
                        this.renderComments();
                        this.showMessage('success', response.data.message);
                        
                        // نمایش دکمه‌های ذخیره نهایی
                        $('.gzseo-cg-final-actions').show();
                    } else {
                        this.showMessage('error', response.data.message);
                    }

                    // به‌روزرسانی موجودی از طریق AJAX
                    $.post(gzseo_ajax.ajax_url, {
                        action: 'gzseo_update_ai_credits',
                        nonce: gzseo_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            $('.gzseo-credits-amount').text(response.data.credits);
                        }
                    });
                },
                error: (xhr, status, error) => {
                    this.showMessage('error', gzseo_cg_ajax.messages.error + ' (Status: ' + xhr.status + ')');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-magic"></i> تولید کامنت‌ها');
                }
            });
        },
        
        // Handle publish type change
        handlePublishTypeChange: function(e) {
            const $radio = $(e.target);
            const commentId = $radio.attr('name').replace('publish_type_', '');
            const value = $radio.val();
            const $comment = $radio.closest('.gzseo-cg-comment');
            const $scheduleOptions = $comment.find('.gzseo-cg-schedule-fields');
            
            // Update comment data
            const comment = this.generatedComments.find(c => c.id === commentId);
            if (comment) {
                comment.publish_immediately = (value === 'immediate');
                comment.save_as_draft = (value === 'draft');
                comment.schedule.enabled = (value === 'schedule');
            }
            
            // Show/hide schedule options
            if (value === 'schedule') {
                $scheduleOptions.slideDown();
            } else {
                $scheduleOptions.slideUp();
            }
        },
        
        // Save all comments
        saveAllComments: function() {
            if (this.generatedComments.length === 0) {
                this.showMessage('error', 'هیچ کامنتی برای ذخیره وجود ندارد');
                return;
            }
            
            const $btn = $('#gzseo-cg-save-all');
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            // Prepare comments data
            const commentsData = this.generatedComments.map(comment => {
                // Get schedule data from form if schedule is enabled
                if (comment.schedule.enabled) {
                    const $comment = $(`.gzseo-cg-comment[data-comment-id="${comment.id}"]`);
                    comment.schedule.interval_type = $comment.find('.gzseo-cg-schedule-type').val();
                    comment.schedule.interval_value = $comment.find('.gzseo-cg-schedule-value').val();
                }
                
                return {
                    content: comment.content,
                    author: comment.author,
                    author_email: comment.author_email,
                    gender: comment.gender,
                    date: comment.date,
                    use_random_date: String(this.formSettings.randomDate), // تبدیل به string
                    post_id: comment.post_id,
                    publish_immediately: String(comment.publish_immediately),
                    save_as_draft: String(comment.save_as_draft),
                    schedule: {
                        enabled: String(comment.schedule.enabled),
                        interval_type: comment.schedule.interval_type,
                        interval_value: comment.schedule.interval_value
                    }
                };
            });
            
            // Make request
            $.ajax({
                url: gzseo_cg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_save_all_comments',
                    nonce: gzseo_cg_ajax.nonce,
                    post_id: $('#gzseo-cg-post-id').val(),
                    comments: commentsData,
                    author_gender: $('#gzseo-cg-author-gender').val() // اضافه کردن جنسیت
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        
                        // پاک کردن لیست و reset فرم
                        this.resetForm();
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save error:', xhr);
                    this.showMessage('error', 'خطا در ذخیره کامنت‌ها');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-save"></i> ذخیره نهایی کامنت‌ها');
                }
            });
        },
        
        // Cancel all
        cancelAll: function() {
            if (confirm('آیا از انصراف و حذف کامنت‌های تولید شده مطمئن هستید؟')) {
                this.resetForm();
            }
        },
        
        // Reset form
        resetForm: function() {
            this.generatedComments = [];
            this.keywords = [];
            this.renderKeywordTags();
            $('#gzseo-cg-results').hide();
            $('#gzseo-cg-comments-list').empty();
            $('.gzseo-cg-final-actions').hide();
        },
        
        // Validate inputs
        validateInputs: function() {
            const title = $('#gzseo-cg-title').val().trim();
            const content = $('#gzseo-cg-content').val().trim();
            
            // اگر هم title و هم content خالی باشند، keyword باید حتماً وجود داشته باشد
            if (!title && !content && this.keywords.length === 0) {
                this.showMessage('error', 'در صورتی که عنوان و محتوا خالی هستند، باید حداقل یک کلمه کلیدی وارد کنید');
                return false;
            }
            
            return true;
        },
        
        // Render comments
        renderComments: function() {
            const container = $('#gzseo-cg-comments-list');
            container.empty();
            
            if (this.generatedComments.length === 0) {
                container.html('<p>هیچ کامنتی تولید نشده است.</p>');
                return;
            }
            
            $('#gzseo-cg-results').show();
            
            this.generatedComments.forEach(comment => {
                const commentHtml = this.createCommentHtml(comment);
                container.append(commentHtml);
            });
        },
        
        // Create comment HTML
        createCommentHtml: function(comment) {
            const randomDate = this.formSettings.randomDate;
            const genderIcon = comment.gender === 'male' ? 'fa-mars' : 'fa-venus';
            const genderColor = comment.gender === 'male' ? '#3498db' : '#e91e63';
            const genderBadge = comment.gender === 'male' ? 
                '<span class="gzseo-cg-author-gender-badge male">مذکر</span>' :
                '<span class="gzseo-cg-author-gender-badge female">مونث</span>';
            
            return $(`
                <div class="gzseo-cg-comment" data-comment-id="${comment.id}">
                    <div class="gzseo-cg-comment-header">
                        <div class="gzseo-cg-author-info">
                            <strong class="gzseo-cg-author-name">${comment.author}</strong>
                            <i class="fas ${genderIcon} gzseo-gender-icon" style="color: ${genderColor};" title="${comment.gender === 'male' ? 'مذکر' : 'مونث'}"></i>
                            ${genderBadge}
                            <button type="button" class="gzseo-cg-change-author" title="تغییر نام">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="gzseo-cg-comment-meta">
                            <span class="gzseo-cg-date">${randomDate ? comment.date : 'امروز'}</span>
                            <span class="gzseo-cg-status gzseo-status-${comment.status}">
                                ${this.getStatusLabel(comment.status)}
                            </span>
                        </div>
                    </div>
                    
                    <div class="gzseo-cg-comment-content">
                        <div class="gzseo-cg-content-view">${comment.content}</div>
                        <textarea class="gzseo-cg-content-edit" style="display: none;">${comment.content}</textarea>
                    </div>
                    
                    <div class="gzseo-cg-comment-actions">
                        <button type="button" class="button gzseo-cg-edit-comment">
                            <i class="fas fa-edit"></i> ویرایش
                        </button>
                        <button type="button" class="button gzseo-cg-delete-comment">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                    
                    <div class="gzseo-cg-comment-edit-actions" style="display: none;">
                        <button type="button" class="button button-primary gzseo-cg-save-comment">
                            <i class="fas fa-save"></i> ذخیره تغییرات
                        </button>
                        <button type="button" class="button gzseo-cg-cancel-edit">
                            <i class="fas fa-times"></i> انصراف
                        </button>
                    </div>
                    
                    <!-- Publish Options -->
                    <div class="gzseo-cg-publish-options">
                        <div class="gzseo-cg-publish-type">
                            <label>
                                <input type="radio" name="publish_type_${comment.id}" value="immediate" checked>
                                <i class="fas fa-bolt"></i> انتشار فوری
                            </label>
                            <label>
                                <input type="radio" name="publish_type_${comment.id}" value="draft">
                                <i class="fas fa-file-alt"></i> ذخیره به صورت پیش‌نویس
                            </label>
                            <label>
                                <input type="radio" name="publish_type_${comment.id}" value="schedule">
                                <i class="fas fa-clock"></i> زمان‌بندی انتشار
                            </label>
                        </div>
                        
                        <div class="gzseo-cg-schedule-fields" style="display: none;">
                            <div class="gzseo-cg-schedule-row">
                                <span>انتشار بعد از:</span>
                                <input type="number" class="gzseo-cg-schedule-value" value="1" min="1">
                                <select class="gzseo-cg-schedule-type">
                                    <option value="minutes">دقیقه</option>
                                    <option value="hours" selected>ساعت</option>
                                    <option value="days">روز</option>
                                    <option value="weeks">هفته</option>
                                    <option value="months">ماه</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        },
        
        // Get status label
        getStatusLabel: function(status) {
            const labels = {
                'pending': 'در انتظار',
                'published': 'منتشر شده',
                'scheduled': 'زمان‌بندی شده',
                'draft': 'پیش‌نویس',
                'preview': 'پیش‌نمایش'
            };
            return labels[status] || status;
        },
        
        // Edit comment
        editComment: function(e) {
            const $comment = $(e.target).closest('.gzseo-cg-comment');
            
            $comment.find('.gzseo-cg-content-view').hide();
            $comment.find('.gzseo-cg-content-edit').show();
            $comment.find('.gzseo-cg-comment-actions').hide();
            $comment.find('.gzseo-cg-comment-edit-actions').show();
        },
        
        // Cancel edit
        cancelEdit: function(e) {
            const $comment = $(e.target).closest('.gzseo-cg-comment');
            const commentId = $comment.data('comment-id');
            const comment = this.generatedComments.find(c => c.id === commentId);
            
            // بازگردانی محتوای اصلی
            if (comment) {
                $comment.find('.gzseo-cg-content-edit').val(comment.content);
            }
            
            $comment.find('.gzseo-cg-content-view').show();
            $comment.find('.gzseo-cg-content-edit').hide();
            $comment.find('.gzseo-cg-comment-actions').show();
            $comment.find('.gzseo-cg-comment-edit-actions').hide();
        },
        
        // Save comment
        saveComment: function(e) {
            const $comment = $(e.target).closest('.gzseo-cg-comment');
            const commentId = $comment.data('comment-id');
            const newContent = $comment.find('.gzseo-cg-content-edit').val();
            const author = $comment.find('.gzseo-cg-author-name').text();
            
            // Update in memory
            const comment = this.generatedComments.find(c => c.id === commentId);
            if (comment) {
                comment.content = newContent;
                comment.author = author;
            }
            
            // Update in UI
            $comment.find('.gzseo-cg-content-view').text(newContent);
            this.cancelEdit(e);
            
            this.showMessage('success', 'تغییرات ذخیره شد');
        },
        
        // Delete comment
        deleteComment: function(e) {
            if (!confirm(gzseo_cg_ajax.messages.confirm_delete)) {
                return;
            }
            
            const $comment = $(e.target).closest('.gzseo-cg-comment');
            const commentId = $comment.data('comment-id');
            
            // Remove from memory
            this.generatedComments = this.generatedComments.filter(c => c.id !== commentId);
            
            // Remove from UI
            $comment.fadeOut(300, function() {
                $(this).remove();
                
                // اگر هیچ کامنتی باقی نمانده، بخش نتایج را مخفی کن
                if ($('.gzseo-cg-comment').length === 0) {
                    $('#gzseo-cg-results').hide();
                    $('.gzseo-cg-final-actions').hide();
                }
            });
            
            this.showMessage('success', 'کامنت حذف شد');
        },
        
        // Change author
        changeAuthor: function(e) {
            const $comment = $(e.target).closest('.gzseo-cg-comment');
            const commentId = $comment.data('comment-id');
            const $authorName = $comment.find('.gzseo-cg-author-name');
            
            // دریافت comment از آرایه
            const commentIndex = this.generatedComments.findIndex(c => c.id === commentId);
            if (commentIndex === -1) return;
            
            const currentComment = this.generatedComments[commentIndex];
            const currentGender = currentComment.gender;
            const selectedGender = $('#gzseo-cg-author-gender').val();
            
            // تعیین جنسیت برای نام جدید
            let genderForNewName = null;
            if (selectedGender === 'male') {
                genderForNewName = 'male';
            } else if (selectedGender === 'female') {
                genderForNewName = 'female';
            }
            // اگر mixed باشد، genderForNewName null می‌ماند و تصادفی انتخاب می‌شود
            
            $.ajax({
                url: gzseo_cg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_get_random_author',
                    nonce: gzseo_cg_ajax.nonce,
                    gender: genderForNewName
                },
                success: (response) => {
                    if (response.success) {
                        const author = response.data;
                        $authorName.text(author.full_name);
                        
                        // Update gender icon
                        const $icon = $comment.find('.gzseo-cg-author-info i.gzseo-gender-icon');
                        const $badge = $comment.find('.gzseo-cg-author-gender-badge');
                        
                        if (author.gender === 'male') {
                            $icon.removeClass('fa-venus').addClass('fa-mars').css('color', '#3498db').attr('title', 'مذکر');
                            $badge.removeClass('female').addClass('male').text('مذکر');
                        } else {
                            $icon.removeClass('fa-mars').addClass('fa-venus').css('color', '#e91e63').attr('title', 'مونث');
                            $badge.removeClass('male').addClass('female').text('مونث');
                        }
                        
                        // Update comment data in memory
                        this.generatedComments[commentIndex] = {
                            ...currentComment,
                            author: author.full_name,
                            gender: author.gender,
                            author_email: this.generateFakeEmail(author.first_name, author.last_name)
                        };
                    }
                }
            });
        },
        
        // Generate fake email (client-side version)
        generateFakeEmail: function(firstName, lastName) {
            const domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'];
            const domain = domains[Math.floor(Math.random() * domains.length)];
            const username = (firstName + '.' + lastName + Math.floor(Math.random() * 90 + 10)).toLowerCase();
            return username + '@' + domain;
        },
        
        // Load existing comments
        loadExistingComments: function() {
            // این متد برای بارگذاری کامنت‌های ذخیره شده قبلی است
            // فعلا خالی است و می‌تواند در آینده پیاده‌سازی شود
        },

        // Load search keywords from Search Console
        loadSearchKeywords: function() {
            const $wrapper = $('#gzseo-search-keywords-wrapper');
            if (!$wrapper.length || $wrapper.data('loaded')) {
                return;
            }
            
            const postId = $wrapper.data('post-id');
            const postUrl = $wrapper.data('post-url');
                        
            // Show loading
            $wrapper.find('.gzseo-loading-keywords').show();
            
            $.ajax({
                url: gzseo_cg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_load_keywords_section',
                    nonce: gzseo_cg_ajax.nonce,
                    post_id: postId,
                    post_url: postUrl
                },
                success: (response) => {
                    if (response.success) {
                        $wrapper.html(response.data.html);
                        $wrapper.data('loaded', true);
                        this.bindKeywordItemEvents();
                    } else {
                        renderError($wrapper, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    renderError($wrapper, 'خطا در دریافت کلمات کلیدی (Status: ' + xhr.status + ')');
                }
            });
        },

        // Bind events for search keyword items
        bindKeywordItemEvents: function() {
            $('.gzseo-keyword-item').off('click').on('click', (e) => {
                const $item = $(e.currentTarget);
                const keyword = $item.data('keyword');
                
                // Check if keyword already exists
                if (!this.keywords.includes(keyword)) {
                    this.keywords.push(keyword);
                    this.renderKeywordTags();
                    
                    // Visual feedback
                    $item.addClass('gzseo-keyword-added');
                    setTimeout(() => {
                        $item.removeClass('gzseo-keyword-added');
                    }, 1000);
                } else {
                    // Already exists
                    $item.addClass('gzseo-keyword-exists');
                    setTimeout(() => {
                        $item.removeClass('gzseo-keyword-exists');
                    }, 1000);
                }
            });
        },
        
        // Show message
        showMessage: function(type, message) {
            const $container = $('#gzseo-cg-messages');
            const $message = $(`
                <div class="gzseo-message gzseo-message-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `);
            
            $container.html($message);
            
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // بررسی وجود المان با ID صحیح
        if ($('.gzseo-comment-generator-wrapper').length > 0) {
            GZSEOCommentGenerator.init();
        }
    });
    
})(jQuery);
