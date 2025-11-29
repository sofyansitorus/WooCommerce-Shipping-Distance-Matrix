<?php

/**
 * Simple math sanity checks to ensure test suite wiring.
 *
 * @group math
 */
class Test_Wcsdm_Math extends WP_UnitTestCase {

	public function test_addition():void {
		$this->assertSame( 4, 2 + 2 );
	}

	public function test_multiplication():void {
		$this->assertSame( 9, 3 * 3 );
	}
}
