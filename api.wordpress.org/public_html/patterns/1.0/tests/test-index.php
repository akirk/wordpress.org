<?php

namespace WordPressdotorg\API\Patterns\Tests;
use PHPUnit\Framework\TestCase;
use Requests, Requests_Response;

/**
 * @group patterns
 */
class Test_Patterns extends TestCase {
	/**
	 * Make an API request to the current sandbox.
	 */
	private function send_request( string $query_string ) : Requests_Response {
		$url = 'https://127.0.0.1/patterns/1.0' . $query_string;

		$headers = array(
			'Accept' => 'application/json',
			'Host'   => 'api.wordpress.org',
		);

		$options = array(
			/*
			 * It's expected that the sandbox hostname won't be valid. This is safe because we're only connecting
			 * to `127.0.0.1`.
			 */
			'verifyname' => false,
		);

		/*
		 * ⚠️ Warning: Only make `get()` requests in this suite.
		 *
		 * POST/UPDATE/DELETE requests would change production data, so those would have to be done in a local
		 * environment.
		 */
		return Requests::get( $url, $headers, $options );
	}

	/**
	 * Asserts that an HTTP response is valid and contains a pattern.
	 *
	 * @param Requests_Response $response
	 */
	public function assertResponseHasPattern( $response ) {
		$patterns = json_decode( $response->body );

		$this->assertSame( 200, $response->status_code );
		$this->assertIsString( $patterns[0]->title->rendered );
		$this->assertIsInt( $patterns[0]->meta->wpop_viewport_width );
		$this->assertIsArray( $patterns[0]->meta->wpop_category_slugs );
		$this->assertIsArray( $patterns[0]->meta->wpop_keyword_slugs );
	}

	/**
	 * Pluck term IDs from a list of patterns.
	 *
	 * @param object[] $patterns
	 *
	 * @return int[]
	 */
	public function get_term_slugs( $patterns ) {
		$term_slugs = array();

		foreach ( $patterns as $pattern ) {
			$term_slugs = array_merge(
				$term_slugs,
				$pattern->meta->wpop_category_slugs
			);
		}

		return array_unique( $term_slugs );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_all_patterns() : void {
		$response   = $this->send_request( '/' );
		$patterns   = json_decode( $response->body );
		$term_slugs = $this->get_term_slugs( $patterns );

		$this->assertResponseHasPattern( $response );
		$this->assertGreaterThan( 1, count( $term_slugs ) );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_category() : void {
		$button_term_id = 2;
		$response       = $this->send_request( '/?pattern-categories=' . $button_term_id );
		$patterns       = json_decode( $response->body );
		$term_slugs     = $this->get_term_slugs( $patterns );

		$this->assertResponseHasPattern( $response );
		$this->assertSame( array( 'buttons' ), $term_slugs );
	}

	/**
	 * @covers ::main()
	 *
	 * @dataProvider data_search_patterns
	 *
	 * @group e2e
	 *
	 * @param string $search_query
	 */
	public function test_search_patterns( $search_term, $match_expected ) : void {
		$response = $this->send_request( '/?search=' . $search_term );
		$patterns = json_decode( $response->body );

		if ( $match_expected ) {
			$this->assertResponseHasPattern( $response );

			$all_patterns_include_query = true;

			foreach ( $patterns as $pattern ) {
				$match_in_title       = stripos( $pattern->title->rendered, $search_term );
				$match_in_description = stripos( $pattern->meta->wpop_description, $search_term );;

				if ( ! $match_in_title && ! $match_in_description ) {
					$all_patterns_include_query = false;
					break;
				}
			}

			$this->assertTrue( $all_patterns_include_query );

		} else {
			$this->assertSame( 200, $response->status_code );
			$this->assertSame( '[]', $response->body );
		}
	}

	public function data_search_patterns() {
		return array(
			'match title' => array(
				'search_term'    => 'side by side',
				'match_expected' => true,
			),

			// todo Enable this once https://github.com/WordPress/pattern-directory/issues/28 is done
//			'match description' => array(
//				'search_term'    => 'bright gradient background',
//				'match_expected' => true,
//			),

			'no matches' => array(
				'search_term'    => 'Supercalifragilisticexpialidocious',
				'match_expected' => false,
			),
		);
	}
}