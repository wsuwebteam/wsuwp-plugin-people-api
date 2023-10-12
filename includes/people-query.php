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
			'tag'  => sanitize_text_field( $request['tag'] ),
			'research_interest' => sanitize_text_field( $request['research-interest'] ),
			'directory_inherit' => ( ! empty( $request['directory_inherit'] ) ) ? sanitize_text_field( $request['directory_inherit'] ) : 'all', // supports none|children|all
			'directory' => $request['directory'] ? sanitize_text_field( $request['directory'] ) : false,
			'ids'       => $request['ids'] ? explode( ',', sanitize_text_field( $request['ids'] ) ) : array(),
		);

		if ( $request['search'] ) {

			$params['search'] = sanitize_text_field( $request['search'] );

		}

		$taxonomies = array(
			'classification' => 'classification',
			'wsuwp_university_category' => 'university_category',
			'wsuwp_university_location' => 'university_location',
			'wsuwp_university_org' => 'university_organization',
			'post_tag' => 'tag',
			'wsuwp_research_interest' => 'research_interest',
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

		$directories  = ( ! empty( $params['directory'] ) ) ? Directories::get_directories( $params['directory'], array( 'inherit' => $params['directory_inherit'] ) ) : array();
		$people_index = ( ! empty( $directories ) ) ? Directories::get_people_directory_index( $directories ) : array();


		if ( ! empty( $directories ) && empty( $params['ids'] ) ) {

			$people_ids = Directories::get_people_ids_from_directories( $directories );

			$args['post__in'] = $people_ids;

			$args['posts_per_page'] = count( $people_ids );

		}

		if ( ! empty( $params['ids'] ) ) {

			$args['post__in'] = array_map( 'intval', $params['ids'] );
			$args['posts_per_page'] = count( $params['ids'] );

		}

		if ( $params['search'] ) {

			$args['s'] = $params['search'];

		}

		if ( $params['nid'] ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => '_wsuwp_profile_ad_nid',
					'value' => array_map( 'trim', explode( ',', $params['nid'] ) ),
					'compare' => 'IN',
				),
				array(
					'key' => '_wsuwp_nid',
					'value' => array_map( 'trim', explode( ',', $params['nid'] ) ),
					'compare' => 'IN',
				),
			);
		}

		$tax_queries = array();

		foreach ( $taxonomies as $key => $value ) {

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
		}

		if ( ! empty( $tax_queries ) ) {
			$args['tax_query'] = $tax_queries;
			$args['tax_query']['relation'] = 'AND';
		}

		// query WordPress
		$query = new \WP_Query( $args );

		// iterate profiles and build response
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id = get_the_ID();
				$url = get_the_permalink();
				$display_name = trim( self::get_first_post_meta( $id, array( 'wsuwp_display_name', '_wsuwp_fallback_display_name' ) ) );
				$name = empty( $display_name ) ? get_the_title() : $display_name;
				$profile = array(
					'post_id' => $id,
					'nid' => self::get_first_post_meta( $id, array( '_wsuwp_nid', '_wsuwp_profile_ad_nid' ) ),
					'name' => trim( $name ),
					'first_name' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_first_name', '_wsuwp_fallback_first_name', '_wsuwp_profile_ad_name_first' ) ) ),
					'last_name' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_last_name', '_wsuwp_fallback_last_name', '_wsuwp_profile_ad_name_last' ) ) ),
					'title' => self::get_first_post_meta( $id, array( 'wsuwp_title', '_wsuwp_fallback_title', '_wsuwp_profile_title', '_wsuwp_profile_ad_title' ) ),
					'email' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_email', '_wsuwp_fallback_email', '_wsuwp_profile_alt_email', '_wsuwp_profile_ad_email' ) ) ),
					'phone' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_phone', '_wsuwp_fallback_phone', '_wsuwp_profile_alt_phone', '_wsuwp_profile_ad_phone' ) ) ),
					'office' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_office', '_wsuwp_fallback_office', '_wsuwp_profile_alt_office', '_wsuwp_profile_ad_office' ) ) ),
					'address' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_address', '_wsuwp_fallback_address', '_wsuwp_profile_alt_address', '_wsuwp_profile_ad_address' ) ) ),
					'degree' => self::get_first_post_meta( $id, array( 'wsuwp_degree', '_wsuwp_fallback_degree', '_wsuwp_profile_degree' ) ),
					'website' => self::custom_trim( self::get_first_post_meta( $id, array( 'wsuwp_website', '_wsuwp_fallback_website', '_wsuwp_profile_website' ) ) ),
					'bio' => apply_filters( 'the_content', get_the_content() ),
					'directories' => ( array_key_exists( $id, $people_index ) ) ?  $people_index[ $id ] : array(),
					'classification' => self::get_taxonomy_names( get_the_terms( $id, 'classification' ) ),
					'category' => array_merge( self::get_taxonomy_names( get_the_terms( $id, 'category' ) ), self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_category' ) ) ),
					'university_location' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_location' ) ),
					'university_organization' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_university_org' ) ),
					'research_interest' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_research_interest' ) ),
					'tag' => self::get_taxonomy_names( get_the_terms( $id, 'post_tag' ) ),
					'focus_area' => self::get_taxonomy_names( get_the_terms( $id, 'wsuwp_focus_area' ) ),

					'photo_sizes' => self::resolve_photo_urls( $id, $image_sizes, self::get_first_post_meta( $id, array( 'wsuwp_photo', '_wsuwp_fallback_photo_sizes', '_wsuwp_profile_photos' ) ) ),
					'photo_srcset' => self::resolve_photo_srcset( $id, self::get_first_post_meta( $id, array( 'wsuwp_photo', '_wsuwp_fallback_photo_srcset', '_wsuwp_profile_photos' ) ) ),

					'profile_url' => $url,
				);
				$profile['photo'] = $profile['photo_sizes'][ $params['photo_size'] ];

				array_push( $profiles, $profile );
			}

			$profiles = self::get_ordered_profiles( $profiles, $params['profile_order'] );
		}

		return $profiles;
	}


	private static function custom_trim( $input ) {

		return trim( $input, " \t\n\r\0\x0B\xC2\xA0" ); // extend to include non-breaking spaces

	}


	private static function resolve_photo_urls( $post_id, $sizes, $photo_meta ) {

		// is an array of ids. Pulled from _wsuwp_profile_photos
		if ( ! empty( $photo_meta ) && is_array( $photo_meta ) && is_int( $photo_meta[0] ) ) {
			return self::get_photo_urls( $post_id, $sizes, $photo_meta );
		}

		// is an array containing photo_sizes already. Pulled from _wsuwp_fallback_photo_sizes
		if ( ! empty( $photo_meta ) && is_array( $photo_meta ) && is_null( $photo_meta[0] ) ) {
			return $photo_meta;
		}

		// is a string containing a photo id. Pulled from wsuwp_photo
		if ( ! empty( $photo_meta ) && is_string( $photo_meta ) && is_numeric( $photo_meta ) ) {
			$photo_id = $photo_meta;

			if ( is_string( get_post_status( $photo_id ) ) ) {
				$photo_urls = array();

				foreach ( $sizes as $size ) {
					$photo_urls[ $size ] = wp_get_attachment_image_src( $photo_id, $size )[0];
				}

				return $photo_urls;
			}
		}

		return null;

	}


	private static function resolve_photo_srcset( $post_id, $photo_meta ) {

		// is an array of ids. Pulled from _wsuwp_profile_photos
		if ( ! empty( $photo_meta ) && is_array( $photo_meta ) && is_int( $photo_meta[0] ) ) {
			return self::get_photo_srcset( $post_id, $photo_meta );
		}

		// is a string containing photo_srcset already. Pulled from _wsuwp_fallback_photo_srcset
		if ( ! empty( $photo_meta ) && is_string( $photo_meta ) && ! is_numeric( $photo_meta ) ) {
			return $photo_meta;
		}

		// is a string containing a photo id. Pulled from wsuwp_photo
		if ( ! empty( $photo_meta ) && is_numeric( $photo_meta ) ) {
			$photo_id = $photo_meta;

			if ( is_string( get_post_status( $photo_id ) ) ) {
				return wp_get_attachment_image_srcset( $photo_id );
			}
		}

		return null;

	}


	/**
	 * Return an array of size urls for the first valid image in a an array
	 *
	 * @param int $postId
	 * @param array $photos List of image ids.
	 * @return array
	 */
	private static function get_photo_urls( $post_id, $sizes, $photo_ids ) {

		$photo_urls = null;

		if ( $photo_ids && is_array( $photo_ids ) ) {
			foreach ( $photo_ids as $i => $photo_id ) {
				if ( is_string( get_post_status( $photo_id ) ) ) {
					$photo_urls = array();

					foreach ( $sizes as $size ) {
						$photo_urls[ $size ] = wp_get_attachment_image_src( $photo_ids[ $i ], $size )[0];
					}

					break; // break, so we only return the first image
				}
			}
		}

		return $photo_urls;

	}


	private static function get_photo_srcset( $post_id, $photo_ids ) {

		$srcset = null;

		if ( $photo_ids && is_array( $photo_ids ) ) {
			foreach ( $photo_ids as $i => $photo_id ) {
				if ( is_string( get_post_status( $photo_id ) ) ) {

					$srcset = wp_get_attachment_image_srcset( $photo_ids[ $i ] );

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
					$p1_last_name = ! empty( $p1['last_name'] ) ? $p1['last_name'] : end( explode( ' ', trim( $p1['name'] ) ) );
					$p2_last_name = ! empty( $p2['last_name'] ) ? $p2['last_name'] : end( explode( ' ', trim( $p2['name'] ) ) );

					return strcasecmp( $p1_last_name, $p2_last_name );
				}
			);

			$profiles = array_merge( $ordered_profiles, $profiles );
		}

		return $profiles;
	}

	private static function get_first_post_meta( $id, $keys ) {

		foreach ( $keys as $key ) {
			$value = get_post_meta( $id, $key, true );

			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		return '';

	}

	/**
	 * search_terms
	 *
	 * @param \WP_REST_Request $request
	 * @return array<WP_Term>
	 */
	public static function search_terms( \WP_REST_Request $request ) {
		$terms = array();
		$params = array(
			'count' => $request['count'] ? sanitize_text_field( $request['count'] ) : 20,
			'taxonomy' => sanitize_text_field( $request['taxonomy'] ),
			'search_term' => sanitize_text_field( $request['s'] ),
		);

		$args = array(
			'taxonomy' => array_map( 'trim', explode( ',', $params['taxonomy'] ) ),
			'number' => $params['count'],
			'hide_empty' => false,
			'name__like' => $params['search_term'],
		);

		$results = get_terms( $args );

		/// Leaving this here as a reference:
		/// For some crazy reason this was returning on object on production instead of an array...at least I think it was this 🤪.
		/// I also added the permission_callback in the same commit.
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

		return $terms;
	}


	/**
	 * get_all_terms
	 *
	 * @param \WP_REST_Request $request
	 * @return array<WP_Term>
	 */
	public static function get_all_terms( \WP_REST_Request $request ) {
		$terms = array();
		$params = array(
			'taxonomy' => sanitize_text_field( $request['taxonomy'] ),
		);

		$args = array(
			'taxonomy' => array_map( 'trim', explode( ',', $params['taxonomy'] ) ),
			'hide_empty' => false,
			'number' => 0,
		);

		$results = get_terms( $args );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		foreach ( $results as $result ) {
			array_push(
				$terms,
				array(
					'term_id' => $result->term_id,
					'name' => $result->name,
					'slug' => $result->slug,
					'parent' => $result->parent,
					'description' => $result->description,
					'taxonomy' => $result->taxonomy,
				)
			);
		}

		return $terms;
	}

	/**
	 * create_organization
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function create_organization( \WP_REST_Request $request ) {

		// header( 'Access-Control-Allow-Origin: *' ); // REMOVE

		$params = $request->get_body_params();

		if ( '' === trim( $params['tag_name'] ) ) {
			return new \WP_Error( 'cant-create', 'Tag name field is required.', array( 'status' => 500 ) );
		}

		$parent_term = get_term_by( 'slug', $params['tag_parent'], 'wsuwp_university_org' );

		$response = wp_insert_term(
			$params['tag_name'],
			'wsuwp_university_org',
			array(
				'slug' => $params['tag_slug'],
				'parent' => $parent_term->term_id,
				'description' => $params['tag_description'],
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$term = get_term( $response['term_id'], 'wsuwp_university_org' );

		return new \WP_REST_Response( $term, 200 );

	}


	public static function sync_organization( \WP_REST_Request $request ) {

		// header( 'Access-Control-Allow-Origin: *' ); // REMOVE

		// get params
		$params = $request->get_body_params();
		$nid = $params['nid'];
		$org_slug = $params['org'];
		$action = $params['action']; // add or remove

		if ( empty( $nid ) || empty( $org_slug ) || empty( $action ) ) {
			return new \WP_Error( 'missing-params', 'nid, organization slug, and action are required.', array( 'status' => 500 ) );
		}

		// setup query
		$args = array(
			'posts_per_page'   => -1,
			'post_type'        => 'wsuwp_people_profile',
			'meta_key'         => '_wsuwp_profile_ad_nid',
			'meta_value'       => $nid,
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id = get_the_ID();

				if ( 'add' === $action ) {
					wp_set_object_terms( $id, $org_slug, 'wsuwp_university_org', true );
				} elseif ( 'remove' === $action ) {
					wp_remove_object_terms( $id, $org_slug, 'wsuwp_university_org' );
				}
			}
		}

	}


	public static function register_api_endpoints() {

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
				'callback' => array( __CLASS__, 'search_terms' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/get-all-terms',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_all_terms' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/create-organization',
			array(
				'methods' => \WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'create_organization' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'peopleapi/v1',
			'/sync-organization',
			array(
				'methods' => \WP_REST_Server::EDITABLE,
				'callback' => array( __CLASS__, 'sync_organization' ),
				'permission_callback' => '__return_true',
			)
		);

	}


	public static function init() {

		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );

	}
}


People_Query::init();
