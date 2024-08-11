<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.4.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor extends WP_UnitTestCase {
	/**
	 * Ensure that the HTML Processor's public constructor function warns a developer to call
	 * the static creator methods instead of directly instantiating a new class.
	 *
	 * The Tag Processor's constructor method is public and PHP doesn't allow changing the
	 * visibility for a method on a subclass, which means that the HTML Processor must
	 * maintain the public interface. However, constructors cannot fail to construct, so
	 * if there are pre-conditions (such as the context node, the encoding form, and the
	 * parsing mode with the HTML Processor) these must be handled through static factory
	 * methods on the class.
	 *
	 * The HTML Processor requires a sentinel string as an optional parameter that hints
	 * at using the static methods. In the absence of the optional parameter it instructs
	 * the callee that it should be using those static methods instead.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::__construct
	 * @expectedIncorrectUsage WP_HTML_Processor::__construct
	 */
	public function test_warns_that_the_static_creator_methods_should_be_called_instead_of_the_public_constructor() {
		new WP_HTML_Processor( '<p>Light roast.</p>' );
	}

	/**
	 * Once stepping to the end of the document, WP_HTML_Processor::get_tag
	 * should no longer report a tag. It should report `null` because there
	 * is no tag matched or open.
	 *
	 * @ticket 59167
	 *
	 * @covers WP_HTML_Processor::get_tag
	 */
	public function test_get_tag_is_null_once_document_is_finished() {
		$processor = WP_HTML_Processor::create_fragment( '<div class="test">Test</div>' );
		$processor->next_tag();
		$this->assertSame( 'DIV', $processor->get_tag() );

		$this->assertFalse( $processor->next_tag() );
		$this->assertNull( $processor->get_tag() );
	}

	/**
	 * Ensures that the HTML Processor maintains its internal state through seek calls.
	 *
	 * Because the HTML Processor must track a stack of open elements and active formatting
	 * elements, when it seeks to another location within its document it must adjust those
	 * stacks, its internal state, in such a way that they remain valid after the seek.
	 *
	 * For instance, if currently matched inside an LI element and the Processor seeks to
	 * an earlier location before the parent UL, then it should not report that it's still
	 * inside an open LI element.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::next_tag
	 * @covers WP_HTML_Processor::seek
	 */
	public function test_clear_to_navigate_after_seeking() {
		$processor = WP_HTML_Processor::create_fragment( '<div one><strong></strong></div><p><strong two></strong></p>' );

		while ( $processor->next_tag() ) {
			// Create a bookmark before entering a stack of elements and formatting elements.
			if ( null !== $processor->get_attribute( 'one' ) ) {
				$this->assertTrue( $processor->set_bookmark( 'one' ) );
				continue;
			}

			// Create a bookmark inside of that stack.
			if ( null !== $processor->get_attribute( 'two' ) ) {
				$processor->set_bookmark( 'two' );
				break;
			}
		}

		// Ensure that it's possible to seek back to the outside location.
		$this->assertTrue( $processor->seek( 'one' ), 'Could not seek to earlier-seen location.' );
		$this->assertSame( 'DIV', $processor->get_tag(), "Should have jumped back to DIV but found {$processor->get_tag()} instead." );

		/*
		 * Ensure that the P element from the inner location isn't still on the stack of open elements.
		 * If it were, then the first STRONG element, inside the outer DIV would match the next call.
		 */
		$this->assertTrue( $processor->next_tag( array( 'breadcrumbs' => array( 'P', 'STRONG' ) ) ), 'Failed to find given location after seeking.' );

		// Only if the stack is properly managed will the processor advance to the inner STRONG element.
		$this->assertTrue( $processor->get_attribute( 'two' ), "Found the wrong location given the breadcrumbs, at {$processor->get_tag()}." );

		// Ensure that in seeking backwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $processor->seek( 'one' ), 'Failed to jump back to first bookmark.' );
		$this->assertSame( array( 'HTML', 'BODY', 'DIV' ), $processor->get_breadcrumbs(), 'Found wrong set of breadcrumbs navigating to node "one".' );

		// Ensure that in seeking forwards the processor reports the correct full set of breadcrumbs.
		$this->assertTrue( $processor->seek( 'two' ), 'Failed to jump forward to second bookmark.' );
		$this->assertTrue( $processor->get_attribute( 'two' ), "Found the wrong location given the bookmark, at {$processor->get_tag()}." );

		$this->assertSame( array( 'HTML', 'BODY', 'P', 'STRONG' ), $processor->get_breadcrumbs(), 'Found wrong set of bookmarks navigating to node "two".' );
	}

	/**
	 * Ensures that support is added for reconstructing active formatting elements.
	 *
	 * @ticket 58517
	 *
	 * @covers WP_HTML_Processor::reconstruct_active_formatting_elements
	 */
	public function test_reconstructs_formatting_elements() {
		$processor = WP_HTML_Processor::create_fragment( '<p><em>One<p><em><span>Two<p><em>Three<p><em>Four' );

		$this->assertTrue( $processor->next_tag( 'EM' ), 'Could not find first EM.' );
		$this->assertSame( array( 'HTML', 'BODY', 'P', 'EM' ), $processor->get_breadcrumbs(), 'Found incorrect breadcrumbs for first EM.' );
		$this->assertTrue( $processor->next_tag( 'SPAN' ), 'Could not find test span.' );
		$this->assertSame(
			array( 'HTML', 'BODY', 'P', 'EM', 'EM', 'SPAN' ),
			$processor->get_breadcrumbs(),
			'Found incorrect breadcrumbs for test SPAN; should have created two EMs.'
		);
	}

	/**
	 * Ensure non-nesting tags do not nest.
	 *
	 * @ticket 60283
	 *
	 * @covers WP_HTML_Processor::step_in_body
	 * @covers WP_HTML_Processor::is_void
	 *
	 * @dataProvider data_void_tags_not_ignored_in_body
	 *
	 * @param string $tag_name Name of void tag under test.
	 */
	public function test_cannot_nest_void_tags( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<{$tag_name}><div>" );

		/*
		 * This HTML represents the same as the following HTML,
		 * assuming that it were provided `<img>` as the tag:
		 *
		 *     <html>
		 *         <body>
		 *             <img>
		 *             <div></div>
		 *         </body>
		 *     </html>
		 */

		$found_tag = $processor->next_tag();

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $processor->get_last_error() ) {
			$this->markTestSkipped( "Tag {$tag_name} is not supported." );
		}

		$this->assertTrue(
			$found_tag,
			"Could not find first {$tag_name}."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			'Found incorrect nesting of first element.'
		);

		$this->assertTrue(
			$processor->next_tag(),
			'Should have found the DIV as the second tag.'
		);

		$this->assertSame(
			array( 'HTML', 'BODY', 'DIV' ),
			$processor->get_breadcrumbs(),
			"DIV should have been a sibling of the {$tag_name}."
		);
	}

	/**
	 * Ensure reporting that normal non-void HTML elements expect a closer.
	 *
	 * @ticket 61257
	 */
	public function test_expects_closer_regular_tags() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p><b><em>' );

		$tags = 0;
		while ( $processor->next_tag() ) {
			$this->assertTrue(
				$processor->expects_closer(),
				"Should have expected a closer for '{$processor->get_tag()}', but didn't."
			);
			++$tags;
		}

		$this->assertSame(
			4,
			$tags,
			'Did not find all the expected tags.'
		);
	}

	/**
	 * Ensure reporting that non-tag HTML nodes expect a closer.
	 *
	 * @ticket 61257
	 *
	 * @dataProvider data_self_contained_node_tokens
	 *
	 * @param string $self_contained_token String starting with HTML token that doesn't expect a closer,
	 *                                     e.g. an HTML comment, text node, void tag, or special element.
	 */
	public function test_expects_closer_expects_no_closer_for_self_contained_tokens( $self_contained_token ) {
		$processor   = WP_HTML_Processor::create_fragment( $self_contained_token );
		$found_token = $processor->next_token();

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $processor->get_last_error() ) {
			$this->markTestSkipped( "HTML '{$self_contained_token}' is not supported." );
		}

		$this->assertTrue(
			$found_token,
			"Failed to find any tokens in '{$self_contained_token}': check test data provider."
		);

		$this->assertFalse(
			$processor->expects_closer(),
			"Incorrectly expected a closer for node of type '{$processor->get_token_type()}'."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_self_contained_node_tokens() {
		$self_contained_nodes = array(
			'Normative comment'                => array( '<!-- comment -->' ),
			'Comment with invalid closing'     => array( '<!-- comment --!>' ),
			'CDATA Section lookalike'          => array( '<![CDATA[ comment ]]>' ),
			'Processing Instruction lookalike' => array( '<?ok comment ?>' ),
			'Funky comment'                    => array( '<//wp:post-meta key=isbn>' ),
			'Text node'                        => array( 'Trombone' ),
		);

		foreach ( self::data_void_tags_not_ignored_in_body() as $tag_name => $_name ) {
			$self_contained_nodes[ "Void elements ({$tag_name})" ] = array( "<{$tag_name}>" );
		}

		foreach ( self::data_special_tags() as $tag_name => $_name ) {
			$self_contained_nodes[ "Special atomic elements ({$tag_name})" ] = array( "<{$tag_name}>content</{$tag_name}>" );
		}

		return $self_contained_nodes;
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_special_tags() {
		return array(
			'IFRAME'   => array( 'IFRAME' ),
			'NOEMBED'  => array( 'NOEMBED' ),
			'NOFRAMES' => array( 'NOFRAMES' ),
			'SCRIPT'   => array( 'SCRIPT' ),
			'STYLE'    => array( 'STYLE' ),
			'TEXTAREA' => array( 'TEXTAREA' ),
			'TITLE'    => array( 'TITLE' ),
			'XMP'      => array( 'XMP' ),
		);
	}

	/**
	 * Ensure non-nesting tags do not nest when processing tokens.
	 *
	 * @ticket 60382
	 *
	 * @dataProvider data_void_tags_not_ignored_in_body
	 *
	 * @param string $tag_name Name of void tag under test.
	 */
	public function test_cannot_nest_void_tags_next_token( $tag_name ) {
		$processor = WP_HTML_Processor::create_fragment( "<{$tag_name}><div>" );

		/*
		 * This HTML represents the same as the following HTML,
		 * assuming that it were provided `<img>` as the tag:
		 *
		 *     <html>
		 *         <body>
		 *             <img>
		 *             <div></div>
		 *         </body>
		 *     </html>
		 */

		$found_tag = $processor->next_token();

		if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $processor->get_last_error() ) {
			$this->markTestSkipped( "Tag {$tag_name} is not supported." );
		}

		$this->assertTrue(
			$found_tag,
			"Could not find first {$tag_name}."
		);

		$this->assertSame(
			array( 'HTML', 'BODY', $tag_name ),
			$processor->get_breadcrumbs(),
			'Found incorrect nesting of first element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_void_tags() {
		return array(
			'AREA'   => array( 'AREA' ),
			'BASE'   => array( 'BASE' ),
			'BR'     => array( 'BR' ),
			'COL'    => array( 'COL' ),
			'EMBED'  => array( 'EMBED' ),
			'HR'     => array( 'HR' ),
			'IMG'    => array( 'IMG' ),
			'INPUT'  => array( 'INPUT' ),
			'KEYGEN' => array( 'KEYGEN' ),
			'LINK'   => array( 'LINK' ),
			'META'   => array( 'META' ),
			'PARAM'  => array( 'PARAM' ),
			'SOURCE' => array( 'SOURCE' ),
			'TRACK'  => array( 'TRACK' ),
			'WBR'    => array( 'WBR' ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_void_tags_not_ignored_in_body() {
		$all_void_tags = self::data_void_tags();
		unset( $all_void_tags['COL'] );

		return $all_void_tags;
	}

	/**
	 * Ensures that the HTML Processor properly reports the depth of a given element.
	 *
	 * @ticket 61255
	 *
	 * @dataProvider data_html_with_target_element_and_depth_in_body
	 *
	 * @param string $html_with_target_element HTML containing element with `target` class.
	 * @param int    $depth_at_element         Depth into document at target node.
	 */
	public function test_reports_proper_element_depth_in_body( $html_with_target_element, $depth_at_element ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_element );

		$this->assertTrue(
			$processor->next_tag( array( 'class_name' => 'target' ) ),
			'Failed to find target element: check test data provider.'
		);

		$this->assertSame(
			$depth_at_element,
			$processor->get_current_depth(),
			'HTML Processor reported the wrong depth at the matched element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_with_target_element_and_depth_in_body() {
		return array(
			'Single element'                    => array( '<div class="target">', 3 ),
			'Basic layout and formatting stack' => array( '<div><span><p><b><em class="target">', 7 ),
			'Adjacent elements'                 => array( '<div><span></span><span class="target"></div>', 4 ),
		);
	}

	/**
	 * Ensures that the HTML Processor properly reports the depth of a given non-element.
	 *
	 * @ticket 61255
	 *
	 * @dataProvider data_html_with_target_element_and_depth_of_next_node_in_body
	 *
	 * @param string $html_with_target_element HTML containing element with `target` class.
	 * @param int    $depth_after_element      Depth into document immediately after target node.
	 */
	public function test_reports_proper_non_element_depth_in_body( $html_with_target_element, $depth_after_element ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_element );

		$this->assertTrue(
			$processor->next_tag( array( 'class_name' => 'target' ) ),
			'Failed to find target element: check test data provider.'
		);

		$this->assertTrue(
			$processor->next_token(),
			'Failed to find next node after target element: check tests data provider.'
		);

		$this->assertSame(
			$depth_after_element,
			$processor->get_current_depth(),
			'HTML Processor reported the wrong depth after the matched element.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_html_with_target_element_and_depth_of_next_node_in_body() {
		return array(
			'Element then text'                 => array( '<div class="target">One Deeper', 4 ),
			'Basic layout and formatting stack' => array( '<div><span><p><b><em class="target">Formatted', 8 ),
			'Basic layout with text'            => array( '<div>a<span>b<p>c<b>e<em class="target">e', 8 ),
			'Adjacent elements'                 => array( '<div><span></span><span class="target">Here</div>', 5 ),
			'Adjacent text'                     => array( '<p>Before<img class="target">After</p>', 4 ),
			'HTML comment'                      => array( '<img class="target"><!-- this is inside the BODY -->', 3 ),
			'HTML comment in DIV'               => array( '<div class="target"><!-- this is inside the BODY -->', 4 ),
			'Funky comment'                     => array( '<div><p>What <br class="target"><//wp:post-author></p></div>', 5 ),
		);
	}

	/**
	 * Ensures that elements which are unopened at the end of a document are implicitly closed.
	 *
	 * @ticket 61576
	 */
	public function test_closes_unclosed_elements() {
		$processor = WP_HTML_Processor::create_fragment( '<div><p><span>' );

		$this->assertTrue(
			$processor->next_tag( 'SPAN' ),
			'Could not find SPAN element: check test setup.'
		);

		// This is the end of the document, but there should be three closing events.
		$processor->next_token();
		$this->assertSame(
			'SPAN',
			$processor->get_tag(),
			'Should have found implicit SPAN closing tag.'
		);

		$processor->next_token();
		$this->assertSame(
			'P',
			$processor->get_tag(),
			'Should have found implicit P closing tag.'
		);

		$processor->next_token();
		$this->assertSame(
			'DIV',
			$processor->get_tag(),
			'Should have found implicit DIV closing tag.'
		);

		$this->assertFalse(
			$processor->next_token(),
			"Should have failed to find any more tokens but found a '{$processor->get_token_name()}'"
		);
	}

	/**
	 * Ensures that subclasses can be created from ::create_fragment method.
	 *
	 * @ticket 61374
	 */
	public function test_subclass_create_fragment_creates_subclass() {
		$processor = WP_HTML_Processor::create_fragment( '' );
		$this->assertInstanceOf( WP_HTML_Processor::class, $processor, '::create_fragment did not return class instance.' );

		$subclass_instance = new class('') extends WP_HTML_Processor {
			public function __construct( $html ) {
				parent::__construct( $html, parent::CONSTRUCTOR_UNLOCK_CODE );
			}
		};

		$subclass_processor = call_user_func( array( get_class( $subclass_instance ), 'create_fragment' ), '' );
		$this->assertInstanceOf( get_class( $subclass_instance ), $subclass_processor, '::create_fragment did not return subclass instance.' );
	}

	/**
	 * Ensures that self-closing elements in foreign content properly report
	 * that they expect no closer.
	 *
	 * @ticket 61576
	 */
	public function test_expects_closer_foreign_content_self_closing() {
		$processor = WP_HTML_Processor::create_fragment( '<svg /><math>' );

		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SVG', $processor->get_tag() );
		$this->assertFalse( $processor->expects_closer() );

		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'MATH', $processor->get_tag() );
		$this->assertTrue( $processor->expects_closer() );
	}

	/**
	 * Ensures that self-closing foreign SCRIPT elements are properly found.
	 *
	 * @ticket 61576
	 */
	public function test_foreign_content_script_self_closing() {
		$processor = WP_HTML_Processor::create_fragment( '<svg><script />' );
		$this->assertTrue( $processor->next_tag( 'script' ) );
	}
}
