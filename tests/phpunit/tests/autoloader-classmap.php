<?php

/**
 * Tests related to the WP_Autoload class.
 *
 * @group basic
 */
class Tests_Autoloader_Classmap extends WP_UnitTestCase {

	/**
	 * Test that all classes in the classmap are lowercase.
	 *
	 * @dataProvider data_autoloader_classmap_is_lowercase
	 *
	 * @param string $class_name Class name.
	 */
	public function test_autoloader_classmap_is_lowercase( $class_name ) {
		$this->assertSame( strtolower( $class_name ), $class_name, "Class name '$class_name' is not lowercase." );
	}

	/**
	 * Data provider for test_autoloader_classmap_is_lowercase.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_is_lowercase() {
		$class_names = array_keys( WP_Autoload::CLASSES_PATHS );

		return array_map(
			function ( $class_name ) {
				return array( $class_name );
			},
			$class_names
		);
	}

	/**
	 * Test that all files in the classmap exist.
	 *
	 * @dataProvider data_autoloader_classmap_files_exist
	 *
	 * @param string $file_path File path relative to WP root directory.
	 */
	public function test_autoloader_classmap_files_exist( $file_path ) {
		$this->assertFileExists( ABSPATH . $file_path );
	}

	/**
	 * Data provider for test_autoloader_classmap_files_exist.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_files_exist() {
		$file_paths = array_values( WP_Autoload::CLASSES_PATHS );

		return array_map(
			function ( $file_path ) {
				return array( $file_path );
			},
			$file_paths
		);
	}

	/**
	 * Test that all classes in the classmap are in the correct file.
	 *
	 * @dataProvider data_autoloader_classmap_is_in_correct_file
	 *
	 * @param string $class_name Class name.
	 * @param string $file_path  File path relative to WP root directory.
	 */
	public function test_autoloader_classmap_is_in_correct_file( $class_name, $file_path ) {
		$this->assertTrue(
			str_contains(
				strtolower( file_get_contents( ABSPATH . $file_path ) ),
				"class $class_name"
			)
		);
	}

	/**
	 * Data provider for test_autoloader_classmap_is_in_correct_file.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_is_in_correct_file() {
		$data = array();
		foreach ( WP_Autoload::CLASSES_PATHS as $class_name => $file_path ) {
			$data[] = array( $class_name, $file_path );
		}

		return $data;
	}

	/**
	 * @group pwcc
	 */
	public function test_autoloader_class_files_exist_in_classmap() {
		$expected_classmap = $this->get_all_wp_class_files();
		$actual_classmap   = WP_Autoload::CLASSES_PATHS;
		foreach ( $expected_classmap as $class_name => $file_path ) {
			$this->assertArrayHasKey( $class_name, $actual_classmap, "Class '$class_name' is missing from the classmap." );
			$this->assertSame( $file_path, $actual_classmap[ $class_name ], "Class '$class_name' is in the wrong file." );
		}
		// $this->assertEqualSetsWithIndex( $expected_classmap, $actual_classmap );
	}

	public function get_all_wp_class_files() {
		$files        = array();
		$directory    = new RecursiveDirectoryIterator( ABSPATH . WPINC );
		$iterator     = new RecursiveIteratorIterator( $directory );
		$regex        = new RegexIterator( $iterator, '/^.+\/class\-[a-z-]+\.php$/i', RecursiveRegexIterator::GET_MATCH );
		$ltrim_length = strlen( trailingslashit( ABSPATH ) );

		$package_paths_to_ignore = array(
			'wp-includes/class-requests.php',  // 3rd-party library.
			'wp-includes/Requests/',           // 3rd-party library.
			'wp-includes/sodium_compat/',      // 3rd-party library.
			'wp-includes/class-avif-info.php', // 3rd-party library.
			'wp-includes/class-simplepie.php', // 3rd-party library.
			'wp-includes/class-snoopy.php',    // Deprecated.
		);

		foreach ( $regex as $file ) {
			$class_file    = $file[0];
			$relative_file = substr( $class_file, $ltrim_length );
			foreach ( $package_paths_to_ignore as $package_path ) {
				if ( str_contains( $relative_file, $package_path ) !== false ) {
					continue 2;
				}
			}

			$file_contents = file_get_contents( $class_file );
			// Extract the class name from the file.
			preg_match( '/^class\s+([a-zA-Z0-9_]+)/m', $file_contents, $matches );
			if ( empty( $matches ) ) {
				continue;
			}
			$class_name           = strtolower( $matches[1] );
			$files[ $class_name ] = $relative_file;
		}

		return $files;
	}
}
