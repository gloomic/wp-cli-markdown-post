<?php
/**
 * This file defines three custom commands for WP-CLI to publish and update a post with markdown.
 * A markdown file for a post conatins meta information and content.
 * - wp create <file> [--force], publish a post with a markdonw file.
 *   If ID already exist in the file, you have to add --force to republish it.
 * - wp update <file> <ID>, update a post specified by ID with a markdown file specified by file.
 * - wp new <file>, new a markdown file with name specified by file.
 *
 * @author: gloomic <https://github.com/gloomic>
 * @date: 2020-01-29
 */

function parse_markdown( $file ) {
    $content = file_get_contents( $file );
    $meta = array();

    if ( strncmp( $content, '---', 3 ) === 0 ) {
        $pos = strpos( $content, '---', 3 );
        if ( $pos !== false ) {
            $yaml = substr( $content, 3, $pos - 3 );
            $meta = spyc_load( $yaml );
            $content = substr( $content, $pos + 3 );
        }
    }

    return array(
       'meta' => $meta,
       'content' => trim( $content )
    );
}

/**
 * Command: create, publish a post with information specified in a markdown file.
 *          The markdown file contains a YAML part to define the meta data of the post.
 * Syntax: create [--force] <markdown-file>
 * Options:
 *   --force, if the markdown already has an ID, you need to add this option to force
 *            to republish it. Otherwise, it will fail.
 * Examples:
 *   1. $ wp create useful-git-commands.md
 *   2. $ wp create git/git-getting-started.md
 * Markdown example YAML part:
 * ---
 * post_title: Useful Git commands
 * post_author: 8
 * post_type: post
 * post_name: useful-git-commands
 * post_status: publish
 * tags_input:
 *   - basic
 * post_category:
 *   - git
 * description: Some command used and useful commands will...
 * ---
 */
$create_post_command = function( $args, $assoc_args ) {
    if ( empty( $args ) ) {
        WP_CLI::error( 'The file argument is missing.' );
    }

    $file = $args[0];
    if ( ! is_file( $file ) || ! file_exists( $file ) ) {
        WP_CLI::error( 'The specified file does not exist.' );
    }

    $post = parse_markdown( $file );
    $meta = $post['meta'];

    // Check whether "--force" option is set if ID exists.
    if ( isset( $meta['ID'] ) ) {
        if ( ! array_key_exists( 'force', $assoc_args ) ) {
            WP_CLI::error( 'ID already exists in the file, you have to add --force option to republish it.' );
        }

        unset( $meta['ID'] );
    }

    // post args
    $args = array (
       'post_content' => $post['content']
    );
    $arg_keys = [
        'post_title',
        'post_name',
        'post_author',
        'post_type',
        'post_status',
        'post_date',
        // 'post_modified', // 'post_modified' field won't be used in wp_new_post().
        'tags_input',
        'post_excerpt',
    ];

    foreach( $arg_keys as $k ) {
        if ( isset( $meta[$k] ) ) {
            $args[$k] = $meta[$k];
            unset( $meta[$k] );
        }
    }

    if ( isset( $meta['post_category'] ) ) {
        $categories = $meta['post_category'];
        if ( ! is_array( $categories ) ) {
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
        foreach ( $categories as $name ) {
            $map[$name] = 1;
        }

        $ids = array();
        if ( ! empty( $terms ) ) {
            foreach ( $terms as $id => $name ) {
                $ids[] = $id;
                unset( $map[$name] );
            }
        }

        // create new categroy if it does not exist
        if ( ! empty( $map ) ) {
            foreach ( $map as $name => $value ) {
                $term = wp_insert_term( $name, 'category', array( 'parent' => 0 ) );
                $ids[] = $term['term_id'];
            }
        }

        $args['post_category'] = $ids;
        unset( $meta['post_category'] );
    }

    // yoast description
    if ( isset( $meta['description'] ) ) {
        $meta['_yoast_wpseo_metadesc'] = $meta['description'];
        unset( $meta['description'] );

        // Only update meta like yoast description.
        $args['meta_input'] = ['_yoast_wpseo_metadesc' => $meta['_yoast_wpseo_metadesc']];
    }

    //$args['meta_input'] = $meta; // Don't set other meta anymore except yoast description.
    $post_id = wp_insert_post( $args, true );
    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( $post_id );
    } else {
        // Update markdown with ID and post_date.

        $meta;
        // Add or update ID
        if ( ! array_key_exists( 'ID', $post['meta'] ) ) {
            $meta = array_merge( array( 'ID' => $post_id ), $post['meta'] ); // Put ID in the beginning.
        } else {
            $meta = $post['meta'];
            $meta['ID'] = $post_id;
        }

        // Add post_date if it does not exist yet.
        if ( empty( $meta['post_date'] ) ) {
            $post_obj = get_post(  $post_id );
            $meta['post_date'] = $post_obj->post_date;
        }

        $content = '---' . PHP_EOL . spyc_dump( $meta ) . '---' . PHP_EOL . PHP_EOL
            . $post['content'] . PHP_EOL; // Use original content.
        file_put_contents( $file, $content );

        WP_CLI::success( $post_id );
    }
};

/**
 * Command: update, update a post's content with post ID and content specified in a markdown file.
 *          It only update content of a post.
 * Syntax: update <markdown-file>
 * Examples:
 *   1. $ wp update git/useful-git-commands.md # Update a post's content
 * Markdown example YAML part:
 * ---
 * ID: 23
 * post_title: Useful Git commands
 * post_name: useful-git-commands
 * post_author: 3
 * post_type: post
 * post_status: publish
 * tags_input:
 *   - basic
 * post_category:
 *   - git
 * description: Some command used and useful commands will...
 * ---
 */
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
    if ( empty( $meta['ID'] ) ) {
        WP_CLI::error( 'ID does not exist or not set in the file.' );
    }
    $args['ID'] = $meta['ID'];

    // Update yoast description if it is set.
    if ( ! empty( $meta['description'] ) ) {
        $args['meta_input'] = ['_yoast_wpseo_metadesc' => $meta['description']];
    }

    $post_id = wp_update_post( $args, true );
    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( $post_id );
    } else {
        WP_CLI::success( $post_id );
    }
};

/**
 * Command: new, new a markdown file containing supported meta data name.
 * Syntax: new <file-name-without-file-extension>
 * Examples:
 *   1. $ wp new useful-git-commands
 *      Success: useful-git-commands.md is created!
 */
$new_markdown_command = function ( $args, $assoc_args ) {
    if ( empty( $args ) ) {
        WP_CLI::error( 'No file name.' );
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
        'post_date: ' .  date( "Y-m-d H:i:s" ),
        //'post_modified: ' . date( "Y-m-d H:i:s" ),
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

WP_CLI::add_command( 'create', $create_post_command );
WP_CLI::add_command( 'update', $update_post_command );
WP_CLI::add_command( 'new', $new_markdown_command );
