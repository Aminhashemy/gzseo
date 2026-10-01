<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GZSEO_Product_List_Schema {

    /**
     * Meta key used to cache the built schema JSON for each taxonomy term.
     */
    const CACHE_META_KEY = 'gzseo_product_list_schema_cache';

    public function __construct() {
        // Front-end output
        add_action( 'wp_head', array( $this, 'output_product_archive_schema' ) );

        // Settings save
        add_action( 'wp_ajax_gzseo_save_product_list_schema_settings', array( $this, 'save_settings' ) );

        // ── Cache invalidation hooks ──────────────────────────────────────────

        // Product saved / updated (includes status, price, stock changes via admin)
        add_action( 'save_post_product', array( $this, 'invalidate_cache_for_product' ), 10, 1 );

        // Product deleted
        add_action( 'before_delete_post', array( $this, 'invalidate_cache_for_product' ), 10, 1 );

        // Product moved to trash
        add_action( 'wp_trash_post', array( $this, 'invalidate_cache_for_product' ), 10, 1 );

        // Product restored from trash
        add_action( 'untrashed_post', array( $this, 'invalidate_cache_for_product' ), 10, 1 );

        // Term assignment changed (product added to / removed from a category or tag)
        add_action( 'set_object_terms', array( $this, 'invalidate_cache_on_term_change' ), 10, 6 );

        // A term relationship row deleted (e.g. wp_delete_object_term_relationships)
        add_action( 'deleted_term_relationships', array( $this, 'invalidate_cache_for_terms' ), 10, 2 );

        // WooCommerce-specific: stock / price updated programmatically
        add_action( 'woocommerce_product_set_stock_status', array( $this, 'invalidate_cache_for_product' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock_status', array( $this, 'invalidate_cache_for_product' ), 10, 1 );
        add_action( 'woocommerce_product_set_stock', array( $this, 'invalidate_cache_for_product' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'invalidate_cache_for_product' ), 10, 1 );

        // Plugin settings saved – clear all schema caches
        add_action( 'update_option_gzseo_product_list_schema_enabled', array( $this, 'invalidate_all_caches' ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Front-end output
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Output ItemList schema in product archive pages.
     */
    public function output_product_archive_schema() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        if ( ! is_tax() && ! is_product_taxonomy() && ! is_shop() ) {
            return;
        }

        if ( ! get_option( 'gzseo_product_list_schema_enabled', false ) ) {
            return;
        }

        global $wp_query;

        if ( ! $wp_query->have_posts() ) {
            return;
        }

        // Determine the term ID for the current archive.
        $term_id = $this->get_current_term_id();

        // Try to serve from cache.
        $cached_schema = $this->get_cached_schema( $term_id );

        if ( false !== $cached_schema ) {
            // Serve from cache.
            echo '<!-- GZSEO Product List Schema (cached) -->' . PHP_EOL;
            echo "<script type=\"application/ld+json\">\n";
            // $cached_schema is already a validated JSON string stored by us.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $cached_schema;
            echo "\n</script>\n";
            return;
        }

        // Build fresh schema.
        $schema = $this->build_schema( $wp_query );

        if ( empty( $schema['itemListElement'] ) ) {
            return;
        }

        $json = wp_json_encode(
            $schema,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        // Store in cache.
        $this->set_cached_schema( $term_id, $json );

        echo '<!-- GZSEO Product List Schema -->' . PHP_EOL;
        echo "<script type=\"application/ld+json\">\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $json;
        echo "\n</script>\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schema builder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the ItemList schema array from a WP_Query result.
     *
     * Note: resets the query pointer back to before-the-loop after building.
     *
     * @param WP_Query $wp_query
     * @return array
     */
    private function build_schema( $wp_query ) {
        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => array(),
        );

        $position = 1;

        // Save query state so we don't disturb the main loop.
        $wp_query->rewind_posts();

        while ( $wp_query->have_posts() ) {
            $wp_query->the_post();

            $product_id = get_the_ID();
            $product    = wc_get_product( $product_id );

            if ( ! $product ) {
                continue;
            }

            $prices = $this->get_product_prices( $product );

            $item = array(
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => array(
                    '@type'       => 'Product',
                    'name'        => wp_strip_all_tags( (string) $product->get_name() ),
                    'url'         => esc_url_raw( get_permalink( $product->get_id() ) ),
                    'description' => wp_strip_all_tags( (string) $product->get_short_description() ),
                ),
            );

            // Image
            $image_id = $product->get_image_id();
            if ( $image_id ) {
                $image_url = wp_get_attachment_url( $image_id );
                if ( $image_url ) {
                    $item['item']['image'] = array( esc_url_raw( $image_url ) );
                }
            }

            // Aggregate rating
            $rating_count   = $product->get_rating_count();
            $average_rating = $product->get_average_rating();

            if ( $rating_count > 0 && $average_rating > 0 ) {
                $item['item']['aggregateRating'] = array(
                    '@type'       => 'AggregateRating',
                    'ratingValue' => number_format( (float) $average_rating, 2 ),
                    'reviewCount' => (int) $rating_count,
                );
            }

            // Offers / price
            if ( ! empty( $prices ) ) {
                if ( $prices['low_price'] === $prices['high_price'] ) {
                    if ( get_woocommerce_currency() === 'IRT' ) {
                        $current_currency = 'IRR';
                        $current_price    = $prices['low_price'] * 10;
                    } else {
                        $current_currency = get_woocommerce_currency();
                        $current_price    = $prices['low_price'];
                    }

                    $item['item']['offers'] = array(
                        '@type'        => 'Offer',
                        'price'        => $current_price,
                        'priceCurrency' => $current_currency,
                        'availability' => $product->is_in_stock()
                            ? 'https://schema.org/InStock'
                            : 'https://schema.org/OutOfStock',
                    );
                } else {
                    if ( get_woocommerce_currency() === 'IRT' ) {
                        $current_currency   = 'IRR';
                        $current_low_price  = $prices['low_price'] * 10;
                        $current_high_price = $prices['high_price'] * 10;
                    } else {
                        $current_currency   = get_woocommerce_currency();
                        $current_low_price  = $prices['low_price'];
                        $current_high_price = $prices['high_price'];
                    }

                    $item['item']['offers'] = array(
                        '@type'         => 'AggregateOffer',
                        'lowPrice'      => $current_low_price,
                        'highPrice'     => $current_high_price,
                        'priceCurrency' => $current_currency,
                        'offerCount'    => count( $product->get_children() ),
                    );
                }
            }

            // Brand
            $brands = wp_get_post_terms( $product_id, 'pa_brand' );
            if ( ! empty( $brands ) && ! is_wp_error( $brands ) ) {
                $item['item']['brand'] = array(
                    '@type' => 'Brand',
                    'name'  => wp_strip_all_tags( (string) $brands[0]->name ),
                );
            }

            // SKU
            $sku = $product->get_sku();
            if ( $sku ) {
                $item['item']['sku'] = $sku;
            }

            $schema['itemListElement'][] = $item;
            $position++;
        }

        wp_reset_postdata();

        return $schema;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cache helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the term_id (or a string key) that identifies the current archive page.
     * For the shop page we use the string 'shop'.
     *
     * @return int|string
     */
    private function get_current_term_id() {
        if ( is_shop() ) {
            return 'shop';
        }

        $queried = get_queried_object();

        if ( $queried instanceof WP_Term ) {
            return (int) $queried->term_id;
        }

        return 0;
    }

    /**
     * Retrieve cached schema JSON for a term.
     *
     * For regular taxonomy terms we use term_meta.
     * For the shop page (which has no term) we use a site option.
     *
     * @param int|string $term_id
     * @return string|false Cached JSON string, or false on miss.
     */
    private function get_cached_schema( $term_id ) {
        if ( 'shop' === $term_id ) {
            $cached = get_option( 'gzseo_product_list_schema_cache_shop', false );
            return ( false !== $cached && '' !== $cached ) ? $cached : false;
        }

        if ( ! $term_id ) {
            return false;
        }

        $cached = get_term_meta( (int) $term_id, self::CACHE_META_KEY, true );
        return ( '' !== $cached && false !== $cached ) ? $cached : false;
    }

    /**
     * Store schema JSON for a term.
     *
     * @param int|string $term_id
     * @param string     $json
     */
    private function set_cached_schema( $term_id, $json ) {
        if ( 'shop' === $term_id ) {
            update_option( 'gzseo_product_list_schema_cache_shop', $json, false );
            return;
        }

        if ( ! $term_id ) {
            return;
        }

        update_term_meta( (int) $term_id, self::CACHE_META_KEY, $json );
    }

    /**
     * Delete cached schema for a specific term.
     *
     * @param int|string $term_id
     */
    private function delete_cached_schema( $term_id ) {
        if ( 'shop' === $term_id ) {
            delete_option( 'gzseo_product_list_schema_cache_shop' );
            return;
        }

        if ( ! $term_id ) {
            return;
        }

        delete_term_meta( (int) $term_id, self::CACHE_META_KEY );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cache invalidation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Invalidate schema cache for all taxonomy terms a product belongs to.
     * Also clears the shop-page cache since the shop lists all products.
     *
     * @param int $product_id
     */
    public function invalidate_cache_for_product( $product_id ) {
        // Only act on products.
        if ( 'product' !== get_post_type( $product_id ) ) {
            return;
        }

        // WooCommerce product taxonomies to check.
        $taxonomies = array( 'product_cat', 'product_tag' );

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term_id ) {
                $this->delete_cached_schema( (int) $term_id );

                // Also clear parent category caches (category pages aggregate children).
                $ancestors = get_ancestors( (int) $term_id, $taxonomy, 'taxonomy' );
                foreach ( $ancestors as $ancestor_id ) {
                    $this->delete_cached_schema( (int) $ancestor_id );
                }
            }
        }

        // Always clear shop-page cache.
        $this->delete_cached_schema( 'shop' );
    }

    /**
     * Invalidate cache when a product's term assignment changes.
     *
     * Fires on `set_object_terms` – clears both old and new term caches.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Array of term IDs / slugs that were set.
     * @param array  $tt_ids     Array of term-taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term-taxonomy IDs.
     */
    public function invalidate_cache_on_term_change( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        if ( 'product' !== get_post_type( $object_id ) ) {
            return;
        }

        if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
            return;
        }

        // Clear caches for newly assigned terms (via term IDs from tt_ids).
        $new_term_ids = array();
        foreach ( $tt_ids as $tt_id ) {
            $term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                $new_term_ids[] = (int) $term->term_id;
            }
        }

        // Clear caches for previously assigned terms.
        $old_term_ids = array();
        foreach ( $old_tt_ids as $tt_id ) {
            $term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                $old_term_ids[] = (int) $term->term_id;
            }
        }

        $all_term_ids = array_unique( array_merge( $new_term_ids, $old_term_ids ) );

        foreach ( $all_term_ids as $term_id ) {
            $this->delete_cached_schema( $term_id );

            $ancestors = get_ancestors( $term_id, $taxonomy, 'taxonomy' );
            foreach ( $ancestors as $ancestor_id ) {
                $this->delete_cached_schema( (int) $ancestor_id );
            }
        }

        // Always clear shop-page cache.
        $this->delete_cached_schema( 'shop' );
    }

    /**
     * Invalidate cache when term relationships are deleted directly.
     *
     * Fires on `deleted_term_relationships`.
     *
     * @param int   $object_id Post ID.
     * @param array $tt_ids    Term-taxonomy IDs that were removed.
     */
    public function invalidate_cache_for_terms( $object_id, $tt_ids ) {
        if ( 'product' !== get_post_type( $object_id ) ) {
            return;
        }

        foreach ( $tt_ids as $tt_id ) {
            // We don't know the taxonomy at this point, so try both.
            foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
                $term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $this->delete_cached_schema( (int) $term->term_id );

                    $ancestors = get_ancestors( (int) $term->term_id, $taxonomy, 'taxonomy' );
                    foreach ( $ancestors as $ancestor_id ) {
                        $this->delete_cached_schema( (int) $ancestor_id );
                    }
                }
            }
        }

        $this->delete_cached_schema( 'shop' );
    }

    /**
     * Remove all cached schemas (e.g. when the feature is toggled on/off).
     */
    public function invalidate_all_caches() {
        // Remove all term_meta entries for our cache key using the WordPress
        // metadata API. The 5th arg ($delete_all = true) tells WordPress to
        // delete every entry matching the meta_key, regardless of object_id,
        // which is exactly what the previous direct query did. WordPress
        // also handles cache invalidation automatically inside
        // delete_metadata(), so no manual cache clear is needed.
        delete_metadata( 'term', 0, self::CACHE_META_KEY, '', true );

        // Remove shop-page cache.
        delete_option( 'gzseo_product_list_schema_cache_shop' );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Price helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get low / high prices for a product.
     *
     * @param WC_Product $product
     * @return array Empty array if no price available.
     */
    private function get_product_prices( $product ) {
        $prices = array();

        if ( $product->is_type( 'variable' ) ) {
            $variations   = $product->get_available_variations();
            $prices_array = array();

            foreach ( $variations as $variation ) {
                $variation_obj = wc_get_product( $variation['variation_id'] );
                if ( $variation_obj ) {
                    $price = $variation_obj->get_price();
                    if ( '' !== $price ) {
                        $prices_array[] = floatval( $price );
                    }
                }
            }

            if ( ! empty( $prices_array ) ) {
                $prices['low_price']  = min( $prices_array );
                $prices['high_price'] = max( $prices_array );
            }
        } else {
            $price = $product->get_price();
            if ( '' !== $price ) {
                $prices['low_price']  = floatval( $price );
                $prices['high_price'] = floatval( $price );
            }
        }

        return $prices;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings AJAX
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save plugin settings via AJAX.
     */
    public function save_settings() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'gzseo_ajax_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'خطای امنیتی', 'gzseo' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'شما دسترسی لازم را ندارید', 'gzseo' ) ) );
        }

        $enabled_raw = isset( $_POST['enabled'] ) ? wp_unslash( $_POST['enabled'] ) : '';
        $enabled     = ( 'true' === $enabled_raw || '1' === $enabled_raw );

        update_option( 'gzseo_product_list_schema_enabled', (bool) $enabled );
        // update_option fires update_option_{option_name} which calls invalidate_all_caches().

        wp_send_json_success(
            array(
                'message' => __( 'تنظیمات با موفقیت ذخیره شد', 'gzseo' ),
            )
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether the schema feature is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option( 'gzseo_product_list_schema_enabled', false );
    }
}

// Initialize the class
new GZSEO_Product_List_Schema();