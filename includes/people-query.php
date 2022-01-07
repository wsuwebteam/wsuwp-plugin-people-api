<?php namespace WSUWP\Plugin\People\API;

class People_Query {

	/**
	 * Return a json array of profiles from the people directory based on request parameters.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_REST_Request  $request {
	 *     Optional. Url params to filter the response.
	 *
	 *     count Number of results per page. 'All' returns all profiles. Defaults to 10.
	 *
	 *     page Integer representing the page of results to return
	 *
	 *     nid Comma delimited list of people network ids
	 *
	 *     profile-order Comma delimited list of people network ids to sort them at the top of the list
	 *
	 *     classification Comma delimited list of classification taxonomy slugs
	 *
	 *     university-category Comma delimited list of wsuwp_university_category taxonomy slugs
	 *
	 *     university-location Comma delimited list of wsuwp_university_location taxonomy slugs
	 *
	 *     university-organization Comma delimited list of wsuwp_university_org taxonomy slugs
	 *
	 *     photo-size Photo size (thumbnail, medium, medium_large, large, full).  Defaults to medium.
	 * }
	 * @return string JSON encoded string of people profiles.
	 */
	public static function get_people( \WP_REST_Request $request ) {
		// setup variables
		$profiles = array();
		$params = array(
			'count' => $request['count'] ? sanitize_text_field( $request['count'] ) : 10,
			'page' => $request['page'] ? sanitize_text_field( $request['page'] ) : 1,
			'nid' => sanitize_text_field( $request['nid'] ),
			'profile_order' => sanitize_text_field( $request['profile-order'] ),
			'classification' => sanitize_text_field( $request['classification'] ),
			'university_category' => sanitize_text_field( $request['university-category'] ),
			'university_location' => sanitize_text_field( $request['university-location'] ),
			'university_organization' => sanitize_text_field( $request['university-organization'] ),
			'photo_size' => $request['photo-size'] ? sanitize_text_field( $request['photo-size'] ) : 'medium',
		);
		$taxonomies = array(
			'classification' => 'classification',
			'wsuwp_university_category' => 'university_category',
			'wsuwp_university_location' => 'university_location',
			'wsuwp_university_org' => 'university_organization',
		);
		$image_sizes = array(
			'thumbnail',
			'medium',
			'medium_large',
			'large',
			'full',
		);

		// build query args
		$args = array(
			'post_type' => 'wsuwp_people_profile',
			'posts_per_page' => strcasecmp( $params['count'], 'All' ) === 0 ? -1 : $params['count'],
			'paged' => $params['page'],
		);

		if ( $params['nid'] ) {
			$args['meta_query'] = array(
				array(
					'key' => '_wsuwp_profile_ad_nid',
					'value' => array_map( 'trim', explode( ',', $params['nid'] ) ),
					'compare' => 'IN',
				),
			);
		}

		foreach ( $taxonomies as $key => $value ) {
			$tax_queries = array();

			if ( $params[ $value ] ) {
				array_push(
					$tax_queries,
					array(
						'taxonomy' => $key,
						'field' => 'slug',
						'terms' => array_map( 'trim', explode( ',', $params[ $value ] ) ),
					),
				);
			}

			if ( ! empty( $tax_queries ) ) {
				$args['tax_query'] = $tax_queries;
			}
		}

		// query WordPress
		$query = new \WP_Query( $args );

		// iterate profiles and build response
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id = get_the_ID();
				$profile = array(
					'post_id' => $id,
					'nid' => get_post_meta( $id, '_wsuwp_profile_ad_nid', true ),
					'name' => get_the_title(),
					'title' => get_post_meta( $id, '_wsuwp_profile_title', true ) ?? get_post_meta( $id, '_wsuwp_profile_ad_title', true ),
					'office' => get_post_meta( $id, '_wsuwp_profile_alt_office', true ) ?? get_post_meta( $id, '_wsuwp_profile_ad_office', true ),
					'email' => get_post_meta( $id, '_wsuwp_profile_alt_email', true ) ?? get_post_meta( $id, '_wsuwp_profile_ad_email', true ),
					'address' => get_post_meta( $id, '_wsuwp_profile_alt_address', true ) ?? get_post_meta( $id, '_wsuwp_profile_ad_address', true ),
					'phone' => get_post_meta( $id, '_wsuwp_profile_alt_phone', true ) ?? get_post_meta( $id, '_wsuwp_profile_ad_phone', true ),
					'degree' => get_post_meta( $id, '_wsuwp_profile_degree', true ),
					'website' => get_post_meta( $id, '_wsuwp_profile_website', true ),
					'classification' => self::get_taxonomy_names( get_the_terms( $id, 'classification' ) ),
					'category' => array_merge( self::get_taxonomy_names( get_the_terms( $id, 'category' ) ), self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_category' ) ) ),
					'university_location' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_location' ) ),
					'university_organization' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_org' ) ),
					'tag' => self::get_taxonomy_names( get_the_terms( $id, 'post_tag' ) ),
					'bio' => apply_filters( 'the_content', get_the_content() ),
					'photo_sizes' => self::get_photo_urls( $id, $image_sizes, get_post_meta( $id, '_wsuwp_profile_photos', true ) ),
					'photo_srcset' => self::get_photo_srcset( $id, get_post_meta( $id, '_wsuwp_profile_photos', true ) ),
				);
				$profile['photo'] = $profile['photo_sizes'][ $params['photo_size'] ];

				array_push( $profiles, $profile );
			}

			$profiles = self::get_ordered_profiles( $profiles, $params['profile_order'] );
		}

