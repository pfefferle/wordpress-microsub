<?php
/**
 * Test Adapter class.
 *
 * @package Microsub
 */

namespace Microsub\Tests;

use WP_UnitTestCase;

require_once dirname( __DIR__ ) . '/includes/class-test-adapter-implementation.php';
require_once dirname( __DIR__ ) . '/includes/class-test-second-adapter.php';

/**
 * Test Adapter class.
 *
 * @coversDefaultClass \Microsub\Adapter
 */
class Test_Adapter extends WP_UnitTestCase {

	/**
	 * Test adapter instance.
	 *
	 * @var Test_Adapter_Implementation
	 */
	private $adapter;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->adapter = new Test_Adapter_Implementation();
	}

	/**
	 * Test that adapter can be registered.
	 *
	 * @covers ::register
	 * @covers ::register_adapter
	 */
	public function test_register_adapter() {
		$this->adapter->register();

		$adapters = apply_filters( 'microsub_adapters', array() );

		$this->assertArrayHasKey( 'test-adapter', $adapters );
		$this->assertEquals( 'Test Adapter', $adapters['test-adapter']['name'] );
	}

	/**
	 * Test get_channels filter.
	 *
	 * @covers ::register
	 */
	public function test_get_channels_filter() {
		$this->adapter->register();

		$channels = apply_filters( 'microsub_get_channels', null, 1 );

		$this->assertIsArray( $channels );
		$this->assertCount( 2, $channels );
		$this->assertEquals( 'notifications', $channels[0]['uid'] );
	}

	/**
	 * Test get_timeline filter.
	 *
	 * @covers ::register
	 */
	public function test_get_timeline_filter() {
		$this->adapter->register();

		$result = apply_filters( 'microsub_get_timeline', null, 'default', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertCount( 1, $result['items'] );
	}

	/**
	 * Test follow filter.
	 *
	 * @covers ::register
	 */
	public function test_follow_filter() {
		$this->adapter->register();

		$result = apply_filters( 'microsub_follow', null, 'default', 'https://example.com', 1 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'feed', $result['type'] );
		$this->assertEquals( 'https://example.com', $result['url'] );
	}

	/**
	 * Test adapter ID and name getters.
	 *
	 * @covers ::get_id
	 * @covers ::get_name
	 */
	public function test_adapter_getters() {
		$this->assertEquals( 'test-adapter', $this->adapter->get_id() );
		$this->assertEquals( 'Test Adapter', $this->adapter->get_name() );
	}

	/**
	 * Test get_following filter.
	 *
	 * @covers ::register
	 */
	public function test_get_following_filter() {
		$this->adapter->register();

		$result = apply_filters( 'microsub_get_following', array(), 'default', 1 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'feed', $result[0]['type'] );
	}

	/**
	 * Test unfollow filter.
	 *
	 * @covers ::register
	 */
	public function test_unfollow_filter() {
		$this->adapter->register();

		$result = apply_filters( 'microsub_unfollow', null, 'default', 'https://example.com', 1 );

		$this->assertTrue( $result );
	}

	/**
	 * Test multiple adapters are aggregated.
	 *
	 * @covers ::register
	 * @covers ::register_adapter
	 */
	public function test_multiple_adapters() {
		$this->adapter->register();

		$second_adapter = new Test_Second_Adapter();
		$second_adapter->register();

		$adapters = apply_filters( 'microsub_adapters', array() );

		$this->assertCount( 2, $adapters );
		$this->assertArrayHasKey( 'test-adapter', $adapters );
		$this->assertArrayHasKey( 'second-adapter', $adapters );
	}

	/**
	 * Test channels are aggregated from multiple adapters.
	 *
	 * @covers ::register
	 */
	public function test_channels_aggregation() {
		$this->adapter->register();

		$second_adapter = new Test_Second_Adapter();
		$second_adapter->register();

		$channels = apply_filters( 'microsub_get_channels', array(), 1 );

		$this->assertCount( 3, $channels );
	}

	/**
	 * Test can_handle_url default implementation.
	 *
	 * @covers ::can_handle_url
	 */
	public function test_can_handle_url_default() {
		$this->assertTrue( $this->adapter->can_handle_url( 'https://example.com' ) );
	}

	/**
	 * Test owns_feed default implementation.
	 *
	 * @covers ::owns_feed
	 */
	public function test_owns_feed_default() {
		$this->assertFalse( $this->adapter->owns_feed( 'https://example.com/feed' ) );
	}
}
