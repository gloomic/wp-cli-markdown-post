<?php
/**
 * This file defines three custom commands for WP-CLI to publish and update a post with markdown.
 * A markdown file for a post conatins meta information and content.
 * - wp new <file>, publish a post with a markdonw file specified by file.
 * - wp update <file> <ID>, update a post specified by ID with a markdown file specified by file.
 * - wp create <file>, create a empty markdown file with name specified by file.
 *
 * date: 2020-01-29
 */

function parse_markdown( $file ) {
    $content = file_get_contents( $file );
    $meta = array();

    if ( '---' === substr( $content, 0, 3 ) ) {
        $pos = strpos( $content, '---', 3 );
        if ( $pos !== false ) {
            $yaml = substr( $content, 3, $pos );
            //$n;
            $meta = spyc_load( $yaml );
            $content = substr( $content, $pos + 3);
        }
    }

    return array(
       'meta' => $meta,
       'content' => trim( $content )
    );
}

$new_post_command = function( $args, $assoc_args ) {
    if ( empty( $args ) ) {
        WP_CLI::error( 'The file argument is missing.' );
    }

    $file = $args[0];
    if ( ! is_file( $file ) || ! file_exists( $file ) ) {
        WP_CLI::error( 'The argument is not a file' );
    }

    $post = parse_markdown( $file );
    $meta = $post['meta'];

    // Ignore ID if it exists.
    if ( array_key_exists( 'ID', $meta ) ) {
        unset( $meta['ID'] );
        WP_CLI::line( 'ID has been existed.' );
    }

    // post args
    $args = array (
       'post_content' => $post['content']
    );
    $arg_keys = [
        'post_type',
        'post_status',
        'post_title',
        'post_author',
        'tags_input',
        'post_excerpt',
        'post_name',

    ];

    foreach( $arg_keys as $k ) {
        if ( array_key_exists( $k, $meta ) ) {
            $args[$k] = $meta[$k];
            unset( $meta[$k] );
        }
    }

    if ( array_key_exists( 'post_category', $meta ) ) {
        $categories = $meta['post_category'];
        if ( !is_array( $categories ) ) {
            $categories = array( $categories );
        }
        $terms = get_terms( array(
            'taxonomy' => 'category',
            'fields' => 'id=>name',
            'hide_empty' => 0,
            'name' => $categories
            )
        );
        $map = array();
        foreach ($categories as $name) {
            $map[$name] = 1;
        }

        $ids = array();
        if ( !empty( $terms ) ) {
            foreach ( $terms as $id => $name ) {
                $ids[] = $id;
                unset( $map[$name] );
            }
        }

        // create new categroy if it does not exist
        if ( !empty( $map ) ) {
            foreach ($map as $name => $value) {
                $term = wp_insert_term( $name, 'category', array( 'parent' => 0 ) );
                $ids[] = $term['term_id'];
            }
        }

        $args['post_category'] = $ids;
        unset( $meta['post_category'] );
    }

    // post meta

    if ( array_key_exists( 'description', $meta ) ) {
        $meta['_yoast_wpseo_metadesc'] = $meta['description'];
        unset( $meta['description'] );
    }

    $args['meta_input'] = $meta;
    $post_id = wp_insert_post( $args, true );

    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( $post_id );
    } else {
        WP_CLI::success( $post_id );
    }
};

$update_post_command = function( $args, $assoc_args ) {
    if ( empty( $args ) ) {
        WP_CLI::error( 'The file argument is missing.' );
    }

    $file = $args[0];
    if ( ! is_file( $file ) || ! file_exists( $file ) ) {
        WP_CLI::error( 'The argument is not a file' );
    }

    $post = parse_markdown( $file );

    // post args
    $args = array (
       'post_content' => $post['content']
    );
    $meta = $post['meta'];
    if ( ! array_key_exists( 'ID', $meta ) ) {
        WP_CLI::error( 'ID does not exist in the file.' );
    }
    $args['ID'] = $meta['ID'];
    $post_id = wp_update_post( $args, true );

    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( $post_id );
    } else {
        WP_CLI::success( $post_id );
    }
};

$create_markdown_command = function ( $args, $assoc_args ) {
    if ( empty( $args ) ) {
        WP_CLI::error( 'No file name' );
    }

    $file_name = $args[0];
    if ( strcmp( substr( $file_name, -3 ), '.md' ) !== 0 ) {
        $file_name .= '.md';
    }

    // content
    $meta = [
        '---',
        'post_title: ',
        'post_author: ',
        'post_type: post',
        'post_status: publish',
        'tags_input:',
        '  - ',
        'post_category: ',
        '  - ',
        'post_excerpt: ',
        'description: ',
        '---'
    ];
    $content = implode( PHP_EOL, $meta );
    $content .= PHP_EOL . PHP_EOL;

    $result = file_put_contents( $file_name, $content );
    if ( $result === false ) {
        WP_CLI::error( $result );
    } else {
        WP_CLI::success( $file_name . ' is created!' );
    }
};

WP_CLI::add_command( 'new', $new_post_command );
WP_CLI::add_command( 'update', $update_post_command );
WP_CLI::add_command( 'create', $create_markdown_command );
