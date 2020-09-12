<?php

/**
 * @group taxonomy
 */
class Tests_Term_termCount extends WP_UnitTestCase {

	/**
	 * Term ID for testing attachment counts.
	 *
	 * @var int
	 */
	public static $attachment_term;

	public static function wpSetUpBeforeClass( $factory ) {
		register_taxonomy( 'wp_test_tax_counts', array( 'post', 'attachment' ) );
		self::$attachment_term = $factory->term->create( array( 'taxonomy' => 'wp_test_tax_counts' ) );
	}

	public function setUp() {
		parent::setUp();

		register_taxonomy( 'wp_test_tax_counts', array( 'post', 'attachment' ) );
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @dataProvider data_term_count_changes_for_post_statuses
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses( $post_status, $change ) {
		$term_count = get_term( get_option( 'default_category' ) )->count;
		$post_id    = self::factory()->post->create( array( 'post_status' => $post_status ) );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	function data_term_count_changes_for_post_statuses() {
		return array(
			// 0. Published post
			array( 'publish', 1 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @dataProvider data_term_counts_incremented_on_publish
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish( $original_post_status, $change ) {
		$post_id    = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		$term_count = get_term( get_option( 'default_category' ) )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @dataProvider data_term_count_transitions_update_term_counts
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	function test_term_count_transitions_update_term_counts( $original_post_status, $new_post_status, $change ) {
		$post_id    = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		$term_count = get_term( get_option( 'default_category' ) )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_count_transitions_update_term_counts() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 1 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 1 ),
			// 2. Private -> published post
			array( 'private', 'publish', 1 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -1 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -1 ),
		);
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @dataProvider data_term_count_changes_for_post_statuses_with_attachments
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses_with_attachments( $post_status, $change ) {
		$term_count = get_term( self::$attachment_term )->count;
		$post_id    = self::factory()->post->create( array( 'post_status' => $post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	function data_term_count_changes_for_post_statuses_with_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 2 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @dataProvider data_term_counts_incremented_on_publish_with_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_attachments( $original_post_status, $change ) {
		$post_id = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );
		$term_count = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish_with_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 2 ),
			// 2. Draft
			array( 'draft', 2 ),
			// 3. Private post
			array( 'private', 2 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @dataProvider data_term_count_transitions_update_term_counts_with_attachments
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	function test_term_count_transitions_update_term_counts_with_attachments( $original_post_status, $new_post_status, $change ) {
		$post_id = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );
		$term_count = get_term( self::$attachment_term )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_count_transitions_update_term_counts_with_attachments() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 2 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 2 ),
			// 2. Private -> published post
			array( 'private', 'publish', 2 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -2 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -2 ),
		);
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @dataProvider data_term_count_changes_for_post_statuses_with_untermed_attachments
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses_with_untermed_attachments( $post_status, $change ) {
		$term_count = get_term( self::$attachment_term )->count;
		$post_id    = self::factory()->post->create( array( 'post_status' => $post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	function data_term_count_changes_for_post_statuses_with_untermed_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 1 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @dataProvider data_term_counts_incremented_on_publish_with_untermed_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_untermed_attachments( $original_post_status, $change ) {
		$post_id = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		$term_count    = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_counts_incremented_on_publish_with_untermed_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @dataProvider data_term_count_transitions_update_term_counts_with_untermed_attachments
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	function test_term_count_transitions_update_term_counts_with_untermed_attachments( $original_post_status, $new_post_status, $change ) {
		$post_id = self::factory()->post->create( array( 'post_status' => $original_post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		$term_count    = get_term( self::$attachment_term )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	function data_term_count_transitions_update_term_counts_with_untermed_attachments() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 1 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 1 ),
			// 2. Private -> published post
			array( 'private', 'publish', 1 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -1 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -1 ),
		);
	}
}
