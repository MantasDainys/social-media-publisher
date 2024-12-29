<?php
/**
 * Plugin Name: Social Media Publisher
 * Plugin URI: https://github.com/MantasDainys/social-media-publisher
 * GitHub Plugin URI: https://github.com/MantasDainys/social-media-publisher
 * GitHub Branch: main
 * Description: Automatically publish WP posts to social media platform
 * Version: 1.0.1
 * Author: Mantas Dainys
 * Text Domain: social-media-publisher
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @package social-media-publisher
 */

if ( ! defined( 'ABSPATH' ) ) { 
	die( 'Invalid request.' );
}

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( 'wpsmp_social_media_publisher', 'wpsmp_options' ) );

class wpsmp_social_media_publisher{

	private $appId;
	private $appSecret;
	private $pageAccessToken;
	private $pageId;

	public static function wpsmp_options() {
		add_option( 'wpsmp_app_id', '' );
		add_option( 'wpsmp_app_secret', '' );
		add_option( 'wpsmp_page_access_token', '' );
		add_option( 'wpsmp_page_id', '' );
	}

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'wpsmp_add_publish_meta_box' ] );
		add_action( 'save_post', [ $this, 'wpsmp_save_publish_meta_box_data' ] );
		add_action( 'admin_notices', [ $this, 'wpsmp_admin_error_notice' ] );

		$this->appId           = get_option( 'wpsmp_app_id' );
		$this->appSecret       = get_option( 'wpsmp_app_secret' );
		$this->pageAccessToken = get_option( 'wpsmp_page_access_token' );
		$this->pageId          = get_option( 'wpsmp_page_id' );

	}

	public function wpsmp_add_publish_meta_box() {
		add_meta_box(
			'wpsmp_publish_meta_box',
			__( 'Publish on Facebook', 'social-media-publisher' ),
			[ $this, 'wpsmp_publish_meta_box_callback' ],
			'post',
			'side',
			'high'
		);
	}

	public function wpsmp_publish_meta_box_callback( $post ) {
		$value = get_post_meta( $post->ID, '_wpsmp_publish', true );
		echo '<label for="wpsmp_publish">';
		echo '<input type="checkbox" id="wpsmp_publish" name="wpsmp_publish" value="1" ' . esc_attr( checked( 1, $value, false ) ) . ' />';
		esc_html_e( 'Publish this post on the FB page', 'social-media-publisher' );
		echo '</label>';
		wp_nonce_field( 'wpsmp_publish_nonce_action', 'wpsmp_publish_nonce_name' );
	}

	public function wpsmp_save_publish_meta_box_data( $post_id ) {

		if ( ! isset( $_POST['wpsmp_publish_nonce_name'] ) || ! check_admin_referer( 'wpsmp_publish_nonce_action', 'wpsmp_publish_nonce_name' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wpsmp_publish'] ) ) {
			$value = get_post_meta( $post_id, '_wpsmp_publish', true );
			if ( ! $value ) {
				update_post_meta( $post_id, '_wpsmp_publish', 1 );
				$this->wpspm_publish_post_to_facebook( $post_id );
			}
		} else {
			update_post_meta( $post_id, '_wpsmp_publish', 0 );
		}
	}

	function wpsmp_get_post_gallery_images_full( $post ) {

		if ( ! has_shortcode( $post->post_content, 'gallery' ) ) {
			return [];
		}
		preg_match( '/\[gallery.*ids=.(.*).\]/', $post->post_content, $ids );
		if ( empty( $ids[1] ) ) {
			return [];
		}

		$image_ids = explode( ',', $ids[1] );
		$images    = [];
		foreach ( $image_ids as $image_id ) {
			$image_url = wp_get_attachment_image_src( $image_id, 'full' )[0];
			if ( $image_url ) {
				$images[] = $image_url;
			}
		}

		return $images;
	}

	private function wpspm_validate_params() {

		if (!$this->appId) {
			set_transient('wpsmp_error', __('AppId is missing', 'social-media-publisher'), 30);
			return false;
		}

		if (!$this->appSecret) {
			set_transient('wpsmp_error', __('AppSecret is missing', 'social-media-publisher'), 30);
			return false;
		}

		if (!$this->pageAccessToken) {
			set_transient('wpsmp_error', __('pageAccessToken is missing', 'social-media-publisher'), 30);
			return false;
		}

		if (!$this->pageId) {
			set_transient('wpsmp_error', __('Page ID is missing', 'social-media-publisher'), 30);
			return false;
		}

		return true;
	}

	private function wpspm_publish_post_to_facebook($post_id) {

		if (!$this->wpspm_validate_params()) {
			return;
		}

		$post = get_post($post_id);
		$text = preg_replace('/\[gallery[^\]]*\]/', '', $post->post_content);

		$postData = [
			'message' => $post->post_title . "\n\n" . wp_strip_all_tags($text),
			'published' => false,
		];

		$attached_media = [];
		$gallery_images = $this->wpsmp_get_post_gallery_images_full($post);

		if (!empty($gallery_images)) {
			foreach ($gallery_images as $index => $image_url) {
				try {
					$postData['url'] = $image_url;

					$photoResponse = $this->wpsmp_send_request('/' . $this->pageId . '/photos', $postData);

					if (!empty($photoResponse['id'])) {
						$attached_media[] = ['media_fbid' => $photoResponse['id']];
					}
				} catch (Exception $e) {
					set_transient('wpsmp_error', __('Facebook API Error: ', 'social-media-publisher') . $e->getMessage(), 30);
				}
			}
		}

		if (!empty($attached_media)) {
			try {
				$postData['attached_media'] = $attached_media;
				$postData['published'] = true;
				$this->wpsmp_send_request('/' . $this->pageId . '/feed', $postData);
			} catch (Exception $e) {
				set_transient('wpsmp_error', __('Facebook API Error: ', 'social-media-publisher') . $e->getMessage(), 30);
			}
		} else {
			$thumbnail_id = get_post_thumbnail_id($post_id);
			if ($thumbnail_id) {
				$thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'full')[0];
				try {
					$postData['url'] = $thumbnail_url;
					$postData['published'] = true;
					$this->wpsmp_send_request('/' . $this->pageId . '/photos', $postData);
				} catch (Exception $e) {
					set_transient('wpsmp_error', __('Facebook API Error: ', 'social-media-publisher') . $e->getMessage(), 30);
				}
			} else {
				try {
					$postData['published'] = true;
					$this->wpsmp_send_request('/' . $this->pageId . '/feed', $postData);
				} catch (Exception $e) {
					set_transient('wpsmp_error', __('Facebook API Error: ', 'social-media-publisher') . $e->getMessage(), 30);
				}
			}
		}
	}

	private function wpsmp_send_request($endpoint, $params) {
		$url = 'https://graph.facebook.com/v21.0' . $endpoint;
		$params['access_token'] = $this->pageAccessToken;

		$response = wp_remote_post($url, [
			'body' => $params,
		]);

		if (is_wp_error($response)) {
			throw new Exception(esc_html($response->get_error_message()));
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($body['error'])) {
			throw new Exception(esc_html($body['error']['message']));
		}

		return $body;
	}

	public function wpsmp_admin_error_notice() {
		$error = get_transient( 'wpsmp_error' );
		if ( ! empty( $error ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			delete_transient( 'wpsmp_error' );
		}
	}
}

new wpsmp_social_media_publisher();
