<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Watermark_Actions_Controller {

	/**
	 * Plugin instance.
	 *
	 * @var Image_Watermark
	 */
	private $plugin;

	/**
	 * Upload handler service.
	 *
	 * @var Image_Watermark_Upload_Handler
	 */
	private $upload_handler;

	/**
	 * Controller constructor.
	 *
	 * @param Image_Watermark $plugin
	 * @param Image_Watermark_Upload_Handler $upload_handler
	 */
	public function __construct( Image_Watermark $plugin, Image_Watermark_Upload_Handler $upload_handler ) {
		$this->plugin = $plugin;
		$this->upload_handler = $upload_handler;
	}

	/**
	 * Handles manual AJAX watermark requests.
	 */
	public function watermark_action_ajax() {
		if ( ! wp_doing_ajax() || ! isset( $_POST['_iw_nonce'], $_POST['iw-action'], $_POST['attachment_id'] ) || ! is_numeric( $_POST['attachment_id'] ) || ! wp_verify_nonce( $_POST['_iw_nonce'], 'image-watermark' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action.', 'image-watermark' ) );
		}

		$post_id = (int) $_POST['attachment_id'];
		$action = sanitize_key( $_POST['iw-action'] );
		$action = in_array( $action, [ 'applywatermark', 'removewatermark' ], true ) ? $action : false;
		$options = $this->plugin->options;

		if ( $post_id > 0 && $action && $options['watermark_image']['manual_watermarking'] == 1 && ( wp_attachment_is_image( $options['watermark_image']['url'] ) || $action === 'removewatermark' ) ) {
			$data = wp_get_attachment_metadata( $post_id, false );

			if ( in_array( get_post_mime_type( $post_id ), $this->plugin->get_allowed_mime_types(), true ) && is_array( $data ) ) {
				if ( $action === 'applywatermark' ) {
					$success = $this->upload_handler->apply_watermark( $data, $post_id, 'manual' );

					if ( ! empty( $success['error'] ) ) {
						wp_send_json_success( $success['error'] );
					}

					wp_send_json_success( 'watermarked' );
				} elseif ( $action === 'removewatermark' ) {
					$success = $this->upload_handler->remove_watermark( $data, $post_id, 'manual' );

					if ( $success ) {
						wp_send_json_success( 'watermarkremoved' );
					}

					wp_send_json_success( 'skipped' );
				}
			} else {
				wp_send_json_success( 'skipped' );
			}
		}

		wp_send_json_error( __( 'You are not allowed to perform this action.', 'image-watermark' ) );
	}

	/**
	 * Handles bulk actions from the media list table.
	 */
	public function watermark_bulk_action() {
		global $pagenow;

		if ( $pagenow !== 'upload.php' || ! $this->plugin->get_extension() ) {
			return;
		}

		$wp_list_table = _get_list_table( 'WP_Media_List_Table' );
		$action = $wp_list_table->current_action();
		$action = in_array( $action, [ 'applywatermark', 'removewatermark' ], true ) ? $action : false;
		$options = $this->plugin->options;

		if ( $action && $options['watermark_image']['manual_watermarking'] == 1 && ( wp_attachment_is_image( $options['watermark_image']['url'] ) || $action === 'removewatermark' ) ) {
			check_admin_referer( 'bulk-media' );

			$location = esc_url( remove_query_arg( [ 'watermarked', 'watermarkremoved', 'skipped', 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ], wp_get_referer() ) );

			if ( ! $location ) {
				$location = 'upload.php';
			}

			$location = esc_url( add_query_arg( 'paged', $wp_list_table->get_pagenum(), $location ) );

			$post_ids = isset( $_REQUEST['media'] ) ? array_map( 'intval', $_REQUEST['media'] ) : [];

			if ( $post_ids ) {
				$watermarked = $watermarkremoved = $skipped = 0;
				$messages = [];

				foreach ( $post_ids as $post_id ) {
					$data = wp_get_attachment_metadata( $post_id, false );

					if ( in_array( get_post_mime_type( $post_id ), $this->plugin->get_allowed_mime_types(), true ) && is_array( $data ) ) {
						if ( $action === 'applywatermark' ) {
							$success = $this->upload_handler->apply_watermark( $data, $post_id, 'manual' );

							if ( ! empty( $success['error'] ) ) {
								$messages[] = $success['error'];
							} else {
								$watermarked++;
								$watermarkremoved = -1;
							}
						} elseif ( $action === 'removewatermark' ) {
							$success = $this->upload_handler->remove_watermark( $data, $post_id, 'manual' );

							if ( $success ) {
								$watermarkremoved++;
							} else {
								$skipped++;
							}

							$watermarked = -1;
						}
					} else {
						$skipped++;
					}
				}

				$args = [
					'watermarked'      => $watermarked,
					'watermarkremoved' => $watermarkremoved,
					'skipped'          => $skipped,
				];

				if ( ! empty( $messages ) ) {
					$args['messages'] = $messages;
				}

				$location = esc_url( add_query_arg( $args, $location ), null, '' );
			}

			wp_redirect( $location );
			exit;
		}
	}
}
