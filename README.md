Key Changes Made:
Taxonomy Regex JOIN: Re-wrote sv_v2_custom_join. The code now uses a preg_replace intercept on the taxonomy join. It translates WordPress's default check of wp_posts.ID = wp_term_relationships.object_id to include an OR condition that checks the post_parent ID as well. Now, subcategories will accurately pull up variations.

Dropped the Postmeta Call: In your original code, sv_v2_custom_join tried to link to _parent_id in the postmeta table. WooCommerce standard variations record their parent directly in the main wp_posts table under the post_parent column. Calling it directly speeds up the query and resolves silent failures.
