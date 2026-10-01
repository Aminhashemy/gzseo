<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Normalize text for JSON responses that will be rendered in the UI.
 * Keep it as plain text (no HTML), without doing output-context escaping here.
 *
 * @param mixed $text Raw text.
 * @return string Plain text.
 */
function gzseo_safe_select2_text( $text ) {
    $text = is_scalar( $text ) ? (string) $text : '';
    $text = wp_strip_all_tags( $text, true );
    return trim( $text );
}


function gzseo_search_pages_ajax() {
    // Verify nonce and capability BEFORE reading any input
    if (!GZSEO_Security_Helpers::verify_ajax_request('gzseo_ajax_nonce', 'edit_posts')) {
        return;
    }
    
    // دریافت پارامترها
    $search = GZSEO_Security_Helpers::get_post( 'search', '' );
    $search = gzseo_safe_select2_text( $search );
    $page = GZSEO_Security_Helpers::get_post_int('page', 1);
    $per_page = 10;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
    }
    
    $results = array();
    
    // گزینه‌های ثابت - فقط در صفحه اول و بدون جستجو نمایش داده شوند
    if ($page === 1 && empty($search)) {
        $results[] = array(
            'text' => __( '⚡ صفحات خاص', 'gzseo' ),
            'children' => array(
                array('id' => 'all', 'text' => __( '🌐 همه صفحات سایت', 'gzseo' )),
                array('id' => 'front_page', 'text' => __( 'صفحه اصلی', 'gzseo' )),
                array('id' => 'blog_page', 'text' => __( 'صفحه بلاگ', 'gzseo' )),
                array('id' => 'search_page', 'text' => __( 'صفحه نتایج جستجو', 'gzseo' )),
                array('id' => '404_page', 'text' => __( 'صفحه 404', 'gzseo' ))
            )
        );
    }
    
    // جستجو در نوشته‌ها
    $posts_args = array(
        'post_type' => 'post',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => 'publish'
    );
    
    if (!empty($search)) {
        $posts_args['s'] = $search;
    }
    
    $posts = get_posts($posts_args);
    
    if (!empty($posts)) {
        $group = array(
            'text' => __( '📝 نوشته‌ها', 'gzseo' ),
            'children' => array()
        );
        
        foreach ($posts as $post) {
            $group['children'][] = array(
                'id' => 'post_' . $post->ID,
                'text' => ! empty( $post->post_title ) ? gzseo_safe_select2_text( $post->post_title ) : __( 'بدون عنوان', 'gzseo' )
            );
        }
        
        $results[] = $group;
    }
    
    // جستجو در برگه‌ها
    $pages_args = array(
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
        'post_status' => 'publish'
    );
    
    if (!empty($search)) {
        $pages_args['search'] = $search;
    }
    
    $pages = get_pages($pages_args);
    
    if (!empty($pages)) {
        $group = array(
            'text' => __( '📄 برگه‌ها', 'gzseo' ),
            'children' => array()
        );
        
        foreach ($pages as $page_item) {
            $group['children'][] = array(
                'id' => 'page_' . $page_item->ID,
                'text' => ! empty( $page_item->post_title ) ? gzseo_safe_select2_text( $page_item->post_title ) : __( 'بدون عنوان', 'gzseo' )
            );
        }
        
        $results[] = $group;
    }
    
    // جستجو در دسته‌بندی‌ها
    $categories_args = array(
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    );
    
    if (!empty($search)) {
        $categories_args['search'] = $search;
    }
    
    $categories = get_categories($categories_args);
    
    if (!empty($categories)) {
        $group = array(
            'text' => __( '📁 دسته‌بندی‌ها', 'gzseo' ),
            'children' => array()
        );
        
        foreach ($categories as $category) {
            $group['children'][] = array(
                'id' => 'category_' . $category->term_id,
                /* translators: %d: Number of posts in category. */
                'text' => gzseo_safe_select2_text( $category->name ) . ' (' . sprintf( _n( '%d نوشته', '%d نوشته', $category->count, 'gzseo' ), $category->count ) . ')'
            );
        }
        
        $results[] = $group;
    }
    
    // جستجو در برچسب‌ها
    $tags_args = array(
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    );
    
    if (!empty($search)) {
        $tags_args['search'] = $search;
    }
    
    $tags = get_tags($tags_args);
    
    if (!empty($tags)) {
        $group = array(
            'text' => __( '🏷️ برچسب‌ها', 'gzseo' ),
            'children' => array()
        );
        
        foreach ($tags as $tag) {
            $group['children'][] = array(
                'id' => 'tag_' . $tag->term_id,
                /* translators: %d: Number of posts with tag. */
                'text' => gzseo_safe_select2_text( $tag->name ) . ' (' . sprintf( _n( '%d نوشته', '%d نوشته', $tag->count, 'gzseo' ), $tag->count ) . ')'
            );
        }
        
        $results[] = $group;
    }
    
    // جستجو در محصولات (اگر ووکامرس فعال باشد)
    if (class_exists('WooCommerce')) {
        $products_args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        );
        
        if (!empty($search)) {
            $products_args['s'] = $search;
        }
        
        $products = get_posts($products_args);
        
        if (!empty($products)) {
            $group = array(
                'text' => __( '🛍️ محصولات', 'gzseo' ),
                'children' => array()
            );
            
            foreach ($products as $product) {
                $group['children'][] = array(
                    'id' => 'product_' . $product->ID,
                    'text' => ! empty( $product->post_title ) ? gzseo_safe_select2_text( $product->post_title ) : __( 'بدون عنوان', 'gzseo' )
                );
            }
            
            $results[] = $group;
        }
        
        // دسته‌بندی محصولات
        $product_cats = get_terms(array(
            'taxonomy' => 'product_cat',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'hide_empty' => false,
            'search' => $search
        ));
        
        if (!empty($product_cats) && !is_wp_error($product_cats)) {
            $group = array(
                'text' => __( '🛒 دسته‌بندی محصولات', 'gzseo' ),
                'children' => array()
            );
            
            foreach ($product_cats as $cat) {
                $group['children'][] = array(
                    'id' => 'product_cat_' . $cat->term_id,
                    /* translators: %d: Number of products. */
                    'text' => gzseo_safe_select2_text( $cat->name ) . ' (' . sprintf( _n( '%d محصول', '%d محصول', $cat->count, 'gzseo' ), $cat->count ) . ')'
                );
            }
            
            $results[] = $group;
        }
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
    }
    
    // ارسال نتایج
    wp_send_json_success(array(
        'results' => $results,
        'pagination' => array(
            'more' => count($results) >= $per_page
        )
    ));
}
add_action('wp_ajax_gzseo_search_pages', 'gzseo_search_pages_ajax');