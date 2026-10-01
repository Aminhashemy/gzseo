<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * توابع کمکی برای استفاده از اسکیمای Person
 * 
 * @package GZSeo
 * @since 2.0.0
 */

/**
 * Normalize and sanitize Person schema settings into canonical internal values.
 * This is sanitization (input validation), not output escaping.
 *
 * @param mixed $schema Raw option value.
 * @return array Normalized schema array.
 */
function gzseo_normalize_person_schema( $schema ) {
    if ( ! is_array( $schema ) ) {
        $schema = array();
    }

    $out = array();

    $out['name']        = isset( $schema['name'] ) ? sanitize_text_field( (string) $schema['name'] ) : '';
    $out['job_title']   = isset( $schema['job_title'] ) ? sanitize_text_field( (string) $schema['job_title'] ) : '';
    $out['telephone']   = isset( $schema['telephone'] ) ? sanitize_text_field( (string) $schema['telephone'] ) : '';
    $out['email']       = isset( $schema['email'] ) ? sanitize_email( (string) $schema['email'] ) : '';
    $out['url']         = isset( $schema['url'] ) ? esc_url_raw( (string) $schema['url'] ) : '';
    $out['address']     = isset( $schema['address'] ) ? sanitize_text_field( (string) $schema['address'] ) : '';
    $out['birthdate']   = isset( $schema['birthdate'] ) ? sanitize_text_field( (string) $schema['birthdate'] ) : '';
    $out['image_url']   = isset( $schema['image_url'] ) ? esc_url_raw( (string) $schema['image_url'] ) : '';
    $out['description'] = isset( $schema['description'] ) ? sanitize_textarea_field( (string) $schema['description'] ) : '';

    // Pages can be used by other components for display logic; keep as array of keys/ids.
    if ( isset( $schema['pages'] ) ) {
        $pages = $schema['pages'];
        if ( is_string( $pages ) ) {
            $pages = preg_split( '/[\r\n,]+/', $pages );
        }
        if ( is_array( $pages ) ) {
            $pages = array_values( array_filter( array_map( 'sanitize_text_field', $pages ) ) );
        } else {
            $pages = array();
        }
        $out['pages'] = $pages;
    } else {
        $out['pages'] = array();
    }

    // Social links may be stored as array or delimited string.
    $links = array();
    if ( isset( $schema['social_links'] ) ) {
        $raw_links = $schema['social_links'];
        if ( is_string( $raw_links ) ) {
            $raw_links = preg_split( '/[\r\n,]+/', $raw_links );
        }
        if ( is_array( $raw_links ) ) {
            $links = array_values( array_filter( array_map( 'esc_url_raw', $raw_links ) ) );
        }
    }
    $out['social_links'] = $links;

    return $out;
}

/**
 * دریافت تنظیمات اسکیمای Person
 */
function gzseo_get_person_schema() {
    $schema = get_option( 'gzseo_person_schema', array() );
    return gzseo_normalize_person_schema( $schema );
}

/**
 * بررسی وجود اسکیمای Person
 */
function gzseo_has_person_schema() {
    $schema = gzseo_get_person_schema();
    return !empty($schema) && !empty($schema['name']);
}

/**
 * دریافت نام شخص از اسکیما
 */
function gzseo_get_person_name() {
    $schema = gzseo_get_person_schema();
    return isset($schema['name']) ? $schema['name'] : '';
}

/**
 * دریافت شغل شخص از اسکیما
 */
function gzseo_get_person_job_title() {
    $schema = gzseo_get_person_schema();
    return isset($schema['job_title']) ? $schema['job_title'] : '';
}

/**
 * دریافت تصویر شخص از اسکیما
 */
function gzseo_get_person_image() {
    $schema = gzseo_get_person_schema();
    return isset($schema['image_url']) ? $schema['image_url'] : '';
}

/**
 * دریافت توضیحات شخص از اسکیما
 */
function gzseo_get_person_description() {
    $schema = gzseo_get_person_schema();
    return isset($schema['description']) ? $schema['description'] : '';
}

/**
 * دریافت شبکه‌های اجتماعی شخص از اسکیما
 */
function gzseo_get_person_social_links() {
    $schema = gzseo_get_person_schema();
    return isset($schema['social_links']) ? $schema['social_links'] : array();
}

/**
 * نمایش کارت ویزیت بر اساس اسکیمای Person
 * 
 * @param array $args آرگومان‌های نمایش
 * @return string HTML کارت ویزیت
 */
function gzseo_display_person_card($args = array()) {
    if (!gzseo_has_person_schema()) {
        return '';
    }
    
    $defaults = array(
        'show_image' => true,
        'show_job_title' => true,
        'show_description' => true,
        'show_contact' => true,
        'show_social' => true,
        'class' => 'gzseo-person-card'
    );
    
    $args = wp_parse_args($args, $defaults);
    $schema = gzseo_get_person_schema();

    // Ensure class is safe for attribute context; allow multiple classes separated by spaces.
    $args['class'] = preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $args['class'] );

    
    ob_start();
    ?>
    <div class="<?php echo esc_attr($args['class']); ?>">
        <?php if ($args['show_image'] && !empty($schema['image_url'])): ?>
            <div class="gzseo-person-image">
                <img src="<?php echo esc_url($schema['image_url']); ?>" alt="<?php echo esc_attr($schema['name']); ?>">
            </div>
        <?php endif; ?>
        
        <div class="gzseo-person-info">
            <h3 class="gzseo-person-name"><?php echo esc_html($schema['name']); ?></h3>
            
            <?php if ($args['show_job_title'] && !empty($schema['job_title'])): ?>
                <p class="gzseo-person-job"><?php echo esc_html($schema['job_title']); ?></p>
            <?php endif; ?>
            
            <?php if ($args['show_description'] && !empty($schema['description'])): ?>
                <p class="gzseo-person-description"><?php echo esc_html($schema['description']); ?></p>
            <?php endif; ?>
            
            <?php if ($args['show_contact']): ?>
                <div class="gzseo-person-contact">
                    <?php if (!empty($schema['telephone'])): ?>
                        <a href="<?php echo esc_url( 'tel:' . $schema['telephone'] ); ?>" class="gzseo-person-phone">
                            <i class="fas fa-phone"></i> <?php echo esc_html($schema['telephone']); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($schema['email'])): ?>
                        <a href="<?php echo esc_url( 'mailto:' . $schema['email'] ); ?>" class="gzseo-person-email">
                            <i class="fas fa-envelope"></i> <?php echo esc_html($schema['email']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($args['show_social'] && !empty($schema['social_links'])): ?>
                <div class="gzseo-person-social">
                    <?php foreach ($schema['social_links'] as $link): ?>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="gzseo-social-link">
                            <i class="fas fa-link"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * شورت‌کد برای نمایش کارت شخص
 */
function gzseo_person_card_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_image' => 'yes',
        'show_job_title' => 'yes',
        'show_description' => 'yes',
        'show_contact' => 'yes',
        'show_social' => 'yes',
        'class' => 'gzseo-person-card'
    ), $atts);
    
    // تبدیل yes/no به boolean
    foreach ( $atts as $key => $value ) {
        if ( $value === 'yes' || $value === 'no' ) {
            $atts[ $key ] = ( $value === 'yes' );
        }
    }

    if ( isset( $atts['class'] ) ) {
        $atts['class'] = preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $atts['class'] );
    }

    return gzseo_display_person_card( $atts );
}
add_shortcode('gzseo_person', 'gzseo_person_card_shortcode');
