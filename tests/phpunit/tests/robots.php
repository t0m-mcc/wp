<?php
/**
 * Robots functions tests.
 *
 * @package WordPress
 */

/**
 * Tests for robots template functions and filters.
 *
 * @group robots
 */
class Tests_Robots extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		remove_all_filters( 'wp_robots' );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_renders_when_relevant() {
		// Do not render robots meta tag when there are no directives.
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		// Render robots meta tag with noindex.
		add_filter( 'wp_robots', array( $this, 'add_noindex_directive' ) );
		$output = get_echo( 'wp_robots' );
		$this->assertSame( "<meta name='robots' content='noindex' />\n", $output );

		// Do not render robots meta tag when there are only false-y directives.
		add_filter( 'wp_robots', array( $this, 'remove_noindex_directive' ), 11 );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_parses_directives_correctly() {
		add_filter(
			'wp_robots',
			function( array $robots ) {
				// Directives that should have values must use strings.
				$robots['directive-with-value']         = 'yes';
				$robots['directive-with-numeric-value'] = '1';
				// Any non-string value will be evaluated as boolean.
				// False-y directives will not be included.
				$robots['directive-active-boolean']   = true;
				$robots['directive-inactive-boolean'] = false;
				$robots['directive-active-integer']   = 1;
				$robots['directive-inactive-integer'] = 0;
				return $robots;
			}
		);

		$expected_directives_string = implode(
			', ',
			array(
				'directive-with-value:yes',
				'directive-with-numeric-value:1',
				'directive-active-boolean',
				'directive-active-integer',
			)
		);

		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'{$expected_directives_string}'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_noindex() {
		add_filter( 'wp_robots', 'wp_robots_noindex' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_no_robots() {
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'noindex, follow'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_sensitive_page() {
		add_filter( 'wp_robots', 'wp_robots_sensitive_page' );

		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'noindex, noarchive'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_max_image_preview_large() {
		add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertContains( "'max-image-preview:large'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url( '?s=ticket+52457+core.trac.wordpress.org' ) );

		$output = get_echo( 'wp_robots' );
		$this->assertContains( 'noindex', $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_non_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url() );

		$output = get_echo( 'wp_robots' );
		$this->assertNotContains( 'noindex', $output );
	}

	public function add_noindex_directive( array $robots ) {
		$robots['noindex'] = true;
		return $robots;
	}

	public function remove_noindex_directive( array $robots ) {
		$robots['noindex'] = false;
		return $robots;
	}

	public function add_follow_directive( array $robots ) {
		$robots['follow'] = true;
		return $robots;
	}

	public function remove_follow_directive( array $robots ) {
		$robots['follow'] = false;
		return $robots;
	}

	public function add_nofollow_directive( array $robots ) {
		$robots['nofollow'] = true;
		return $robots;
	}

	public function remove_nofollow_directive( array $robots ) {
		$robots['nofollow'] = false;
		return $robots;
	}

	public function add_archive_directive( array $robots ) {
		$robots['archive'] = true;
		return $robots;
	}

	public function remove_archive_directive( array $robots ) {
		$robots['archive'] = false;
		return $robots;
	}

	public function add_noarchive_directive( array $robots ) {
		$robots['noarchive'] = true;
		return $robots;
	}

	public function remove_noarchive_directive( array $robots ) {
		$robots['noarchive'] = false;
		return $robots;
	}
}
