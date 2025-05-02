<?php
/**
 * Checklist Seokar - AJAX Handler Functions
 *
 * This file contains the callback functions for handling AJAX requests
 * initiated by the checklist's JavaScript (assets/js/script.js).
 *
 * @package Checklist_Seokar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler to check the featured image status for a given post.
 *
 * Reads the post ID from the AJAX request, checks nonce and permissions,
 * determines if a featured image is set, and returns a JSON response
 * with the status ('success' or 'warning') and a corresponding message.
 *
 * Hooked to 'wp_ajax_csk_check_featured_image'.
 */
function csk_ajax_check_featured_image() {
	// 1. Verify Nonce
	// Use the specific nonce created for this action in wp_localize_script
	$nonce_key = 'csk_featured_image_nonce'; // Should match the key used in wp_localize_script
	$nonce_value = isset($_POST['_ajax_nonce']) ? sanitize_key($_POST['_ajax_nonce']) : ''; // WP usually sends nonce via _ajax_nonce
    // Fallback if JS sends it directly (make sure JS uses the correct key 'featuredImageNonce')
    if (empty($nonce_value) && isset($_POST['featuredImageNonce'])) {
         $nonce_value = sanitize_key($_POST['featuredImageNonce']);
    }

	if ( ! wp_verify_nonce( $nonce_value, 'csk_featured_image_nonce' ) ) {
		wp_send_json_error(
			[
				'message' => __( 'Nonce verification failed. Security check.', CSK_TEXT_DOMAIN ),
                'debug'   => 'Nonce value received: ' . $nonce_value, // For debugging only
			],
			403 // Forbidden
		);
		return; // wp_die() is called by wp_send_json_error
	}

	// 2. Get and Validate Post ID
	if ( ! isset( $_POST['post_id'] ) ) {
		wp_send_json_error(
			[
				'message' => __( 'Error: Post ID not provided in the request.', CSK_TEXT_DOMAIN ),
                'debug'   => 'Missing post_id parameter.'
			],
			400 // Bad Request
		);
		return;
	}
	$post_id = absint( $_POST['post_id'] );
	if ( $post_id <= 0 ) {
		wp_send_json_error(
			[
				'message' => __( 'Error: Invalid Post ID received.', CSK_TEXT_DOMAIN ),
                'debug'   => 'Invalid post_id value: ' . esc_html($_POST['post_id']),
			],
			400 // Bad Request
		);
		return;
	}

	// 3. Check User Permissions
    // Ensure the user has the capability to edit the specific post
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error(
			[
				'message' => __( 'You do not have permission to check the status for this post.', CSK_TEXT_DOMAIN ),
                'debug'   => 'Permission denied for user: ' . get_current_user_id() . ' on post: ' . $post_id,
			],
			403 // Forbidden
		);
		return;
	}

    // 4. Perform the Actual Check (Featured Image)
    // Use the same logic as in checklist-functions.php, but simplified for AJAX context
    $has_thumbnail = has_post_thumbnail( $post_id );

    if ( $has_thumbnail ) {
        $status = 'success';
        $message = __( 'تصویر شاخص تنظیم شده است.', CSK_TEXT_DOMAIN );
    } else {
        $status = 'warning'; // Warning, not usually a hard error
        $message = __( 'تصویر شاخص تنظیم نشده است (پیشنهاد می‌شود).', CSK_TEXT_DOMAIN );
    }

    // 5. Send JSON Response
    wp_send_json_success(
        [
            'status'  => $status,
            'message' => $message,
            'post_id' => $post_id // Optionally return the post_id for confirmation
        ]
    );

    // wp_send_json_success automatically calls wp_die()
}

// Hook the function to the WordPress AJAX action
// The action name 'csk_check_featured_image' must match the 'action' parameter in the JS AJAX call
add_action( 'wp_ajax_csk_check_featured_image', 'csk_ajax_check_featured_image' );

// Note: We don't need a 'wp_ajax_nopriv_' version because this check is only relevant for logged-in users in the admin area.

?>
