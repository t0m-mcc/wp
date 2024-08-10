<?php
/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies extends WP_UnitTestCase {
	public function test_add() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'two' ) );

		// Cannot reuse names.
		$this->assertFalse( $dep->add( 'one', '' ) );
	}

	public function test_remove() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->remove( 'one' );

		$this->assertFalse( $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'two' ) );
	}

	public function test_enqueue() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$dep->enqueue( 'one' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );

		$dep->enqueue( 'two' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
	}

	public function test_dequeue() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->enqueue( 'one' );
		$dep->enqueue( 'two' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );

		$dep->dequeue( 'one' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );

		$dep->dequeue( 'two' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
	}

	public function test_enqueue_args() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$dep->enqueue( 'one?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['one'] );

		$dep->enqueue( 'two?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['two'] );
	}

	public function test_dequeue_args() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->enqueue( 'one?arg' );
		$dep->enqueue( 'two?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['one'] );
		$this->assertSame( 'arg', $dep->args['two'] );

		$dep->dequeue( 'one' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertArrayNotHasKey( 'one', $dep->args );

		$dep->dequeue( 'two' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
		$this->assertArrayNotHasKey( 'two', $dep->args );
	}

	/**
	 * @ticket 21741
	 */
	public function test_query_and_registered_enqueued() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one', 'registered' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one', 'scripts' ) );

		$this->assertFalse( $dep->query( 'one', 'enqueued' ) );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );

		$dep->enqueue( 'one' );

		$this->assertTrue( $dep->query( 'one', 'enqueued' ) );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );

		$dep->dequeue( 'one' );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );

		$dep->remove( 'one' );
		$this->assertFalse( $dep->query( 'one' ) );
	}

	public function test_enqueue_before_register() {
		$dep = new WP_Dependencies();

		$this->assertArrayNotHasKey( 'one', $dep->registered );

		$dep->enqueue( 'one' );

		$this->assertNotContains( 'one', $dep->queue );

		$this->assertTrue( $dep->add( 'one', '' ) );

		$this->assertContains( 'one', $dep->queue );
	}

	/**
	 * Data provider for test_get_etag.
	 *
	 * @return array
	 */
	public function data_provider_get_etag() {
		return array(
			'should accept one dependency'              => array(
				'load'               => array(
					'abcd' => '1.0.2',
				),
				'wp_version'         => '',
				'hash_source_string' => 'WP:abcd:1.0.2;',
				'expected'           => 'W/"fea2fd8012dd8af0696ebafbaa68db85"',
			),
			'should accept empty array of dependencies' => array(
				'load'               => array(),
				'wp_version'         => '',
				'hash_source_string' => 'WP:;',
				'expected'           => 'W/"f6457280f73cc597e76df87ee891457a"',
			),
			'should accept more then one dependency and wp version' => array(
				'load'               => array(
					'abcd' => '1.0.2',
					'abdy' => '1.0.3',
				),
				'wp_version'         => '5.4.3',
				'hash_source_string' => 'WP:5.4.3;abcd:1.0.2;abdy:1.0.3;',
				'expected'           => 'W/"88649143b0142d1491883065e9351178"',
			),
		);
	}

	/**
	 * Tests get_etag method for WP_Scripts.
	 *
	 * @ticket 58433
	 * @ticket 61485
	 *
	 * @covers WP_Dependencies::get_etag
	 *
	 * @dataProvider data_provider_get_etag
	 *
	 * @param array $load List of scripts to load.
	 * @param string $wp_version WordPress version.
	 * @param string $hash_source_string Hash source string.
	 * @param string $expected Expected etag.
	 */
	public function test_get_etag_scripts( $load, $wp_version, $hash_source_string, $expected ) {
		$instance = wp_scripts();

		foreach ( $load as $handle => $ver ) {
			// The src should not be empty.
			wp_enqueue_script( $handle, 'https://example.cdn', array(), $ver );
		}

		$result = $instance->get_etag( $wp_version, array_keys( $load ) );

		$this->assertSame( $expected, $result, "Expected MD hash: $expected for $hash_source_string, but got: $result." );
	}

	/**
	 * Tests get_etag method for WP_Styles.
	 *
	 * @ticket 58433
	 * @ticket 61485
	 *
	 * @covers WP_Dependencies::get_etag
	 *
	 * @dataProvider data_provider_get_etag
	 *
	 * @param array $load List of scripts to load.
	 * @param string $wp_version WordPress version.
	 * @param string $hash_source_string Hash source string.
	 * @param string $expected Expected etag.
	 */
	public function test_get_etag_styles( $load, $wp_version, $hash_source_string, $expected ) {
		$instance = wp_styles();

		foreach ( $load as $handle => $ver ) {
			// The src should not be empty.
			wp_enqueue_style( $handle, 'https://example.cdn', array(), $ver );
		}

		$result = $instance->get_etag( $wp_version, array_keys( $load ) );

		$this->assertSame( $expected, $result, "Expected MD hash: $expected for $hash_source_string, but got: $result." );
	}
}
