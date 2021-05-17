<?php namespace WSUWP\Plugin\People\API;


class People_Query
{
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
     *     university_category Comma delimited list of wsuwp_university_category taxonomy slugs
     *
     *     university_location Comma delimited list of wsuwp_university_location taxonomy slugs
     *
     *     university_organization Comma delimited list of wsuwp_university_org taxonomy slugs
     * }
     * @return string JSON encoded string of people profiles.
     */
    public static function get_people(\WP_REST_Request $request)
    {
        $profiles = array();
        $count = $request['count'] ?? 10;
        $taxonomies = array(
            'wsuwp_university_category' => 'university_category',
            'wsuwp_university_location' => 'university_location',
            'wsuwp_university_org' => 'university_organization'
        );


        // build query args
        $args = array(
            'post_type' => 'wsuwp_people_profile',
            'posts_per_page' => strcasecmp($count, 'All') == 0 ? -1 : $count,
            'paged' => $request['page'] ?? 1,
        );

        if ($request['nid']) {
            $args['meta_query'] = array(
                array(
                    'key' => '_wsuwp_profile_ad_nid',
                    'value' => array_map('trim', explode(',', $request['nid'])),
                    'compare' => 'IN',
                ),
            );
        }

        foreach($taxonomies as $key => $value){
            $tax_queries = array();

            if ($request[$value]) {
                array_push($tax_queries,
                    array(
                        'taxonomy' => $key,
                        'field' => 'slug',
                        'terms' => array_map('trim', explode(',', $request[$value])),
                    ),
                );
            }

            if(!empty($tax_queries)){
                $args['tax_query'] = $tax_queries;
            }
        }

        // query wordpress
        $query = new \WP_Query($args);

        // iterate profiles and build response
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $profile = array(
                    'nid' => get_post_meta($id, '_wsuwp_profile_ad_nid', true),
                    'photo' => self::get_photo_urls($id, get_post_meta($id, '_wsuwp_profile_photos', true)),
                    'name' => get_the_title(),
                    'title' => get_post_meta($id, '_wsuwp_profile_title', true) ?? get_post_meta($id, '_wsuwp_profile_ad_title', true),
                    'office' => get_post_meta($id, '_wsuwp_profile_alt_office', true) ?? get_post_meta($id, '_wsuwp_profile_ad_office', true),
                    'email' => get_post_meta($id, '_wsuwp_profile_alt_email', true) ?? get_post_meta($id, '_wsuwp_profile_ad_email', true),
                    'address' => get_post_meta($id, '_wsuwp_profile_alt_address', true) ?? get_post_meta($id, '_wsuwp_profile_ad_address', true),
                    'phone' => get_post_meta($id, '_wsuwp_profile_alt_phone', true) ?? get_post_meta($id, '_wsuwp_profile_ad_phone', true),
                    'degree' => get_post_meta($id, '_wsuwp_profile_degree', true),
                    'bio' => get_the_content(),
                    'website' => get_post_meta($id, '_wsuwp_profile_website', true),
                );

                array_push($profiles, $profile);
            }
        }

        // return profiles
        return json_encode( $profiles );
    }


    /**
     * Return an array of size urls for the first valid image in a an array
     *
     * @param int $postId
     * @param array $photos List of image ids.
     * @return array
     */
    private static function get_photo_urls($postId, $photos){
        $photo_urls = null;

		if ( $photos && is_array( $photos ) ) {
			foreach ( $photos as $i => $photo_id ) {
				if ( is_string( get_post_status( $photo_id ) ) ) {
                    $photo_urls = array();

                    foreach(get_intermediate_image_sizes() as $size){
                        $photo_urls[$size] = wp_get_attachment_image_src( $photos[ $i ], $size )[0];
                    }

					$photo_urls['full'] = wp_get_attachment_image_src( $photos[ $i ], 'full' )[0];

					break; // break, so we only return the first image
				}
			}
		}

        return $photo_urls;
    }


    public function init()
    {
        add_action('rest_api_init', function () {
            register_rest_route('peopleapi/v1', '/people', array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_people')
            ));
        });
    }
}


(new People_Query)->init();
