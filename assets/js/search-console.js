/**
 * GZ SEO Search Console JavaScript
 * 
 * @package GZ_SEO
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    const GZSEOSearchConsole = {
        
        // Properties
        authWindow: null,
        checkInterval: null,
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.toggleAdvancedSettings();
        },
        
        // Bind events
        bindEvents: function() {
            // Advanced settings checkbox
            $('#gzseo-sc-advanced').on('change', this.toggleAdvancedSettings.bind(this));
            
            // Auth button
            $('#gzseo-sc-auth').on('click', this.handleAuth.bind(this));
            
            // Disconnect button
            $('#gzseo-sc-disconnect').on('click', this.handleDisconnect.bind(this));
            
            // Save settings button
            $('#gzseo-sc-save-settings').on('click', this.saveSettings.bind(this));
        },
        
        // Toggle advanced settings
        toggleAdvancedSettings: function() {
            const isChecked = $('#gzseo-sc-advanced').is(':checked');
            const $advancedFields = $('.gzseo-sc-advanced-fields');
            
            if (isChecked) {
                $advancedFields.slideDown();
            } else {
                $advancedFields.slideUp();
            }
        },
        
        // Handle authentication
        handleAuth: function() {
            const $btn = $('#gzseo-sc-auth');
            const useAdvanced = $('#gzseo-sc-advanced').is(':checked');
            
            // Prepare data
            const data = {
                action: 'gzseo_search_console_auth',
                nonce: gzseo_ajax.nonce,
                use_advanced: useAdvanced
            };
            
            // Add advanced settings if enabled
            if (useAdvanced) {
                data.site_url = $('#gzseo-site-url').val();
                data.domain_type = $('#gzseo-domain-type').val();
                
                // Validate
                if (!data.site_url) {
                    this.showMessage('error', 'لطفا آدرس سایت را وارد کنید');
                    return;
                }
            }
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال اتصال...');
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // Open auth window
                        this.openAuthWindow(response.data.auth_url);
                        
                        // Start checking for completion
                        this.startCheckingAuth();
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr) => {
                    this.showMessage('error', 'خطا در برقراری ارتباط');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-plug"></i> احراز هویت با گوگل');
                }
            });
        },
        
        // Open authentication window
        openAuthWindow: function(url) {
            // Calculate center position
            const width = 600;
            const height = 700;
            const left = (window.screen.width - width) / 2;
            const top = (window.screen.height - height) / 2;
            
            // Open popup
            this.authWindow = window.open(
                url,
                'GoogleAuth',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );
            
            // Focus on popup
            if (this.authWindow) {
                this.authWindow.focus();
            }
        },
        
        // Start checking for auth completion
        startCheckingAuth: function() {
            this.checkInterval = setInterval(() => {
                // Check if window is closed
                if (this.authWindow && this.authWindow.closed) {
                    this.stopCheckingAuth();
                    
                    // Check if connection was successful
                    this.checkConnectionStatus();
                }
            }, 1000);
        },
        
        // Stop checking
        stopCheckingAuth: function() {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
            }
        },
        
        // Check connection status
        checkConnectionStatus: function() {
            // Reload the page to show updated status
            window.location.reload();
        },
        
        // Handle disconnect
        handleDisconnect: function() {
            if (!confirm('آیا از قطع اتصال با سرچ کنسول گوگل مطمئن هستید؟')) {
                return;
            }
            
            const $btn = $('#gzseo-sc-disconnect');
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال قطع اتصال...');
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gzseo_search_console_disconnect',
                    nonce: gzseo_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        
                        // Reload page after 2 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: () => {
                    this.showMessage('error', 'خطا در قطع اتصال');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-unlink"></i> قطع اتصال');
                }
            });
        },
        
        // Save settings
        saveSettings: function() {
            const $btn = $('#gzseo-sc-save-settings');
            const useAdvanced = $('#gzseo-sc-advanced').is(':checked');
            
            // Prepare data
            const data = {
                action: 'gzseo_search_console_save_settings',
                nonce: gzseo_ajax.nonce,
                use_advanced: useAdvanced
            };
            
            // Add advanced settings if enabled
            if (useAdvanced) {
                data.site_url = $('#gzseo-site-url').val();
                data.domain_type = $('#gzseo-domain-type').val();
            }
            
            // Show loading
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...');
            
            // Make request
            $.ajax({
                url: gzseo_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                    } else {
                        this.showMessage('error', response.data.message);
                    }
                },
                error: () => {
                    this.showMessage('error', 'خطا در ذخیره تنظیمات');
                },
                complete: () => {
                    $btn.prop('disabled', false)
                        .html('<i class="fas fa-save"></i> ذخیره تنظیمات');
                }
            });
        },
        
        // Show message
        showMessage: function(type, message) {
            const $container = $('#gzseo-sc-messages');
            const $message = $(`
                <div class="gzseo-message gzseo-message-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `);
            
            $container.html($message);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        if ($('#gzseo-search-console-tab').length > 0) {
            GZSEOSearchConsole.init();
        }
    });
    
})(jQuery);