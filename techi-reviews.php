<?php
/**
 * Plugin Name: TECHi Reviews
 * Description: A self-contained review management system for TECHi.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Setup custom post type and review category taxonomy
function techi_reviews_register_data() {
    register_post_type('techi_review', [
        'labels' => [
            'name' => 'Reviews',
            'singular_name' => 'Review'
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor'],
    ]);

    register_taxonomy('review-category', 'techi_review', [
        'labels' => [
            'name' => 'Review Categories',
            'singular_name' => 'Review Category'
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
    ]);
}
add_action('init', 'techi_reviews_register_data');

// Flush rules on activation so the new post type works immediately
function techi_reviews_activate() {
    techi_reviews_register_data();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'techi_reviews_activate');

// Register the meta box in the post editor
function techi_reviews_add_meta_box() {
    add_meta_box('techi_review_meta', 'Review Details', 'techi_reviews_render_meta_box', 'techi_review', 'side', 'high');
}
add_action('add_meta_boxes', 'techi_reviews_add_meta_box');

// Display fields for review data entry
function techi_reviews_render_meta_box($post) {
    wp_nonce_field('techi_save_review', 'techi_review_nonce');

    $score = get_post_meta($post->ID, '_techi_score', true);
    $price = get_post_meta($post->ID, '_techi_price', true);
    $verdict = get_post_meta($post->ID, '_techi_verdict', true);
    
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

// Validate and save meta data to database
function techi_reviews_save_meta($post_id) {
    if (!isset($_POST['techi_review_nonce']) || !wp_verify_nonce($_POST['techi_review_nonce'], 'techi_save_review')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['techi_score'])) {
        update_post_meta($post_id, '_techi_score', sanitize_text_field($_POST['techi_score']));
    }
    
    if (isset($_POST['techi_price'])) {
        update_post_meta($post_id, '_techi_price', absint($_POST['techi_price']));
    }
    
    if (isset($_POST['techi_verdict'])) {
        update_post_meta($post_id, '_techi_verdict', sanitize_text_field($_POST['techi_verdict']));
    }

    if (isset($_POST['techi_pros'])) {
        $pros_array = array_map('trim', explode(',', sanitize_text_field($_POST['techi_pros'])));
        $pros_array = array_filter($pros_array); 
        update_post_meta($post_id, '_techi_pros', $pros_array);
    }

    if (isset($_POST['techi_cons'])) {
        $cons_array = array_map('trim', explode(',', sanitize_text_field($_POST['techi_cons'])));
        $cons_array = array_filter($cons_array);
        update_post_meta($post_id, '_techi_cons', $cons_array);
    }
}
add_action('save_post_techi_review', 'techi_reviews_save_meta');

// Generate and output the review scorecard on single posts
function techi_display_scorecard($content) {
    if (is_singular('techi_review') && in_the_loop() && is_main_query()) {
        
        $post_id = get_the_ID();
        
        $score   = get_post_meta($post_id, '_techi_score', true);
        $price   = get_post_meta($post_id, '_techi_price', true);
        $verdict = get_post_meta($post_id, '_techi_verdict', true);
        $pros    = get_post_meta($post_id, '_techi_pros', true);
        $cons    = get_post_meta($post_id, '_techi_cons', true);

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

        return $output . $content;
    }

    return $content;
}
add_filter('the_content', 'techi_display_scorecard');

// Expose review data via custom REST endpoint
add_action('rest_api_init', function () {
    register_rest_route('techi/v1', '/reviews', [
        'methods' => 'GET',
        'callback' => 'techi_get_reviews_api',
        'permission_callback' => '__return_true',
    ]);
});

function techi_get_reviews_api($request) {
    $category = sanitize_text_field($request->get_param('category'));
    $min_score = floatval($request->get_param('min_score'));
    $per_page = min(intval($request->get_param('per_page') ?: 10), 50);
    $page = max(intval($request->get_param('page') ?: 1), 1);
    
    $meta_query = [];
    if ($min_score > 0) {
        $meta_query[] = [
            'key' => '_techi_score',
            'value' => $min_score,
            'compare' => '>=',
            'type' => 'NUMERIC'
        ];
    }

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

// Add submenu under Tools for management functions
add_action('admin_menu', function() {
    add_management_page('TECHi Tools', 'Techi Reviews', 'manage_options', 'techi-tools', 'techi_tools_page');
});

// UI for seeding and clearing test data
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