<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gzseo-wrap">
    <div class="gzseo-page-header">
        <h1><?php esc_html_e( 'آپدیت دسته جمعی', 'gzseo' ); ?></h1>
    </div>
    
    <div class="gzseo-bulk-update-page">
        <?php
        $gzseo_saved_items = get_option('gzseo_bulk_update_items', array());
        
        if (empty($gzseo_saved_items)) {
            echo '<div class="gzseo-alert gzseo-alert-info">';
            echo '<i class="fas fa-info-circle"></i> ';
            esc_html_e( 'هیچ قانون آپدیت دسته جمعی تعریف نشده است. برای تعریف قانون جدید به بخش داشبورد > آپدیت دسته جمعی مراجعه کنید.', 'gzseo' );
            echo '</div>';
        } else {
            foreach ($gzseo_saved_items as $gzseo_index => $gzseo_item) {
                // Normalize and sanitize saved rule data (stored options are untrusted).
                $gzseo_post_type   = isset( $gzseo_item['post_type'] ) ? sanitize_key( (string) $gzseo_item['post_type'] ) : '';
                $gzseo_last_update = isset( $gzseo_item['last_update'] ) ? absint( $gzseo_item['last_update'] ) : 0;
                $gzseo_update_type = isset( $gzseo_item['update_type'] ) ? sanitize_key( (string) $gzseo_item['update_type'] ) : '';
                $gzseo_taxonomy_type = isset( $gzseo_item['taxonomy_type'] ) ? sanitize_key( (string) $gzseo_item['taxonomy_type'] ) : 'all';
                $gzseo_selected_terms = array();
                if ( ! empty( $gzseo_item['selected_terms'] ) && is_array( $gzseo_item['selected_terms'] ) ) {
                    $gzseo_selected_terms = array_values( array_filter( array_map( 'absint', $gzseo_item['selected_terms'] ) ) );
                }

                // محاسبه تعداد پست‌های واجد شرایط
                $gzseo_args = array(
                    'post_type' => $gzseo_post_type,
                    // Only the total is needed; do not load every post ID.
                    'posts_per_page' => 1,
                    'post_status' => 'publish', // فقط پست‌های منتشر شده
                    'fields' => 'ids',
                    'date_query' => array(
                        array(
                            'column' => 'post_modified',
                            'before' => $gzseo_last_update . ' days ago'
                        )
                    )
                );
                
                // اضافه کردن شرط تکسونومی اگر ترم‌هایی انتخاب شده باشند
                if ( ! empty( $gzseo_selected_terms ) ) {
                    $gzseo_tax_query = array();
                    
                    // گروه‌بندی ترم‌ها بر اساس تکسونومی
                    $gzseo_terms_by_tax = array();
                    foreach ( $gzseo_selected_terms as $gzseo_term_id ) {
                        $term = get_term($gzseo_term_id);
                        if ($term && !is_wp_error($term)) {
                            if (!isset($gzseo_terms_by_tax[$term->taxonomy])) {
                                $gzseo_terms_by_tax[$term->taxonomy] = array();
                            }
                            $gzseo_terms_by_tax[$term->taxonomy][] = $gzseo_term_id;
                        }
                    }
                    
                    // ایجاد tax_query
                    foreach ($gzseo_terms_by_tax as $taxonomy => $gzseo_term_ids) {
                        $gzseo_tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field' => 'term_id',
                            'terms' => $gzseo_term_ids,
                            'operator' => 'IN'
                        );
                    }
                    
                    if (!empty($gzseo_tax_query)) {
                        $gzseo_tax_query['relation'] = 'OR';
                        $gzseo_args['tax_query'] = $gzseo_tax_query;
                    }
                }
                
                $gzseo_query = new WP_Query($gzseo_args);
                $gzseo_total_posts = $gzseo_query->found_posts;
                
                // نمایش نوع آپدیت به فارسی - قابل ترجمه
                $gzseo_update_types = array(
                    'table'          => __( 'جدول', 'gzseo' ),
                    'comment'        => __( 'کامنت', 'gzseo' ),
                    'content_update' => __( 'آپدیت محتوا', 'gzseo' ),
                    'video_update'   => __( 'آپدیت ویدیویی', 'gzseo' ),
                    'feature_img'    => __( 'تصویر شاخص', 'gzseo' )
                );
                
                $gzseo_update_type_label = isset( $gzseo_update_types[ $gzseo_update_type ] ) ? $gzseo_update_types[ $gzseo_update_type ] : $gzseo_update_type;
                
                // دریافت label post type
                $gzseo_post_type_obj = $gzseo_post_type ? get_post_type_object( $gzseo_post_type ) : null;
                $gzseo_post_type_label = $gzseo_post_type_obj ? $gzseo_post_type_obj->label : $gzseo_post_type;
                ?>
                
                <div class="gzseo-bulk-box" data-index="<?php echo esc_attr( $gzseo_index ); ?>">
                    <div class="gzseo-bulk-info">
                        <p><strong><?php esc_html_e( 'نوع نوشته:', 'gzseo' ); ?></strong> <?php echo esc_html( $gzseo_post_type_label ); ?></p>
                        <p><strong><?php esc_html_e( 'نوع طبقه بندی:', 'gzseo' ); ?></strong> 
                            <?php 
                            if ( $gzseo_taxonomy_type === 'all' ) {
                                esc_html_e( 'همه محتواها (all)', 'gzseo' );
                            } elseif ( $gzseo_taxonomy_type === 'category' ) {
                                esc_html_e( 'دسته ها', 'gzseo' );
                            } elseif ( $gzseo_taxonomy_type === 'post_tag' ) {
                                esc_html_e( 'برچسب ها', 'gzseo' );
                            }
                            ?>
                        </p>
                        <p><strong><?php esc_html_e( 'نوع آپدیت:', 'gzseo' ); ?></strong> <?php echo esc_html( $gzseo_update_type_label ); ?></p>
                        <p><strong><?php esc_html_e( 'آخرین زمان آپدیت:', 'gzseo' ); ?></strong> <?php echo esc_html( $gzseo_last_update ); ?> <?php esc_html_e( 'روز گذشته', 'gzseo' ); ?></p>
                        <p><strong><?php
                            /* translators: %s: Post type label (e.g., posts, pages, products). */
                            printf( esc_html__( 'تعداد %s مطابق با انتخاب شما:', 'gzseo' ), esc_html( $gzseo_post_type_label ) );
                        ?></strong> <?php echo esc_html( number_format_i18n( $gzseo_total_posts ) ); ?></p>
                    </div>
                    
                    <div class="gzseo-bulk-actions">
                        <form class="gzseo-bulk-execute-form" data-index="<?php echo esc_attr( $gzseo_index ); ?>">
                            <?php wp_nonce_field( 'gzseo_bulk_execute', 'gzseo_bulk_execute_nonce' ); ?>
                            <div class="gzseo-form-inline">
                                <label><?php esc_html_e( 'تعداد برای آپدیت:', 'gzseo' ); ?></label>
                                <input type="number" name="update_count" class="gzseo-input" value="10" min="1" max="<?php echo esc_attr( $gzseo_total_posts ); ?>">
                                <button type="button" class="gzseo-btn gzseo-btn-secondary gzseo-preview-btn">
                                    <i class="fas fa-eye"></i> <?php esc_html_e( 'پیش نمایش', 'gzseo' ); ?>
                                </button>
                                <button type="submit" class="gzseo-btn gzseo-btn-primary gzseo-execute-btn">
                                    <i class="fas fa-play"></i> <?php esc_html_e( 'اجرا', 'gzseo' ); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="gzseo-preview-results" style="display: none;">
                            <!-- نتایج پیش نمایش اینجا نمایش داده می‌شود -->
                        </div>
                    </div>
                </div>
                
                <?php
            }
        }
        ?>
    </div>
