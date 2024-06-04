<?php

/**
 * XML API: WP_XML_Decoder class
 *
 * Decodes spans of raw text found inside XML content.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since WP_VERSION
 */
class WP_XML_Decoder {

	/**
	 * Decodes a span of XML text.
	 *
	 * Example:
	 *
	 *     '©' = WP_XML_Decoder::decode( 'data', '&copy;' );
	 *
	 * @since WP_VERSION
	 *
	 * @access private
	 *
	 * @param string $text    Text document containing span of text to decode.
	 * @return string Decoded UTF-8 string.
	 */
	public static function decode( $text ) {
		$decoded = '';
		$end     = strlen( $text );
		$at      = 0;
		$was_at  = 0;

		while ( $at < $end ) {
			$next_character_reference_at = strpos( $text, '&', $at );
			if ( false === $next_character_reference_at || $next_character_reference_at >= $end ) {
				break;
			}
			// $next_character_reference_at += 1;

			/*
			 * Capture all bytes that could form a character reference.
			 *
			 * This only supports:
			 *
			 * * The five mandated character references, that is &amp; &lt; &gt; &quot; &apos;
			 * * Numeric character references, e.g. &#123; or &#x1A;
			 *
			 * XML grammar rule for parsing numeric references is:
			 *
			 *     [66] CharRef   ::= '&#' [0-9]+ ';' | '&#x' [0-9a-fA-F]+ ';' [WFC: Legal Character]
			 *
			 * See https://www.w3.org/TR/xml/#NT-CharRef
			 */
			$token_length = strspn(
				$text,
				'ampltgquos#xX0123456789bcdefABCDEF;',
				$next_character_reference_at + 1,
				/*
				 * Limit the length of the token to avoid scanning the entire document in case
				 * a semicolon is missing.
				 *
				 * The maximum supported code point is 10FFFF, which is 9 characters long when
				 * represented as either decimal or hexadecimal numeric character reference entity.
				 * Technically, you can also add zeros to the front of the entity, which makes the
				 * string longer, for example &#00000010FFFF;
				 *
				 * We limit this scan to 30 characters, which allows twenty zeros at the front.
				 */
				30
			);

			if ( false === $token_length ) {
				return null;
			}

			if ( ';' !== $text[ $next_character_reference_at + 1 + $token_length - 1 ] ) {
				/*
				 * In XML, all character references must end with a semicolon.
				 */
				return null;
			}

			$token = strtolower( substr( $text, $next_character_reference_at + 1, $token_length - 1 ) );

			if ( 'amp' === $token ) {
				$character_reference = '&';
			} elseif ( 'lt' === $token ) {
				$character_reference = '<';
			} elseif ( 'gt' === $token ) {
				$character_reference = '>';
			} elseif ( 'quot' === $token ) {
				$character_reference = '"';
			} elseif ( 'apos' === $token ) {
				$character_reference = "'";
			} else {
				$code_point = self::parse_code_point( $text, $next_character_reference_at );
				if ( null === $code_point ) {
					/*
					 * > The following are forbidden, and constitute fatal errors:
					 * > * the appearance of a reference to an unparsed entity, except in the EntityValue in an entity declaration.
					 *
					 * See https://www.w3.org/TR/xml/#forbidden
					 */
					return null;
				}
				$character_reference = WP_HTML_Decoder::code_point_to_utf8_bytes( $code_point );
				if (
					'�' === $character_reference &&
					0xFFFD !== $code_point
				) {
					/*
					 * Stop processing if we got an invalid character AND the reference does not
					 * specifically refer code point FFFD (�).
					 *
					 * > It is a fatal error when an XML processor encounters an entity with an
					 * > encoding that it is unable to process. It is a fatal error if an XML entity
					 * > is determined (via default, encoding declaration, or higher-level protocol)
					 * > to be in a certain encoding but contains byte sequences that are not legal
					 * > in that encoding. Specifically, it is a fatal error if an entity encoded in
					 * >  UTF-8 contains any ill-formed code unit sequences, as defined in section
					 * > 3.9 of Unicode [Unicode]. Unless an encoding is determined by a higher-level
					 * > protocol, it is also a fatal error if an XML entity contains no encoding
					 * > declaration and its content is not legal UTF-8 or UTF-16.
					 *
					 * See https://www.w3.org/TR/xml/#charencoding
					 */
					return null;
				}
			}

			$at       = $next_character_reference_at;
			$decoded .= substr( $text, $was_at, $at - $was_at );
			$decoded .= $character_reference;
			$at      += $token_length + 1;
			$was_at   = $at;
		}

		if ( 0 === $was_at ) {
			return $text;
		}

		if ( $was_at < $end ) {
			$decoded .= substr( $text, $was_at, $end - $was_at );
		}

		return $decoded;
	}

