<?php
/**
 * Plugin Name: Social Media Publisher
 * Plugin URI: https://github.com/MantasDainys/social-media-publisher
 * GitHub Plugin URI: https://github.com/MantasDainys/social-media-publisher
 * GitHub Branch: main
 * Description: Automatically publish WP posts to social media platform
 * Version: 1.0.0
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

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

register_activation_hook( __FILE__, array( 'SocialMediaPublisher', 'facebook_options' ) );

/**
 * @todo Remember me
 */
class SocialMediaPublisher {

	private $appId;
	private $appSecret;
	private $pageAccessToken;
	private $pageId;
	private $repo_url = 'https://api.github.com/repos/MantasDainys/social-media-publisher/releases/latest';
	private $plugin_slug = 'social-media-publisher';

	public static function facebook_options() {
		add_option( 'wp_facebook_auto_publish_app_id', '' );
		add_option( 'wp_facebook_auto_publish_app_secret', '' );
		add_option( 'wp_facebook_auto_publish_page_access_token', '' );
		add_option( 'wp_facebook_auto_publish_page_id', '' );
	}

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_facebook_publish_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_facebook_publish_meta_box_data' ] );
		add_action( 'admin_notices', [ $this, 'custom_admin_error_notice' ] );


		$this->appId           = get_option( 'wp_facebook_auto_publish_app_id' );
		$this->appSecret       = get_option( 'wp_facebook_auto_publish_app_secret' );
		$this->pageAccessToken = get_option( 'wp_facebook_auto_publish_page_access_token' );
		$this->pageId          = get_option( 'wp_facebook_auto_publish_page_id' );

	}

	public static function get_plugin_version() {
		$plugin_file = __FILE__;
		$plugin_data = get_file_data( $plugin_file, [
			'Version' => 'Version',
		]);
		return $plugin_data['Version'] ?? 'Unknown';
	}


	public function add_facebook_publish_meta_box() {
		add_meta_box(
			'facebook_publish_meta_box',
			__( 'Publish on Facebook', 'social-media-publisher' ),
			[ $this, 'facebook_publish_meta_box_callback' ],
			'post',
			'side',
			'high'
		);
	}

	public function facebook_publish_meta_box_callback( $post ) {
		$value = get_post_meta( $post->ID, '_facebook_publish', true );
		echo '<label for="facebook_publish">';
		echo '<input type="checkbox" id="facebook_publish" name="facebook_publish" value="1" ' . esc_attr( checked( 1, $value, false ) ) . ' />';
		esc_html_e( 'Publish this post on the FB page', 'social-media-publisher' );
		echo '</label>';
		wp_nonce_field( 'facebook_publish_nonce_action', 'facebook_publish_nonce_name' );
	}

	public function save_facebook_publish_meta_box_data( $post_id ) {

		if ( ! isset( $_POST['facebook_publish_nonce_name'] ) || ! check_admin_referer( 'facebook_publish_nonce_action', 'facebook_publish_nonce_name' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['facebook_publish'] ) ) {
			$value = get_post_meta( $post_id, '_facebook_publish', true );
			if ( ! $value ) {
				update_post_meta( $post_id, '_facebook_publish', 1 );
				$this->publish_post_to_facebook( $post_id );
			}
		} else {
			update_post_meta( $post_id, '_facebook_publish', 0 );
		}
	}

	function get_post_gallery_images_full( $post ) {

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

	private function publish_post_to_facebook( $post_id ) {

		$post = get_post( $post_id );

		$fb = new Facebook( [
			'app_id'                => $this->appId,
			'app_secret'            => $this->appSecret,
			'default_graph_version' => 'v21.0',
		] );

		$text = preg_replace( '/\[gallery[^\]]*\]/', '', $post->post_content );

		$postData = [
			'message'   => $post->post_title . "\n\n" . wp_strip_all_tags( $text ),
			'published' => false
		];

		$attached_media = [];
		$gallery_images = $this->get_post_gallery_images_full( $post );

		if ( ! empty( $gallery_images ) ) {
			foreach ( $gallery_images as $index => $image_url ) {
				try {
					$postData['url']       = $image_url;
					$postData['published'] = false;
					$photoResponse         = $fb->post( '/' . $this->pageId . '/photos', $postData, $this->pageAccessToken );
					$photoId = $photoResponse->getGraphNode()['id'];
					$attached_media[] = [ 'media_fbid' => $photoId ];
				} catch ( FacebookResponseException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				} catch ( FacebookSDKException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				}
			}
		}

		if ( ! empty( $attached_media ) ) {
			try {
				$postData['attached_media'] = $attached_media;
				$postData['published']      = true;
				$fb->post( '/' . $this->pageId . '/feed', $postData, $this->pageAccessToken );
			} catch ( FacebookResponseException $e ) {
				set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
			} catch ( FacebookSDKException $e ) {
				set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
			}
		} else {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$thumbnail_url = wp_get_attachment_image_src( $thumbnail_id, 'full' )[0];
				try {
					$postData['url']       = $thumbnail_url;
					$postData['published'] = true;
					$fb->post( '/' . $this->pageId . '/photos', $postData, $this->pageAccessToken );
				} catch ( FacebookResponseException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				} catch ( FacebookSDKException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				}
			} else {
				try {
					$postData['published'] = true;
					$fb->post( '/' . $this->pageId . '/feed', $postData, $this->pageAccessToken );
				} catch ( FacebookResponseException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				} catch ( FacebookSDKException $e ) {
					set_transient( 'fb_error', 'FacebookResponseException: ' . $e->getMessage(), 30 );
				}
			}
		}
	}

	public function custom_admin_error_notice() {
		$error = get_transient( 'fb_error' );
		if ( ! empty( $error ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			delete_transient( 'fb_error' );
		}
	}
}

new SocialMediaPublisher();