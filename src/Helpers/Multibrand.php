<?php

namespace Endurance\WP\Module\Data\Helpers;

/**
 * Helper class for gathering and formatting multibrand data
 */
class Multibrand {

	/**
	 * Get originating plugin based on plugin constants
	 * 
	 * @return string
	 */
	public static function get_origin_plugin() {
		if ( defined( 'BLUEHOST_PLUGIN_VERSION' ) ) {
			return array(
                'name' => 'Bluehost',
                'slug' => 'bluehost-wordpress-plugin/bluehost-wordpress-plugin.php',
                'version' => BLUEHOST_PLUGIN_VERSION,
            );
		}
		if ( defined( 'MM_VERSION' ) ) {
			return array(
                'name' => 'Mojo',
                'slug' => 'mojo-marketplace-wp-plugin/mojo-marketplace.php',
                'version' => MM_VERSION,
            );
		}
		return 'unknown';
	}

	/**
	 * Get originating plugin version
	 * 
	 * @return version string
	 */
	public static function get_origin_plugin_version() {	
		$origin = self::get_origin_plugin();
        return $origin['version'];
	}

	/**
	 * Get originating plugin name
	 * 
	 * @return name string
	 */
	public static function get_origin_plugin_name() {	
		$origin = self::get_origin_plugin();
        return $origin['name'];
	}

	/**
	 * Get originating plugin slug
	 * 
	 * @return slug string
	 */
	public static function get_origin_plugin_slug() {	
		$origin = self::get_origin_plugin();
        return $origin['slug'];
	}

	/**
	 * Get originating plugin via reflection class
	 * 
	 * @return string
	 */
	public static function get_origin_plugin_path() {
		$reflector = new \ReflectionClass( get_class( $this ) );
		$plugins   = get_plugins();
		$file      = plugin_basename( $reflector->getFileName() );

		// is this file a standalone plugin? shouldn't be
		if ( array_key_exists( $file, $plugins ) ) {
			return $file;
		}

		// is file within another plugin? (as a vendor package) - expected
		$paths    = explode( '/', $file );
		$root_dir = array_shift( $paths );
		foreach ( $plugins as $path => $data ) {
			if ( 0 === strpos( $path, $root_dir ) ) {
				return $path;
			}
		}

		// plugin_file still not set? just return the full path
		// ie file not contained within a plugin (our ewphub local setup)
		if ( '' === $plugin_file ) {
			return $file;
		}

		// if none of those then what?
		return 'unknown';
	}

}