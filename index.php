<?php
/*
Plugin Name: React Reviews
Plugin URI: http://www.11online.us
Description: Review Plugin using React and the WP API
Version: 1.0
Revision Date: December 21, 2017
License: GNU General Public License 3.0 (GPL) http://www.gnu.org/licenses/gpl.html
Author: Eric Debelak
Author URI: http://www.11online.us
*/

// add our content filter
add_filter( 'the_content', 'add_react_reviews_to_posts' );

function add_react_reviews_to_posts( $content ) {

	// if this is a post, but not the blog page
	if ( is_single() && ! is_home() ) {
		// get our post id for the api
		$post_id  = get_the_ID();
		$user_id  = get_current_user_id();
		$rest_url = get_rest_url();
		// add our content, a div with id of root for our react script
		$content .= "<div id='root' data-id='$post_id' data-user='$user_id' data-resturl='$rest_url'></div>";

		$files = array_filter(
			glob( __DIR__ . '/wp-plugin/build/static/js/*.js' ), function( $file ) {
				return false !== strpos( $file, 'js/main.' );
			}
		);
		$file = end( $files );
		$file = plugins_url( str_replace( __DIR__, '', $file ), __FILE__ );

		// enqueue our build script
		wp_enqueue_script( 'react_script', $file );
	}

	return $content;
}


// add our api add review action
add_action(
	'rest_api_init', function () {
		register_rest_route( 'reviews/v1', '/add-review', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => 'add_review_route',
		) );
	}
);

function add_review( $data = array() ) {
	$data = wp_parse_args( $data, array(
		'user_id' => 0,
		'post_id' => 0,
		'review'  => 0,
	) );
	$data = array_map( 'absint', $data );
	if ( empty( $data['user_id'] ) || empty( $data['post_id'] ) ) {
		return false;
	}

	$data['result'] = update_user_meta(
		$data['user_id'],
		'review_of_post_' . $data['post_id'],
		$data['review']
	);

	return $data;
}

function add_review_route( $request ) {
	$data = add_review( $request['data'] ) ? $request['data'] : [];

	$post_id = isset( $data['post_id'] )
		? $data['post_id']
		: 0;

	return get_reviews_by_post( $post_id );
}

// add our api get reviews action
add_action(
	'rest_api_init', function () {
		register_rest_route( 'reviews/v1', '/get-reviews/(?P<id>\d+)', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'get_reviews_by_post_route',
		) );
	}
);

function get_reviews_by_post_route( $data ) {
	return get_reviews_by_post( ! empty( $data['id'] ) ? $data['id'] : 0 );
}

// this is our get reviews helper function
function get_reviews_by_post( $post_id ) {
	$post_id = absint( $post_id );

	$reviews = [];
	if ( empty( $post_id ) ) {
		return $reviews;
	}

	$meta_key = 'review_of_post_' . $post_id;

	$users = get_users( [
		'meta_key' => $meta_key,
		'fields'   => [ 'ID', 'user_nicename' ],
	] );

	// I know this isn't efficient, but for our demo it will suffice. In production, you'd want to write a join to get all the data at once.
	foreach ( $users as $user ) {
		$reviews[ $user->ID ] = [
			'review' => get_user_meta( $user->ID, $meta_key, true ),
			'image'  => get_avatar_url( $user->ID, [ 'size' => 36 ] ),
			'name'   => $user->user_nicename,
			'id'     => $user->ID,
		];
	}

	return $reviews;
}
