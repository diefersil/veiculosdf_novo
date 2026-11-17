<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface Agent_Cars_Adapter {
	public function id();
	public function supports( $url );
	public function trusted_hosts();
	public function request_headers();
	public function user_agent();
	public function listing_urls( $html, $base_url );
	public function vehicle_from_listing( $url );
	public function parse_vehicle( $html, $url );
	public function seller_data( $source_config = array() );
}
