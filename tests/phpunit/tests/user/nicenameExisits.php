<?php

/**
 * Tests the `nicename_exists` function.
 *
 * @group user
 *
 * @covers ::nicename_exists
 */
class Tests_User_NicenameExists extends WP_UnitTestCase {

	/**
	 * Tests that `nicename_exists` returns the user ID when the nicename exists.
	 *
	 * @ticket 44921
	 */
	public function test_nicename_exists_with_existing_nicename() {
		$user_id = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );

		$this->assertSame( $user_id, nicename_exists( 'test-nicename' ) );
	}

	/**
	 * Tests that `nicename_exists` returns false when the nicename does not exist.
	 *
	 * @ticket 44921
	 */
	public function test_nicename_exists_with_nonexistent_nicename() {
		$this->assertFalse( nicename_exists( 'nonexistent-nicename' ) );
	}

	/**
	 * Tests that `nicename_exists` returns false when the nicename exists but belongs to a different user.
	 *
	 * @ticket 44921
	 */
	public function test_nicename_exists_with_different_user_login() {
		$user_id_1 = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );
		$user_id_2 = $this->factory()->user->create();
		$user_2    = get_user_by( 'id', $user_id_2 );

		$this->assertSame( $user_id_1, nicename_exists( 'test-nicename', $user_2->user_login ) );
	}

	/**
	 * Tests that `nicename_exists` returns false when the nicename exists but belongs to a different user.
	 *
	 * @ticket 44921
	 */
	public function test_nicename_exists_with_same_user_login() {
		$user_id_1 = $this->factory()->user->create( array( 'user_nicename' => 'test-nicename' ) );
		$user_1    = get_user_by( 'id', $user_id_1 );

		$this->assertFalse( nicename_exists( 'test-nicename', $user_1->user_login ) );
	}
}
