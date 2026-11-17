<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/interface-agent-cars-adapter.php';

final class Agent_Cars_Casa_Do_Automovel_Adapter implements Agent_Cars_Adapter {
	public function id() { return 'casadoautomovel'; }
	public function supports( $url ) { return in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), array( 'casadoautomovel.com.br', 'www.casadoautomovel.com.br' ), true ); }
	public function trusted_hosts() { return array( 'casadoautomovel.com.br', 'www.casadoautomovel.com.br', 's3.amazonaws.com' ); }
	public function request_headers() { return array(); }
	public function user_agent() { return ''; }

	public function seller_data( $source_config = array() ) {
		return array_merge( array(
			'email' => 'uesleiweb@casadoautomovel.com.br',
			'login' => 'casadoautomovel',
			'name' => 'Casa do Automóvel',
			'first_name' => 'Casa do',
			'last_name' => 'Automóvel',
			'website' => 'https://casadoautomovel.com.br',
			'bio' => 'Loja de veículos seminovos localizada na Cidade do Automóvel, em Brasília, Distrito Federal.',
			'whatsapp' => '(61) 98139-1492',
			'phone' => '(61) 4141-1836',
			'address' => 'SCIA Quadra 15 Conjunto 7, Lote 07/08',
			'city' => 'Brasília',
			'state' => 'DF',
			'maps_rating' => '',
		), isset( $source_config['seller'] ) && is_array( $source_config['seller'] ) ? $source_config['seller'] : array() );
	}

	public function listing_urls( $html, $base_url ) {
		$dom = $this->dom( $html );
		$urls = array();
		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			$href = trim( $anchor->getAttribute( 'href' ) );
			if ( preg_match( '~/seminovo/(\d+)~', $href ) ) {
				$url = Agent_Cars::absolute_url( $href, $base_url );
				if ( $url && $this->supports( $url ) && ! in_array( $url, $urls, true ) ) { $urls[] = $url; }
			}
		}
		return $urls;
	}
	public function vehicle_from_listing( $url ) { return null; }

	public function parse_vehicle( $html, $url ) {
		$dom = $this->dom( $html );
		$xpath = new DOMXPath( $dom );
		$title = trim( $xpath->evaluate( 'string(//h1[1])' ) );
		if ( ! $title ) { $title = trim( $xpath->evaluate( 'string(//title)' ) ); $title = preg_replace( '/\s*\|.*$/u', '', $title ); }
		if ( ! $title ) { return new WP_Error( 'agent_cars_title', 'Título não encontrado.' ); }
		$vehicle = array( 'url' => $url, 'external_id' => '', 'title' => sanitize_text_field( $title ), 'price' => 0, 'fields' => array(), 'images' => array(), 'description' => '', 'brand' => '', 'model' => '', 'version' => '', 'style' => '', 'vehicle_type' => 'Carros' );
		if ( preg_match( '~/seminovo/(\d+)~', $url, $match ) ) { $vehicle['external_id'] = $match[1]; }
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( preg_match( '/Por apenas\s*:?\s*R\$\s*([\d.]+,\d{2})/iu', $text, $match ) ) { $vehicle['price'] = (float) str_replace( ',', '.', str_replace( '.', '', $match[1] ) ); }
		$labels = array( 'Ano' => 'ano_modelo', 'Quilometragem' => 'quilometragem', 'Cor' => 'cor', 'Combustível' => 'combustivel', 'Motor' => 'motor', 'Câmbio' => 'cambio', 'Portas' => 'portas' );
		foreach ( $labels as $label => $key ) {
			foreach ( $xpath->query( '//h2|//h3|//h4|//dt' ) as $node ) {
				if ( 0 !== strcasecmp( trim( $node->textContent ), $label ) ) { continue; }
				$value_node = $node->nextSibling;
				while ( $value_node && XML_ELEMENT_NODE !== $value_node->nodeType && '' === trim( $value_node->textContent ) ) { $value_node = $value_node->nextSibling; }
				$value = $value_node ? trim( $value_node->textContent ) : '';
				if ( $value ) { $vehicle['fields'][ $key ] = sanitize_text_field( $value ); break; }
			}
		}
		if ( preg_match( '/Quilometragem\s*([\d.]+)\s*KM/iu', $text, $match ) ) { $vehicle['fields']['quilometragem'] = preg_replace( '/\D/', '', $match[1] ); }
		// A loja informa apenas um campo "Ano"; não invente o ano de fabricação.
		$vehicle['fields']['ano_fabricacao'] = '';
		if ( preg_match( '/<h3[^>]*>\s*Marca\s*<\/h3>\s*<p[^>]*>(.*?)<\/p>/isu', $html, $match ) ) { $vehicle['brand'] = sanitize_text_field( wp_strip_all_tags( $match[1] ) ); }
		if ( preg_match( '/<h3[^>]*>\s*Observações\s*<\/h3>\s*<p[^>]*>(.*?)<\/p>/isu', $html, $match ) ) { $vehicle['description'] = trim( wp_strip_all_tags( $match[1] ) ); }
		$vehicle['model'] = $this->infer_model( $vehicle['title'], $vehicle['brand'] );
		$vehicle['version'] = trim( preg_replace( '/^' . preg_quote( $vehicle['brand'], '/' ) . '\s*' . preg_quote( $vehicle['model'], '/' ) . '/iu', '', $vehicle['title'] ) );
		$vehicle['style'] = $this->infer_style( $vehicle['title'] );
		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			$src = trim( $anchor->getAttribute( 'href' ) );
			if ( ! preg_match( '~s3\.amazonaws\.com/.+/\d+-[^/]+\.(?:jpe?g|png|webp)~i', $src ) ) { continue; }
			$src = Agent_Cars::absolute_url( $src, $url );
			if ( $src && ! in_array( $src, $vehicle['images'], true ) ) { $vehicle['images'][] = $src; }
		}
		usort( $vehicle['images'], function( $a, $b ) { preg_match( '~/([0-9]+)-~', wp_parse_url( $a, PHP_URL_PATH ), $left ); preg_match( '~/([0-9]+)-~', wp_parse_url( $b, PHP_URL_PATH ), $right ); return ( (int) ( $left[1] ?? 999 ) ) <=> ( (int) ( $right[1] ?? 999 ) ); } );
		$vehicle['images'] = array_slice( $vehicle['images'], 0, 12 );
		return $vehicle;
	}

	private function infer_model( $title, $brand ) {
		$without_brand = preg_replace( '/^' . preg_quote( $brand, '/' ) . '\s*/iu', '', $title );
		$parts = preg_split( '/\s+/', trim( $without_brand ) );
		return implode( ' ', array_slice( $parts, 0, min( 2, count( $parts ) ) ) );
	}

	private function infer_style( $title ) {
		$title = strtolower( $title );
		$styles = array( 'picape' => array( 'picape', 'montana', 'ranger', 'hilux', 's10', 'rampage', 'toro' ), 'suv' => array( 'suv', 'tiggo', 'dolphin mini' ), 'moto' => array( 'gs ', 'f750', 'cc ' ), 'sedan' => array( 'cruze', 'sedan', 'corolla', 'civic', 'onix plus' ), 'hatch' => array( 'hatch', 'onix', 'polo', 'hb20' ) );
		foreach ( $styles as $style => $needles ) { foreach ( $needles as $needle ) { if ( false !== strpos( $title, $needle ) ) { return ucfirst( $style ); } } }
		return 'Carro';
	}

	private function dom( $html ) {
		$previous = libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $dom;
	}
}
