<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php'; // change path as needed

class AutoFacebookPost {

	private $page_id = '';

	private $access_token = '';

	public function __construct( $page_id, $app_id, $app_secret, $access_token ) {
		$this->access_token = $access_token;
		$this->page_id      = $page_id;
		$this->fb           = new \Facebook\Facebook(
			[
				'app_id'                => $app_id,
				'app_secret'            => $app_secret,
				'default_graph_version' => 'v5.0',
				'default_access_token'  => $access_token,
			]
		);
	}

	public function post( $product ) {
		$page_access_token = $this->getPageAccessToken();
		if ( ! $page_access_token ) {
			return new WP_Error( 'error', __( 'Graph did not return a page access token', 'afp' ) );
		}

		$linkData = $this->preparePostData( $product );

		try {
			$response = $this->fb->post( "/{$this->page_id}/feed", $linkData, $page_access_token );
		} catch ( \Facebook\Exceptions\FacebookResponseException $e ) {
			return new WP_Error( 'error', sprintf( __( 'Graph returned an error: %s', 'afp' ), $e->getMessage() ) );
		}
		$graph_node = $response->getGraphNode();
		return $graph_node;
	}


	/**
	 * Get page access token
	 *
	 * @return string Page access token
	 */
	private function getPageAccessToken() {
		try {
			$response = $this->fb->get( '/me/accounts' );
		} catch ( \Facebook\Exceptions\FacebookResponseException $e ) {
			error_log( 'Graph returned an error: ' . $e->getMessage() );
			return false;
		}

		if ( 200 != $response->getHttpStatusCode() ) {
			error_log( 'Graph returned a HTTP status: ' . $response->getHttpStatusCode() );
			return false;
		}

		$body = $response->getDecodedBody();
		$data = isset( $body['data'] ) ? $body['data'] : '';
		if ( $data && count( $data ) > 0 ) {
			foreach ( $data as $account ) {
				if ( isset( $account['id'] ) && $account['id'] === $this->page_id ) {
					return $account['access_token'];
				}
			}
		}
		return false;
	}
	/**
	 * Prepare post data to share on facebook from product
	 *
	 * @param  WC_Product $product product to extract data to post
	 * @return array               post data
	 */
	public function preparePostData( $product ) {
		$settings = get_option( 'afp_settings' );

		$use_top_hashtags = $settings['afp_fb_use_top_allhashtags'];
		$hashtags         = explode( ' ', $settings['afp_fb_hashtags'] );
		$product_tags     = get_the_terms( $product->get_id(), 'product_tag' );

		if ( ! empty( $product_tags ) ) {
			$product_tags = array_column( $product_tags, 'name' );
			$hashtags     = array_merge( $hashtags, $product_tags );
		}

		foreach ( $hashtags as $key => $value ) {
			$hashtags[ $key ] = '#' . $value;
		}

		if ( 'yes' === $use_top_hashtags ) {
			$top_hashtags = $this->get_top_hashtags( $hashtags );
			$hashtags     = array_merge( $hashtags, $top_hashtags );
		}

		$message = sprintf( "%s\n%s\n%s", $product->get_title(), wp_strip_all_tags( $product->get_description() ), implode( ' ', $hashtags ) );

		$images_ids        = $product->get_gallery_image_ids();
		$child_attachments = [];
		$i                 = 0;
		foreach ( $images_ids as $id ) {
			if ( $i > 5 ) {
				break;
			}
			$child_attachments[] = [
				'description' => sprintf( '%.2f%s', $product->get_price(), get_woocommerce_currency_symbol() ),
				'link'        => $product->get_permalink(),
				'name'        => $product->get_title(),
				'picture'     => esc_url( wp_get_attachment_url( $id ) ),
			];
			++$i;
		}

		$feed_targeting        = [];
		$age_max               = intval( $settings['afp_fb_feed_age_max'] );
		$age_min               = intval( $settings['afp_fb_feed_age_min'] );
		$college_years         = $settings['afp_fb_feed_college_years'];
		$genders               = intval( $settings['afp_fb_feed_genders'] );
		$relationship_statuses = isset( $settings['afp_fb_relationship_statuses'] ) ? $settings['afp_fb_relationship_statuses'] : '';
		$geo_locations         = isset( $settings['afp_fb_feed_geo_locations'] ) ? $settings['afp_fb_feed_geo_locations'] : '';

		if ( '' !== $age_max && $age_max > 0 ) {
			$feed_targeting['age_max'] = $age_max;
		}
		if ( '' !== $age_min && $age_min > 0 ) {
			$feed_targeting['age_min'] = $age_min;
		}
		if ( ! empty( $college_years ) ) {
			$feed_targeting['college_years'] = explode( ' ', $college_years );
		}
		if ( $genders > 0 ) {
			$feed_targeting['genders'] = $genders;
		}
		if ( ! empty( $relationship_statuses ) ) {
			$feed_targeting['relationship_statuses'] = $relationship_statuses;
		}
		if ( ! empty( $geo_locations ) ) {
			$feed_targeting['geo_locations'] = [
				'countries' => $geo_locations,
			];
		}

		$linkData = [
			'link'              => $product->get_permalink(),
			'message'           => $message,
			'child_attachments' => $child_attachments,
		];

		if ( ! empty( $feed_targeting ) ) {
			$linkData['feed_targeting'] = $feed_targeting;
		}

		return apply_filters( 'afp_fb_link_data', $linkData );
	}

	protected function get_top_hashtags( $hashtags = [] ) {
		$top_hashtags = [];
		foreach ( $hashtags as $hashtag ) {
			$response = wp_remote_post(
				'https://www.all-hashtag.com/library/contents/ajax_generator.php',
				[
					'method'  => 'POST',
					'timeout' => 120,
					'body'    => [
						'keyword' => $hashtag,
						'filter'  => 'top',
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log( "Something went wrong: $error_message" );
				return;
			} else {
				$all_hashtags = $this->parse_allhashtags_response( $response['body'] );
				$top_hashtags = array_merge( $top_hashtags, array_splice( $all_hashtags, 1, 1 ) );
				// return $top_hashtags;
			}
		}

		return $top_hashtags;
	}

	protected function parse_allhashtags_response( $body ) {
		preg_match( '/<div id="copy-hashtags" class="copy-hashtags">(.+?)<\/div>/', $body, $m );
		return isset( $m[1] ) ? preg_split( '/\s+/', $m[1], -1, PREG_SPLIT_NO_EMPTY ) : [];
	}
}
