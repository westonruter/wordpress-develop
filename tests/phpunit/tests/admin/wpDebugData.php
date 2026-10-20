<?php

/**
 * @group admin
 * @group site-health
 *
 * @coversDefaultClass WP_Debug_Data
 */
class Tests_Admin_wpDebugData extends WP_UnitTestCase {

	/**
	 * File cache only mode has no shared-memory statistics to report.
	 *
	 * @ticket 64707
	 * @covers ::debug_data
	 */
	public function test_opcode_cache_with_file_cache_only() {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			$this->markTestSkipped( 'The OPcache status function is required.' );
		}

		$status = opcache_get_status( false );
		if ( ! is_array( $status ) || empty( $status['file_cache_only'] ) ) {
			$this->markTestSkipped( 'Requires OPcache with opcache.file_cache_only=1 and a writable file cache.' );
		}

		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_not_needed' );
			}
		);

		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		$data   = WP_Debug_Data::debug_data();
		$fields = $data['wp-server']['fields'];

		$this->assertSame( __( 'Enabled' ), $fields['opcode_cache']['value'] );
		$this->assertTrue( $fields['opcode_cache']['debug'] );
		$this->assertArrayNotHasKey( 'opcode_cache_memory_usage', $fields );
	}
}
