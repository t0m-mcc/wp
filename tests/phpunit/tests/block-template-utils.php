<?php
/**
 * Tests for the Block Templates abstraction layer.
 *
 * @package WordPress
 *
 * @group block-templates
 */
class Tests_Block_Template_Utils extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	private static $template_post;
	private static $template_part_post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		/*
		 * Set up a template post corresponding to a different theme.
		 * We do this to ensure resolution and slug creation works as expected,
		 * even with another post of that same name present for another theme.
		 */
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'my_template',
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						'this-theme-should-not-resolve',
					),
				),
			)
		);

		wp_set_post_terms( self::$template_post->ID, 'this-theme-should-not-resolve', 'wp_theme' );

		// Set up template post.
		self::$template_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'my_template',
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);

		wp_set_post_terms( self::$template_post->ID, self::TEST_THEME, 'wp_theme' );

		// Set up template part post.
		self::$template_part_post = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template_part',
				'post_name'    => 'my_template_part',
				'post_title'   => 'My Template Part',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template part',
				'tax_input'    => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);

		wp_set_post_terms( self::$template_part_post->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( self::$template_part_post->ID, self::TEST_THEME, 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$template_post->ID );
	}

	public function set_up() {
		parent::set_up();
		switch_theme( self::TEST_THEME );
	}

	/**
	 * Tear down after each test.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'tests/hooked-block' ) ) {
			unregister_block_type( 'tests/hooked-block' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_inject_theme_attribute_in_template_part_block() {
		$template_part_block = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		_inject_theme_attribute_in_template_part_block( $template_part_block );
		$expected = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
				'theme'     => get_stylesheet(),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);
		$this->assertSame(
			$expected,
			$template_part_block,
			'`theme` attribute was not correctly injected in template part block.'
		);
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_not_inject_theme_attribute_in_template_part_block_theme_attribute_exists() {
		$template_part_block = array(
			'blockName'    => 'core/template-part',
			'attrs'        => array(
				'slug'      => 'header',
				'align'     => 'full',
				'tagName'   => 'header',
				'className' => 'site-header',
				'theme'     => 'fake-theme',
			),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		$expected = $template_part_block;
		_inject_theme_attribute_in_template_part_block( $template_part_block );
		$this->assertSame(
			$expected,
			$template_part_block,
			'Existing `theme` attribute in template part block was not respected by attribute injection.'
		);
	}

	/**
	 * @ticket 59338
	 *
	 * @covers ::_inject_theme_attribute_in_template_part_block
	 */
	public function test_not_inject_theme_attribute_non_template_part_block() {
		$non_template_part_block = array(
			'blockName'    => 'core/post-content',
			'attrs'        => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);

		$expected = $non_template_part_block;
		_inject_theme_attribute_in_template_part_block( $non_template_part_block );
		$this->assertSame(
			$expected,
			$non_template_part_block,
			'`theme` attribute injection modified non-template-part block.'
		);
	}

	/**
	 * @ticket 59452
	 *
	 * @covers ::_inject_theme_attribute_in_block_template_content
	 *
	 * @expectedDeprecated _inject_theme_attribute_in_block_template_content
	 */
	public function test_inject_theme_attribute_in_block_template_content() {
		$theme                           = get_stylesheet();
		$content_without_theme_attribute = '<!-- wp:template-part {"slug":"header","align":"full", "tagName":"header","className":"site-header"} /-->';
		$template_content                = _inject_theme_attribute_in_block_template_content(
			$content_without_theme_attribute,
			$theme
		);
		$expected                        = sprintf(
			'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
			get_stylesheet()
		);
		$this->assertSame( $expected, $template_content );

		$content_without_theme_attribute_nested = '<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full", "tagName":"header","className":"site-header"} /--><!-- /wp:group -->';
		$template_content                       = _inject_theme_attribute_in_block_template_content(
			$content_without_theme_attribute_nested,
			$theme
		);
		$expected                               = sprintf(
			'<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /--><!-- /wp:group -->',
			get_stylesheet()
		);
		$this->assertSame( $expected, $template_content );

		// Does not inject theme when there is an existing theme attribute.
		$content_with_existing_theme_attribute = '<!-- wp:template-part {"slug":"header","theme":"fake-theme","align":"full", "tagName":"header","className":"site-header"} /-->';
		$template_content                      = _inject_theme_attribute_in_block_template_content(
			$content_with_existing_theme_attribute,
			$theme
		);
		$this->assertSame( $content_with_existing_theme_attribute, $template_content );

		// Does not inject theme when there is no template part.
		$content_with_no_template_part = '<!-- wp:post-content /-->';
		$template_content              = _inject_theme_attribute_in_block_template_content(
			$content_with_no_template_part,
			$theme
		);
		$this->assertSame( $content_with_no_template_part, $template_content );
	}

	/**
	 * @ticket 54448
	 * @ticket 59460
	 *
	 * @dataProvider data_remove_theme_attribute_in_block_template_content
	 *
	 * @expectedDeprecated _remove_theme_attribute_in_block_template_content
	 */
	public function test_remove_theme_attribute_in_block_template_content( $template_content, $expected ) {
		$this->assertSame( $expected, _remove_theme_attribute_in_block_template_content( $template_content ) );
	}

	/**
	 * @ticket 59460
	 *
	 * @covers ::_remove_theme_attribute_from_template_part_block
	 * @covers ::traverse_and_serialize_blocks
	 *
	 * @dataProvider data_remove_theme_attribute_in_block_template_content
	 *
	 * @param string $template_content The template markup.
	 * @param string $expected         The expected markup after removing the theme attribute from Template Part blocks.
	 */
	public function test_remove_theme_attribute_from_template_part_block( $template_content, $expected ) {
		$template_content_parsed_blocks = parse_blocks( $template_content );

		$this->assertSame(
			$expected,
			traverse_and_serialize_blocks(
				$template_content_parsed_blocks,
				'_remove_theme_attribute_from_template_part_block'
			)
		);
	}

	public function data_remove_theme_attribute_in_block_template_content() {
		return array(
			array(
				'<!-- wp:template-part {"slug":"header","theme":"tt1-blocks","align":"full","tagName":"header","className":"site-header"} /-->',
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			array(
				'<!-- wp:group --><!-- wp:template-part {"slug":"header","theme":"tt1-blocks","align":"full","tagName":"header","className":"site-header"} /--><!-- /wp:group -->',
				'<!-- wp:group --><!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /--><!-- /wp:group -->',
			),
			// Does not modify content when there is no existing theme attribute.
			array(
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
				'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			// Does not remove theme when there is no template part.
			array(
				'<!-- wp:post-content /-->',
				'<!-- wp:post-content /-->',
			),
		);
	}

	/**
	 * Should retrieve the template from the theme files.
	 */
	public function test_get_block_template_from_file() {
		$id       = get_stylesheet() . '//' . 'index';
		$template = get_block_template( $id, 'wp_template' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'index', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'wp_template', $template->type );

		// Test template parts.
		$id       = get_stylesheet() . '//' . 'small-header';
		$template = get_block_template( $id, 'wp_template_part' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'small-header', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'wp_template_part', $template->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template->area );
	}

	/**
	 * Should retrieve the template from the CPT.
	 */
	public function test_get_block_template_from_post() {
		$id       = get_stylesheet() . '//' . 'my_template';
		$template = get_block_template( $id, 'wp_template' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'wp_template', $template->type );

		// Test template parts.
		$id       = get_stylesheet() . '//' . 'my_template_part';
		$template = get_block_template( $id, 'wp_template_part' );
		$this->assertSame( $id, $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template_part', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'wp_template_part', $template->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template->area );
	}

	/**
	 * Should flatten nested blocks
	 */
	public function test_flatten_blocks() {
		$content_template_part_inside_group = '<!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group -->';
		$blocks                             = parse_blocks( $content_template_part_inside_group );
		$actual                             = _flatten_blocks( $blocks );
		$expected                           = array( $blocks[0], $blocks[0]['innerBlocks'][0] );
		$this->assertSame( $expected, $actual );

		$content_template_part_inside_group_inside_group = '<!-- wp:group --><!-- wp:group --><!-- wp:template-part {"slug":"header"} /--><!-- /wp:group --><!-- /wp:group -->';
		$blocks   = parse_blocks( $content_template_part_inside_group_inside_group );
		$actual   = _flatten_blocks( $blocks );
		$expected = array( $blocks[0], $blocks[0]['innerBlocks'][0], $blocks[0]['innerBlocks'][0]['innerBlocks'][0] );
		$this->assertSame( $expected, $actual );

		$content_without_inner_blocks = '<!-- wp:group /-->';
		$blocks                       = parse_blocks( $content_without_inner_blocks );
		$actual                       = _flatten_blocks( $blocks );
		$expected                     = array( $blocks[0] );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Should generate block templates export file.
	 *
	 * @ticket 54448
	 * @requires extension zip
	 */
	public function test_wp_generate_block_templates_export_file() {
		$filename = wp_generate_block_templates_export_file();
		$this->assertFileExists( $filename, 'zip file is created at the specified path' );
		$this->assertTrue( filesize( $filename ) > 0, 'zip file is larger than 0 bytes' );

		// Open ZIP file and make sure the directories exist.
		$zip = new ZipArchive();
		$zip->open( $filename );
		$has_theme_json               = $zip->locateName( 'theme.json' ) !== false;
		$has_block_templates_dir      = $zip->locateName( 'templates/' ) !== false;
		$has_block_template_parts_dir = $zip->locateName( 'parts/' ) !== false;
		$this->assertTrue( $has_theme_json, 'theme.json exists' );
		$this->assertTrue( $has_block_templates_dir, 'theme/templates directory exists' );
		$this->assertTrue( $has_block_template_parts_dir, 'theme/parts directory exists' );

		// ZIP file contains at least one HTML file.
		$has_html_files = false;
		$num_files      = $zip->numFiles;
		for ( $i = 0; $i < $num_files; $i++ ) {
			$filename = $zip->getNameIndex( $i );
			if ( '.html' === substr( $filename, -5 ) ) {
				$has_html_files = true;
				break;
			}
		}
		$this->assertTrue( $has_html_files, 'contains at least one html file' );
	}

	/**
	 * @ticket 60671
	 *
	 * @covers inject_ignored_hooked_blocks_metadata_attributes
	 */
	public function test_inject_ignored_hooked_blocks_metadata_attributes_into_template() {
		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'tests/anchor-block' => 'after',
				),
			)
		);

		$id      = self::TEST_THEME . '//' . 'my_template';
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates/' . $id );
		$request->set_param( 'id', $id );
		$template = get_block_template( $id, 'wp_template' );

		$changes               = new stdClass();
		$changes->ID           = $template->wp_id;
		$changes->post_content = '<!-- wp:tests/anchor-block -->Hello<!-- /wp:tests/anchor-block -->';

		$post = inject_ignored_hooked_blocks_metadata_attributes( $changes, $request );
		$this->assertSame(
			'<!-- wp:tests/anchor-block {"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->Hello<!-- /wp:tests/anchor-block -->',
			$post->post_content,
			'The hooked block was not injected into the anchor block\'s ignoredHookedBlocks metadata.'
		);
	}

	/**
	 * @ticket 60671
	 *
	 * @covers inject_ignored_hooked_blocks_metadata_attributes
	 */
	public function test_inject_ignored_hooked_blocks_metadata_attributes_into_template_part() {
		register_block_type(
			'tests/hooked-block',
			array(
				'block_hooks' => array(
					'tests/anchor-block' => 'after',
				),
			)
		);

		$id      = self::TEST_THEME . '//' . 'my_template_part';
		$request = new WP_REST_Request( 'POST', '/wp/v2/template-parts/' . $id );
		$request->set_param( 'id', $id );
		$template = get_block_template( $id, 'wp_template_part' );

		$changes               = new stdClass();
		$changes->ID           = $template->wp_id;
		$changes->post_content = '<!-- wp:tests/anchor-block -->Hello<!-- /wp:tests/anchor-block -->';

		$post = inject_ignored_hooked_blocks_metadata_attributes( $changes, $request );
		$this->assertSame(
			'<!-- wp:tests/anchor-block {"metadata":{"ignoredHookedBlocks":["tests/hooked-block"]}} -->Hello<!-- /wp:tests/anchor-block -->',
			$post->post_content,
			'The hooked block was not injected into the anchor block\'s ignoredHookedBlocks metadata.'
		);
	}

	/**
	 * @ticket 60754
	 *
	 * @covers inject_ignored_hooked_blocks_metadata_attributes
	 */
	public function test_inject_ignored_hooked_blocks_metadata_attributes_into_template_applies_filter_correctly() {
		$action = new MockAction();
		add_filter( 'hooked_block_types', array( $action, 'filter' ), 10, 4 );

		$id      = self::TEST_THEME . '//' . 'my_template';
		$request = new WP_REST_Request( 'POST', '/wp/v2/templates/' . $id );
		$request->set_param( 'id', $id );

		$changes               = new stdClass();
		$changes->post_name    = 'my-updated-template';
		$changes->ID           = self::$template_post->ID;
		$changes->post_content = '<!-- wp:tests/anchor-block -->Hello<!-- /wp:tests/anchor-block -->';

		inject_ignored_hooked_blocks_metadata_attributes( $changes, $request );

		$args              = $action->get_args();
		$anchor_block_type = end( $args )[2];
		$context           = end( $args )[3];

		$this->assertSame( 'tests/anchor-block', $anchor_block_type );

		$this->assertInstanceOf( 'WP_Block_Template', $context );

		$this->assertSame(
			$changes->post_name,
			$context->slug,
			'The slug field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);
		$this->assertSame(
			$changes->ID,
			$context->wp_id,
			'The wp_id field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);
		$this->assertSame(
			'publish',
			$context->status,
			'The status field of the context passed to the hooked_block_types filter isn\'t set to publish.'
		);
		$this->assertSame(
			$changes->post_content,
			$context->content,
			'The content field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);

		$this->assertSame(
			self::$template_post->post_title,
			$context->title,
			'The title field of the context passed to the hooked_block_types filter doesn\'t match the template post object.'
		);
		$this->assertSame(
			self::$template_post->post_excerpt,
			$context->description,
			'The description field of the context passed to the hooked_block_types filter doesn\'t match the template post object.'
		);
	}

	/**
	 * @ticket 60754
	 *
	 * @covers inject_ignored_hooked_blocks_metadata_attributes
	 */
	public function test_inject_ignored_hooked_blocks_metadata_attributes_into_template_part_applies_filter_correctly() {
		$action = new MockAction();
		add_filter( 'hooked_block_types', array( $action, 'filter' ), 10, 4 );

		$id      = self::TEST_THEME . '//' . 'my_template_part';
		$request = new WP_REST_Request( 'POST', '/wp/v2/template-parts/' . $id );
		$request->set_param( 'id', $id );

		$changes               = new stdClass();
		$changes->post_name    = 'my-updated-template-part';
		$changes->ID           = self::$template_part_post->ID;
		$changes->post_content = '<!-- wp:tests/anchor-block -->Hello<!-- /wp:tests/anchor-block -->';

		$changes->tax_input['wp_template_part_area'] = WP_TEMPLATE_PART_AREA_FOOTER;

		inject_ignored_hooked_blocks_metadata_attributes( $changes, $request );

		$args              = $action->get_args();
		$anchor_block_type = end( $args )[2];
		$context           = end( $args )[3];

		$this->assertSame( 'tests/anchor-block', $anchor_block_type );

		$this->assertInstanceOf( 'WP_Block_Template', $context );

		$this->assertSame(
			$changes->post_name,
			$context->slug,
			'The slug field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);
		$this->assertSame(
			$changes->ID,
			$context->wp_id,
			'The wp_id field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);
		$this->assertSame(
			'publish',
			$context->status,
			'The status field of the context passed to the hooked_block_types filter isn\'t set to publish.'
		);
		$this->assertSame(
			$changes->post_content,
			$context->content,
			'The content field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);
		$this->assertSame(
			$changes->tax_input['wp_template_part_area'],
			$context->area,
			'The area field of the context passed to the hooked_block_types filter doesn\'t match the template changes.'
		);

		$this->assertSame(
			self::$template_part_post->post_title,
			$context->title,
			'The title field of the context passed to the hooked_block_types filter doesn\'t match the template post object.'
		);
		$this->assertSame(
			self::$template_part_post->post_excerpt,
			$context->description,
			'The description field of the context passed to the hooked_block_types filter doesn\'t match the template post object.'
		);
	}
}