</div>

<style>
.gzseo-bulk-update-page {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: flex-start;
    margin: 0 auto;
    max-width: 100%;
    padding: 0 1rem;
}

.gzseo-bulk-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    flex: 1;
    min-width: 320px;
    max-width: 450px;
    transition: box-shadow 0.3s ease, transform 0.2s ease;
}

.gzseo-bulk-box:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.gzseo-bulk-info p {
    margin: 8px 0;
    font-size: 14px;
    line-height: 1.5;
}

.gzseo-bulk-actions {
    display: flex;
    flex-direction: column;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.gzseo-form-inline {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.gzseo-form-inline label {
    white-space: nowrap;
    font-weight: 500;
}

.gzseo-form-inline .gzseo-input {
    width: 80px;
    min-width: 60px;
}

.gzseo-preview-results {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.gzseo-preview-results h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #495057;
}

.gzseo-preview-results ul {
    margin: 10px 0;
    padding-left: 20px;
}

.gzseo-page-header {
    margin-bottom: 30px;
    text-align: center;
}

.gzseo-page-header h1 {
    font-size: 28px;
    color: #333;
    margin: 0;
}

/* استایل‌های اضافی برای پیشرفت و لاگ */
.gzseo-progress-section {
    margin-top: 20px;
}

.gzseo-progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.gzseo-progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
}

.gzseo-progress-info {
    text-align: center;
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.gzseo-progress-log {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    line-height: 1.6;
}

.gzseo-log-item {
    padding: 5px 8px;
    margin-bottom: 5px;
    border-radius: 3px;
}

.gzseo-log-item.success {
    background: #e8f5e9;
    color: #2e7d32;
}

.gzseo-log-item.error {
    background: #ffebee;
    color: #c62828;
}

/* اضافه کردن فاصله بین دکمه‌ها */
.gzseo-btn + .gzseo-btn {
    margin-left: 10px;
}

/* استایل loading */
.gzseo-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
    .gzseo-bulk-update-page {
        flex-direction: column;
        gap: 1rem;
        padding: 0 0.5rem;
    }
    
    .gzseo-bulk-box {
        /*min-width: 100%;
        max-width: 100%;*/
		min-width:fit-content;
        padding: 15px;
    }
    
    .gzseo-form-inline {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .gzseo-form-inline .gzseo-input {
        width: 100%;
    }
    
    .gzseo-btn {
        width: 100%;
        text-align: center;
    }
    
    .gzseo-btn + .gzseo-btn {
        margin-left: 0;
        margin-top: 8px;
    }
    
    .gzseo-page-header h1 {
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .gzseo-bulk-update-page {
        padding: 0 0.25rem;
    }
    
    .gzseo-bulk-box {
        padding: 12px;
    }
    
    .gzseo-bulk-info p {
        font-size: 13px;
    }
    
    .gzseo-page-header h1 {
        font-size: 20px;
    }
}

/* تنظیمات برای صفحه‌های بزرگ */
@media (min-width: 1200px) {
    .gzseo-bulk-update-page {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .gzseo-bulk-box {
        max-width: 348px;
    }
}

/* تنظیمات خاص برای تبلت */
@media (min-width: 769px) and (max-width: 1024px) {
    .gzseo-bulk-box {
        min-width: 300px;
        max-width: 350px;
    }
}

/* حالت RTL برای زبان فارسی */
.gzseo-bulk-update-page {
    direction: rtl;
}

.gzseo-bulk-info p {
    text-align: right;
}

.gzseo-form-inline {
    direction: rtl;
}

.gzseo-preview-results ul {
    padding-right: 20px;
    padding-left: 0;
}
</style>
