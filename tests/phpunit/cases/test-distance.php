<?php
/**
 * Tests for Wcsdm_Distance value object.
 *
 * @group distance
 */
class Test_Wcsdm_Distance extends WP_UnitTestCase {

	public static function formatter(string $number):string {
		$dp = '';
		$trim_zeros = true;
	
		if ( '' === $dp || false === $dp || null === $dp ) {
			$dp = 2;
		}

		$dp = (int) $dp;
		$formatted = number_format( (float) $number, $dp, '.', '' );

		if ( $trim_zeros && false !== strpos( $formatted, '.' ) ) {
			$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		}

		return $formatted;
	}

	public function test_from_m_and_get_raw_data(): void {
		$distance = Wcsdm_Distance::from_m( '123.45' );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );

		$this->assertSame(
			array(
				'number' => '123.45',
				'unit'   => 'm',
			),
			$distance->to_array()
		);
	}

	public function test_converts_m_to_km(): void {
		$distance = Wcsdm_Distance::from_m( '1000' );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );

		$this->assertEquals( '1', $distance->in_km(), 0.0001 );
	}

	public function test_converts_km_to_m(): void {
		$distance = Wcsdm_Distance::from_km( '1.5' );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );
		
		$this->assertEquals( '1500', $distance->in_m() );
	}

	public function test_converts_mi_to_km_and_m(): void {
		$distance = Wcsdm_Distance::from_mi( '1' );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );
		
		$this->assertEquals( '1.61', $distance->in_km() );
		$this->assertEquals( '1609.34', $distance->in_m() );
	}

	public function test_converts_km_to_mi(): void {
		$distance = Wcsdm_Distance::from_km( '1.60934' );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );
		
		$this->assertEquals( '1', $distance->in_mi() );
	}

	public function test_ceiling_rounds_up(): void {
		$distance = Wcsdm_Distance::from_m( '5100' ); // 5.1 km.
		$distance->set_ceiling( true );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );

		$this->assertEquals( '6', $distance->in_km() );
		$this->assertEquals( '5100', $distance->in_m() );
	}

	public function test_invalid_unit_throws_exception(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid unit type!' );

		new Wcsdm_Distance( '1', 'nope' );
	}

	public function test_to_array_returns_raw_data(): void {
		$distance = Wcsdm_Distance::from_km( '12.345' );
		$distance->set_ceiling( true );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );

		$this->assertSame(
			array(
				'number' => '12.345',
				'unit'   => 'km',
			),
			$distance->to_array()
		);
	}

	public function test_from_array_round_trip_preserves_data(): void {
		$original = Wcsdm_Distance::from_mi( '1' );
		$data     = $original->to_array();

		$distance = Wcsdm_Distance::from_array( $data );
		$distance->set_formatter( array( 'Test_Wcsdm_Distance', 'formatter' ) );

		$this->assertSame( $data, $distance->to_array() );
		$this->assertEquals( '1', $distance->in_mi() );
		$this->assertEquals( '1.61', $distance->in_km() );
	}

	public function test_from_array_invalid_unit_throws_exception(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid unit type!' );

		Wcsdm_Distance::from_array(
			array(
				'number' => '1',
				'unit'   => 'nope',
			)
		);
	}
}
