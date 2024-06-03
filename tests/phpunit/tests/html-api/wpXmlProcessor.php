<?php
/**
 * Unit tests covering WP_XML_Processor functionality.
 *
 * @package WordPress
 * @subpackage XML-API
 */

$base_dir = __DIR__ . '/../../../../src/wp-includes/html-api';
require_once $base_dir . "/class-wp-html-token.php";
require_once $base_dir . "/class-wp-html-span.php";
require_once $base_dir . "/class-wp-html-text-replacement.php";
require_once $base_dir . "/class-wp-html-decoder.php";
require_once $base_dir . "/class-wp-html-attribute-token.php";
require_once $base_dir . "/class-wp-xml-processor.php";
require_once $base_dir . "/class-wp-xml-tag-processor.php";

/**
 * @group xml-api
 *
 * @coversDefaultClass WP_XML_Processor
 */
class Tests_XmlApi_WpXmlProcessor extends PHPUnit_Framework_TestCase {
	const XML_SIMPLE       = '<wp:content id="first"><wp:text id="second">Text</wp:text></wp:content>';
	const XML_WITH_CLASSES = '<wp:content wp:post-type="main with-border" id="first"><wp:text wp:post-type="not-main bold with-border" id="second">Text</wp:text></wp:content>';
	const XML_MALFORMED    = '<wp:content><wp:text wp:post-type="d-md-none" Notifications</wp:text><wp:text wp:post-type="d-none d-md-inline">Back to notifications</wp:text></wp:content>';

	/**
	 * @ticket 56299
	 * 
	 * @covers WP_XML_Processor::next_tag
	 * @covers WP_XML_Processor::get_breadcrumbs
	 */
	public function test_get_breadcrumbs()
	{
		$processor = new WP_XML_Processor( <<<XML
			<wp:content>
				<wp:text>
					<photo />
				</wp:text>
			</wp:content>
			XML
		);
		$processor->next_tag();
		$this->assertEquals(
			array('wp:content'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag();
		$this->assertEquals(
			array('wp:content', 'wp:text'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag();
		$this->assertEquals(
			array('wp:content', 'wp:text', 'photo'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$this->assertFalse($processor->next_tag());
	}

	/**
	 * @ticket 57852
	 * 
	 * @return void
	 */
	public function test_matches_breadcrumbs()
	{
		// Initialize the WP_XML_Processor with the given XML string
		$processor = new WP_XML_Processor('<root><wp:post><content><image /></content></wp:post></root>');

		// Move to the next element with tag name 'img'
		$processor->next_tag('image');

		// Assert that the breadcrumbs match the expected sequences
		$this->assertTrue($processor->matches_breadcrumbs(array('content', 'image')));
		$this->assertTrue($processor->matches_breadcrumbs(array('wp:post', 'content', 'image')));
		$this->assertFalse($processor->matches_breadcrumbs(array('wp:post', 'image')));
		$this->assertTrue($processor->matches_breadcrumbs(array('wp:post', '*', 'image')));
	}

	/**
	 * @ticket 57852
	 * 
	 * @return void
	 */
	public function test_next_tag_by_breadcrumbs()
	{
		// Initialize the WP_XML_Processor with the given XML string
		$processor = new WP_XML_Processor('<root><wp:post><content><image /></content></wp:post></root>');

		// Move to the next element with tag name 'img'
		$processor->next_tag(array(
			'breadcrumbs' => array('content', 'image')
		));

		$this->assertEquals('image', $processor->get_tag(), 'Did not find the expected tag');
	}

	/**
	 * @ticket 57852
	 * 
	 * @return void
	 */
	public function test_get_current_depth()
	{
        // Initialize the WP_XML_Processor with the given XML string
        $processor = new WP_XML_Processor('<?xml version="1.0" ?><root><wp:text><post /></wp:text><image /></root>');

        // Assert that the initial depth is 0
        $this->assertEquals(0, $processor->get_current_depth());

        // Opening the root element increases the depth
        $processor->next_tag();
        $this->assertEquals(1, $processor->get_current_depth());

        // Opening the wp:text element increases the depth
        $processor->next_tag();
        $this->assertEquals(2, $processor->get_current_depth());

        // Opening the post element increases the depth
        $processor->next_tag();
        $this->assertEquals(3, $processor->get_current_depth());

        // Elements are closed during `next_tag()` so the depth is decreased to reflect that
        $processor->next_tag();
        $this->assertEquals(2, $processor->get_current_depth());

		// All elements are closed, so the depth is 0
		$processor->next_tag();
		$this->assertEquals(0, $processor->get_current_depth());
	}

	/**
	 * @ticket 56299
	 */
	public function test_no_text_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root>text' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Found a non-existent tag.' );
		
		$this->assertEquals(
			WP_XML_Tag_Processor::ERROR_SYNTAX,
			$processor->get_last_error(),
			'Did not run into a parse error after the root element' 
		);
	}

	/**
	 * @ticket 56299
	 */
	public function test_whitespace_text_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root>   ' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Found a non-existent tag.' );
		$this->assertNull( $processor->get_last_error(), 'Ran into a parse error after the root element' );
	}

	/**
	 * @ticket 56299
	 */
	public function test_processing_directives_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root><?xml processing directive! ?>' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Found a non-existent tag.' );
		$this->assertNull( $processor->get_last_error(), 'Ran into a parse error after the root element' );
	}

