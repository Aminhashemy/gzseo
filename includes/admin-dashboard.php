<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch remote content data from API
 * 
 * @return array|false Returns array of content items or false on failure
 */
function gzseo_fetch_remote_content() {
    // Check transient cache first (24 hours)
    $cached_content = get_transient('gzseo_remote_content');
    if (false !== $cached_content) {
        return $cached_content;
    }
    
    // Fetch from API
    $response = wp_remote_get('https://gzseo.in/wp-json/mcc/v1/contents', array(
        'timeout' => 10,
        'limit_response_size' => 1 * MB_IN_BYTES,
        'sslverify' => true
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        return false;
    }
    
    // Get response body
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return false;
    }
    
    // Parse JSON
    $content = json_decode($body, true);
    if (!is_array($content)) {
        return false;
    }
    
    // Validate and sanitize content
    $sanitized_content = array();
    foreach ($content as $item) {
        if (!is_array($item) || !isset($item['type'])) {
            continue;
        }
        
        $sanitized_item = array(
            'type' => sanitize_text_field($item['type']),
            'alignment' => isset($item['alignment']) ? sanitize_text_field($item['alignment']) : 'left'
        );
        
        if ($item['type'] === 'video') {
            $sanitized_item['video_link'] = isset($item['video_link']) ? esc_url_raw($item['video_link']) : '';
            $sanitized_item['video_image'] = isset($item['video_image']) ? esc_url_raw($item['video_image']) : '';
            $sanitized_item['video_title'] = isset($item['video_title']) ? sanitize_text_field($item['video_title']) : '';
            $sanitized_item['video_description'] = isset($item['video_description']) ? wp_kses_post($item['video_description']) : '';
        } elseif ($item['type'] === 'banner') {
            $sanitized_item['banner_image'] = isset($item['banner_image']) ? esc_url_raw($item['banner_image']) : '';
            $sanitized_item['banner_link'] = isset($item['banner_link']) ? esc_url_raw($item['banner_link']) : '';
        }
        
        $sanitized_content[] = $sanitized_item;
    }
    
    // Cache for 24 hours
    set_transient('gzseo_remote_content', $sanitized_content, DAY_IN_SECONDS);
    
    return $sanitized_content;
}

/**
 * Render remote content (video and banners)
 * 
 * @param string $type Content type to render ('video' or 'banner')
 */
