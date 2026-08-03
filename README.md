# TECHi Review Plugin

A lightweight, self-contained WordPress plugin for managing structured review data.

## Features
- Custom Post Type: `techi_review`
- Hierarchical Taxonomy: `review-category`
- Custom Meta Fields (Score, Price, Verdict, Pros, Cons)
- REST API Endpoint: `/wp-json/techi/v1/reviews`
- Tools page for synthetic data generation

## Installation
1. Upload the `techi-reviews` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin via the WordPress Admin dashboard.
3. Go to **Settings > Permalinks** and save changes to flush rewrite rules.

## Tech Specs
- Tested on WordPress 6.x
- PHP version: 8.0+

## Decisions & Tradeoffs
- **Custom Metaboxes:** Chosen over frameworks to ensure zero dependencies and high performance.
- **REST API:** Implemented a public, read-only endpoint with meta queries for filtered searching.
- **Data Persistence:** Used standard `update_post_meta` with proper sanitization to ensure data integrity.

## Time Spent
- ~7-8 hours

## Improvements
- Implement transient caching for the REST API response.
- Add a JS-based filter for the review grid shortcode.