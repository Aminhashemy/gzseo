/**
 * GZSEO Video Update JavaScript - Fixed Version
 */
(function($) {
    'use strict';

    // Video Update Manager
    const GZSEOVideoUpdate = {
        selectedVideos: [],
        searchResults: [],
        
        init: function() {
            this.bindEvents();
            this.loadSavedVideos();
        },
        
        bindEvents: function() {
            // Search videos
            $('#gzseo-search-videos').on('click', this.searchVideos.bind(this));
            
            // Enter key on search input
            $('#gzseo-video-keyword').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    this.searchVideos();
                }
            }.bind(this));
            
            // Save videos
            $('#gzseo-save-videos').on('click', this.saveVideos.bind(this));
            
            // Video selection
            $(document).on('change', '.gzseo-video-checkbox', this.toggleVideoSelection.bind(this));
            
            // Select all/none
            $(document).on('click', '.gzseo-select-all', this.selectAllVideos.bind(this));
            $(document).on('click', '.gzseo-select-none', this.selectNoneVideos.bind(this));
            
            // Video preview
            $(document).on('click', '.gzseo-video-preview-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.showVideoPreview(e);
            }.bind(this));
            
            // Modal close
            $('#gzseo-modal-close').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.closeVideoModal();
            }.bind(this));

            $('.gzseo-video-modal').on('click', function(e) {
                if ($(e.target).hasClass('gzseo-video-modal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeVideoModal();
                }
            }.bind(this));

            $('.gzseo-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },
        
        searchVideos: function() {
            const keyword = $('#gzseo-video-keyword').val().trim();
            
            if (!keyword) {
                this.showMessage('error', 'لطفا کلمه کلیدی را وارد کنید');
                return;
            }
            
            const $btn = $('#gzseo-search-videos');
            const originalText = $btn.html();
            
            // Show loading
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + gzseo_video.messages.searching);
            
            // Clear previous results
            this.clearResults();
            
            $.ajax({
                url: gzseo_video.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_search_videos',
                    nonce: gzseo_video.nonce,
                    keyword: keyword
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.videos && response.data.videos.length > 0) {
                            this.displayResults(response.data.videos);
                            this.showMessage('success', `${response.data.count} ویدیو یافت شد`);
                        } else {
                            this.showMessage('info', gzseo_video.messages.no_results);
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
                    } else {
                        this.showMessage('error', response.data.message || gzseo_video.messages.error);
                    }
                },
                error: () => {
                    this.showMessage('error', gzseo_video.messages.error);
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        displayResults: function(videos) {
            this.searchResults = videos;
            const $resultsSection = $('.gzseo-video-results-section');
            const $resultsContainer = $('.gzseo-video-results').not('.gzseo-saved-videos-grid');
            
            // Clear container
            $resultsContainer.empty();
            
            // Remove any existing controls
            $('.gzseo-video-controls').remove();
            
            // Add select all/none buttons
            const controlsHtml = `
                <div class="gzseo-video-controls">
                    <button type="button" class="button button-small gzseo-select-all">انتخاب همه</button>
                    <button type="button" class="button button-small gzseo-select-none">لغو انتخاب همه</button>
                </div>
            `;
            $('<div>').html(controlsHtml).insertAfter($resultsSection.find('h4'));
            
            // اضافه کردن پیام اطلاع رسانی
            const infoMessage = `
                <div class="gzseo-info-message" style="background: #e3f2fd; border: 1px solid #2196f3; color: #1565c0; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    <i class="fas fa-info-circle"></i>
                    <span>این ویدیوها به صورت دستی در حال اضافه شدن هستند و با نماد <i class="fas fa-hand-pointer" style="color: #2196f3;"></i> مشخص خواهند شد.</span>
                </div>
            `;
            $(infoMessage).insertAfter('.gzseo-video-controls');
            
            // Display videos
            videos.forEach((video, index) => {
                const videoHtml = this.createVideoElement(video, index);
                $resultsContainer.append(videoHtml);
            });
            
            // Show results section and position selector
            $resultsSection.show();
            $('.gzseo-video-position').show();
            $('.gzseo-video-actions').show();
            
            // Update selected count
            this.updateSelectedCount();
        },
        
        createVideoElement: function(video, index) {
            const platformClass = video.platform.toLowerCase();
            const isYouTube = video.platform.toLowerCase() === 'youtube';
            
            // For YouTube, extract video ID and create thumbnail
            let thumbnailHtml = '';
            if (isYouTube) {
                const videoId = this.extractYouTubeId(video.url);
                if (videoId) {
                    thumbnailHtml = `<img src="https://img.youtube.com/vi/${videoId}/mqdefault.jpg" alt="Video thumbnail" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
            }
            
            // If no thumbnail (non-YouTube), show iframe
            if (!thumbnailHtml) {
                thumbnailHtml = video.embed_code.replace('width="560" height="315"', 'class="gzseo-video-iframe"');
            }
            
            return `
                <div class="gzseo-video-item" data-index="${index}">
                    <input type="checkbox" class="gzseo-video-checkbox" value="${index}">
                    <div class="gzseo-video-preview">
                        ${thumbnailHtml}
                        <button type="button" class="gzseo-video-preview-btn" data-index="${index}" title="مشاهده ویدیو">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    <div class="gzseo-video-info">
                        <span class="gzseo-video-platform ${platformClass}">${video.platform}</span>
                    </div>
                </div>
            `;
        },
        
        extractYouTubeId: function(url) {
            const patterns = [
                /youtube\.com\/watch\?v=([^\&\?\/]+)/,
                /youtube\.com\/embed\/([^\&\?\/]+)/,
                /youtube\.com\/v\/([^\&\?\/]+)/,
                /youtu\.be\/([^\&\?\/]+)/,
                /youtube\.com\/@[^\/]+\/([^\&\?\/]+)/
            ];
            
            for (let pattern of patterns) {
                const match = url.match(pattern);
                if (match) {
                    return match[1];
                }
            }
            
            return null;
        },
        
        toggleVideoSelection: function(e) {
            const $checkbox = $(e.target);
            const index = parseInt($checkbox.val());
            const $item = $checkbox.closest('.gzseo-video-item');
            
            if ($checkbox.is(':checked')) {
                $item.addClass('selected');
                if (!this.selectedVideos.includes(index)) {
                    this.selectedVideos.push(index);
                }
            } else {
                $item.removeClass('selected');
                this.selectedVideos = this.selectedVideos.filter(i => i !== index);
            }
            
            this.updateSelectedCount();
        },
        
        selectAllVideos: function() {
            $('.gzseo-video-checkbox').prop('checked', true).trigger('change');
        },
        
        selectNoneVideos: function() {
            $('.gzseo-video-checkbox').prop('checked', false).trigger('change');
        },
        
        updateSelectedCount: function() {
            const count = this.selectedVideos.length;
            $('.gzseo-selected-count').text(`${count} ویدیو انتخاب شده`);
            
            // Enable/disable save button
            $('#gzseo-save-videos').prop('disabled', count === 0);
        },
        
        saveVideos: function() {
			if (this.selectedVideos.length === 0) {
				this.showMessage('error', gzseo_video.messages.select_videos);
				return;
			}
			
			// اضافه کردن ویژگی bulk_generated به ویدیوهای جدید
			const selectedVideoData = this.selectedVideos.map(index => {
				const video = { ...this.searchResults[index] };
				// مطمئن شویم که ویدیوهای دستی به عنوان غیر bulk مشخص می‌شوند
				video.bulk_generated = false;
				video.is_active = true; // ویدیوهای دستی پیش‌فرض فعال
				return video;
			});
			
			const position = $('#gzseo-video-display-position').val();
			
			const $btn = $('#gzseo-save-videos');
			const originalText = $btn.html();
			
			// Show loading
			$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
			
			// ابتدا ویدیوهای قبلی را دریافت کنید
			$.ajax({
				url: gzseo_video.ajax_url,
				type: 'POST',
				data: {
					action: 'gzseo_get_video_updates',
					nonce: gzseo_video.nonce,
					post_id: gzseo_video.post_id
				},
				success: (getResponse) => {
					let existingVideos = [];
					
					if (getResponse.success && getResponse.data.videos && Array.isArray(getResponse.data.videos)) {
						// کپی عمیق از ویدیوهای موجود با حفظ تمام ویژگی‌ها
						existingVideos = JSON.parse(JSON.stringify(getResponse.data.videos));
					}
					
					// ویدیوهای جدید را به لیست موجود اضافه کنید
					const allVideos = [...existingVideos, ...selectedVideoData];

					// حذف تکراری‌ها بر اساس URL
					const uniqueVideos = allVideos.filter((video, index, self) =>
						index === self.findIndex((v) => v.url === video.url)
					);
					
					// ذخیره همه ویدیوها
					$.ajax({
						url: gzseo_video.ajax_url,
						type: 'POST',
						data: {
							action: 'gzseo_save_video_update',
							nonce: gzseo_video.nonce,
							post_id: gzseo_video.post_id,
							videos: uniqueVideos,
							position: position,
							is_bulk_update: 'false' // مشخص کردن اینکه این آپدیت دستی است
						},
						success: (response) => {
							if (response.success) {
								this.showMessage('success', `${selectedVideoData.length} ویدیو جدید اضافه شد`);
								this.loadSavedVideos();
								this.clearResults();
								
								// Clear search input
								$('#gzseo-video-keyword').val('');
								
								// اگر ویدیوهای bulk وجود دارند، ممکن است نیاز به refresh باشد
								let hasBulk = uniqueVideos.some(v => v.bulk_generated === true);
								if (hasBulk && $('.gzseo-bulk-videos-notice').length === 0) {
									setTimeout(() => {
										location.reload();
									}, 1500);
								}
							} else {
								this.showMessage('error', response.data.message || gzseo_video.messages.save_error);
							}
						},
						error: () => {
							this.showMessage('error', gzseo_video.messages.save_error);
						},
						complete: () => {
							$btn.prop('disabled', false).html(originalText);
						}
					});
				},
				error: () => {
					// اگر نتوانست ویدیوهای قبلی را دریافت کند، فقط ویدیوهای جدید را ذخیره کند
					$.ajax({
						url: gzseo_video.ajax_url,
						type: 'POST',
						data: {
							action: 'gzseo_save_video_update',
							nonce: gzseo_video.nonce,
							post_id: gzseo_video.post_id,
							videos: selectedVideoData,
							position: position,
							is_bulk_update: 'false'
						},
						success: (response) => {
							if (response.success) {
								this.showMessage('success', response.data.message);
								this.loadSavedVideos();
								this.clearResults();
								$('#gzseo-video-keyword').val('');
							} else {
								this.showMessage('error', response.data.message || gzseo_video.messages.save_error);
							}
						},
						error: () => {
							this.showMessage('error', gzseo_video.messages.save_error);
						},
						complete: () => {
							$btn.prop('disabled', false).html(originalText);
						}
					});
				}
			});
		},
        
        /*loadSavedVideos: function() {
            $.ajax({
                url: gzseo_video.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_get_video_updates',
                    nonce: gzseo_video.nonce,
                    post_id: gzseo_video.post_id
                },
                success: (response) => {
                    if (response.success && response.data.videos.length > 0) {
                        this.displaySavedVideos(response.data.videos);
                        
                        // Set saved position
                        if (response.data.position) {
                            $('#gzseo-video-display-position').val(response.data.position);
                        }
                    }
                }
            });
        },*/
        
        displaySavedVideos: function(videos) {
            const $section = $('.gzseo-saved-videos-section');
            const $container = $('.gzseo-saved-videos');
            
            $container.empty();
            
            // Create grid container for saved videos
            const $grid = $('<div class="gzseo-saved-videos-grid"></div>');
            
            videos.forEach((video, index) => {
                const videoHtml = this.createSavedVideoElement(video, index);
                $grid.append(videoHtml);
            });
            
            $container.append($grid);
            
            // Add remove all button
            $container.append(`
                <div style="margin-top: 20px; text-align: center;">
                    <button type="button" class="button button-link-delete" id="gzseo-remove-all-videos">
                        <i class="fas fa-trash"></i> حذف همه ویدیوها
                    </button>
                </div>
            `);
            
            // Bind remove events
            this.bindRemoveEvents();
            
            $section.show();
        },
        
        createSavedVideoElement: function(video, index) {
            const platformClass = video.platform.toLowerCase();
            const isYouTube = video.platform.toLowerCase() === 'youtube';
            
            let previewHtml = '';
            if (isYouTube && video.video_id) {
                previewHtml = `
                    <div class="gzseo-video-preview">
                        <img src="https://img.youtube.com/vi/${video.video_id}/mqdefault.jpg" alt="Video thumbnail" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
                        <button type="button" class="gzseo-video-preview-btn gzseo-saved-preview" data-video='${JSON.stringify(video)}' title="مشاهده ویدیو">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                `;
            } else {
                previewHtml = `
                    <div class="gzseo-video-preview">
                        ${video.embed_code.replace('width="560" height="315"', 'class="gzseo-video-iframe"')}
                        <button type="button" class="gzseo-video-preview-btn gzseo-saved-preview" data-video='${JSON.stringify(video)}' title="مشاهده ویدیو">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                `;
            }
            
            // اضافه کردن نشانگر نوع ویدیو فقط اگر bulk باشد
            let typeIndicator = '';
            if (video.bulk_generated === true) {
                typeIndicator = `
                    <div class="gzseo-bulk-indicator">
                        <i class="fas fa-robot"></i>
                        <span>آپدیت دسته جمعی</span>
                    </div>
                `;
            }
            // برای ویدیوهای دستی نشانگر نمی‌گذاریم مگر اینکه صراحتاً خواسته شده باشد
            
            return `
                <div class="gzseo-saved-video-item ${video.bulk_generated === true ? 'bulk-generated' : 'manual-generated'}" data-index="${index}">
                    ${typeIndicator}
                    <button type="button" class="gzseo-remove-video" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                    ${previewHtml}
                    <div class="gzseo-video-info">
                        <span class="gzseo-video-platform ${platformClass}">${video.platform}</span>
                    </div>
                </div>
            `;
        },
        
        bindRemoveEvents: function() {
            $('.gzseo-remove-video').on('click', this.removeSingleVideo.bind(this));
            $('#gzseo-remove-all-videos').on('click', this.removeAllVideos.bind(this));
            
            // Preview for saved videos
            $('.gzseo-saved-preview').on('click', this.showSavedVideoPreview.bind(this));
        },
        
        showSavedVideoPreview: function(e) {
            e.preventDefault();
            const videoData = $(e.currentTarget).data('video');
            
            if (!videoData) return;
            
            // Create embed code with autoplay
            let embedCode = videoData.embed_code;
            
            // Add autoplay parameter based on platform
            switch (videoData.platform.toLowerCase()) {
                case 'youtube':
                    embedCode = embedCode.replace('youtube.com/embed/', 'youtube.com/embed/');
                    embedCode = embedCode.replace('" frameborder', '?autoplay=1&rel=0" frameborder');
                    break;
                case 'aparat':
                    embedCode = embedCode.replace('" width', '?autoplay=true" width');
                    break;
            }
            
            // Update modal content
            $('#gzseo-modal-video').html(embedCode);
            
            // Show modal
            $('#gzseo-video-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        },
        
        removeSingleVideo: function(e) {
			e.preventDefault();
			e.stopPropagation();

			if (!confirm('آیا از حذف این ویدیو اطمینان دارید؟')) {
				return;
			}
			
			const index = parseInt($(e.currentTarget).data('index'));
			const $btn = $(e.currentTarget);
			
			// غیرفعال کردن دکمه
			$btn.prop('disabled', true);
			
			// ابتدا ویدیوهای فعلی را دریافت کنید
			$.ajax({
				url: gzseo_video.ajax_url,
				type: 'POST',
				data: {
					action: 'gzseo_get_video_updates',
					nonce: gzseo_video.nonce,
					post_id: gzseo_video.post_id
				},
				success: (response) => {
					if (response.success && response.data.videos) {
						let videos = response.data.videos;
						
						// ایجاد آرایه جدید بدون ویدیوی حذف شده
						let updatedVideos = [];
						
						for (let i = 0; i < videos.length; i++) {
							if (i !== index) {
								// کپی عمیق و دقیق با حفظ تمام ویژگی‌ها
								let videoCopy = JSON.parse(JSON.stringify(videos[i]));
								
								// اطمینان از اینکه is_active به درستی کپی شده
								if (videos[i].hasOwnProperty('is_active')) {
									videoCopy.is_active = videos[i].is_active;
								}
								
								// اطمینان از اینکه bulk_generated به درستی کپی شده
								if (videos[i].hasOwnProperty('bulk_generated')) {
									videoCopy.bulk_generated = videos[i].bulk_generated;
								}
								
								updatedVideos.push(videoCopy);
							}
						}
						
						// ذخیره لیست جدید
						const position = $('#gzseo-video-display-position').val();
						
						$.ajax({
							url: gzseo_video.ajax_url,
							type: 'POST',
							data: {
								action: 'gzseo_save_video_update',
								nonce: gzseo_video.nonce,
								post_id: gzseo_video.post_id,
								videos: updatedVideos,
								position: position || 'end_content',
								is_bulk_update: 'false',
								preserve_properties: 'true', // مهم: فلگ حفظ ویژگی‌ها
								is_remove_action: 'true' // فلگ جدید برای مشخص کردن عملیات حذف
							},
							success: (saveResponse) => {
								if (saveResponse.success) {
									this.showMessage('success', 'ویدیو حذف شد');
									
									if (updatedVideos.length === 0) {
										$('.gzseo-saved-videos-section').hide();
										// اگر هیچ ویدیویی باقی نمانده، لیست bulk را هم مخفی کن
										$('.gzseo-bulk-videos-notice').hide();
									} else {
										this.loadSavedVideos();
										
										// بررسی اگر ویدیوهای bulk وجود دارند
										let hasBulkVideos = false;
										for (let video of updatedVideos) {
											if (video.bulk_generated === true) {
												hasBulkVideos = true;
												break;
											}
										}
										
										// اگر ویدیوهای bulk وجود دارند و لیست نمایش داده نمی‌شود
										if (hasBulkVideos && $('.gzseo-bulk-videos-notice').length === 0) {
											// refresh صفحه برای نمایش لیست bulk
											setTimeout(() => {
												location.reload();
											}, 1500);
										}
									}
								} else {
									this.showMessage('error', 'خطا در حذف ویدیو');
									$btn.prop('disabled', false);
								}
							},
							error: () => {
								this.showMessage('error', 'خطا در ذخیره تغییرات');
								$btn.prop('disabled', false);
							}
						});
					}
				},
				error: () => {
					this.showMessage('error', 'خطا در دریافت اطلاعات');
					$btn.prop('disabled', false);
				}
			});
		},
        
        removeAllVideos: function() {
			if (!confirm('آیا از حذف همه ویدیوها اطمینان دارید؟')) {
				return;
			}

			const position = $('#gzseo-video-display-position').val();
			
			$.ajax({
				url: gzseo_video.ajax_url,
				type: 'POST',
				data: {
					action: 'gzseo_save_video_update',
					nonce: gzseo_video.nonce,
					post_id: gzseo_video.post_id,
					videos: [], // آرایه خالی برای حذف همه
					position: position,
					is_bulk_update: 'false' // این یک عملیات دستی است
				},
				success: (response) => {
					if (response.success) {
						this.showMessage('success', 'همه ویدیوها حذف شدند');
						$('.gzseo-saved-videos-section').hide();
						
						// اگر لیست bulk وجود دارد، آن را هم مخفی کن
						$('.gzseo-bulk-videos-notice').hide();
						
						// یا می‌توانید صفحه را refresh کنید
						// setTimeout(() => {
						//     location.reload();
						// }, 1000);
					} else {
						this.showMessage('error', response.data.message || 'خطا در حذف ویدیوها');
					}
				},
				error: () => {
					this.showMessage('error', 'خطا در حذف ویدیوها');
				}
			});
		},
        
        clearResults: function() {
            this.selectedVideos = [];
            this.searchResults = [];
            $('.gzseo-video-results').not('.gzseo-saved-videos-grid').empty();
            $('.gzseo-video-results-section').hide();
            $('.gzseo-video-actions').hide();
            $('.gzseo-video-controls').remove();
        },
        
        showVideoPreview: function(e) {
            e.preventDefault();
            e.stopPropagation();
            const index = $(e.currentTarget).data('index');
            const video = this.searchResults[index];
            
            if (!video) return;
            
            // Create embed code with autoplay
            let embedCode = video.embed_code;
            
            // Add autoplay parameter based on platform
            switch (video.platform.toLowerCase()) {
                case 'youtube':
                    embedCode = embedCode.replace('youtube.com/embed/', 'youtube.com/embed/');
                    embedCode = embedCode.replace('" frameborder', '?autoplay=1&rel=0" frameborder');
                    break;
                case 'aparat':
                    embedCode = embedCode.replace('" width', '?autoplay=true" width');
                    break;
                case 'namasha':
                case 'tamasha':
                case 'ifilo':
                    // These platforms might have their own autoplay parameters
                    break;
            }
            
            // Update modal content
            $('#gzseo-modal-video').html(embedCode);
            
            // Show modal
            $('#gzseo-video-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        },
        
        closeVideoModal: function() {
            $('#gzseo-video-modal').removeClass('active');
            $('#gzseo-modal-video').empty();
            $('body').css('overflow', '');
        },
		
		closeTaxonomyVideoModal: function() {
            $('#gzseo-video-taxonomy-modal').removeClass('active');
            $('#gzseo-taxonomy-modal-video').empty();
            $('body').css('overflow', '');
        },
		

		checkAndRefreshBulkList: function() {
			// بررسی وجود بخش bulk videos
			const $bulkSection = $('.gzseo-bulk-videos-notice');
			
			if ($bulkSection.length === 0) {
				// اگر بخش bulk وجود ندارد، بررسی کن که آیا باید وجود داشته باشد
				$.ajax({
					url: gzseo_video.ajax_url,
					type: 'POST',
					data: {
						action: 'gzseo_check_bulk_videos',
						nonce: gzseo_video.nonce,
						post_id: gzseo_video.post_id
					},
					success: (response) => {
						if (response.success && response.data.has_bulk) {
							// اگر ویدیوهای bulk وجود دارد ولی نمایش داده نمی‌شود
							// صفحه را refresh کن
							location.reload();
						}
					}
				});
			}
		},

		// اضافه کردن به متد loadSavedVideos
		loadSavedVideos: function() {
			$.ajax({
				url: gzseo_video.ajax_url,
				type: 'POST',
				data: {
					action: 'gzseo_get_video_updates',
					nonce: gzseo_video.nonce,
					post_id: gzseo_video.post_id
				},
				success: (response) => {
					if (response.success && response.data.videos.length > 0) {
						this.displaySavedVideos(response.data.videos);
						
						// Set saved position
						if (response.data.position) {
							$('#gzseo-video-display-position').val(response.data.position);
						}
						
						// بررسی وجود ویدیوهای bulk
						let hasBulk = false;
						response.data.videos.forEach(video => {
							if (video.bulk_generated === true) {
								hasBulk = true;
							}
						});
						
						// اگر ویدیو bulk وجود دارد ولی لیست نمایش داده نمی‌شود
						if (hasBulk && $('.gzseo-bulk-videos-notice').length === 0) {
							// یا می‌توانید یک پیام به کاربر نمایش دهید
							this.showMessage('info', 'ویدیوهای آپدیت دسته جمعی وجود دارند. برای مشاهده لیست کامل، صفحه را refresh کنید.');
						}
					}
				}
			});
		},
        
        showMessage: function(type, message) {
            // Remove existing messages
            $('.gzseo-message').remove();
            
            const classes = {
                'success': 'gzseo-success-message',
                'error': 'gzseo-error-message',
                'info': 'gzseo-info-message'
            };
            
            const $message = $(`<div class="gzseo-message ${classes[type]}">${message}</div>`);
            
            $('.gzseo-video-update-wrapper').prepend($message);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        // Check if we're in the video update tab
        if ($('.gzseo-metabox-wrapper').length) {
            // Initialize video update when tab is clicked
            $('.gzseo-metabox-tab[data-tab="video-update"]').on('click', function() {
                // Initialize only once
                if (!GZSEOVideoUpdate.initialized) {
                    GZSEOVideoUpdate.init();
                    GZSEOVideoUpdate.initialized = true;
                }
            });
            
            // If video update tab is already active, initialize immediately
            if ($('#gzseo-video-update').hasClass('active')) {
                GZSEOVideoUpdate.init();
                GZSEOVideoUpdate.initialized = true;
            }
        }
        
        // ESC key to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#gzseo-video-modal').hasClass('active')) {
                GZSEOVideoUpdate.closeVideoModal();
            }
        });
    });
	
	/********************************** آپدیت محتوای ویدیویی تکسونومی ها ********************************/
	// Search Taxonomy Videos
	jQuery(document).on('click', '#gzseo-search-taxonomy-videos', function() {
		const $btn = jQuery(this);
		const keyword = jQuery('#gzseo-video-taxonomy-keyword').val();

		if (!keyword) {
			alert('لطفا کلمه کلیدی را وارد کنید');
			return;
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال جستجو...');

		jQuery.ajax({
			url: gzseo_video.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_search_videos',
				nonce: gzseo_video.nonce,
				keyword: keyword
			},
			success: function(response) {
				if (response.success && response.data.videos.length > 0) {
					let videosHtml = '';

					response.data.videos.forEach(function(video) {
						videosHtml += `
							<div class="gzseo-video-result-item">
								<div class="gzseo-video-checkbox">
									<input type="checkbox" class="gzseo-video-select" 
										data-video='${JSON.stringify(video)}'>
								</div>
								<div class="gzseo-video-preview-thumb">
									${video.embed_code}
								</div>
								<div class="gzseo-video-info">
									<span class="gzseo-video-platform">${video.platform}</span>
								</div>
							</div>
						`;
					});

					jQuery('.gzseo-video-results').html(videosHtml);
					jQuery('.gzseo-video-results-section').slideDown();

				} else {
					alert('ویدیویی یافت نشد');
				}
			},
			error: function() {
				alert('خطا در جستجو');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-search"></i> جستجوی ویدیو');
			}
		});
	});

	// Update selected count for taxonomy videos
	jQuery(document).on('change', '.gzseo-video-select', function() {
		const selectedCount = jQuery('.gzseo-video-select:checked').length;
		jQuery('.gzseo-selected-count').text(selectedCount + ' ویدیو انتخاب شده');

		if (selectedCount > 0) {
			jQuery('.gzseo-video-actions').slideDown();
		} else {
			jQuery('.gzseo-video-actions').slideUp();
		}
	});
	
	// Save Taxonomy Videos
	jQuery(document).on('click', '#gzseo-save-taxonomy-videos', function() {
		const $btn = jQuery(this);
		const termId = jQuery('#gzseo-video-term-id').val();
		const taxonomy = jQuery('#gzseo-video-taxonomy').val();
		const position = jQuery('#gzseo-video-taxonomy-display-position').val();

		const selectedVideos = [];
		jQuery('.gzseo-video-select:checked').each(function() {
			const videoData = jQuery(this).data('video');

			// ذخیره کامل تمام فیلدها بدون تغییر
			if (videoData) {
				selectedVideos.push(videoData);
			}
		});

		if (selectedVideos.length === 0) {
			alert('لطفا حداقل یک ویدیو انتخاب کنید');
			return;
		}

		// نمایش یک نمونه از embed_code برای بررسی
		if (selectedVideos[0] && selectedVideos[0].embed_code) {
		} else {
			console.error('WARNING: First video has no embed_code!');
		}

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');

		jQuery.ajax({
			url: gzseo_video.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_save_taxonomy_video_update',
				nonce: gzseo_video.nonce,
				term_id: termId,
				taxonomy: taxonomy,
				videos: selectedVideos,
				position: position,
				is_bulk_update: false
			},
			success: function(response) {
				if (response.success) {

					// بررسی اولین ویدیوی برگشتی
					if (response.data.videos && response.data.videos.length > 0) {
						if (response.data.videos[0].embed_code) {
						} else {
							console.error('First video MISSING embed_code!');
						}
					}

					alert(response.data.message);

					// پاک کردن نتایج جستجو
					jQuery('.gzseo-video-results-section').slideUp();
					jQuery('.gzseo-video-results').html('');
					jQuery('.gzseo-video-select:checked').prop('checked', false);
					jQuery('.gzseo-selected-count').text('0 ویدیو انتخاب شده');

					// نمایش ویدیوهای ذخیره شده
					if (response.data.videos && response.data.videos.length > 0) {
						displaySavedTaxonomyVideos(response.data.videos);
						jQuery('.gzseo-saved-videos-section').slideDown();
					}

				} else {
					alert(response.data.message || 'خطا در ذخیره ویدیوها');
				}
			},
			error: function(xhr, status, error) {
				alert('خطا در ارتباط با سرور. جزئیات در Console');
			},
			complete: function() {
				$btn.prop('disabled', false).html('<i class="fas fa-save"></i> ذخیره ویدیوهای انتخاب شده');
			}
		});
	});
	
	function loadSavedTaxonomyVideos(termId) {
		jQuery.ajax({
			url: gzseo_video.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_get_taxonomy_video_updates',
				nonce: gzseo_video.nonce,
				term_id: termId
			},
			success: function(response) {
				if (response.success && response.data.videos) {
					displaySavedTaxonomyVideos(response.data.videos);
				}
			}
		});
	}
	
	function displaySavedTaxonomyVideos(videos) {

		if (!videos || videos.length === 0) {
			jQuery('.gzseo-saved-videos').html('<div class="gzseo-no-videos"><i class="fas fa-video-slash"></i><p>هنوز ویدیویی ذخیره نشده است.</p></div>');
			return;
		}

		let html = '';

		videos.forEach(function(video, index) {

			const isBulk = video.bulk_generated === true || video.bulk_generated === 'true';
			const isActive = video.is_active !== false;
			const itemClass = isBulk ? 'bulk-generated' : 'manual';
			const platform = video.platform || 'unknown';

			// استفاده مستقیم از embed_code بدون تغییر
			const embedCode = video.embed_code || '<p style="color: red;">⚠️ کد embed موجود نیست</p>';

			html += `
				<div class="gzseo-saved-video-item ${itemClass}" data-index="${index}">
					<div class="gzseo-saved-video-header">
						<div class="gzseo-saved-video-type ${isBulk ? 'bulk' : 'manual'}">
							<i class="fas fa-${isBulk ? 'robot' : 'hand-pointer'}"></i>
							<span>${isBulk ? 'دسته جمعی' : 'دستی'}</span>
						</div>
						<div class="gzseo-saved-video-platform">
							<span class="gzseo-video-platform ${platform.toLowerCase()}">${platform}</span>
						</div>
						<div class="gzseo-saved-video-actions">
							<button class="gzseo-video-action-btn preview gzseo-preview-saved-video" 
									data-video-index="${index}" 
									title="پیش‌نمایش">
								<i class="fas fa-eye"></i>
							</button>
							<button class="gzseo-video-action-btn remove gzseo-remove-saved-video" 
									data-index="${index}" 
									title="حذف">
								<i class="fas fa-trash"></i>
							</button>
						</div>
					</div>
					<div class="gzseo-saved-video-preview">
						${embedCode}
					</div>
				</div>
			`;
		});

		jQuery('.gzseo-saved-videos').html(html);

		// ذخیره داده‌های ویدیو برای استفاده بعدی
		jQuery('.gzseo-saved-videos').data('all-videos', videos);
	}
	
	// حذف ویدیو
	jQuery(document).on('click', '.gzseo-remove-saved-video', function(e) {
		e.preventDefault();
		e.stopPropagation();

		if (!confirm('آیا از حذف این ویدیو مطمئن هستید؟')) {
			return;
		}

		const $btn = jQuery(this);
		const $item = $btn.closest('.gzseo-saved-video-item');
		const index = parseInt($btn.data('index'));
		const termId = jQuery('#gzseo-video-term-id').val();
		const taxonomy = jQuery('#gzseo-video-taxonomy').val();

		// غیرفعال کردن دکمه
		$btn.prop('disabled', true);

		// دریافت تمام ویدیوها
		jQuery.ajax({
			url: gzseo_video.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_get_taxonomy_video_updates',
				nonce: gzseo_video.nonce,
				term_id: termId
			},
			success: function(response) {
				if (response.success && response.data.videos) {
					let videos = response.data.videos;

					// بررسی اینکه ایندکس معتبر است
					if (index >= 0 && index < videos.length) {
						// حذف ویدیوی مورد نظر
						videos.splice(index, 1);

						// ذخیره مجدد با آپدیت مستقیم term_meta
						jQuery.ajax({
							url: gzseo_video.ajax_url,
							type: 'POST',
							data: {
								action: 'gzseo_delete_taxonomy_video',
								nonce: gzseo_video.nonce,
								term_id: termId,
								taxonomy: taxonomy,
								video_index: index
							},
							success: function(deleteResponse) {
								if (deleteResponse.success) {
									// حذف از DOM
									$item.fadeOut(400, function() {
										jQuery(this).remove();

										// بررسی اگر ویدیویی نماند
										if (jQuery('.gzseo-saved-video-item').length === 0) {
											displaySavedTaxonomyVideos([]);
										} else {
											// بارگذاری مجدد لیست با ایندکس های صحیح
											if (deleteResponse.data.videos) {
												displaySavedTaxonomyVideos(deleteResponse.data.videos);
											}
										}
									});

									// نمایش پیام موفقیت
									if (deleteResponse.data.message) {
										// می‌توانید یک notification نمایش دهید
									}
								} else {
									alert(deleteResponse.data.message || 'خطا در حذف ویدیو');
									$btn.prop('disabled', false);
								}
							},
							error: function() {
								alert('خطا در ارتباط با سرور');
								$btn.prop('disabled', false);
							}
						});
					} else {
						alert('ایندکس ویدیو نامعتبر است');
						$btn.prop('disabled', false);
					}
				} else {
					alert('خطا در دریافت لیست ویدیوها');
					$btn.prop('disabled', false);
				}
			},
			error: function() {
				alert('خطا در ارتباط با سرور');
				$btn.prop('disabled', false);
			}
		});
	});
	
	jQuery(document).on('click', '.gzseo-preview-saved-video', function(e) {
		e.preventDefault();
		e.stopPropagation();

		const videoIndex = jQuery(this).data('video-index');
		const allVideos = jQuery('.gzseo-saved-videos').data('all-videos');

		if (allVideos && allVideos[videoIndex]) {
			const videoData = allVideos[videoIndex];

			if (videoData.embed_code) {
				jQuery('#gzseo-taxonomy-modal-video').html(videoData.embed_code);
				jQuery('#gzseo-video-taxonomy-modal').addClass('active');
				jQuery('body').css('overflow', 'hidden');
			} else {
				alert('کد embed برای این ویدیو موجود نیست');
			}
		} else {
			alert('ویدیو یافت نشد');
		}
	});
	
	jQuery(document).on('click', '.gzseo-video-action-btn.preview', function() {
		const videoData = jQuery(this).data('video');

		if (!videoData || !videoData.embed_code) return;

		jQuery('#gzseo-taxonomy-modal-video').html(videoData.embed_code);
		jQuery('#gzseo-video-taxonomy-modal').addClass('active');
		jQuery('body').css('overflow', 'hidden');
	});
	
	jQuery(document).on('click', '.gzseo-video-action-btn.remove', function() {
		if (!confirm('آیا از حذف این ویدیو مطمئن هستید؟')) {
			return;
		}

		const $btn = jQuery(this);
		const index = $btn.data('index');
		const termId = jQuery('#gzseo-video-term-id').val();

		// اینجا باید API call برای حذف ویدیو بزنی
		// فعلاً فقط از DOM حذف می‌کنیم
		$btn.closest('.gzseo-saved-video-item').fadeOut(function() {
			jQuery(this).remove();
		});
	});
	
	jQuery(document).ready(function() {
		const termId = jQuery('#gzseo-video-term-id').val();

		if (termId) {

			jQuery.ajax({
				url: gzseo_video.ajax_url,
				type: 'POST',
				data: {
					action: 'gzseo_get_taxonomy_video_updates',
					nonce: gzseo_video.nonce,
					term_id: termId
				},
				success: function(response) {
					if (response.success && response.data.videos && response.data.videos.length > 0) {
						displaySavedTaxonomyVideos(response.data.videos);
						jQuery('.gzseo-saved-videos-section').slideDown();

						// تنظیم موقعیت
						if (response.data.position) {
							jQuery('#gzseo-video-taxonomy-display-position').val(response.data.position);
						}
					} else {

					}
				},
				error: function(xhr, status, error) {
					console.error('Error loading saved videos:', error);
				}
			});
		}
	});

	// Toggle Taxonomy Video Status
	jQuery(document).on('change', '.gzseo-taxonomy-video-toggle', function() {
		const $toggle = jQuery(this);
		const index = $toggle.data('index');
		const status = $toggle.is(':checked');
		const termId = jQuery('#gzseo-video-term-id').val();

		jQuery.ajax({
			url: gzseo_video.ajax_url,
			type: 'POST',
			data: {
				action: 'gzseo_toggle_taxonomy_video_status',
				nonce: gzseo_video.nonce,
				term_id: termId,
				video_index: index,
				status: status
			},
			success: function(response) {
				if (response.success) {
					const $item = $toggle.closest('.gzseo-bulk-video-item');
					if (status) {
						$item.addClass('active').removeClass('inactive');
						$item.find('.gzseo-status-text').text('فعال');
					} else {
						$item.removeClass('active').addClass('inactive');
						$item.find('.gzseo-status-text').text('غیرفعال');
					}
					jQuery('.gzseo-taxonomy-active-count').text(response.data.active_count);
				} else {
					$toggle.prop('checked', !status);
					alert(response.data.message);
				}
			},
			error: function() {
				$toggle.prop('checked', !status);
				alert('خطا در تغییر وضعیت ویدیو');
			}
		});
	});

	// Preview Taxonomy Bulk Video
	jQuery(document).on('click', '.gzseo-preview-taxonomy-bulk-video', function() {
		const videoData = jQuery(this).data('video');

		if (!videoData || !videoData.embed_code) return;

		jQuery('#gzseo-taxonomy-modal-video').html(videoData.embed_code);
		jQuery('#gzseo-video-taxonomy-modal').addClass('active');
		jQuery('body').css('overflow', 'hidden');
	});
	
	$('#gzseo-taxonomy-modal-close').on('click', function(e) {
        e.preventDefault();
		e.stopPropagation();
		$('#gzseo-video-taxonomy-modal').removeClass('active');
		$('#gzseo-taxonomy-modal-video').empty();
		$('body').css('overflow', '');
    }.bind(this));
	
	jQuery(document).on('click', '#gzseo-video-taxonomy-modal', function(e) {
		if (e.target === this) {
			jQuery(this).removeClass('active');
			jQuery('#gzseo-taxonomy-modal-video').html('');
			jQuery('body').css('overflow', '');
		}
	});

})(jQuery);