		// return $profiles;
		return wp_json_encode( $profiles );
	}


	/**
	 * Return an array of size urls for the first valid image in a an array
	 *
	 * @param int $postId
	 * @param array $photos List of image ids.
	 * @return array
	 */
	private static function get_photo_urls( $post_id, $sizes, $photos ) {
		$photo_urls = null;

		if ( $photos && is_array( $photos ) ) {
			foreach ( $photos as $i => $photo_id ) {
				if ( is_string( get_post_status( $photo_id ) ) ) {
					$photo_urls = array();

					foreach ( $sizes as $size ) {
						$photo_urls[ $size ] = wp_get_attachment_image_src( $photos[ $i ], $size )[0];
					}

					break; // break, so we only return the first image
				}
			}
		}

		return $photo_urls;
	}


	private static function get_photo_srcset( $post_id, $photos ) {
		$srcset = null;

		if ( $photos && is_array( $photos ) ) {
			foreach ( $photos as $i => $photo_id ) {
				if ( is_string( get_post_status( $photo_id ) ) ) {

					$srcset = wp_get_attachment_image_srcset( $photos[ $i ] );

					break; // break, so we only return the first image
				}
			}
		}

		return $srcset;
	}


	/**
	 * Return an key-value-pair of taxonomy slugs and names
	 *
	 * @param array<WP_Term> $terms
	 * @return array
	 */
	private static function get_taxonomy_names( $terms ) {
		$result = array();

		if ( $terms && is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				array_push(
					$result,
					array(
						'slug' => $t->slug,
						'name' => $t->name,
					)
				);
			}
		}

		return $result;
	}


	private static function get_ordered_profiles( $profiles, $ordered_nids ) {

		if ( ! empty( $profiles ) ) {
			$ordered_profiles = array();

			// force custom ordered profiles
			if ( ! empty( $ordered_nids ) ) {
				$nids = array_map( 'trim', explode( ',', $ordered_nids ) );

				foreach ( $nids as $nid ) {
					if ( $nid ) {
						$key = array_search( $nid, array_column( $profiles, 'nid' ), true );

						if ( false !== $key ) {
							$profile = array_splice( $profiles, $key, 1 )[0];
							array_push( $ordered_profiles, $profile );
						}
					}
				}
			}

			// sort remaining profiles
			usort(
				$profiles,
				function( $p1, $p2 ) {
					return strcasecmp( $p1['name'], $p2['name'] );
				}
			);

			$profiles = array_merge( $ordered_profiles, $profiles );
		}

		return $profiles;
	}


	public static function get_terms( \WP_REST_Request $request ) {
		$terms = array();
		$params = array(
			'count' => $request['count'] ? sanitize_text_field( $request['count'] ) : 20,
			'taxonomy' => sanitize_text_field( $request['taxonomy'] ),
			'search_term' => sanitize_text_field( $request['s'] ),
		);

		$args = array(
			'taxonomy' => array_map( 'trim', explode( ',', $params['taxonomy'] ) ),
			'number' => $params['count'],
			'name__like' => $params['search_term'],
		);

		$results = get_terms( $args );

		// $terms = array_map(
		// 	function( $result ) {
		// 		return array(
		// 			'term_id' => $result->term_id,
		// 			'name' => $result->name,
		// 			'slug' => $result->slug,
		// 			'taxonomy' => $result->taxonomy,
		// 		);
		// 	},
		// 	$results
		// );

		foreach ( $results as $result ) {
			array_push(
				$terms,
				array(
					'term_id' => $result->term_id,
					'name' => $result->name,
					'slug' => $result->slug,
					'taxonomy' => $result->taxonomy,
				)
			);
		}

		return wp_json_encode( $terms );
	}


	public static function init() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'peopleapi/v1',
					'/people',
					array(
						'methods' => \WP_REST_Server::READABLE,
						'callback' => array( __CLASS__, 'get_people' ),
						'permission_callback' => '__return_true',
					)
				);

				register_rest_route(
					'peopleapi/v1',
					'/terms',
					array(
						'methods' => \WP_REST_Server::READABLE,
						'callback' => array( __CLASS__, 'get_terms' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}
}


People_Query::init();
