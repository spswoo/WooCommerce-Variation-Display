/**
 * Display Variations as Single Products - Version 2 (Debugged)
 * * This version displays only variations and hides the main variable product.
 * It also forces variations to inherit their parent's categories and subcategories.
 * * @version 1.1
 * @author WP Simple Hacks (Modified)
 * @website https://wpsimplehacks.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if we should modify this query
 * * @param WP_Query $query Query object
 * @return bool Whether to modify the query
 */
function sv_v2_should_modify_query($query): bool {
    if (is_admin()) {
        return false;
    }
    
    if (wp_doing_ajax()) {
        return false;
    }
    
    $min_price = sanitize_text_field($_GET['min_price'] ?? '');
    $max_price = sanitize_text_field($_GET['max_price'] ?? '');
    $price_filter = sanitize_text_field($_GET['price_filter'] ?? '');
    
    if (!empty($min_price) || !empty($max_price) || !empty($price_filter)) {
        return false;
    }
    
    $blocksy_filter = sanitize_text_field($_GET['blocksy_filter'] ?? '');
    $ct_filter = sanitize_text_field($_GET['ct_filter'] ?? '');
    
    if (!empty($blocksy_filter) || !empty($ct_filter)) {
        return false;
    }
    
    if (!$query->is_main_query()) {
        return false;
    }
    
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_search()) {
        return false;
    }
    
    $post_types = $query->get('post_type');
    if (is_array($post_types)) {
        return in_array('product', $post_types, true) || in_array('product_variation', $post_types, true);
    } else {
        return $post_types === 'product' || $post_types === 'product_variation';
    }
}

/**
 * Modify product query to include variations and hide variable products
 * * @param WP_Query $query Query object
 */
function sv_v2_modify_product_query($query): void {
    if (is_admin()) {
        return;
    }
    
    // Always include product variations
    $post_types = array('product', 'product_variation');
    $query->set('post_type', $post_types);
    
    // Exclude variable products (hide main products)
    $tax_query = $query->get('tax_query');
    if (!is_array($tax_query)) {
        $tax_query = array();
    }
    
    $tax_query[] = array(
        'taxonomy' => 'product_type',
        'field'    => 'slug',
        'terms'    => 'variable',
        'operator' => 'NOT IN',
    );
    
    $query->set('tax_query', $tax_query);
    
    // Set custom ordering to group variations with parent products
    $query->set('orderby', 'parent_group menu_order ID');
    $query->set('order', 'ASC');
}
add_action('woocommerce_product_query', 'sv_v2_modify_product_query', 25);

/**
 * Custom JOIN to allow variations to inherit parent categories
 * * @param string $join JOIN clause
 * @param WP_Query $query Query object
 * @return string Modified JOIN clause
 */
function sv_v2_custom_join(string $join, $query): string {
    global $wpdb;
    
    if (!sv_v2_should_modify_query($query)) {
        return $join;
    }
    
    // Check if we are on a category or tag page
    if (is_product_category() || is_product_tag()) {
        // Intercept standard taxonomy JOIN. Allow variations to appear if their post_parent has the category term.
        $join = preg_replace(
            "/ON\s*\(\s*({$wpdb->posts}\.ID)\s*=\s*([^\.]+\.object_id)\s*\)/",
            "ON ( $1 = $2 OR ( {$wpdb->posts}.post_parent = $2 AND {$wpdb->posts}.post_parent > 0 ) )",
            $join
        );
    }
    
    return $join;
}
add_filter('posts_join', 'sv_v2_custom_join', 10, 2);

/**
 * Custom FIELDS to add parent grouping
 * * @param string $fields FIELDS clause
 * @param WP_Query $query Query object
 * @return string Modified FIELDS clause
 */
function sv_v2_custom_fields(string $fields, $query): string {
    global $wpdb;
    
    if (!sv_v2_should_modify_query($query)) {
        return $fields;
    }
    
    if (strpos($fields, 'parent_group') !== false) {
        return $fields;
    }
    
    // Use native post_parent for better performance instead of joining postmeta
    $fields .= ", CASE WHEN {$wpdb->posts}.post_parent > 0 THEN {$wpdb->posts}.post_parent ELSE {$wpdb->posts}.ID END AS parent_group";
    
    return $fields;
}
add_filter('posts_fields', 'sv_v2_custom_fields', 10, 2);

/**
 * Custom ORDER BY to group variations with their parent products
 * * @param string $orderby ORDER BY clause
 * @param WP_Query $query Query object
 * @return string Modified ORDER BY clause
 */
function sv_v2_custom_orderby(string $orderby, $query): string {
    global $wpdb;
    
    if (!sv_v2_should_modify_query($query)) {
        return $orderby;
    }
    
    if (strpos($orderby, 'parent_group') !== false) {
        return $orderby;
    }
    
    // Group by parent product ID natively
    $custom_orderby = "
        parent_group ASC,
        CASE 
            WHEN {$wpdb->posts}.post_parent > 0 THEN 1 
            ELSE 0 
        END ASC,
        {$wpdb->posts}.menu_order ASC,
        {$wpdb->posts}.ID ASC
    ";
    
    return $custom_orderby;
}
add_filter('posts_orderby', 'sv_v2_custom_orderby', 10, 2);