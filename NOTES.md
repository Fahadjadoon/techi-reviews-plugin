# Architectural Notes: TECHi Data Modeling

## Mapping TECHi Content
- **Articles:** These fit into the native WordPress `Post` type.
- **Authors:** These map perfectly to WordPress `Users`, allowing for "byline" author profile pages.
- **Reviews:** By using a `Custom Post Type (techi_review)`, we separate review data from standard news articles. This prevents our review schema from "leaking" into general news posts.
- **Categories:** Using `review-category` taxonomy allows us to categorize reviews (e.g., "Phones", "Laptops") independently of news categories.

## Leveraging Core WordPress
- **Users:** Used for editorial control and author attribution.
- **Media:** Used for featured images within the reviews.
- **REST API:** Utilized the built-in `WP_Query` infrastructure to expose our custom data, avoiding the need to write custom SQL.
- **Gutenberg:** By setting `show_in_rest => true`, we allow editors to use the native Block Editor for review content.