<?php namespace WSUWP\Plugin\People\API;

class Plugin {

	protected static $version = '0.0.4';

	public static function get( $property ) {

		switch ( $property ) {

			case 'version':
				return self::$version;

			case 'plugin_dir':
				return plugin_dir_path( dirname( __FILE__ ) );

			case 'plugin_url':
				return plugin_dir_url( dirname( __FILE__ ) );

			case 'template_dir':
				return plugin_dir_path( dirname( __FILE__ ) ) . '/templates';

			case 'class_dir':
				return plugin_dir_path( dirname( __FILE__ ) ) . '/classes';

			default:
				return '';

		}

	}


	public function init() {

		require_once __DIR__ . '/people-query.php';

	}

}


( new Plugin() )->init();
