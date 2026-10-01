<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
   exit;
}

// Check user capability for admin access
if (!current_user_can('manage_options')) {
   wp_die(esc_html__('شما مجوز لازم برای دسترسی به این صفحه را ندارید', 'gzseo'), 403);
}

// تنظیمات صفحه‌بندی
$gzseo_items_per_page = 10;

// صفحه فعلی برای هر بخش - sanitized pagination parameters (read-only, no nonce needed)
$gzseo_content_page = isset($_GET['content_page']) ? max(1, absint(wp_unslash($_GET['content_page']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
$gzseo_image_page   = isset($_GET['image_page']) ? max(1, absint(wp_unslash($_GET['image_page']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
$gzseo_comment_page = isset($_GET['comment_page']) ? max(1, absint(wp_unslash($_GET['comment_page']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
$gzseo_video_page   = isset($_GET['video_page']) ? max(1, absint(wp_unslash($_GET['video_page']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.

// تابع کمکی برای ایجاد pagination
function gzseo_render_pagination($total_items, $current_page, $gzseo_items_per_page, $page_param) {
   $total_pages = ceil($total_items / $gzseo_items_per_page);
   
   if ($total_pages <= 1) return;
   
   $base_url = remove_query_arg($page_param);
   ?>
   <div class="gzseo-pagination">
       <div class="tablenav-pages">
           <span class="displaying-num">
                <?php
                /* translators: %d: Total items count. */
                echo esc_html( sprintf( __( '%d مورد', 'gzseo' ), $total_items ) );
                ?>
            </span>
           <span class="pagination-links">
               <?php if ($current_page > 1): ?>
                   <a class="first-page button" href="<?php echo esc_url( add_query_arg( $page_param, 1, $base_url ) ); ?>">
                       <span aria-hidden="true">«</span>
                   </a>
                   <a class="prev-page button" href="<?php echo esc_url( add_query_arg( $page_param, $current_page - 1, $base_url ) ); ?>">
                       <span aria-hidden="true">‹</span>
                   </a>
               <?php else: ?>
                   <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                   <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
               <?php endif; ?>
               
               <span class="paging-input">
                   <span class="tablenav-paging-text">
                       <?php echo esc_html( $current_page ); ?> <?php esc_html_e( 'از', 'gzseo' ); ?> <span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
                   </span>
               </span>
               
               <?php if ($current_page < $total_pages): ?>
                   <a class="next-page button" href="<?php echo esc_url( add_query_arg( $page_param, $current_page + 1, $base_url ) ); ?>">
                       <span aria-hidden="true">›</span>
                   </a>
                   <a class="last-page button" href="<?php echo esc_url( add_query_arg( $page_param, $total_pages, $base_url ) ); ?>">
                       <span aria-hidden="true">»</span>
                   </a>
               <?php else: ?>
                   <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                   <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
               <?php endif; ?>
           </span>
       </div>
   </div>
   <?php
}

// دریافت آمار محتواهای bulk
 $gzseo_content_update = new GZSEO_Content_Update();
 $gzseo_bulk_posts = $gzseo_content_update->get_posts_with_bulk_content();
 $gzseo_total_bulk_contents = 0;

foreach ($gzseo_bulk_posts as $gzseo_post_data) {
   $gzseo_total_bulk_contents += $gzseo_post_data->content_count;
}

// دریافت آمار تصاویر شاخص bulk
 $gzseo_featured_image_ai = new GZSEO_Featured_Image_AI();
 $gzseo_image_bulk_posts = $gzseo_featured_image_ai->get_posts_with_bulk_featured_images();
 $gzseo_total_bulk_images = $gzseo_featured_image_ai->get_bulk_featured_images_count();

// دریافت آمار کامنت‌های bulk
 $gzseo_comment_generator = new GZSEO_Comment_Generator_AI();
 $gzseo_posts_with_bulk_comments = $gzseo_comment_generator->get_posts_with_bulk_comments();
 $gzseo_total_bulk_comments = 0;

foreach ($gzseo_posts_with_bulk_comments as $gzseo_comment_post_data) {
   $gzseo_total_bulk_comments += $gzseo_comment_post_data->comment_count;
}

// دریافت آمار ویدیوهای bulk
 $gzseo_video_update = new GZSEO_Video_Update();
 $gzseo_posts_with_bulk_videos = $gzseo_video_update->get_posts_with_bulk_videos();
 $gzseo_total_bulk_posts = count($gzseo_posts_with_bulk_videos);
 $gzseo_total_bulk_videos = 0;

// محاسبه فقط ویدیوهای bulk
foreach ($gzseo_posts_with_bulk_videos as $gzseo_post_data) {
   $gzseo_videos = get_post_meta($gzseo_post_data->ID, '_gzseo_video_updates', true);
   if (is_array($gzseo_videos)) {
       $gzseo_bulk_count = 0;
       foreach ($gzseo_videos as $gzseo_video) {
           if (isset($gzseo_video['bulk_generated']) && $gzseo_video['bulk_generated'] === true) {
               $gzseo_bulk_count++;
           }
       }
       $gzseo_post_data->video_count = $gzseo_bulk_count; // بروزرسانی تعداد فقط برای bulk
       $gzseo_total_bulk_videos += $gzseo_bulk_count;
   }
}

// فیلتر پست‌هایی که واقعاً ویدیو bulk دارند
 $gzseo_posts_with_bulk_videos = array_filter($gzseo_posts_with_bulk_videos, function($post) {
   return isset($post->video_count) && $post->video_count > 0;
});
 $gzseo_total_bulk_posts = count($gzseo_posts_with_bulk_videos);
?>

<div class="gzseo-bulk-content-stats">
   <h3><?php esc_html_e('آمار محتواهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?></h3>

   <div class="gzseo-stats-grid">
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-number"><?php echo esc_html( count( $gzseo_bulk_posts ) ); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('تعداد پست‌های دارای محتوای bulk', 'gzseo'); ?></div>
       </div>

       <div class="gzseo-stat-box">
           <div class="gzseo-stat-number"><?php echo esc_html( $gzseo_total_bulk_contents ); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('مجموع محتواهای تولید شده', 'gzseo'); ?></div>
       </div>
   </div>

   <?php if (!empty($gzseo_bulk_posts)): ?>
       <div class="gzseo-bulk-posts-list">
           <h4><?php esc_html_e('لیست پست‌های دارای محتوای bulk:', 'gzseo'); ?></h4>
           
           <?php 
           // محاسبه آیتم‌های صفحه فعلی
           $gzseo_content_offset = ($gzseo_content_page - 1) * $gzseo_items_per_page;
           $gzseo_content_items = array_slice($gzseo_bulk_posts, $gzseo_content_offset, $gzseo_items_per_page);
           ?>
           
           <table class="wp-list-table widefat fixed striped">
               <thead>
                   <tr>
                       <th><?php esc_html_e('عنوان پست', 'gzseo'); ?></th>
                       <th><?php esc_html_e('تعداد محتوا', 'gzseo'); ?></th>
                       <th><?php esc_html_e('عملیات', 'gzseo'); ?></th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($gzseo_content_items as $gzseo_post_data):
                       $post = get_post($gzseo_post_data->post_id);
                       if ($post):
                           ?>
                           <tr>
                               <td>
                                   <a href="<?php echo esc_url( get_edit_post_link($post->ID) ); ?>">
                                       <?php echo esc_html($post->post_title); ?>
                                   </a>
                               </td>
                               <td><?php echo esc_html( $gzseo_post_data->content_count ); ?></td>
                               <td>
                                   <a href="<?php echo esc_url( get_permalink($post->ID) ); ?>" target="_blank" class="button button-small">
                                       <?php esc_html_e('مشاهده', 'gzseo'); ?>
                                   </a>
                               </td>
                           </tr>
                           <?php
                       endif;
                   endforeach;
                   ?>
               </tbody>
           </table>
           
           <?php gzseo_render_pagination(count($gzseo_bulk_posts), $gzseo_content_page, $gzseo_items_per_page, 'content_page'); ?>
       </div>
   <?php endif; ?>
</div>

<!------------------------------------ گزارشات تصویر شاخص ------------------------------------->

<div class="gzseo-bulk-featured-stats">
   <h3>
       <span class="dashicons dashicons-format-image"></span>
       <?php esc_html_e('آمار تصاویر شاخص تولید شده با آپدیت دسته جمعی', 'gzseo'); ?>
   </h3>

   <div class="gzseo-stats-grid">
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-images-alt2"></span>
           </div>
           <div class="gzseo-stat-number">
               <?php echo esc_html( $gzseo_total_bulk_images ); ?>
           </div>
           <div class="gzseo-stat-label"><?php esc_html_e('تعداد پست‌های دارای تصویر bulk', 'gzseo'); ?></div>
       </div>

       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-format-gallery"></span>
           </div>
           <div class="gzseo-stat-number">
               <?php echo count($gzseo_image_bulk_posts); ?>
           </div>
           <div class="gzseo-stat-label"><?php esc_html_e('تصاویر شاخص فعال', 'gzseo'); ?></div>
       </div>
   </div>

   <?php if (!empty($gzseo_image_bulk_posts)): ?>
   <div class="gzseo-bulk-images-list">
       <h4><?php esc_html_e('لیست پست‌های دارای تصویر شاخص bulk:', 'gzseo'); ?></h4>
       
       <?php 
       // محاسبه آیتم‌های صفحه فعلی
       $gzseo_image_offset = ($gzseo_image_page - 1) * $gzseo_items_per_page;
       $gzseo_image_items = array_slice($gzseo_image_bulk_posts, $gzseo_image_offset, $gzseo_items_per_page);
       ?>
       
       <div class="gzseo-images-grid">
           <?php foreach ($gzseo_image_items as $gzseo_image_post_data):
               $gzseo_image_post = get_post($gzseo_image_post_data->ID);
               if ($gzseo_image_post):
                   $gzseo_thumbnail_url = wp_get_attachment_image_url($gzseo_image_post_data->thumbnail_id, 'thumbnail');
                   ?>
           <div class="gzseo-image-item">
               <div class="gzseo-image-preview">
                   <?php if ($gzseo_thumbnail_url): ?>
                   <img src="<?php echo esc_url($gzseo_thumbnail_url); ?>" alt="<?php echo esc_attr($gzseo_image_post->post_title); ?>">
                   <?php else: ?>
                   <div class="gzseo-no-image">
                       <span class="dashicons dashicons-format-image"></span>
                   </div>
                   <?php endif; ?>
               </div>
               <div class="gzseo-image-info">
                   <h5>
                       <a href="<?php echo esc_url( get_edit_post_link($gzseo_image_post->ID) ); ?>">
                           <?php echo esc_html($gzseo_image_post->post_title); ?>
                       </a>
                   </h5>
                   <div class="gzseo-image-meta">
                       <span class="dashicons dashicons-calendar"></span>
                       <?php
                       $gzseo_bulk_date = get_post_meta($gzseo_image_post->ID, '_gzseo_bulk_featured_image_date', true);
                       echo $gzseo_bulk_date ? esc_html( date_i18n('Y/m/d', strtotime($gzseo_bulk_date)) ) : esc_html__('نامشخص', 'gzseo');
                       ?>
                   </div>
                   <div class="gzseo-image-actions">
                       <a href="<?php echo esc_url( get_permalink($gzseo_image_post->ID) ); ?>" target="_blank" class="button button-small">
                           <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('مشاهده', 'gzseo'); ?>
                       </a>
                       <a href="<?php echo esc_url( wp_get_attachment_url($gzseo_image_post_data->thumbnail_id) ); ?>" target="_blank"
                           class="button button-small">
                           <span class="dashicons dashicons-download"></span> <?php esc_html_e('دانلود', 'gzseo'); ?>
                       </a>
                   </div>
               </div>
           </div>
           <?php
               endif;
           endforeach;
           ?>
       </div>
       
       <?php gzseo_render_pagination(count($gzseo_image_bulk_posts), $gzseo_image_page, $gzseo_items_per_page, 'image_page'); ?>
   </div>
   <?php endif; ?>
</div>

<!------------------------------------- گزارش آپدیت کامنت --------------------------------->
<div class="gzseo-bulk-comments-stats">
   <h3>
       <span class="dashicons dashicons-admin-comments"></span>
       <?php esc_html_e('آمار کامنت‌های تولید شده با آپدیت دسته جمعی', 'gzseo'); ?>
   </h3>
   
   <div class="gzseo-stats-grid">
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-admin-page"></span>
           </div>
           <div class="gzseo-stat-number"><?php echo count($gzseo_posts_with_bulk_comments); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('تعداد پست‌های دارای کامنت bulk', 'gzseo'); ?></div>
       </div>
       
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-admin-comments"></span>
           </div>
           <div class="gzseo-stat-number"><?php echo esc_html( $gzseo_total_bulk_comments ); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('مجموع کامنت‌های تولید شده', 'gzseo'); ?></div>
       </div>
       
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-thumbs-up"></span>
           </div>
           <div class="gzseo-stat-number"><?php esc_html_e('تجربه مثبت', 'gzseo'); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('لحن کامنت‌ها', 'gzseo'); ?></div>
       </div>
   </div>
   
   <?php if (!empty($gzseo_posts_with_bulk_comments)) : ?>
   <div class="gzseo-bulk-comments-list">
       <h4><?php esc_html_e('لیست پست‌های دارای کامنت bulk:', 'gzseo'); ?></h4>
       
       <?php 
       // محاسبه آیتم‌های صفحه فعلی
       $gzseo_comment_offset = ($gzseo_comment_page - 1) * $gzseo_items_per_page;
       $gzseo_comment_items = array_slice($gzseo_posts_with_bulk_comments, $gzseo_comment_offset, $gzseo_items_per_page);
       ?>
       
       <table class="wp-list-table widefat fixed striped">
           <thead>
               <tr>
                   <th><?php esc_html_e('عنوان پست', 'gzseo'); ?></th>
                   <th><?php esc_html_e('تعداد کامنت', 'gzseo'); ?></th>
                   <th><?php esc_html_e('آخرین کامنت', 'gzseo'); ?></th>
                   <th><?php esc_html_e('عملیات', 'gzseo'); ?></th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($gzseo_comment_items as $gzseo_post_data) : 
                   $post = get_post($gzseo_post_data->ID);
                   if ($post) :
               ?>
               <tr>
                   <td>
                       <strong>
                           <a href="<?php echo esc_url( get_edit_post_link($post->ID) ); ?>">
                               <?php echo esc_html($post->post_title); ?>
                           </a>
                       </strong>
                   </td>
                   <td>
                       <span class="gzseo-comment-count">
                           <?php echo esc_html( $gzseo_post_data->comment_count ); ?> <?php esc_html_e('کامنت', 'gzseo'); ?>
                       </span>
                   </td>
                   <td>
                       <?php echo esc_html( date_i18n('Y/m/d H:i', strtotime($gzseo_post_data->last_comment_date)) ); ?>
                   </td>
                   <td>
                       <a href="<?php echo esc_url( get_permalink($post->ID) ); ?>" target="_blank" class="button button-small">
                           <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('مشاهده', 'gzseo'); ?>
                       </a>
                       <a href="<?php echo esc_url( admin_url('edit-comments.php?p=' . $post->ID) ); ?>" class="button button-small">
                           <span class="dashicons dashicons-admin-comments"></span> <?php esc_html_e('کامنت‌ها', 'gzseo'); ?>
                       </a>
                       <button class="button button-small button-link-delete gzseo-delete-bulk-comments" 
                               data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                               data-comment-count="<?php echo esc_attr( $gzseo_post_data->comment_count ); ?>">
                           <span class="dashicons dashicons-trash"></span> <?php esc_html_e('حذف کامنت‌ها', 'gzseo'); ?>
                       </button>
                   </td>
               </tr>
               <?php 
                   endif;
               endforeach; 
               ?>
           </tbody>
       </table>
       
       <?php gzseo_render_pagination(count($gzseo_posts_with_bulk_comments), $gzseo_comment_page, $gzseo_items_per_page, 'comment_page'); ?>
   </div>
   
   <script>
   jQuery(document).ready(function($) {
       $('.gzseo-delete-bulk-comments').on('click', function() {
           const $btn = $(this);
           const postId = $btn.data('post-id');
           const commentCount = $btn.data('comment-count');
           
           if (!confirm(sprintf(__('آیا از حذف %d کامنت bulk این پست مطمئن هستید؟', 'gzseo'), commentCount))) {
               return;
           }
           
           $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('در حال حذف...', 'gzseo')); ?>);
           
           $.ajax({
               url: ajaxurl,
               type: 'POST',
               data: {
                   action: 'gzseo_delete_bulk_comments',
                   nonce: '<?php echo esc_attr( wp_create_nonce('gzseo_delete_bulk_comments') ); ?>',
                   post_id: postId
               },
               success: function(response) {
                   if (response.success) {
                       $btn.closest('tr').fadeOut(400, function() {
                           $(this).remove();
                       });
                   } else {
                       alert(<?php echo wp_json_encode(__('خطا در حذف کامنت‌ها', 'gzseo')); ?>);
                       $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + <?php echo wp_json_encode(__('حذف کامنت‌ها', 'gzseo')); ?>);
                   }
               },
               error: function() {
                   alert(<?php echo wp_json_encode(__('خطا در ارتباط با سرور', 'gzseo')); ?>);
                   $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + <?php echo wp_json_encode(__('حذف کامنت‌ها', 'gzseo')); ?>);
               }
           });
       });
   });
   </script>
   <?php endif; ?>
</div>

<!------------------------------------- گزارش آپدیت ویدیو --------------------------------->

<div class="gzseo-bulk-videos-stats">
   <h3>
       <span class="dashicons dashicons-video-alt3"></span>
       <?php esc_html_e('آمار ویدیوهای تولید شده با آپدیت دسته جمعی', 'gzseo'); ?>
   </h3>
   
   <div class="gzseo-stats-grid">
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-admin-page"></span>
           </div>
           <div class="gzseo-stat-number"><?php echo esc_html( $gzseo_total_bulk_posts ); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('تعداد پست‌های دارای ویدیو bulk', 'gzseo'); ?></div>
       </div>
       
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-video-alt2"></span>
           </div>
           <div class="gzseo-stat-number"><?php echo esc_html( $gzseo_total_bulk_videos ); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('مجموع ویدیوهای یافت شده', 'gzseo'); ?></div>
       </div>
       
       <div class="gzseo-stat-box">
           <div class="gzseo-stat-icon">
               <span class="dashicons dashicons-hidden"></span>
           </div>
           <div class="gzseo-stat-number"><?php esc_html_e('غیرفعال', 'gzseo'); ?></div>
           <div class="gzseo-stat-label"><?php esc_html_e('وضعیت پیش‌فرض', 'gzseo'); ?></div>
       </div>
   </div>
   
   <?php if (!empty($gzseo_posts_with_bulk_videos)) : ?>
   <div class="gzseo-bulk-videos-table">
       <h4><?php esc_html_e('لیست پست‌های دارای ویدیو bulk:', 'gzseo'); ?></h4>
       
       <?php 
       // محاسبه آیتم‌های صفحه فعلی
       $gzseo_video_offset = ($gzseo_video_page - 1) * $gzseo_items_per_page;
       $gzseo_video_items = array_slice($gzseo_posts_with_bulk_videos, $gzseo_video_offset, $gzseo_items_per_page);
       ?>
       
       <table class="wp-list-table widefat fixed striped">
           <thead>
               <tr>
                   <th><?php esc_html_e('عنوان پست', 'gzseo'); ?></th>
                   <th><?php esc_html_e('تعداد ویدیو', 'gzseo'); ?></th>
                   <th><?php esc_html_e('ویدیوهای فعال', 'gzseo'); ?></th>
                   <th><?php esc_html_e('تاریخ آپدیت', 'gzseo'); ?></th>
                   <th><?php esc_html_e('عملیات', 'gzseo'); ?></th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($gzseo_video_items as $gzseo_post_data) : 
                   $post = get_post($gzseo_post_data->ID);
                   if ($post) :
                       // دریافت تعداد ویدیوهای فعال
                       $gzseo_active_count = $gzseo_video_update->get_active_videos_count($gzseo_post_data->ID);
               ?>
               <tr>
                   <td>
                       <strong>
                           <a href="<?php echo esc_url( get_edit_post_link($post->ID) ); ?>">
                               <?php echo esc_html($post->post_title); ?>
                           </a>
                       </strong>
                   </td>
                   <td>
                       <span class="gzseo-video-count">
                           <?php echo esc_html( $gzseo_post_data->video_count ); ?>
                       </span>
                   </td>
                   <td>
                       <span class="gzseo-active-video-count <?php echo esc_attr( $gzseo_active_count > 0 ? 'has-active' : 'no-active' ); ?>">
                           <?php echo esc_html( $gzseo_active_count ); ?> <?php esc_html_e('از', 'gzseo'); ?> <?php echo esc_html( $gzseo_post_data->video_count ); ?>
                       </span>
                   </td>
                   <td>
                       <?php 
                       if ($gzseo_post_data->update_date) {
                           echo esc_html( date_i18n('Y/m/d H:i', strtotime($gzseo_post_data->update_date)) );
                       } else {
                           echo '-';
                       }
                       ?>
                   </td>
                   <td>
                       <a href="<?php echo esc_url( get_permalink($post->ID) ); ?>" target="_blank" class="button button-small">
                           <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('مشاهده', 'gzseo'); ?>
                       </a>
                       <a href="<?php echo esc_url( get_edit_post_link($post->ID) ); ?>#gzseo-video-update" class="button button-small">
                           <span class="dashicons dashicons-edit"></span> <?php esc_html_e('ویرایش', 'gzseo'); ?>
                       </a>
                       <button class="button button-small button-link-delete gzseo-delete-bulk-videos" 
                               data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                               data-video-count="<?php echo esc_attr( $gzseo_post_data->video_count ); ?>">
                           <span class="dashicons dashicons-trash"></span> <?php esc_html_e('حذف ویدیوها', 'gzseo'); ?>
                       </button>
                   </td>
               </tr>
               <?php 
                   endif;
               endforeach; 
               ?>
           </tbody>
       </table>
       
       <?php gzseo_render_pagination(count($gzseo_posts_with_bulk_videos), $gzseo_video_page, $gzseo_items_per_page, 'video_page'); ?>
   </div>
   
   <div class="gzseo-bulk-video-info">
       <h4>
           <span class="dashicons dashicons-info"></span>
           <?php esc_html_e('راهنما', 'gzseo'); ?>
       </h4>
       <ul>
           <li><?php esc_html_e('ویدیوهای تولید شده با آپدیت دسته جمعی به صورت پیش‌فرض غیرفعال هستند.', 'gzseo'); ?></li>
           <li><?php esc_html_e('برای فعال‌سازی ویدیوها، به صفحه ویرایش پست مراجعه کرده و در تب "آپدیت ویدیویی" ویدیوهای مورد نظر را فعال کنید.', 'gzseo'); ?></li>
           <li><?php esc_html_e('موقعیت نمایش ویدیوها بر اساس تنظیمات پیکربندی افزونه تعیین می‌شود.', 'gzseo'); ?></li>
           <li><?php esc_html_e('می‌توانید ویدیوهای bulk را به صورت دسته جمعی از این صفحه حذف کنید.', 'gzseo'); ?></li>
       </ul>
   </div>
   
   <script>
   jQuery(document).ready(function($) {
       $('.gzseo-delete-bulk-videos').on('click', function() {
           const $btn = $(this);
           const postId = $btn.data('post-id');
           const videoCount = $btn.data('video-count');
           
           if (!confirm(sprintf(__('آیا از حذف %d ویدیو bulk این پست مطمئن هستید؟', 'gzseo'), videoCount))) {
               return;
           }
           
           $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('در حال حذف...', 'gzseo')); ?>);
           
           $.ajax({
               url: ajaxurl,
               type: 'POST',
               data: {
                   action: 'gzseo_delete_bulk_videos',
                   nonce: '<?php echo esc_attr( wp_create_nonce('gzseo_delete_bulk_videos') ); ?>',
                   post_id: postId
               },
               success: function(response) {
                   if (response.success) {
                       $btn.closest('tr').fadeOut(400, function() {
                           $(this).remove();
                           
                           // بروزرسانی آمار
                           const currentPosts = parseInt($('.gzseo-stat-box:first .gzseo-stat-number').text());
                           const currentVideos = parseInt($('.gzseo-stat-box:nth-child(2) .gzseo-stat-number').text());
                           
                           $('.gzseo-stat-box:first .gzseo-stat-number').text(currentPosts - 1);
                           $('.gzseo-stat-box:nth-child(2) .gzseo-stat-number').text(currentVideos - videoCount);
                           
                           // اگر هیچ پستی باقی نمانده
                           if (currentPosts - 1 === 0) {
                               $('.gzseo-bulk-videos-table').html('<p>' . __('هیچ پستی با ویدیو bulk یافت نشد.', 'gzseo') . '</p>');
                           }
                       });
                   } else {
                       alert(<?php echo wp_json_encode(__('خطا در حذف ویدیوها: ', 'gzseo')); ?> + response.data.message);
                       $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + <?php echo wp_json_encode(__('حذف ویدیوها', 'gzseo')); ?>);
                   }
               },
               error: function() {
                   alert(<?php echo wp_json_encode(__('خطا در ارتباط با سرور', 'gzseo')); ?>);
                   $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + <?php echo wp_json_encode(__('حذف ویدیوها', 'gzseo')); ?>);
               }
           });
       });
   });
   </script>
   <?php endif; ?>
</div>

<style>
    .gzseo-bulk-content-stats {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .gzseo-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .gzseo-bulk-posts-list {
        margin-top: 30px;
    }

    .gzseo-bulk-posts-list h4 {
        margin-bottom: 15px;
    }

    /************************** تصویر شاخص ************************/
    .gzseo-bulk-featured-stats {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .gzseo-bulk-featured-stats h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        color: #333;
    }

    .gzseo-stat-box {
        text-align: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .gzseo-stat-icon {
        font-size: 36px;
        color: #f39c12;
        margin-bottom: 10px;
    }

    .gzseo-bulk-images-list {
        margin-top: 30px;
    }

    .gzseo-bulk-images-list h4 {
        margin-bottom: 15px;
    }

    .gzseo-images-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }

    .gzseo-image-item {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.2s;
    }

    .gzseo-image-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .gzseo-image-preview {
        height: 150px;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .gzseo-image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .gzseo-no-image {
        font-size: 48px;
        color: #ccc;
    }

    .gzseo-image-info {
        padding: 15px;
    }

    .gzseo-image-info h5 {
        margin: 0 0 10px 0;
        font-size: 14px;
    }

    .gzseo-image-info h5 a {
        text-decoration: none;
        color: #333;
    }

    .gzseo-image-info h5 a:hover {
        color: #2271b1;
    }

    .gzseo-image-meta {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .gzseo-image-actions {
        display: flex;
        gap: 5px;
    }

    .gzseo-image-actions .button {
        font-size: 12px;
        padding: 2px 8px;
        height: auto;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .gzseo-image-actions .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    /********************** بخش گزارش کامنت ها *********************/
    .gzseo-bulk-comments-stats {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .gzseo-bulk-comments-stats h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        color: #333;
    }

    .gzseo-bulk-comments-list {
        margin-top: 30px;
    }

    .gzseo-bulk-comments-list h4 {
        margin-bottom: 15px;
    }

    .gzseo-comment-count {
        background: #f0f0f0;
        padding: 3px 8px;
        border-radius: 3px;
        font-weight: 600;
        color: #ff9800;
    }

    .button-link-delete {
        color: #a00 !important;
    }

    .button-link-delete:hover {
        color: #dc3232 !important;
    }

    /**************************** بخش گزارش ویدیوها ****************************/
    .gzseo-bulk-videos-stats {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .gzseo-bulk-videos-stats h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        color: #333;
    }

    .gzseo-video-count {
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 8px;
        border-radius: 3px;
        font-weight: 600;
    }

    .gzseo-active-video-count {
        font-size: 13px;
        padding: 3px 8px;
        border-radius: 3px;
    }

    .gzseo-active-video-count.has-active {
        background: #e8f5e9;
        color: #388e3c;
    }

    .gzseo-active-video-count.no-active {
        background: #fff3e0;
        color: #f57c00;
    }

    .gzseo-bulk-videos-table {
        margin-top: 30px;
    }

    .gzseo-bulk-videos-table h4 {
        margin-bottom: 15px;
    }

    .gzseo-bulk-video-info {
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .gzseo-bulk-video-info h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 15px;
        color: #1976d2;
    }

    .gzseo-bulk-video-info ul {
        margin: 0;
        padding-right: 20px;
    }

    .gzseo-bulk-video-info li {
        margin-bottom: 8px;
        line-height: 1.6;
    }

    /* Pagination Styles */
    .gzseo-pagination {
        margin-top: 20px;
        text-align: left;
    }

    .gzseo-pagination .tablenav-pages {
        float: none;
        margin: 0;
        height: auto;
    }

    .gzseo-pagination .displaying-num {
        margin-left: 10px;
    }

    .gzseo-pagination .pagination-links {
        margin-right: 10px;
    }

    .gzseo-pagination .button {
        margin: 0 2px;
    }

    .gzseo-pagination .paging-input {
        margin: 0 8px;
    }
</style>
