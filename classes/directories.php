<?php namespace WSUWP\Plugin\People\API;

class Directories {

	private static $post_type = 'wsu_directory';

	public static function get_directory_people_ids( $directory_id, $include_inherited = true ) {

		$people_ids = array();

		$directories = array( $directory_id );

		if ( $include_inherited ) {

			$child_directories = self::get_child_directory_ids( $directory_id );

			$directories = array_merge( $directories, $child_directories );

		}

		$directories = array_filter( array_unique( $directories ) );

		foreach ( $directories as $directory ) {

			$directory_people_ids = self::get_people_ids( $directory );

			$people_ids = array_merge( $people_ids, $directory_people_ids );

		}

		return $people_ids;

	}


	public static function get_child_directory_ids( $directory_id ) {

		$directory_ids = array();

		$child_directories = self::get_child_directories( $directory_id );

		foreach ( $child_directories as $directory ) {

			$directory_ids[] = $directory->ID;

		}

		return $directory_ids;

	}


	public static function get_child_directories( $directory_id ) {

		$directory_posts = get_pages(
			array(
				'child_of'  => $directory_id,
				'post_type' => self::$post_type,
			)
		);

		return ( ! empty( $directory_posts ) ) ? $directory_posts : array();

	}


	public static function get_people_ids( $directory_id ) {

		$people = get_post_meta( $directory_id, 'wsu_people', true );

		$people_array = explode( ',', $people );

		if ( ! empty( $people_array ) && is_array( $people_array ) ) {

			return array_map( 'intval', array_filter( $people_array ) );

		} else {

			return array();

		}

	}


}