	/**
	 * @ticket 56299
	 */
	public function test_mixed_misc_grammar_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root>   <?xml hey ?> <!-- comment --> <?xml another pi ?> <!-- more comments! -->' );
		
		$processor->next_tag();
		$this->assertEquals( 'root', $processor->get_tag(), 'Did not find a tag.' );

		$processor->next_tag();
		$this->assertNull( $processor->get_last_error(), 'Did not run into a parse error after the root element' );
	}

	/**
	 * @ticket 56299
	 */
	public function test_elements_not_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root><another-root>' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Fount an illegal tag.' );
		$this->assertEquals(
			WP_XML_Tag_Processor::ERROR_SYNTAX,
			$processor->get_last_error(),
			'Did not run into a parse error after the root element' 
		);
	}

	public function test_comments_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root><!-- comment -->' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Found a comment node after the root element' );
		$this->assertNull( $processor->get_last_error(), 'Did not run into a parse error after the root element' );
		// $this->assertEquals( ' comment ', $processor->get_modifiable_text(), 'Did not parse the comment after the root element' );
	}

	public function test_cdata_not_allowed_after_root_element() {
		$processor = new WP_XML_Processor( '<root></root><![CDATA[ cdata ]]>' );
		$this->assertTrue( $processor->next_tag(), 'Did not find a tag.' );
		$this->assertFalse( $processor->next_tag(), 'Did not reject a comment node after the root element' );
		$this->assertEquals(
			WP_XML_Tag_Processor::ERROR_SYNTAX,
			$processor->get_last_error(),
			'Did not run into a parse error after the root element' 
		);
	}

	/**
	 * 
	 * @covers WP_XML_Processor::next_tag
	 */
	public function test_detects_invalid_document_no_root_tag() {
		$processor = new WP_XML_Processor( <<<XML
			<?xml version="1.0" encoding="UTF-8"?>
			<!-- comment no root tag -->
		XML
		);
		$this->assertFalse( $processor->next_tag(), 'Found an element when there was none.' );
		$this->assertTrue( $processor->paused_at_incomplete_token(), 'Did not indicate that the XML input was incomplete.' );
	}

	/**
	 * 
	 * @covers WP_XML_Processor::next_tag
	 */
	public function test_unclosed_root_yields_incomplete_input() {
		$processor = new WP_XML_Processor( <<<XML
			<root inert="yes" title="test">
				<child></child>
				<?xml directive ?>
		XML
		);
		while( $processor->next_tag() ) {
			continue;
		}
		$this->assertTrue( $processor->paused_at_incomplete_token(), 'Did not indicate that the XML input was incomplete.' );
	}

}
