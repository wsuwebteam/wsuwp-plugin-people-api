<?php namespace WSUWP\Plugin\People\API;

class Directories {

	private static $post_type = 'wsu_directory';


	public static function get_directories( $directory_id, $args = array() ) {

		$default_args = array(
			'include_parent' => true,
			'inherit'        => 'all', // supports all|children
		);

		self::parse_args( $default_args, $args );

		$directories = array();

		if ( $args['include_parent'] ) {

			$directories[] = self::get_directory( $directory_id );

		}

		$child_directories = self::get_child_directories( $directory_id, $args );

		if ( ! empty( $child_directories ) ) {

			$directories = array_merge( $directories, $child_directories );

		}

		return $directories;

	}


	public static function get_directories_from_posts( $posts ) {

		$directories = array();

		foreach ( $posts as $directory ) {

			$directories[] = self::get_directory( $directory );

		}

		return $directories;

	}


	public static function get_directory( $directory, $args = array() ) {

		if ( ! $directory instanceof \WP_Post ) {

			if ( is_numeric( $directory ) ) {

				$directory = get_post( intval( $directory ) );

			} else {

				$directory = false;

			}
		}

		if ( $directory ) {

			$default_args = array(
				'fields' => array(
					'slug',
					'title',
					'id',
					'peopleIDs',
					'path',
					'editLink',
				),
			);

			self::parse_args( $default_args, $args );

			$people_array = self::get_people_ids_from_directory( $directory->ID );

			$directory_array = array(
				'slug'       => $directory->post_name,
				'title'      => $directory->post_title,
				'id'         => $directory->ID,
				'peopleIDs'  => ( in_array( 'peopleIDs', $args['fields'], true ) ) ? self::get_people_ids_from_directory( $directory->ID ) : array(),
				'path'       => ( in_array( 'path', $args['fields'], true ) ) ? self::get_directory_path( $directory->ID ) : array(),
				'editLink'   => ( in_array( 'editLink', $args['fields'], true ) ) ? admin_url() . '/post.php?post=' . $directory->ID . '&action=edit' : '',
			);

			// Unset array keys we didn't ask for
			foreach ( $directory_array as $key => $value ) {

				if ( ! in_array( $key, $args['fields'], true ) ) {

					unset( $directory_array[ $key ] );

				}
			}
		}

		return $directory_array;

	}


	public static function get_people_directory_index( $directories ) {

		$people_array = array();

		foreach ( $directories as $directory ) {

			if ( is_array( $directory['peopleIDs'] ) ) {

				foreach ( $directory['peopleIDs'] as $person_id ) {

					if ( ! array_key_exists( $person_id, $people_array ) ) {

						$people_array[ $person_id ] = array();

					}

					$people_array[ $person_id ][] = array(
						'name' => $directory['title'],
						'slug'  => $directory['slug'],
					);
				}
			}
		}

		return $people_array;

	}


	public static function get_directory_people_ids( $directory_id, $args = array() ) {

		$default_args = array(
			'inherit' => 'all',
		);

		self::parse_args( $default_args, $args );

		$people_ids = array();

		$directories = self::get_directories( $directory_id, $args );

		return self::get_people_ids_from_directories( $directories );

	}


	public static function get_child_directory_ids( $directory_id ) {

		$directory_ids = array();

		$child_directories = self::get_child_directories( $directory_id );

		foreach ( $child_directories as $directory ) {

			$directory_ids[] = $directory['id'];

		}

		return $directory_ids;

	}


	public static function get_child_directories( $directory_id, $args = array() ) {

		$default_args = array(
			'inherit'        => 'all', // supports all|children
		);

		self::parse_args( $default_args, $args );

		if ( 'none' === $args['inherit'] ) {

			return array();

		}

		$directory_array = array();

		$directory_query = array(
			'post_type' => self::$post_type,
		);

		switch ( $args['inherit'] ) {

			case 'all':
				$directory_query['child_of'] = $directory_id;
				break;
			case 'children':
				$directory_query['parent'] = $directory_id;
				break;
		}

		$directory_posts = get_pages( $directory_query );

		if ( ! empty( $directory_posts ) ) {

			foreach ( $directory_posts as $directory ) {

				$directory_array[] = self::get_directory( $directory, $args );

			}
		}

		return $directory_array;

	}


	public static function get_people_ids_from_directory( $directory_id ) {

		$people = get_post_meta( $directory_id, 'wsu_people', true );

		$people_array = explode( ',', $people );

		if ( ! empty( $people_array ) && is_array( $people_array ) ) {

			return array_map( 'intval', array_filter( $people_array ) );

		} else {

			return array();

		}

	}

	public static function get_people_ids_from_directories( $directories ) {

		$people_ids = array();

		foreach ( $directories as $directory ) {

			$people_ids = array_merge( $people_ids, $directory['peopleIDs'] );

		}

		return $people_ids;

	}


	public static function get_directory_path( $directory_id ) {

		$directory_path_array = array();

		$directory = get_post( $directory_id );

		self::directory_parent_recursive( $directory_path_array, $directory );

		return array_reverse( $directory_path_array );

	}


	public static function get_directory_search( $term, $args = array() ) {

		$default_args = array(
			'inherit_children' => false, // supports true|false
		);

		self::parse_args( $default_args, $args );

		$results = array();

		$query_args = array(
			's'              => $term,
			'post_type'      => self::$post_type,
			'posts_per_page' => 50,
		);

		$directories = get_posts( $query_args );

		if ( ! empty( $directories ) ) {

			$results = self::get_directories_from_posts( $directories );
		}

		if ( ! empty( $directories ) && 1 === count( $directories ) && ! empty( $args['inherit_children'] ) ) {

			$child_directories = self::get_directories( $directories[0]->ID, array( 'include_parent' => false ) );

			$results = array_merge( $results, $child_directories );

		}

		return $results;

	}


	public function directory_parent_recursive( &$directory_path, $directory) {

		// $parent_post = get_post_parent( $directory_id ); NOt supported in 5.6

		if ( $directory ) {

			$parent_post = get_post( $directory->post_parent );

			if ( $parent_post ) {

				$directory_path[] = array( 
					'title' => $parent_post->post_title,
					'post_id' => $parent_post->ID,
					'editLink'   => admin_url() . '/post.php?post=' . $directory->ID . '&action=edit',
				);

				self::directory_parent_recursive( $directory_path, $parent_post );

			}
		}

	}


	protected static function parse_args( $default_args, &$args ) {

		foreach ( $default_args as $key => $default ) {

			if ( ! array_key_exists( $key, $args ) ) {

				$args[ $key ] = $default;

			}
		}

	}


}