function gzseo_render_remote_content($type = 'all') {
    $content = gzseo_fetch_remote_content();
    
    if (false === $content || empty($content)) {
        return;
    }
    
    foreach ($content as $item) {
        if ($type !== 'all' && $item['type'] !== $type) {
            continue;
        }
        
        if ($item['type'] === 'video' && !empty($item['video_link'])) {
            echo '<div class="gzseo-dashboard-box">';
            echo '<div class="gzseo-dashboard-text">';
            if (!empty($item['video_title'])) {
                echo '<h3>' . esc_html($item['video_title']) . '</h3>';
            }
            if (!empty($item['video_description'])) {
                echo '<p>' . wp_kses_post($item['video_description']) . '</p>';
            }
            echo '</div>';
            echo '<div class="gzseo-dashboard-video">';
            echo '<video controls';
            if (!empty($item['video_image'])) {
                echo ' poster="' . esc_url($item['video_image']) . '"';
            }
            echo '>';
            echo '<source src="' . esc_url($item['video_link']) . '" type="video/mp4">';
            esc_html_e('Your browser does not support the video tag.', 'gzseo');
            echo '</video>';
            echo '</div>';
            echo '</div>';
        } elseif ($item['type'] === 'banner' && !empty($item['banner_image'])) {
            echo '<div class="gzseo-dashboard-links">';
            if (!empty($item['banner_link'])) {
                echo '<a href="' . esc_url($item['banner_link']) . '" target="_blank" rel="noopener noreferrer">';
            }
            echo '<img src="' . esc_url($item['banner_image']) . '" alt="' . esc_attr__('Banner', 'gzseo') . '">';
            if (!empty($item['banner_link'])) {
                echo '</a>';
            }
            echo '</div>';
        }
    }
}
?>
<div class="gzseo-wrap">
    <div class="gzseo-container">
        <div class="gzseo-sidebar">
            <div class="gzseo-sidebar-header">
                <div class="gzseo-sidebar-header-left">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="gzseo-sidebar-header-right">
                    <div class="gzseo-sidebar-title"><?php esc_html_e('تنظیمات جی زد سئو', 'gzseo'); ?></div>
                    <div class="gzseo-sidebar-version">
                        <?php
                        /* translators: %s: Plugin version. */
                        printf(esc_html__('نسخه %s', 'gzseo'), esc_html(GZSEO_VERSION));
                        ?>
                    </div>
                </div>
            </div>

            <div class="gzseo-sidebar-menu">
                <a href="#dashboard" class="gzseo-menu-item active" data-tab="dashboard">
                    <div class="gzseo-menu-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="gzseo-menu-content">
                        <div class="gzseo-menu-title"><?php esc_html_e('داشبورد', 'gzseo'); ?></div>
                        <div class="gzseo-menu-desc"><?php esc_html_e('معرفی افزونه', 'gzseo'); ?></div>
                    </div>
                </a>

                <a href="#search-console" class="gzseo-menu-item" data-tab="search-console">
                    <div class="gzseo-menu-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="gzseo-menu-content">
                        <div class="gzseo-menu-title"><?php esc_html_e('سرچ کنسول', 'gzseo'); ?></div>
                        <div class="gzseo-menu-desc"><?php esc_html_e('اتصال به سرچ کنسول و رفع ایراد', 'gzseo'); ?></div>
                    </div>
                </a>

                <a href="#ai" class="gzseo-menu-item" data-tab="ai">
                    <div class="gzseo-menu-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="gzseo-menu-content">
                        <div class="gzseo-menu-title"><?php esc_html_e('هوش مصنوعی', 'gzseo'); ?></div>
                        <div class="gzseo-menu-desc"><?php esc_html_e('اتصال به هوش مصنوعی و خرید شارژ', 'gzseo'); ?></div>
                    </div>
                </a>

                <a href="#bulk-update" class="gzseo-menu-item" data-tab="bulk-update">
                    <div class="gzseo-menu-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="gzseo-menu-content">
                        <div class="gzseo-menu-title"><?php esc_html_e('آپدیت دسته جمعی', 'gzseo'); ?></div>
                        <div class="gzseo-menu-desc"><?php esc_html_e('انتخاب محتواهای دلخواه برای بروزرسانی', 'gzseo'); ?></div>
                    </div>
                </a>

                <a href="#config" class="gzseo-menu-item" data-tab="config">
                    <div class="gzseo-menu-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="gzseo-menu-content">
                        <div class="gzseo-menu-title"><?php esc_html_e('پیکربندی افزونه', 'gzseo'); ?></div>
                        <div class="gzseo-menu-desc"><?php esc_html_e('تنظیمات مربوط به هوش مصنوعی', 'gzseo'); ?></div>
                    </div>
                </a>
            </div>
        </div>

        <div class="gzseo-content">
            <!-- Dashboard Tab -->
            <div class="gzseo-tab-content active" id="gzseo-dashboard">
                <div class="gzseo-content-header">
                    <div class="gzseo-header-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="gzseo-header-title"><?php esc_html_e('داشبورد', 'gzseo'); ?></div>
                    <div class="gzseo-header-action">
                        <a href="#" class="gzseo-refresh-dashboard">
                            <?php esc_html_e('بروزرسانی داشبورد', 'gzseo'); ?>
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>

                <div class="gzseo-content-body">
                    <?php
                    // Render video and banner content from API
                    gzseo_render_remote_content('all');
                    ?>
                </div>
            </div>

            <!-- Search Console Tab -->
            <div class="gzseo-tab-content" id="gzseo-search-console">
                <div class="gzseo-content-header">
                    <div class="gzseo-header-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="gzseo-header-title"><?php esc_html_e('اتصال به سرچ کنسول', 'gzseo'); ?></div>
                    <div class="gzseo-header-action">
                        <a href="#" class="gzseo-help-guide">
                            <?php esc_html_e('مشاهده راهنما', 'gzseo'); ?>
                            <i class="fas fa-lightbulb"></i>
                        </a>
                    </div>
                </div>

                <div class="gzseo-content-body">
                    <?php
                    // بررسی وضعیت اتصال سرچ کنسول
                    $gzseo_sc_connected = GZSEO_Search_Console::is_connected();
                    $gzseo_sc_email = GZSEO_Search_Console::get_connected_email();
                    $gzseo_sc_use_advanced = get_option('gzseo_sc_use_advanced', false);
                    $gzseo_sc_site_url = get_option('gzseo_sc_site_url', get_site_url());
                    $gzseo_sc_domain_type = get_option('gzseo_sc_domain_type', 'domain');
                    ?>

                    <?php if ($gzseo_sc_connected): ?>
                        <div class="gzseo-alert gzseo-alert-success">
                            <i class="fas fa-check"></i>
                            <?php esc_html_e('اتصال برقرار است!', 'gzseo'); ?>
                            <?php if ($gzseo_sc_email): ?>
                                <span style="margin-right: 10px;">
                                    <?php
                                    /* translators: %s: Search Console connected email. */
                                    printf(esc_html__('ایمیل: %s', 'gzseo'), esc_html($gzseo_sc_email));
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="gzseo-text-muted"><?php esc_html_e('اگر در دریافت اطلاعات مشکل دارید، می‌توانید قطع اتصال کرده و مجدداً احراز هویت کنید.', 'gzseo'); ?></p>
                    <?php else: ?>
                        <div class="gzseo-alert gzseo-alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php esc_html_e('برای دریافت اطلاعات از سرچ کنسول گوگل، ابتدا باید احراز هویت انجام دهید.', 'gzseo'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="gzseo-form-group">
                        <label>
                            <input type="checkbox" id="gzseo-advanced-settings" <?php checked($gzseo_sc_use_advanced); ?>>
                            <?php esc_html_e('تنظیمات حرفه ای', 'gzseo'); ?>
                        </label>
                    </div>

                    <div id="gzseo-advanced-panel" style="<?php echo esc_attr( $gzseo_sc_use_advanced ? '' : 'display: none;' ); ?>">
                        <div class="gzseo-form-group">
                            <label><?php esc_html_e('آدرس سایت', 'gzseo'); ?></label>
                            <input type="url" id="gzseo-site-url" class="gzseo-input"
                                value="<?php echo esc_attr($gzseo_sc_site_url); ?>" placeholder="<?php esc_attr_e('https://example.com', 'gzseo'); ?>">
                            <small style="display: block; margin-top: 5px; color: #666;"><?php esc_html_e('آدرس دقیق سایتی که در سرچ کنسول ثبت شده است', 'gzseo'); ?></small>
                        </div>

                        <div class="gzseo-form-group">
                            <label><?php esc_html_e('نوع دامنه', 'gzseo'); ?></label>
                            <select id="gzseo-domain-type" class="gzseo-select">
                                <option value="domain" <?php selected($gzseo_sc_domain_type, 'domain'); ?>>Domain</option>
                                <option value="url" <?php selected($gzseo_sc_domain_type, 'url'); ?>>URL</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <?php if (!$gzseo_sc_connected): ?>
                            <button class="gzseo-btn gzseo-btn-primary" id="gzseo-auth-google">
                                <i class="fas fa-plug"></i> <?php esc_html_e('احراز هویت با گوگل', 'gzseo'); ?>
                            </button>
                        <?php else: ?>
                            <button class="gzseo-btn gzseo-btn-danger" id="gzseo-disconnect-google"
                                style="margin-right: 10px;">
                                <i class="fas fa-unlink"></i> <?php esc_html_e('قطع اتصال', 'gzseo'); ?>
                            </button>
                            <button class="gzseo-btn gzseo-btn-secondary" id="gzseo-save-sc-settings">
                                <i class="fas fa-save"></i> <?php esc_html_e('ذخیره تنظیمات', 'gzseo'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="gzseo-sc-messages" style="margin-top: 20px;"></div>
                </div>
            </div>

            <!-- AI Tab -->
            <div class="gzseo-tab-content" id="gzseo-ai">
                <div class="gzseo-content-header">
                    <div class="gzseo-header-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="gzseo-header-title"><?php esc_html_e('هوش مصنوعی', 'gzseo'); ?></div>
                    <div class="gzseo-header-action">
                        <a href="#" class="gzseo-help-guide">
                            <?php esc_html_e('مشاهده راهنما', 'gzseo'); ?>
                            <i class="fas fa-lightbulb"></i>
                        </a>
                    </div>
                </div>

                <div class="gzseo-content-body">
                    <?php if (!$this->is_ai_connected()): ?>
                        <div class="gzseo-ai-intro">
                            <i class="fas fa-info-circle"></i>
                            <h4><?php esc_html_e('اتصال به اکانت هوش مصنوعی', 'gzseo'); ?></h4>
                            <p><?php esc_html_e('سلام، من سید محمد امین هاشمی متخصص و مدرس سئو هستم. حدود 10 سالی هست که در حوزه های مختلف دیجیتال مارکتینگ کار کردم (از طراحی و برنامه نویسی بگیر تا الان که سئو کار میکنم)...', 'gzseo'); ?></p>
                        </div>

                        <div class="gzseo-ai-auth">
                            <div class="gzseo-tabs">
                                <button class="gzseo-tab-btn active" data-tab="login"><?php esc_html_e('ورود به اکانت', 'gzseo'); ?></button>
                                <button class="gzseo-tab-btn" data-tab="register"><?php esc_html_e('ثبت نام جدید', 'gzseo'); ?></button>
                            </div>

                            <div class="gzseo-tab-panel active" id="gzseo-login-panel">
                                <form id="gzseo-ai-login-form">
                                    <div class="gzseo-form-group">
                                        <input type="email" name="email" placeholder="<?php esc_attr_e('ایمیل', 'gzseo'); ?>" class="gzseo-input" required>
                                    </div>
                                    <div class="gzseo-form-group">
                                        <input type="password" name="password" placeholder="<?php esc_attr_e('رمز عبور', 'gzseo'); ?>" class="gzseo-input"
                                            required>
                                    </div>
                                    <button type="submit" class="gzseo-btn gzseo-btn-primary"><?php esc_html_e('ورود', 'gzseo'); ?></button>
                                </form>
                            </div>

                            <div class="gzseo-tab-panel" id="gzseo-register-panel">
                                <form id="gzseo-ai-register-form">
                                    <div class="gzseo-form-group">
                                        <input type="text" name="name" placeholder="<?php esc_attr_e('نام', 'gzseo'); ?>" class="gzseo-input" required>
                                    </div>
                                    <div class="gzseo-form-group">
                                        <input type="email" name="email" placeholder="<?php esc_attr_e('ایمیل', 'gzseo'); ?>" class="gzseo-input" required>
                                    </div>
                                    <div class="gzseo-form-group">
                                        <input type="password" name="password" placeholder="<?php esc_attr_e('رمز عبور', 'gzseo'); ?>" class="gzseo-input"
                                            required>
                                    </div>
                                    <div class="gzseo-form-group">
                                        <input type="password" name="confirm_password" placeholder="<?php esc_attr_e('تکرار رمز عبور', 'gzseo'); ?>"
                                            class="gzseo-input" required>
                                    </div>
                                    <button type="submit" class="gzseo-btn gzseo-btn-primary"><?php esc_html_e('ثبت نام', 'gzseo'); ?></button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="gzseo-ai-status">
                            <div class="gzseo-ai-status-left">
                                <i class="fas fa-check"></i>
                                <?php
                                /* translators: %s: AI credits count. */
                                printf(esc_html__('اعتبار شما : %s توکن می باشد.', 'gzseo'), esc_html( number_format_i18n( $this->get_ai_credits() ) ));
                                ?>
                            </div>
                            <div class="gzseo-ai-status-right">
                                <span><?php echo esc_html($this->get_ai_email()); ?></span>
                                <button class="gzseo-btn gzseo-btn-purple" id="gzseo-ai-disconnect"><?php esc_html_e('قطع اتصال', 'gzseo'); ?></button>
                            </div>
                        </div>

                        <div class="gzseo-ai-purchase">
                            <?php esc_html_e('خرید یا افزایش اعتبار هوش مصنوعی', 'gzseo'); ?>
                        </div>

                        <div class="gzseo-ai-packages">
                            <div class="gzseo-package">
                                <div class="gzseo-package-title"><?php esc_html_e('پکیج پایه', 'gzseo'); ?></div>
                                <div class="gzseo-package-words"><?php esc_html_e('جمع کلمات : 10,000', 'gzseo'); ?></div>
                                <div class="gzseo-package-price"><?php esc_html_e('مبلغ : 50,000 تومان', 'gzseo'); ?></div>
                                <button class="gzseo-btn gzseo-btn-primary"><?php esc_html_e('افزایش اعتبار', 'gzseo'); ?></button>
                            </div>

                            <div class="gzseo-package">
                                <div class="gzseo-package-title"><?php esc_html_e('پکیج حرفه‌ای', 'gzseo'); ?></div>
                                <div class="gzseo-package-words"><?php esc_html_e('جمع کلمات : 50,000', 'gzseo'); ?></div>
                                <div class="gzseo-package-price"><?php esc_html_e('مبلغ : 200,000 تومان', 'gzseo'); ?></div>
                                <button class="gzseo-btn gzseo-btn-primary"><?php esc_html_e('افزایش اعتبار', 'gzseo'); ?></button>
                            </div>

                            <div class="gzseo-package">
                                <div class="gzseo-package-title"><?php esc_html_e('پکیج ویژه', 'gzseo'); ?></div>
                                <div class="gzseo-package-words"><?php esc_html_e('جمع کلمات : 100,000', 'gzseo'); ?></div>
                                <div class="gzseo-package-price"><?php esc_html_e('مبلغ : 350,000 تومان', 'gzseo'); ?></div>
                                <button class="gzseo-btn gzseo-btn-primary"><?php esc_html_e('افزایش اعتبار', 'gzseo'); ?></button>
                            </div>
                        </div>

                        <?php
                            // دریافت اطلاعات کاربر
                            $gzseo_user_data = get_option('gzseo_ai_user_data', array());
                            $gzseo_transactions = isset($gzseo_user_data['transactions']) ? $gzseo_user_data['transactions'] : array();
                        ?>
                        <div class="gzseo-ai-transactions">
                            <h3 style="margin-top: 30px; margin-bottom: 20px;">
                                <i class="fas fa-receipt"></i> <?php esc_html_e('تاریخچه تراکنش‌ها', 'gzseo'); ?>
                            </h3>

                            <div class="gzseo-transactions-table">
                                <table style="width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
                                    <thead>
                                        <tr style="background: #4e4b83; color: #fff;">
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('ردیف', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('نام پکیج', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('مبلغ (ریال)', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('اعتبار', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('شماره فاکتور', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('وضعیت', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: right;"><?php esc_html_e('تاریخ', 'gzseo'); ?></th>
                                            <th style="padding: 12px; text-align: center;"><?php esc_html_e('عملیات', 'gzseo'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gzseo_transactions as $gzseo_index => $gzseo_transaction): ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td style="padding: 12px;"><?php echo esc_html( $gzseo_index + 1 ); ?></td>
                                                <td style="padding: 12px;"><?php echo esc_html( $gzseo_transaction['plan_name'] ); ?></td>
                                                <td style="padding: 12px;"><?php echo esc_html( number_format_i18n( $gzseo_transaction['price'] ) ); ?></td>
                                                <td style="padding: 12px;">
                                                    <?php 
                                                    if (isset($gzseo_transaction['gpt_4o_credits'])) {
                                                        echo esc_html( number_format_i18n( $gzseo_transaction['gpt_4o_credits'] ) ) . ' ' . esc_html__( 'کلمه', 'gzseo' );
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 12px;"><?php echo esc_html( $gzseo_transaction['invoice'] ); ?></td>
                                                <td style="padding: 12px;">
                                                    <?php if ($gzseo_transaction['status'] === 'completed'): ?>
                                                        <span style="color: #28a745;">
                                                            <i class="fas fa-check-circle"></i> <?php esc_html_e('تکمیل شده', 'gzseo'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: #dc3545;">
                                                            <i class="fas fa-times-circle"></i> <?php esc_html_e('ناموفق', 'gzseo'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 12px;"><?php echo esc_html( $gzseo_transaction['updated_at'] ); ?></td>
                                                <td style="padding: 12px; text-align: center;">
                                                    <button class="gzseo-btn gzseo-btn-secondary gzseo-download-receipt" 
                                                            data-transaction-index="<?php echo esc_attr( $gzseo_index ); ?>"
                                                            style="padding: 5px 10px; font-size: 12px;">
                                                        <i class="fas fa-download"></i> <?php esc_html_e('دانلود رسید', 'gzseo'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Canvas برای تولید رسید PDF -->
                        <canvas id="gzseo-receipt-canvas" style="display: none;"></canvas>

                        <script>
                        jQuery(document).ready(function($) {
                            // دانلود رسید
                            $('.gzseo-download-receipt').on('click', function() {
                                var transactionIndex = $(this).data('transaction-index');
                                var transactions = <?php echo wp_json_encode($gzseo_transactions); ?>;
                                var transaction = transactions[transactionIndex];

                                // ایجاد canvas برای رسید
                                var canvas = document.getElementById('gzseo-receipt-canvas');
                                var ctx = canvas.getContext('2d');

                                // تنظیم ابعاد canvas
                                canvas.width = 600;
                                canvas.height = 800;

                                // پس‌زمینه سفید
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);

                                // Header
                                ctx.fillStyle = '#4e4b83';
                                ctx.fillRect(0, 0, canvas.width, 100);

                                // عنوان رسید
                                ctx.fillStyle = '#ffffff';
                                ctx.font = 'bold 28px Arial';
                                ctx.textAlign = 'center';
                                ctx.fillText(<?php echo wp_json_encode( __( 'رسید پرداخت', 'gzseo' ) ); ?>, canvas.width / 2, 60);

                                // اطلاعات سایت
                                ctx.fillStyle = '#333333';
                                ctx.font = '16px Arial';
                                ctx.textAlign = 'right';
                                ctx.fillText(<?php echo wp_json_encode(get_bloginfo('name')); ?>, canvas.width - 50, 140);
                                ctx.fillText(<?php echo wp_json_encode(get_bloginfo('url')); ?>, canvas.width - 50, 165);

                                // خط جدا کننده
                                ctx.beginPath();
                                ctx.moveTo(50, 190);
                                ctx.lineTo(canvas.width - 50, 190);
                                ctx.strokeStyle = '#cccccc';
                                ctx.stroke();

                                // اطلاعات تراکنش
                                var yPos = 230;
                                var lineHeight = 35;

                                ctx.font = '14px Arial';
                                ctx.fillStyle = '#666666';

                                // شماره فاکتور
                                ctx.textAlign = 'right';
                                ctx.fillText(<?php echo wp_json_encode( __( 'شماره فاکتور:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                ctx.textAlign = 'left';
                                ctx.fillText(transaction.invoice, 50, yPos);
                                yPos += lineHeight;

                                // تاریخ
                                ctx.textAlign = 'right';
                                ctx.fillText(<?php echo wp_json_encode( __( 'تاریخ:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                ctx.textAlign = 'left';
                                ctx.fillText(transaction.updated_at, 50, yPos);
                                yPos += lineHeight;

                                // نام پکیج
                                ctx.textAlign = 'right';
                                ctx.fillText(<?php echo wp_json_encode( __( 'نام پکیج:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                ctx.textAlign = 'left';
                                ctx.fillText(transaction.plan_name, 50, yPos);
                                yPos += lineHeight;

                                // اعتبار
                                if (transaction.gpt_4o_credits) {
                                    ctx.textAlign = 'right';
                                    ctx.fillText(<?php echo wp_json_encode( __( 'اعتبار دریافتی:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                    ctx.textAlign = 'left';
                                    ctx.fillText(transaction.gpt_4o_credits.toLocaleString() + ' ' + <?php echo wp_json_encode( __( 'توکن', 'gzseo' ) ); ?>, 50, yPos);
                                    yPos += lineHeight;
                                }

                                // مبلغ
                                ctx.textAlign = 'right';
                                ctx.fillText(<?php echo wp_json_encode( __( 'مبلغ پرداختی:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                ctx.textAlign = 'left';
                                ctx.font = 'bold 16px Arial';
                                ctx.fillStyle = '#333333';
                                ctx.fillText(transaction.price.toLocaleString() + ' ' + <?php echo wp_json_encode( __( 'ریال', 'gzseo' ) ); ?>, 50, yPos);
                                yPos += lineHeight;

                                // وضعیت
                                ctx.font = '14px Arial';
                                ctx.textAlign = 'right';
                                ctx.fillStyle = '#666666';
                                ctx.fillText(<?php echo wp_json_encode( __( 'وضعیت:', 'gzseo' ) ); ?>, canvas.width - 50, yPos);
                                ctx.textAlign = 'left';
                                if (transaction.status === 'completed') {
                                    ctx.fillStyle = '#28a745';
                                    ctx.fillText(<?php echo wp_json_encode( __( 'تکمیل شده', 'gzseo' ) ); ?>, 50, yPos);
                                } else {
                                    ctx.fillStyle = '#dc3545';
                                    ctx.fillText(<?php echo wp_json_encode( __( 'ناموفق', 'gzseo' ) ); ?>, 50, yPos);
                                }
                                yPos += lineHeight;

                                // خط جدا کننده
                                yPos += 20;
                                ctx.beginPath();
                                ctx.moveTo(50, yPos);
                                ctx.lineTo(canvas.width - 50, yPos);
                                ctx.strokeStyle = '#cccccc';
                                ctx.stroke();

                                // Footer
                                yPos = canvas.height - 100;
                                ctx.fillStyle = '#999999';
                                ctx.font = '12px Arial';
                                ctx.textAlign = 'center';
                                ctx.fillText(<?php echo wp_json_encode( __( 'این رسید توسط افزونه جی زد سئو تولید شده است', 'gzseo' ) ); ?>, canvas.width / 2, yPos);
                                ctx.fillText(<?php echo wp_json_encode(current_time('Y/m/d H:i:s')); ?>, canvas.width / 2, yPos + 20);

                                // تبدیل canvas به تصویر و دانلود
                                canvas.toBlob(function(blob) {
                                    var url = URL.createObjectURL(blob);
                                    var a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'receipt_' + transaction.invoice + '.png';
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    URL.revokeObjectURL(url);
                                });
                            });
                        });
                        </script>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bulk Update Tab -->
            <div class="gzseo-tab-content" id="gzseo-bulk-update">
                <div class="gzseo-content-header">
                    <div class="gzseo-header-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="gzseo-header-title"><?php esc_html_e( 'آپدیت دسته جمعی', 'gzseo' ); ?></div>
                    <div class="gzseo-header-action">
                        <a href="#" class="gzseo-help-guide">
                            <?php esc_html_e( 'مشاهده راهنما', 'gzseo' ); ?>
                            <i class="fas fa-lightbulb"></i>
                        </a>
                    </div>
                </div>

                <div class="gzseo-content-body">
                    <!-- نمایش آیتم‌های ذخیره شده -->
                    <div id="gzseo-saved-bulk-items">
                        <?php
                        $gzseo_saved_items = get_option('gzseo_bulk_update_items', array());
                        if (!empty($gzseo_saved_items)) {
                            foreach ($gzseo_saved_items as $gzseo_index => $gzseo_item) {
                                ?>
                                <div class="gzseo-bulk-update-item gzseo-saved-item" data-index="<?php echo esc_attr( $gzseo_index ); ?>">
                                    <button class="gzseo-btn gzseo-btn-danger gzseo-remove-saved-item" data-index="<?php echo esc_attr( $gzseo_index ); ?>"><?php esc_html_e( 'حذف', 'gzseo' ); ?></button>
                                    <button class="gzseo-btn gzseo-btn-secondary gzseo-edit-saved-item" data-index="<?php echo esc_attr( $gzseo_index ); ?>"><?php esc_html_e( 'ویرایش', 'gzseo' ); ?></button>
                                    
                                    <div class="gzseo-bulk-update-fields">
                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e( 'نوع نوشته', 'gzseo' ); ?></label>
                                            <select class="gzseo-select" name="post_type" disabled>
                                                <?php
                                                $gzseo_post_types = get_post_types(array('public' => true), 'objects');
                                                foreach ($gzseo_post_types as $gzseo_post_type) {
                                                    ?>
                                                    <option value="<?php echo esc_attr($gzseo_post_type->name); ?>" <?php selected($gzseo_item['post_type'], $gzseo_post_type->name); ?>>
                                                        <?php echo esc_html($gzseo_post_type->label); ?>
                                                    </option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e( 'آخرین آپدیت (روز)', 'gzseo' ); ?></label>
                                            <input type="number" class="gzseo-input" name="last_update" value="<?php echo esc_attr($gzseo_item['last_update']); ?>" disabled>
                                        </div>

                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e( 'نوع آپدیت', 'gzseo' ); ?></label>
                                            <select class="gzseo-select" name="update_type" disabled>
                                                <option value="table" <?php selected($gzseo_item['update_type'], 'table'); ?>><?php esc_html_e( 'جدول', 'gzseo' ); ?></option>
                                                <option value="comment" <?php selected($gzseo_item['update_type'], 'comment'); ?>><?php esc_html_e( 'کامنت', 'gzseo' ); ?></option>
                                                <option value="content_update" <?php selected($gzseo_item['update_type'], 'content_update'); ?>><?php esc_html_e( 'آپدیت محتوا', 'gzseo' ); ?></option>
                                                <option value="video_update" <?php selected($gzseo_item['update_type'], 'video_update'); ?>><?php esc_html_e( 'آپدیت ویدیویی', 'gzseo' ); ?></option>
                                                <option value="feature_img" <?php selected($gzseo_item['update_type'], 'feature_img'); ?>><?php esc_html_e( 'تصویر شاخص', 'gzseo' ); ?></option>
                                            </select>
                                        </div>

                                        <div class="gzseo-form-group">
                                            <label><?php esc_html_e( 'نوع تکسونومی', 'gzseo' ); ?></label>
                                            <select class="gzseo-select gzseo-taxonomy-type" name="taxonomy_type" disabled>
                                                <option value="all" <?php selected($gzseo_item['taxonomy_type'], 'all'); ?>><?php esc_html_e( 'همه موارد', 'gzseo' ); ?></option>
                                                <option value="category" <?php selected($gzseo_item['taxonomy_type'], 'category'); ?>><?php esc_html_e( 'دسته ها', 'gzseo' ); ?></option>
                                                <option value="post_tag" <?php selected($gzseo_item['taxonomy_type'], 'post_tag'); ?>><?php esc_html_e( 'برچسب ها', 'gzseo' ); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gzseo-bulk-update-taxonomies">
                                        <?php
                                        if (!empty($gzseo_item['selected_terms'])) {
                                            echo '<p>' . esc_html__( 'ترم‌های انتخاب شده:', 'gzseo' ) . ' ' . implode( ', ', array_map( 'esc_html', $gzseo_item['selected_terms'] ) ) . '</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>

                    <!-- فرم جدید -->
                    <h3 style="margin-top: 30px;"><?php esc_html_e( 'افزودن آیتم جدید', 'gzseo' ); ?></h3>
                    <div id="gzseo-bulk-update-container">
                        <div class="gzseo-bulk-update-item gzseo-new-item">
                            <div class="gzseo-bulk-update-fields">
                                <div class="gzseo-form-group">
                                    <label><?php esc_html_e( 'نوع نوشته', 'gzseo' ); ?></label>
                                    <select class="gzseo-select" name="post_type">
                                        <?php
                                        $gzseo_post_types = get_post_types(array('public' => true), 'objects');
                                        foreach ($gzseo_post_types as $gzseo_post_type) {
                                            ?>
                                            <option value="<?php echo esc_attr($gzseo_post_type->name); ?>">
                                                <?php echo esc_html($gzseo_post_type->label); ?>
                                            </option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="gzseo-form-group">
                                    <label><?php esc_html_e( 'آخرین آپدیت (روز)', 'gzseo' ); ?></label>
                                    <input type="number" class="gzseo-input" name="last_update" placeholder="<?php esc_attr_e('مثال: 30', 'gzseo'); ?>">
                                </div>

                                <div class="gzseo-form-group">
                                    <label><?php esc_html_e( 'نوع آپدیت', 'gzseo' ); ?></label>
                                    <select class="gzseo-select" name="update_type">
                                        <option value="table"><?php esc_html_e( 'جدول', 'gzseo' ); ?></option>
                                        <option value="comment"><?php esc_html_e( 'کامنت', 'gzseo' ); ?></option>
                                        <option value="content_update"><?php esc_html_e( 'آپدیت محتوا', 'gzseo' ); ?></option>
                                        <option value="video_update"><?php esc_html_e( 'آپدیت ویدیویی', 'gzseo' ); ?></option>
                                        <option value="feature_img"><?php esc_html_e( 'تصویر شاخص', 'gzseo' ); ?></option>
                                    </select>
                                </div>

                                <div class="gzseo-form-group">
                                    <label><?php esc_html_e( 'نوع تکسونومی', 'gzseo' ); ?></label>
                                    <select class="gzseo-select gzseo-taxonomy-type" name="taxonomy_type">
                                        <option value="all"><?php esc_html_e( 'همه موارد', 'gzseo' ); ?></option>
                                        <option value="category"><?php esc_html_e( 'دسته ها', 'gzseo' ); ?></option>
                                        <option value="post_tag"><?php esc_html_e( 'برچسب ها', 'gzseo' ); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="gzseo-bulk-update-taxonomies">
                                <!-- اینجا به صورت داینامیک پر می‌شود -->
                            </div>
                        </div>
                    </div>

                    <div class="gzseo-bulk-actions">
                        <button class="gzseo-btn gzseo-btn-primary" id="gzseo-save-bulk-update">
                            <i class="fas fa-save"></i>
                            <?php esc_html_e( 'ذخیره', 'gzseo' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Config Tab -->
            <div class="gzseo-tab-content" id="gzseo-config">
                <div class="gzseo-content-header">
                    <div class="gzseo-header-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="gzseo-header-title"><?php esc_html_e( 'پیکربندی افزونه', 'gzseo' ); ?></div>
                    <div class="gzseo-header-action">
                        <a href="#" class="gzseo-help-guide">
                            <?php esc_html_e( 'مشاهده راهنما', 'gzseo' ); ?>
                            <i class="fas fa-lightbulb"></i>
                        </a>
                    </div>
                </div>

                <div class="gzseo-content-body">
                    <div class="gzseo-config-tabs">
                        <button class="gzseo-config-tab active" data-config-tab="table-style"><?php esc_html_e( 'استایل جدول', 'gzseo' ); ?></button>
                        <button class="gzseo-config-tab" data-config-tab="comment-management"><?php esc_html_e( 'مدیریت کامنت', 'gzseo' ); ?></button>
                        <button class="gzseo-config-tab" data-config-tab="content-update"><?php esc_html_e( 'مدیریت آپدیت محتوا', 'gzseo' ); ?></button>
                        <button class="gzseo-config-tab" data-config-tab="featured-image"><?php esc_html_e( 'تصویر شاخص', 'gzseo' ); ?></button>
                        <button class="gzseo-config-tab" data-config-tab="schema"><?php esc_html_e( 'اسکیما', 'gzseo' ); ?></button>
                    </div>

                    <div class="gzseo-config-content">
                        <?php
                        // این کد را در ابتدای بخش Table Style Tab در فایل admin-dashboard.php اضافه کنید
                        // قبل از <div class="gzseo-config-panel active" id="config-table-style">

                        // دریافت تنظیمات ذخیره شده
                        $gzseo_table_styles = get_option('gzseo_table_styles', array());

                        // تنظیمات پیش‌فرض
                        $gzseo_defaults = array(
                            'use_predefined_styles' => 'true',
                            'table_position' => 'after_content',
                            'selected_style' => '1',
                            'header_bg' => '#4e4b83',
                            'header_text' => '#FFFFFF',
                            'odd_bg' => '#f4f4ff',
                            'odd_text' => '#333333',
                            'even_bg' => '#FFFFFF',
                            'even_text' => '#333333'
                        );

                        // ترکیب با مقادیر پیش‌فرض
                        $gzseo_settings = wp_parse_args($gzseo_table_styles, $gzseo_defaults);
                        ?>

                        <!-- Table Style Tab -->
                        <div class="gzseo-config-panel active" id="config-table-style">
                            <div class="gzseo-config-row">
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e( 'استفاده از استایل های آماده', 'gzseo' ); ?></h4>
                                    <p><?php esc_html_e( 'برای استفاده از استایل های قالب سایت خود، این گزینه را غیرفعال بگذارید.', 'gzseo' ); ?></p>
                                    <label>
                                        <input type="checkbox" <?php checked($gzseo_settings['use_predefined_styles'], 'true'); ?>> <?php esc_html_e( 'فعال / غیرفعال', 'gzseo' ); ?>
                                    </label>
                                </div>
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e( 'موقعیت جدول', 'gzseo' ); ?></h4>
                                    <p><?php esc_html_e( 'مشخص کنید به صورت سراسری جداول ساخته شده در کدام قسمت محتوا نمایش داده شود.', 'gzseo' ); ?></p>
                                    <select class="gzseo-select">
                                        <option value="before_content" <?php selected($gzseo_settings['table_position'], 'before_content'); ?>><?php esc_html_e( 'ابتدا محتوا', 'gzseo' ); ?></option>
                                        <option value="after_content" <?php selected($gzseo_settings['table_position'], 'after_content'); ?>><?php esc_html_e( 'انتها محتوا', 'gzseo' ); ?></option>
                                        <option value="after_first_paragraph" <?php selected($gzseo_settings['table_position'], 'after_first_paragraph'); ?>><?php esc_html_e( 'بعد از اولین پاراگراف', 'gzseo' ); ?></option>
                                        <option value="after_first_heading" <?php selected($gzseo_settings['table_position'], 'after_first_heading'); ?>><?php esc_html_e( 'بعد از اولین تیتر', 'gzseo' ); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="gzseo-table-styles">
                                <div class="gzseo-table-style <?php echo esc_attr( $gzseo_settings['selected_style'] == '1' ? 'active' : '' ); ?>" data-style="1">
                                    <img src="<?php echo esc_url( GZSEO_PLUGIN_URL . 'assets/images/table-style-1.png' ); ?>" alt="Style 1">
                                </div>
                                <div class="gzseo-table-style <?php echo esc_attr( $gzseo_settings['selected_style'] == '2' ? 'active' : '' ); ?>" data-style="2">
                                    <img src="<?php echo esc_url( GZSEO_PLUGIN_URL . 'assets/images/table-style-2.png' ); ?>" alt="Style 2">
                                </div>
                                <div class="gzseo-table-style <?php echo esc_attr( $gzseo_settings['selected_style'] == '3' ? 'active' : '' ); ?>" data-style="3">
                                    <img src="<?php echo esc_url( GZSEO_PLUGIN_URL . 'assets/images/table-style-3.png' ); ?>" alt="Style 3">
                                </div>
                                <div class="gzseo-table-style <?php echo esc_attr( $gzseo_settings['selected_style'] == '4' ? 'active' : '' ); ?>" data-style="4">
                                    <img src="<?php echo esc_url( GZSEO_PLUGIN_URL . 'assets/images/table-style-4.png' ); ?>" alt="Style 4">
                                </div>
                                <div class="gzseo-table-style <?php echo esc_attr( $gzseo_settings['selected_style'] == '5' ? 'active' : '' ); ?>" data-style="5">
                                    <img src="<?php echo esc_url( GZSEO_PLUGIN_URL . 'assets/images/table-style-5.png' ); ?>" alt="Style 5">
                                </div>
                                <div class="gzseo-table-style gzseo-custom-style <?php echo esc_attr( $gzseo_settings['selected_style'] == 'custom' ? 'active' : '' ); ?>" data-style="custom">
                                    <i class="fas fa-palette"></i>
                                </div>
                            </div>

                            <div class="gzseo-color-settings">
                                <div class="gzseo-color-group">
                                    <label><?php esc_html_e( 'سربرگ', 'gzseo' ); ?></label>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['header_bg']); ?>"> <?php esc_html_e( 'پس زمینه', 'gzseo' ); ?>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['header_text']); ?>"> <?php esc_html_e( 'متن', 'gzseo' ); ?>
                                </div>
                                <div class="gzseo-color-group">
                                    <label><?php esc_html_e( 'ردیف های فرد', 'gzseo' ); ?></label>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['odd_bg']); ?>"> <?php esc_html_e( 'پس زمینه', 'gzseo' ); ?>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['odd_text']); ?>"> <?php esc_html_e( 'متن', 'gzseo' ); ?>
                                </div>
                                <div class="gzseo-color-group">
                                    <label><?php esc_html_e( 'ردیف های زوج', 'gzseo' ); ?></label>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['even_bg']); ?>"> <?php esc_html_e( 'پس زمینه', 'gzseo' ); ?>
                                    <input type="color" value="<?php echo esc_attr($gzseo_settings['even_text']); ?>"> <?php esc_html_e( 'متن', 'gzseo' ); ?>
                                </div>
                            </div>

                            <div class="gzseo-table-preview">
                                <table>
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'ستون 1', 'gzseo' ); ?></th>
                                            <th><?php esc_html_e( 'ستون 2', 'gzseo' ); ?></th>
                                            <th><?php esc_html_e( 'ستون 3', 'gzseo' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php esc_html_e( 'محتوا 1', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 2', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 3', 'gzseo' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e( 'محتوا 4', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 5', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 6', 'gzseo' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e( 'محتوا 7', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 8', 'gzseo' ); ?></td>
                                            <td><?php esc_html_e( 'محتوا 9', 'gzseo' ); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="gzseo-table-save-button" style="margin-top: 30px;">
                                <button id="gzseo-save-table-styles" class="gzseo-btn gzseo-btn-primary">
                                    <i class="fas fa-save"></i> <?php esc_html_e('ذخیره تنظیمات', 'gzseo'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Comment Management Tab -->
                        <div class="gzseo-config-panel" id="config-comment-management">
                            <?php
                                // بازیابی مقادیر ذخیره شده
                                $gzseo_comment_word_count = get_option('gzseo_comment_word_count', '50');
                                $gzseo_comment_tone = get_option('gzseo_comment_tone', 'سوال جامع');
                            ?>
                            <div class="gzseo-config-row">
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e('لحن محتوا', 'gzseo'); ?></h4>
                                    <p><?php esc_html_e('مشخص کنید محتوای شما با چه لحنی تولید شود', 'gzseo'); ?></p>
                                    <select class="gzseo-select">
                                        <option value="" disabled="" selected=""><?php esc_html_e('لطفاً یک گزینه انتخاب کنید', 'gzseo'); ?></option>
                                        <option <?php selected($gzseo_comment_tone, 'سوال جامع، سوال کوتاه، تجربه منفی، تجربه مثبت'); ?> value="سوال جامع، سوال کوتاه، تجربه منفی، تجربه مثبت"><?php esc_html_e('رندوم', 'gzseo'); ?></option>
                                        <option <?php selected($gzseo_comment_tone, 'سوال جامع'); ?> value="سوال جامع"><?php esc_html_e('سوال جامع', 'gzseo'); ?></option>
                                        <option <?php selected($gzseo_comment_tone, 'سوال کوتاه'); ?> value="سوال کوتاه"><?php esc_html_e('سوال کوتاه', 'gzseo'); ?></option>
                                        <option <?php selected($gzseo_comment_tone, 'تجربه منفی'); ?> value="تجربه منفی"><?php esc_html_e('تجربه منفی', 'gzseo'); ?></option>
                                        <option <?php selected($gzseo_comment_tone, 'تجربه مثبت'); ?> value="تجربه مثبت"><?php esc_html_e('تجربه مثبت', 'gzseo'); ?></option>
                                    </select>
                                </div>
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e('تعداد کلمات', 'gzseo'); ?></h4>
                                    <p><?php esc_html_e('مشخص کنید محتوای شما شامل چند کلمه باشد', 'gzseo'); ?></p>
                                    <input type="number" class="gzseo-input" value="<?php echo esc_attr( $gzseo_comment_word_count ); ?>">
                                </div>
                            </div>

                            <button class="gzseo-btn gzseo-btn-primary">
                                <i class="fas fa-save"></i>
                                <?php esc_html_e('ذخیره تنظیمات', 'gzseo'); ?>
                            </button>
                        </div>

                        <!-- Content Update Tab -->
                        <div class="gzseo-config-panel" id="config-content-update">
                            <?php
                            // بازیابی مقادیر ذخیره شده
                            $gzseo_accordion_display = get_option('gzseo_accordion_display', false);
                            $gzseo_accordion_title = get_option('gzseo_accordion_title', 'کمی بیشتر بخوانید ...');
                            $gzseo_text_position = get_option('gzseo_text_position', 'after_content');
                            $gzseo_video_position = get_option('gzseo_video_position', 'after_content');
                            ?>
                            <h3><?php esc_html_e('تنظیمات آپدیت محتوای متنی', 'gzseo'); ?></h3>

                            <div class="gzseo-config-row">
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e('نمایش آکاردئونی', 'gzseo'); ?></h4>
                                    <p><?php esc_html_e('با فعال کردن این گزینه، آپدیت محتوا به جای حالت معمولی(پاراگراف)، به صورت کشویی (آکاردئون) نمایش داده خواهد شد.', 'gzseo'); ?></p>
                                    <label>
                                        <input type="checkbox" <?php checked($gzseo_accordion_display, 1); ?>> <?php esc_html_e('فعال/غیرفعال', 'gzseo'); ?>
                                    </label>
                                </div>
                                <div class="gzseo-config-col">
                                    <h4><?php esc_html_e('نمایش آکاردئونی – عنوان دکمه مشاهده بیشتر', 'gzseo'); ?></h4>
                                    <p><?php esc_html_e('میتوانید به جای متن پیشفرض "مشاهده بیشتر" در عنوان آکاردئون ها، متن دلخواه خود را وارد نمایید.', 'gzseo'); ?></p>
                                    <input type="text" class="gzseo-input" value="<?php echo esc_attr($gzseo_accordion_title); ?>">
                                </div>
                            </div>

                            <div class="gzseo-form-group">
                                <h4><?php esc_html_e('موقعیت نمایش پیشفرض', 'gzseo'); ?></h4>
                                <p><?php esc_html_e('مشخص کنید آپدیت محتواها به صورت پیش فرض در کجا نمایش داده شود.', 'gzseo'); ?></p>
                                <select class="gzseo-select">
                                    <option value="before_content" <?php selected($gzseo_text_position, 'before_content'); ?>><?php esc_html_e('ابتدا محتوا', 'gzseo'); ?></option>
                                    <option value="after_content" <?php selected($gzseo_text_position, 'after_content'); ?>><?php esc_html_e('انتها محتوا', 'gzseo'); ?></option>
                                    <option value="after_first_paragraph" <?php selected($gzseo_text_position, 'after_first_paragraph'); ?>><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                    <option value="after_first_heading" <?php selected($gzseo_text_position, 'after_first_heading'); ?>><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                                </select>
                            </div>

                            <hr class="gzseo-divider">

                            <h3><?php esc_html_e('تنظیمات آپدیت محتوای ویدیویی', 'gzseo'); ?></h3>

                            <div class="gzseo-form-group">
                                <h4><?php esc_html_e('موقعیت نمایش پیشفرض', 'gzseo'); ?></h4>
                                <p><?php esc_html_e('مشخص کنید، آپدیت محتواهای ویدیویی به صورت پیش فرض در کجا نمایش داده شود.', 'gzseo'); ?></p>
                                <select class="gzseo-select">
                                    <option value="before_content" <?php selected($gzseo_video_position, 'before_content'); ?>><?php esc_html_e('ابتدا محتوا', 'gzseo'); ?></option>
                                    <option value="after_content" <?php selected($gzseo_video_position, 'after_content'); ?>><?php esc_html_e('انتها محتوا', 'gzseo'); ?></option>
                                    <option value="after_first_paragraph" <?php selected($gzseo_video_position, 'after_first_paragraph'); ?>><?php esc_html_e('بعد از اولین پاراگراف', 'gzseo'); ?></option>
                                    <option value="after_first_heading" <?php selected($gzseo_video_position, 'after_first_heading'); ?>><?php esc_html_e('بعد از اولین تیتر', 'gzseo'); ?></option>
                                </select>
                            </div>

                            <button class="gzseo-btn gzseo-btn-primary">
                                <i class="fas fa-save"></i>
                                <?php esc_html_e('ذخیره تنظیمات', 'gzseo'); ?>
                            </button>
                        </div>

                        <?php
                            // نمایش پنل تنظیمات تصویر شاخص
                            if (class_exists('GZSEO_Featured_Image_Settings')) {
                                $gzseo_featured_image_settings = new GZSEO_Featured_Image_Settings();
                                $gzseo_featured_image_settings->render_config_panel();
                            }
                        ?>

                        <!-- Schema Tab -->
                        <div class="gzseo-config-panel" id="config-schema">
                            <?php
                            // بازیابی تنظیمات اسکیمای Person
                            $gzseo_person_schema = get_option('gzseo_person_schema', array());
                            $gzseo_selected_pages = isset($gzseo_person_schema['pages']) ? $gzseo_person_schema['pages'] : array();
                            ?>

                            <!-- تنظیمات اسکیمای لیست محصولات -->
                            <div class="gzseo-schema-section" style="margin-bottom: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                <h3 style="margin-top: 0;">
                                    <i class="fas fa-list"></i> <?php esc_html_e('تنظیمات اسکیمای لیست محصولات', 'gzseo'); ?>
                                </h3>
                                
                                <?php 
                                // بررسی فعال بودن ووکامرس
                                if (class_exists('WooCommerce')): 
                                    $gzseo_list_schema_enabled = get_option('gzseo_product_list_schema_enabled', false);
                                ?>
                                    <div class="gzseo-schema-alert gzseo-schema-alert-info" style="margin-bottom: 20px;">
                                        <i class="fas fa-info-circle"></i>
                                        <?php esc_html_e('با فعال کردن این گزینه، اسکیمای ItemList برای صفحات دسته‌بندی محصولات ووکامرس تولید می‌شود.', 'gzseo'); ?>
                                    </div>
                                    
                                    <div class="gzseo-form-group">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" id="gzseo-product-list-schema-toggle" <?php checked($gzseo_list_schema_enabled); ?> style="margin-left: 10px;">
                                            <span style="font-size: 16px;"><?php esc_html_e('فعال‌سازی اسکیمای لیست محصولات', 'gzseo'); ?></span>
                                        </label>
                                        <small style="display: block; margin-top: 10px; color: #666;">
                                            <?php esc_html_e('این اسکیما در صفحات دسته‌بندی محصولات، برچسب‌های محصولات و صفحه فروشگاه نمایش داده خواهد شد.', 'gzseo'); ?>
                                        </small>
                                    </div>
                                    
                                    <button type="button" class="gzseo-btn gzseo-btn-primary" id="gzseo-save-product-list-schema">
                                        <i class="fas fa-save"></i> <?php esc_html_e('ذخیره تنظیمات', 'gzseo'); ?>
                                    </button>
                                <?php else: ?>
                                    <div class="gzseo-schema-alert gzseo-schema-alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php esc_html_e('برای استفاده از این قابلیت، ابتدا باید افزونه ووکامرس را نصب و فعال کنید.', 'gzseo'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <hr class="gzseo-divider" style="margin: 30px 0;">
                            
                            <h3><?php esc_html_e('تنظیمات اسکیمای Person', 'gzseo'); ?></h3>
                            
                            <?php if (!empty($gzseo_person_schema)): ?>
                                <div class="gzseo-schema-alert gzseo-schema-alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <?php esc_html_e('یک اسکیمای Person قبلاً ذخیره شده است. برای ایجاد اسکیمای جدید، ابتدا اسکیمای فعلی را حذف کنید.', 'gzseo'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form id="gzseo-person-schema-form" <?php echo !empty($gzseo_person_schema) ? 'data-mode="edit"' : 'data-mode="create"'; ?>>
                                <!-- انتخاب صفحات - تمام عرض -->
                                <div class="gzseo-schema-form-group full-width">
                                    <label><?php esc_html_e('نوشته هایی که اسکیما در آن نشان داده شود', 'gzseo'); ?></label>
                                    <select class="gzseo-pages-select" name="pages[]" multiple="multiple" id="gzseo-schema-pages-select">
                                        <?php
                                        // فقط نمایش موارد انتخاب شده قبلی
                                        if (!empty($gzseo_selected_pages)) {
                                            foreach ($gzseo_selected_pages as $gzseo_page_id) {
                                                $gzseo_title = '';
                                                $gzseo_emoji = '';
                                                
                                                // موارد ثابت
                                                $gzseo_fixed_options = array(
                                                    'all' => array('emoji' => '🌐', 'title' => __( 'همه صفحات سایت', 'gzseo' )),
                                                    'front_page' => array('emoji' => '⚡', 'title' => __( 'صفحه اصلی', 'gzseo' )),
                                                    'blog_page' => array('emoji' => '⚡', 'title' => __( 'صفحه بلاگ', 'gzseo' )),
                                                    'search_page' => array('emoji' => '⚡', 'title' => __( 'صفحه نتایج جستجو', 'gzseo' )),
                                                    '404_page' => array('emoji' => '⚡', 'title' => __( 'صفحه 404', 'gzseo' ))
                                                );
                                                
                                                if (isset($gzseo_fixed_options[$gzseo_page_id])) {
                                                    $gzseo_emoji = $gzseo_fixed_options[$gzseo_page_id]['emoji'];
                                                    $gzseo_title = $gzseo_fixed_options[$gzseo_page_id]['title'];
                                                } elseif (strpos($gzseo_page_id, '_') !== false) {
                                                    list($type, $id) = explode('_', $gzseo_page_id, 2);
                                                    
                                                    switch ($type) {
                                                        case 'post':
                                                            $post = get_post($id);
                                                            if ($post) {
                                                                $gzseo_title = $post->post_title;
                                                                $gzseo_emoji = '📝';
                                                            }
                                                            break;
                                                        case 'page':
                                                            $page = get_post($id);
                                                            if ($page) {
                                                                $gzseo_title = $page->post_title;
                                                                $gzseo_emoji = '📄';
                                                            }
                                                            break;
                                                        case 'product':
                                                            if (class_exists('WooCommerce')) {
                                                                $gzseo_product = get_post($id);
                                                                if ($gzseo_product) {
                                                                    $gzseo_title = $gzseo_product->post_title;
                                                                    $gzseo_emoji = '🛍️';
                                                                }
                                                            }
                                                            break;
                                                        case 'category':
                                                            $gzseo_category = get_category($id);
                                                            if ($gzseo_category && !is_wp_error($gzseo_category)) {
                                                                $gzseo_title = $gzseo_category->name . ' (' . $gzseo_category->count . ' ' . __( 'نوشته', 'gzseo' ) . ')';
                                                                $gzseo_emoji = '📁';
                                                            }
                                                            break;
                                                        case 'tag':
                                                            $tag = get_tag($id);
                                                            if ($tag && !is_wp_error($tag)) {
                                                                $gzseo_title = $tag->name . ' (' . $tag->count . ' ' . __( 'نوشته', 'gzseo' ) . ')';
                                                                $gzseo_emoji = '🏷️';
                                                            }
                                                            break;
                                                    }
                                                }
                                                
                                                if ($gzseo_title) {
                                                    echo '<option value="' . esc_attr( $gzseo_page_id ) . '" selected>' . esc_html( $gzseo_emoji ) . ' ' . esc_html( $gzseo_title ) . '</option>';
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                    <small class="gzseo-schema-description"><?php esc_html_e( 'می‌توانید چندین صفحه را انتخاب کنید. برای جستجو شروع به تایپ کنید.', 'gzseo' ); ?></small>
                                </div>

                                <!-- Grid container برای فیلدهای دو ستونه -->
                                <div class="gzseo-schema-form-grid">
                                    <!-- نام و نام خانوادگی -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e('نام و نام خانوادگی', 'gzseo'); ?></label>
                                        <input type="text" class="gzseo-schema-input" name="name" value="<?php echo isset($gzseo_person_schema['name']) ? esc_attr($gzseo_person_schema['name']) : ''; ?>" placeholder="<?php esc_attr_e('نام و نام خانوادگی', 'gzseo'); ?>" required>
                                        <small class="gzseo-schema-description"><?php esc_html_e('مثال: علی ایران دوست', 'gzseo'); ?></small>
                                    </div>

                                    <!-- عنوان شغلی -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e('عنوان شغلی', 'gzseo'); ?></label>
                                        <input type="text" class="gzseo-schema-input" name="job_title" value="<?php echo isset($gzseo_person_schema['job_title']) ? esc_attr($gzseo_person_schema['job_title']) : ''; ?>" placeholder="<?php esc_attr_e('عنوان شغلی', 'gzseo'); ?>">
                                        <small class="gzseo-schema-description"><?php esc_html_e('مثال: برنامه نویس وب', 'gzseo'); ?></small>
                                    </div>

                                    <!-- تلفن -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e('تلفن', 'gzseo'); ?></label>
                                        <input type="tel" class="gzseo-schema-input" name="telephone" value="<?php echo isset($gzseo_person_schema['telephone']) ? esc_attr($gzseo_person_schema['telephone']) : ''; ?>" placeholder="<?php esc_attr_e('شماره تلفن', 'gzseo'); ?>">
                                        <small class="gzseo-schema-description"><?php esc_html_e('مثال: 09123456789', 'gzseo'); ?></small>
                                    </div>

                                    <!-- ایمیل -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e('ایمیل', 'gzseo'); ?></label>
                                        <input type="email" class="gzseo-schema-input" name="email" value="<?php echo isset($gzseo_person_schema['email']) ? esc_attr($gzseo_person_schema['email']) : ''; ?>" placeholder="<?php esc_attr_e('آدرس ایمیل', 'gzseo'); ?>">
                                        <small class="gzseo-schema-description"><?php esc_html_e('مثال: ali@example.com', 'gzseo'); ?></small>
                                    </div>

                                    <!-- آدرس وب سایت -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e( 'آدرس وب سایت', 'gzseo' ); ?></label>
                                        <input type="url" class="gzseo-schema-input" name="url" value="<?php echo isset( $gzseo_person_schema['url'] ) ? esc_attr( $gzseo_person_schema['url'] ) : esc_attr( get_site_url() ); ?>" placeholder="<?php esc_attr_e('آدرس وب سایت', 'gzseo'); ?>">
                                        <small class="gzseo-schema-description"><?php esc_html_e( 'مثال: https://example.com/ali', 'gzseo' ); ?></small>
                                    </div>

                                    <!-- تاریخ تولد -->
                                    <div class="gzseo-schema-form-group">
                                        <label><?php esc_html_e('تاریخ تولد (شمسی)', 'gzseo'); ?></label>
                                        <input type="text" class="gzseo-schema-input" name="birthdate" value="<?php echo isset($gzseo_person_schema['birthdate']) ? esc_attr($gzseo_person_schema['birthdate']) : ''; ?>" placeholder="<?php esc_attr_e('1375/05/15', 'gzseo'); ?>">
                                        <small class="gzseo-schema-description"><?php esc_html_e('مثال: 1375/05/15', 'gzseo'); ?></small>
                                    </div>
                                </div>

                                <!-- آدرس فیزیکی - تمام عرض -->
                                <div class="gzseo-schema-form-group full-width">
                                    <label><?php esc_html_e('آدرس', 'gzseo'); ?></label>
                                    <input type="text" class="gzseo-schema-input" name="address" value="<?php echo isset($gzseo_person_schema['address']) ? esc_attr($gzseo_person_schema['address']) : ''; ?>" placeholder="<?php esc_attr_e('آدرس محل سکونت', 'gzseo'); ?>">
                                    <small class="gzseo-schema-description"><?php esc_html_e('مثال: تهران، خیابان آزادی، پلاک 123', 'gzseo'); ?></small>
                                </div>

                                <!-- تصویر - تمام عرض -->
                                <div class="gzseo-schema-form-group full-width">
                                    <label><?php esc_html_e('تصویر', 'gzseo'); ?></label>
                                    <div class="gzseo-schema-image-selector">
                                        <input type="hidden" name="image_id" id="gzseo-person-image-id" value="<?php echo isset($gzseo_person_schema['image_id']) ? esc_attr($gzseo_person_schema['image_id']) : ''; ?>">
                                        <input type="text" class="gzseo-schema-input" id="gzseo-person-image-url" value="<?php echo isset($gzseo_person_schema['image_url']) ? esc_attr($gzseo_person_schema['image_url']) : ''; ?>" readonly>
                                        <button type="button" class="gzseo-btn gzseo-btn-secondary gzseo-schema-btn gzseo-schema-btn-secondary" id="gzseo-select-image"><?php esc_html_e('انتخاب تصویر', 'gzseo'); ?></button>
                                        <button type="button" class="gzseo-btn gzseo-btn-danger gzseo-schema-btn gzseo-schema-btn-danger" id="gzseo-remove-image" style="<?php echo esc_attr( empty( $gzseo_person_schema['image_id'] ) ? 'display:none;' : '' ); ?>"><?php esc_html_e('حذف', 'gzseo'); ?></button>
                                    </div>
                                    <?php if (!empty($gzseo_person_schema['image_url'])): ?>
                                        <div class="gzseo-schema-image-preview">
                                            <img src="<?php echo esc_url($gzseo_person_schema['image_url']); ?>" alt="<?php esc_attr_e('پیش‌نمایش تصویر', 'gzseo'); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <small class="gzseo-schema-description"><?php esc_html_e('عکس خود را از رسانه انتخاب کنید', 'gzseo'); ?></small>
                                </div>

                                <!-- توضیحات - تمام عرض -->
                                <div class="gzseo-schema-form-group full-width">
                                    <label><?php esc_html_e('توضیحات', 'gzseo'); ?></label>
                                    <textarea class="gzseo-schema-input" name="description" rows="3" placeholder="<?php esc_attr_e('توضیحات کوتاه درباره خودتان', 'gzseo'); ?>"><?php echo isset($gzseo_person_schema['description']) ? esc_textarea($gzseo_person_schema['description']) : ''; ?></textarea>
                                    <small class="gzseo-schema-description"><?php esc_html_e('مثال: یک برنامه نویس عاشق کد و قهوه', 'gzseo'); ?></small>
                                </div>

                                <!-- شبکه های اجتماعی - تمام عرض -->
                                <div class="gzseo-schema-form-group full-width">
                                    <label><?php esc_html_e('شبکه های اجتماعی', 'gzseo'); ?></label>
                                    <div id="gzseo-schema-social-links">
                                        <?php
                                        $gzseo_social_links = isset($gzseo_person_schema['social_links']) ? $gzseo_person_schema['social_links'] : array('');
                                        foreach ($gzseo_social_links as $gzseo_link):
                                        ?>
                                            <div class="gzseo-schema-social-link-item">
                                                <input type="url" class="gzseo-schema-input" name="social_links[]" value="<?php echo esc_attr($gzseo_link); ?>" placeholder="<?php esc_attr_e('https://twitter.com/username', 'gzseo'); ?>">
                                                <button type="button" class="gzseo-btn gzseo-btn-danger gzseo-schema-btn gzseo-schema-btn-danger gzseo-remove-social"><?php esc_html_e('حذف', 'gzseo'); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="gzseo-btn gzseo-btn-secondary gzseo-schema-btn gzseo-schema-btn-secondary" id="gzseo-schema-add-social">
                                        <i class="fas fa-plus"></i> <?php esc_html_e('افزودن شبکه اجتماعی', 'gzseo'); ?>
                                    </button>
                                    <small class="gzseo-schema-description"><?php esc_html_e('مثال: https://twitter.com/alimohammadi', 'gzseo'); ?></small>
                                </div>

                                <!-- دکمه های عملیات -->
                                <div class="gzseo-schema-form-actions">
                                    <?php if (empty($gzseo_person_schema)): ?>
                                        <button type="submit" class="gzseo-btn gzseo-btn-primary gzseo-schema-btn gzseo-schema-btn-primary">
                                            <i class="fas fa-save"></i> <?php esc_html_e('ذخیره اسکیما', 'gzseo'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="gzseo-btn gzseo-btn-primary gzseo-schema-btn gzseo-schema-btn-primary">
                                            <i class="fas fa-save"></i> <?php esc_html_e('بروزرسانی اسکیما', 'gzseo'); ?>
                                        </button>
                                        <button type="button" class="gzseo-btn gzseo-btn-danger gzseo-schema-btn gzseo-schema-btn-danger" id="gzseo-delete-schema">
                                            <i class="fas fa-trash"></i> <?php esc_html_e('حذف اسکیما', 'gzseo'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