	private static function parse_code_point( $text, $entity_at ) {
		$length = strlen( $text );
		$at     = $entity_at;
		/*
		 * Numeric character references.
		 *
		 * When truncated, these will encode the code point found by parsing the
		 * digits that are available. For example, when `&#x1f170;` is truncated
		 * to `&#x1f1` it will encode `Ǳ`. It does not:
		 *  - know how to parse the original `🅰`.
		 *  - fail to parse and return plaintext `&#x1f1`.
		 *  - fail to parse and return the replacement character `�`
		 */
		if ( '#' !== $text[ $at + 1 ] ) {
			return null;
		}
		if ( $at + 2 >= $length ) {
			return null;
		}

		/** Tracks inner parsing within the numeric character reference. */
		$digits_at = $at + 2;

		if ( 'x' === $text[ $digits_at ] || 'X' === $text[ $digits_at ] ) {
			$numeric_base   = 16;
			$numeric_digits = '0123456789abcdefABCDEF';
			$max_digits     = 6; // &#x10FFFF;
			++$digits_at;
		} else {
			$numeric_base   = 10;
			$numeric_digits = '0123456789';
			$max_digits     = 7; // &#1114111;
		}

		// Cannot encode invalid Unicode code points. Max is to U+10FFFF.
		$zero_count    = strspn( $text, '0', $digits_at );
		$digit_count   = strspn( $text, $numeric_digits, $digits_at + $zero_count );
		$after_digits  = $digits_at + $zero_count + $digit_count;
		$has_semicolon = $after_digits < $length && ';' === $text[ $after_digits ];
		$end_of_span   = $has_semicolon ? $after_digits + 1 : $after_digits;

		// `&#` or `&#x` without digits returns into plaintext.
		if ( 0 === $digit_count && 0 === $zero_count ) {
			return null;
		}

		// Whereas `&#` and only zeros is invalid.
		if ( 0 === $digit_count ) {
			$match_byte_length = $end_of_span - $at;
			return '�';
		}

		// If there are too many digits then it's not worth parsing. It's invalid.
		if ( $digit_count > $max_digits ) {
			$match_byte_length = $end_of_span - $at;
			return '�';
		}

		$digits     = substr( $text, $digits_at + $zero_count, $digit_count );
		$code_point = intval( $digits, $numeric_base );

		/*
		 * Code points in the C1 controls area need to be remapped as if they
		 * were stored in Windows-1252. Note! This transformation only happens
		 * for numeric character references. The raw code points in the byte
		 * stream are not translated.
		 *
		 * > If the number is one of the numbers in the first column of
		 * > the following table, then find the row with that number in
		 * > the first column, and set the character reference code to
		 * > the number in the second column of that row.
		 */
		if ( $code_point >= 0x80 && $code_point <= 0x9F ) {
			$windows_1252_mapping = array(
				0x20AC, // 0x80 -> EURO SIGN (€).
				0x81,   // 0x81 -> (no change).
				0x201A, // 0x82 -> SINGLE LOW-9 QUOTATION MARK (‚).
				0x0192, // 0x83 -> LATIN SMALL LETTER F WITH HOOK (ƒ).
				0x201E, // 0x84 -> DOUBLE LOW-9 QUOTATION MARK („).
				0x2026, // 0x85 -> HORIZONTAL ELLIPSIS (…).
				0x2020, // 0x86 -> DAGGER (†).
				0x2021, // 0x87 -> DOUBLE DAGGER (‡).
				0x02C6, // 0x88 -> MODIFIER LETTER CIRCUMFLEX ACCENT (ˆ).
				0x2030, // 0x89 -> PER MILLE SIGN (‰).
				0x0160, // 0x8A -> LATIN CAPITAL LETTER S WITH CARON (Š).
				0x2039, // 0x8B -> SINGLE LEFT-POINTING ANGLE QUOTATION MARK (‹).
				0x0152, // 0x8C -> LATIN CAPITAL LIGATURE OE (Œ).
				0x8D,   // 0x8D -> (no change).
				0x017D, // 0x8E -> LATIN CAPITAL LETTER Z WITH CARON (Ž).
				0x8F,   // 0x8F -> (no change).
				0x90,   // 0x90 -> (no change).
				0x2018, // 0x91 -> LEFT SINGLE QUOTATION MARK (‘).
				0x2019, // 0x92 -> RIGHT SINGLE QUOTATION MARK (’).
				0x201C, // 0x93 -> LEFT DOUBLE QUOTATION MARK (“).
				0x201D, // 0x94 -> RIGHT DOUBLE QUOTATION MARK (”).
				0x2022, // 0x95 -> BULLET (•).
				0x2013, // 0x96 -> EN DASH (–).
				0x2014, // 0x97 -> EM DASH (—).
				0x02DC, // 0x98 -> SMALL TILDE (˜).
				0x2122, // 0x99 -> TRADE MARK SIGN (™).
				0x0161, // 0x9A -> LATIN SMALL LETTER S WITH CARON (š).
				0x203A, // 0x9B -> SINGLE RIGHT-POINTING ANGLE QUOTATION MARK (›).
				0x0153, // 0x9C -> LATIN SMALL LIGATURE OE (œ).
				0x9D,   // 0x9D -> (no change).
				0x017E, // 0x9E -> LATIN SMALL LETTER Z WITH CARON (ž).
				0x0178, // 0x9F -> LATIN CAPITAL LETTER Y WITH DIAERESIS (Ÿ).
			);

			$code_point = $windows_1252_mapping[ $code_point - 0x80 ];
		}

		return $code_point;
	}
}