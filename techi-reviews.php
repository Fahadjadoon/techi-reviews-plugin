<?php
/**
 * Plugin Name: TECHi Reviews
 * Description: A self-contained review management system for TECHi.
 * Version: 1.0.0
 */

// Exit if accessed directly for security
if (!defined('ABSPATH')) {
    exit;
}

// 1. Register Post Type and Taxonomy
function techi_reviews_register_data() {
    
    // Register CPT: techi_review
    register_post_type('techi_review', [
        'labels' => [
            'name' => 'Reviews',
            'singular_name' => 'Review'
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true, // Enables Gutenberg and REST API
        'supports' => ['title', 'editor'], // Basic support
    ]);

    // Register Taxonomy: review-category
    register_taxonomy('review-category', 'techi_review', [
        'labels' => [
            'name' => 'Review Categories',
            'singular_name' => 'Review Category'
        ],
        'hierarchical' => true, // Acts like Categories
        'show_in_rest' => true,
    ]);
}
add_action('init', 'techi_reviews_register_data');

// 2. Flush rewrites ONLY on activation
function techi_reviews_activate() {
    techi_reviews_register_data();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'techi_reviews_activate');


// Add the Meta Box
function techi_reviews_add_meta_box() {
    add_meta_box('techi_review_meta', 'Review Details', 'techi_reviews_render_meta_box', 'techi_review', 'side', 'high');
}
add_action('add_meta_boxes', 'techi_reviews_add_meta_box');

// Render the Updated HTML Form
function techi_reviews_render_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('techi_save_review', 'techi_review_nonce');

    // Fetch existing values
    $score = get_post_meta($post->ID, '_techi_score', true);
    $price = get_post_meta($post->ID, '_techi_price', true);
    $verdict = get_post_meta($post->ID, '_techi_verdict', true);
    
    // Get array data and turn into string for the input
    $pros = get_post_meta($post->ID, '_techi_pros', true);
    $cons = get_post_meta($post->ID, '_techi_cons', true);
    
    $pros_string = is_array($pros) ? implode(', ', $pros) : '';
    $cons_string = is_array($cons) ? implode(', ', $cons) : '';

    echo '<p><label>Score (0-10):</label><br><input type="number" step="0.1" name="techi_score" value="'.esc_attr($score).'" style="width:100%"></p>';
    echo '<p><label>Price (PKR):</label><br><input type="number" name="techi_price" value="'.esc_attr($price).'" style="width:100%"></p>';
    echo '<p><label>Verdict (Max 140 chars):</label><br><textarea name="techi_verdict" maxlength="140" style="width:100%">'.esc_textarea($verdict).'</textarea></p>';
    echo '<p><label>Pros (comma separated):</label><br><input type="text" name="techi_pros" value="'.esc_attr($pros_string).'" style="width:100%"></p>';
    echo '<p><label>Cons (comma separated):</label><br><input type="text" name="techi_cons" value="'.esc_attr($cons_string).'" style="width:100%"></p>';
}


