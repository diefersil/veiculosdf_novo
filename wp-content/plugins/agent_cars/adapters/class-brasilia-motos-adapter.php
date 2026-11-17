<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/interface-agent-cars-adapter.php';

final class Agent_Cars_Brasilia_Motos_Adapter implements Agent_Cars_Adapter {
	private $listing_vehicles = array();

	public function id() { return 'brasiliamotos'; }
	public function supports( $url ) { return in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), array( 'brasiliamotos.com.br', 'www.brasiliamotos.com.br' ), true ); }
	public function trusted_hosts() { return array( 'brasiliamotos.com.br', 'www.brasiliamotos.com.br', 's3.carro57.com.br', 'cdn.revendamais.com.br' ); }
	public function request_headers() {
		return array(
			'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
			'Referer' => 'https://brasiliamotos.com.br/',
		);
	}
	public function user_agent() { return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'; }

	public function seller_data( $source_config = array() ) {
		return array_merge( array(
			'email' => 'brasiliamotos61@gmail.com',
			'login' => 'brasiliamotos',
			'name' => 'Brasília Motos',
			'first_name' => 'Brasília',
			'last_name' => 'Motos',
			'website' => 'https://brasiliamotos.com.br',
			'bio' => 'Loja especializada na compra e venda de motocicletas em Brasília, Distrito Federal.',
			'whatsapp' => '(61) 99683-6161',
			'phone' => '(61) 99683-6161',
			'address' => 'QMSW 2 Bloco C, Nº 08, Loja, Setor Sudoeste, CEP 70680-209',
			'city' => 'Brasília',
			'state' => 'DF',
			'maps_rating' => '',
		), isset( $source_config['seller'] ) && is_array( $source_config['seller'] ) ? $source_config['seller'] : array() );
	}

	public function listing_urls( $html, $base_url ) {
		$this->listing_vehicles = array();
		$dom = $this->dom( $html );
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//div[contains(concat(" ",normalize-space(@class)," ")," carro ")]' ) as $card ) {
			$anchor = $xpath->query( './/a[contains(concat(" ",normalize-space(@class)," ")," big-inf2 ")]', $card )->item( 0 );
			if ( ! $anchor ) { continue; }
			$url = esc_url_raw( Agent_Cars::absolute_url( $anchor->getAttribute( 'href' ), $base_url ) );
			if ( ! $url || ! $this->supports( $url ) ) { continue; }
			$title = trim( $xpath->evaluate( 'string(.//div[contains(concat(" ",normalize-space(@class)," ")," first-name ")])', $card ) );
			$version = trim( $xpath->evaluate( 'string(.//div[contains(concat(" ",normalize-space(@class)," ")," last-name ")])', $card ) );
			if ( ! $title ) { $title = trim( $anchor->getAttribute( 'title' ) ); $title = preg_replace( '/^Ver mais detalhes sobre o veículo\s*/iu', '', $title ); }
			if ( ! $title ) { continue; }
			$path_parts = array_values( array_filter( explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) ), 'strlen' ) );
			$brand = isset( $path_parts[1] ) ? strtoupper( str_replace( '-', ' ', rawurldecode( $path_parts[1] ) ) ) : '';
			$model_slug = isset( $path_parts[2] ) ? rawurldecode( $path_parts[2] ) : '';
			$model_path = strtoupper( str_replace( '-', ' ', $model_slug ) );
			if ( 'KAWASAKI' === $brand && 0 === stripos( $model_slug, 'kawasak' ) ) { $model_path = strtoupper( substr( $model_slug, 7 ) ); }
			$path_version = isset( $path_parts[3] ) && ! preg_match( '/\.html$/i', $path_parts[3] ) ? strtoupper( str_replace( '-', ' ', rawurldecode( $path_parts[3] ) ) ) : '';
			$title_upper = strtoupper( trim( $title ) );
			if ( $brand && 0 === strpos( $title_upper, $brand ) ) { $title_upper = trim( substr( $title_upper, strlen( $brand ) ) ); }
			elseif ( 'KAWASAKI' === $brand && 0 === strpos( $title_upper, 'KAWASAK' ) ) { $title_upper = trim( substr( $title_upper, 7 ) ); }
			$model_path = preg_replace( '/\s+/', ' ', trim( $model_path ) );
			$model = $model_path && 0 === strpos( $title_upper, $model_path ) ? $model_path : $title_upper;
			if ( 'KAWASAKI' === $brand && 'NINJA' === $model_path ) { $model = 'NINJA'; }
			if ( $path_version ) { $version = $path_version; }
			elseif ( $model && 0 === strpos( $title_upper, $model ) ) { $version = trim( $version ? $version : substr( $title_upper, strlen( $model ) ) ); }
			$external_id = '';
			if ( preg_match( '/-(\d+)\.html$/i', (string) wp_parse_url( $url, PHP_URL_PATH ), $match ) ) { $external_id = $match[1]; }
			if ( ! $external_id ) { continue; }
			$year_text = trim( $xpath->evaluate( 'string(.//div[contains(concat(" ",normalize-space(@class)," ")," year ")])', $card ) );
			$year_fabrication = '';
			$year_model = '';
			if ( preg_match( '/(\d{4})\s*\/\s*(\d{4})/', $year_text, $match ) ) { $year_fabrication = $match[1]; $year_model = $match[2]; }
			$price_text = trim( $xpath->evaluate( 'string(.//h3[contains(concat(" ",normalize-space(@class)," ")," preco ")])', $card ) );
			$price = 0;
			if ( preg_match( '/([\d.]+,\d{2})/', $price_text, $match ) ) { $price = (float) str_replace( ',', '.', str_replace( '.', '', $match[1] ) ); }
			$options = array();
			foreach ( $xpath->query( './/div[contains(concat(" ",normalize-space(@class)," ")," box-opicionais-home ")]//span[contains(concat(" ",normalize-space(@class)," ")," text-none ")]', $card ) as $option ) { $options[] = trim( $option->textContent ); }
			$image = $xpath->query( './/img[@data-src or @src]', $card )->item( 0 );
			$image_url = $image ? ( $image->getAttribute( 'data-src' ) ? $image->getAttribute( 'data-src' ) : $image->getAttribute( 'src' ) ) : '';
			if ( $image_url && 0 === strpos( $image_url, '/' ) ) { $image_url = Agent_Cars::absolute_url( $image_url, $base_url ); }
			$transmission = isset( $options[0] ) ? sanitize_text_field( $options[0] ) : '';
			if ( ! in_array( strtolower( $transmission ), array( 'manual', 'automático', 'automatico', 'cvt', 'automatizado' ), true ) ) { $transmission = ''; }
			$fields = array(
				'ano_fabricacao' => $year_fabrication,
				'ano_modelo' => $year_model,
				'quilometragem' => isset( $options[1] ) ? preg_replace( '/\D/', '', $options[1] ) : '',
				'cambio' => $transmission,
				'combustivel' => isset( $options[2] ) ? sanitize_text_field( $options[2] ) : '',
			);
			$normalized_title = $year_text ? $year_text . ' ' . $brand . ' ' . $model . ( $version ? ' ' . $version : '' ) : $brand . ' ' . $model . ( $version ? ' ' . $version : '' );
			$this->listing_vehicles[ $url ] = array(
				'url' => $url,
				'external_id' => $external_id,
				'title' => sanitize_text_field( trim( $normalized_title ) ),
				'price' => $price,
				'fields' => $fields,
				'images' => $image_url ? array( $image_url ) : array(),
				'description' => '',
				'brand' => sanitize_text_field( $brand ),
				'model' => sanitize_text_field( $model ),
				'version' => sanitize_text_field( $version ),
				'style' => $this->infer_style( $title_upper ),
				'vehicle_type' => 'Motos',
			);
		}
		return array_keys( $this->listing_vehicles );
	}

	public function parse_vehicle( $html, $url ) {
		if ( isset( $this->listing_vehicles[ $url ] ) ) { return $this->listing_vehicles[ $url ]; }
		return new WP_Error( 'agent_cars_moto_listing_data', 'O anúncio não foi localizado na listagem múltipla da Brasília Motos.' );
	}
	public function vehicle_from_listing( $url ) { return $this->listing_vehicles[ $url ] ?? null; }

	private function infer_style( $title ) {
		if ( preg_match( '/\b(nmax|pcx|biz|neo|elite|fluo|scooter)\b/i', $title ) ) { return 'Scooter'; }
		if ( preg_match( '/\b(bros|tiger|adv|xre|lander|tenere|rally|trail)\b/i', $title ) ) { return 'Trail'; }
		if ( preg_match( '/\b(yzf|r3|cbr|ninja|zx[- ]?\d|esportiva)\b/i', $title ) ) { return 'Esportiva'; }
		if ( preg_match( '/\b(harley|meteor|classic|shadow|custom)\b/i', $title ) ) { return 'Custom'; }
		return 'Street';
	}

	private function dom( $html ) {
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		if ( ! preg_match( '//u', $html ) && function_exists( 'mb_convert_encoding' ) ) { $html = mb_convert_encoding( $html, 'UTF-8', 'Windows-1252' ); }
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $dom;
	}
}
