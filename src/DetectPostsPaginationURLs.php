<?php

namespace WP2Static;

class DetectPostsPaginationURLs {

    public static function detect() {
        global $wpdb, $wp_rewrite;

        $post_urls = array();
        $unique_post_types = array();

        $query = "
            SELECT ID,post_type
            FROM %s
            WHERE post_status = '%s'
            AND post_type NOT IN ('%s','%s')";

        $posts = $wpdb->get_results(
            sprintf(
                $query,
                $wpdb->posts,
                'publish',
                'revision',
                'nav_menu_item'
            )
        );

        foreach ( $posts as $post ) {
            // capture all post types
            $unique_post_types[] = $post->post_type;
        }

        // get all pagination links for each post_type
        $post_types = array_unique( $unique_post_types );
        $pagination_base = $wp_rewrite->pagination_base;
        $default_posts_per_page = get_option( 'posts_per_page' );

        $urls_to_include = array();

        foreach ( $post_types as $post_type ) {
            $query = "
                SELECT ID,post_type
                FROM %s
                WHERE post_status = '%s'
                AND post_type = '%s'";

            $count = $wpdb->get_results(
                sprintf(
                    $query,
                    $wpdb->posts,
                    'publish',
                    $post_type
                )
            );

            $post_type_obj = get_post_type_object( $post_type );

            if ( ! $post_type_obj ) {
                continue;
            }

            // cast WP's object back to array
            $post_type_labels = (array) $post_type_obj->labels;

            $plural_form = strtolower( $post_type_labels['name'] );

            // skip post type names containing spaces
            if ( strpos( $plural_form, ' ' ) !== false ) {
                continue;
            }

            $count = $wpdb->num_rows;

            $total_pages = ceil( $count / $default_posts_per_page );

            for ( $page = 1; $page <= $total_pages; $page++ ) {
                $pagination_url =
                    "/{$plural_form}/{$pagination_base}/{$page}";

                $urls_to_include[] = str_replace(
                    '/posts/',
                    '/',
                    $pagination_url
                );
            }
        }

        return $urls_to_include;
    }
}