function techi_reviews_save_meta($post_id) {
    // 1. Security checks
    if (!isset($_POST['techi_review_nonce']) || !wp_verify_nonce($_POST['techi_review_nonce'], 'techi_save_review')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // 2. Save Score
    if (isset($_POST['techi_score'])) {
        update_post_meta($post_id, '_techi_score', sanitize_text_field($_POST['techi_score']));
    }
    
    // 3. Save Price
    if (isset($_POST['techi_price'])) {
        update_post_meta($post_id, '_techi_price', absint($_POST['techi_price']));
    }
    
    // 4. Save Verdict
    if (isset($_POST['techi_verdict'])) {
        update_post_meta($post_id, '_techi_verdict', sanitize_text_field($_POST['techi_verdict']));
    }

    // 5. Save Pros
    if (isset($_POST['techi_pros'])) {
        // Explode into array, trim whitespace from each item
        $pros_array = array_map('trim', explode(',', sanitize_text_field($_POST['techi_pros'])));
        // Remove empty items if the user leaves it blank or types just a comma
        $pros_array = array_filter($pros_array); 
        update_post_meta($post_id, '_techi_pros', $pros_array);
    }

    // 6. Save Cons
    if (isset($_POST['techi_cons'])) {
        $cons_array = array_map('trim', explode(',', sanitize_text_field($_POST['techi_cons'])));
        $cons_array = array_filter($cons_array);
        update_post_meta($post_id, '_techi_cons', $cons_array);
    }
}
add_action('save_post_techi_review', 'techi_reviews_save_meta');



function techi_display_scorecard($content) {
    // 1. Ensure we are on a single 'techi_review' post in the main query
    if (is_singular('techi_review') && in_the_loop() && is_main_query()) {
        
        $post_id = get_the_ID();
        
        // Fetch meta data
        $score   = get_post_meta($post_id, '_techi_score', true);
        $price   = get_post_meta($post_id, '_techi_price', true);
        $verdict = get_post_meta($post_id, '_techi_verdict', true);
        $pros    = get_post_meta($post_id, '_techi_pros', true);
        $cons    = get_post_meta($post_id, '_techi_cons', true);

        // Build HTML Scorecard
        $output = '<div class="techi-scorecard" style="border:1px solid #ccc; padding:20px; margin-bottom:20px; background:#f9f9f9;">';
        $output .= '<h3>Review Summary</h3>';
        $output .= '<p><strong>Score:</strong> ' . esc_html($score) . '/10</p>';
        $output .= '<p><strong>Price:</strong> PKR ' . esc_html(number_format($price)) . '</p>';
        $output .= '<p><strong>Verdict:</strong> ' . esc_html($verdict) . '</p>';
        
        if (!empty($pros)) {
            $output .= '<p><strong>Pros:</strong> ' . esc_html(implode(', ', (array)$pros)) . '</p>';
        }
        if (!empty($cons)) {
            $output .= '<p><strong>Cons:</strong> ' . esc_html(implode(', ', (array)$cons)) . '</p>';
        }
        $output .= '</div>';

        // Add JSON-LD Schema
        $schema = [
            "@context" => "https://schema.org/",
            "@type"    => "Review",
            "itemReviewed" => [
                "@type" => "Product",
                "name"  => get_the_title()
            ],
            "reviewRating" => [
                "@type"       => "Rating",
                "ratingValue" => esc_attr($score),
                "bestRating"  => "10"
            ]
        ];
        
        $output .= '<script type="application/ld+json">' . json_encode($schema) . '</script>';

        // Return combined output
        return $output . $content;
    }

    return $content;
}
add_filter('the_content', 'techi_display_scorecard');


add_action('rest_api_init', function () {
    register_rest_route('techi/v1', '/reviews', [
        'methods' => 'GET',
        'callback' => 'techi_get_reviews_api',
        'permission_callback' => '__return_true', // Public endpoint
    ]);
});

function techi_get_reviews_api($request) {
    // 1. Get and Sanitize Parameters
    $category = sanitize_text_field($request->get_param('category'));
    $min_score = floatval($request->get_param('min_score'));
    $per_page = min(intval($request->get_param('per_page') ?: 10), 50); // Cap at 50
    $page = max(intval($request->get_param('page') ?: 1), 1);
    
    // 2. Setup Meta Query for Score filtering
    $meta_query = [];
    if ($min_score > 0) {
        $meta_query[] = [
            'key' => '_techi_score',
            'value' => $min_score,
            'compare' => '>=',
            'type' => 'NUMERIC'
        ];
    }

    // 3. Query the Reviews
    $args = [
        'post_type' => 'techi_review',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'meta_query' => $meta_query
    ];

    if ($category) {
        $args['tax_query'] = [[
            'taxonomy' => 'review-category',
            'field' => 'slug',
            'terms' => $category
        ]];
    }

    $query = new WP_Query($args);
    
    // 4. Format the response data
    $reviews = [];
    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $reviews[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'score' => get_post_meta($post->ID, '_techi_score', true),
                'price' => get_post_meta($post->ID, '_techi_price', true),
                'verdict' => get_post_meta($post->ID, '_techi_verdict', true)
            ];
        }
    }

    return new WP_REST_Response($reviews, 200);
}


// 1. Add Tools Menu
add_action('admin_menu', function() {
    add_management_page('TECHi Tools', 'Techi Reviews', 'manage_options', 'techi-tools', 'techi_tools_page');
});

// 2. Render the Tools Page UI
function techi_tools_page() {
    ?>
    <div class="wrap">
        <h1>TECHi Review Tools</h1>
        <form method="post" action="">
            <?php wp_nonce_field('techi_tools_action', 'techi_tools_nonce'); ?>
            <p>
                <input type="submit" name="seed_reviews" class="button button-primary" value="Generate 25 Reviews">
                <input type="submit" name="delete_reviews" class="button button-danger" value="Delete All Reviews">
            </p>
        </form>
    </div>
    <?php
    // Handle form submissions
    if (isset($_POST['seed_reviews']) && check_admin_referer('techi_tools_action', 'techi_tools_nonce')) {
        for ($i = 1; $i <= 25; $i++) {
            $pid = wp_insert_post([
                'post_title' => "Synthetic Review #$i",
                'post_type' => 'techi_review',
                'post_status' => 'publish'
            ]);
            update_post_meta($pid, '_techi_score', rand(5, 10));
            update_post_meta($pid, '_techi_price', rand(1000, 50000));
        }
        echo '<div class="updated"><p>25 reviews generated!</p></div>';
    }

    if (isset($_POST['delete_reviews']) && check_admin_referer('techi_tools_action', 'techi_tools_nonce')) {
        $reviews = get_posts(['post_type' => 'techi_review', 'numberposts' => -1]);
        foreach ($reviews as $review) {
            wp_delete_post($review->ID, true);
        }
        echo '<div class="updated"><p>All reviews deleted!</p></div>';
    }
}