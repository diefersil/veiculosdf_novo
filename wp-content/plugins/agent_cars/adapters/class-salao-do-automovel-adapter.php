<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/interface-agent-cars-adapter.php';

final class Agent_Cars_Salao_Do_Automovel_Adapter implements Agent_Cars_Adapter {
	public function id() { return 'salaodoauto'; }
	public function supports( $url ) { return in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), array( 'salaodoauto.com.br', 'www.salaodoauto.com.br' ), true ); }
	public function trusted_hosts() { return array( 'salaodoauto.com.br', 'www.salaodoauto.com.br', 's3.amazonaws.com' ); }
	public function request_headers() { return array(); }
	public function user_agent() { return ''; }

	public function seller_data( $source_config = array() ) {
		return array_merge( array(
			'email' => 'cfemveiculosdf@gmail.com',
			'login' => 'salaodoautomovel',
			'name' => 'Salão do Automóvel',
			'first_name' => 'Salão do',
			'last_name' => 'Automóvel',
			'website' => 'https://salaodoauto.com.br',
			'bio' => 'Revenda de veículos seminovos na Cidade do Automóvel, em Brasília, Distrito Federal.',
			'whatsapp' => '(61) 3036-4040',
			'phone' => '(61) 3036-4040',
			'address' => 'SCIA Quadra 15, Conjunto 09, Loja 03, Cidade do Automóvel, CEP 71250-045',
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
		if ( ! $title ) { $title = trim( $xpath->evaluate( 'string(//h2[contains(@class,"fw-bold")][1])' ) ); }
		if ( ! $title ) { return new WP_Error( 'agent_cars_title', 'Título do anúncio do Salão do Automóvel não encontrado.' ); }
		$brand = trim( $xpath->evaluate( 'string((//img[@title])[1]/@title)' ) );
		$vehicle = array( 'url' => $url, 'external_id' => '', 'title' => sanitize_text_field( $title ), 'price' => 0, 'fields' => array(), 'images' => array(), 'description' => '', 'brand' => sanitize_text_field( $brand ), 'model' => '', 'version' => '', 'style' => '', 'vehicle_type' => 'Carros' );
		if ( preg_match( '~/seminovo/(\d+)~', $url, $match ) ) { $vehicle['external_id'] = $match[1]; }
		$labels = array( 'Ano/Modelo' => 'years', 'Quilometragem' => 'quilometragem', 'Cor' => 'cor', 'Combustível' => 'combustivel', 'Motor' => 'motor', 'Câmbio' => 'cambio', 'Portas' => 'portas', 'Por apenas' => 'price' );
		foreach ( $xpath->query( '//div[contains(concat(" ",normalize-space(@class)," ")," ic-label ")]' ) as $label_node ) {
			$label = trim( $label_node->textContent );
			if ( ! isset( $labels[ $label ] ) ) { continue; }
			$value = trim( $xpath->evaluate( 'string(following-sibling::div[contains(concat(" ",normalize-space(@class)," ")," ic-value ")][1])', $label_node ) );
			$key = $labels[ $label ];
			if ( 'price' === $key ) {
				if ( preg_match( '/R\$\s*([\d.]+,\d{2})/u', $value, $match ) ) { $vehicle['price'] = (float) str_replace( ',', '.', str_replace( '.', '', $match[1] ) ); }
			} elseif ( 'years' === $key ) {
				if ( preg_match( '/(\d{4})\s*\/\s*(\d{4})/', $value, $match ) ) {
					$vehicle['fields']['ano_fabricacao'] = $match[1];
					$vehicle['fields']['ano_modelo'] = $match[2];
				} elseif ( preg_match( '/\d{4}/', $value, $match ) ) {
					$vehicle['fields']['ano_fabricacao'] = $match[0];
					$vehicle['fields']['ano_modelo'] = $match[0];
				}
			} elseif ( 'quilometragem' === $key ) {
				$vehicle['fields'][ $key ] = preg_replace( '/\D/', '', $value );
			} else {
				$vehicle['fields'][ $key ] = sanitize_text_field( $value );
			}
		}
		if ( ! $vehicle['brand'] && preg_match( '/^([A-ZÀ-Ü][A-ZÀ-Ü -]+?)\s+[A-Z0-9]/u', strtoupper( $title ), $match ) ) { $vehicle['brand'] = trim( $match[1] ); }
		$model_text = trim( preg_replace( '/^' . preg_quote( $vehicle['brand'], '/' ) . '\s*/iu', '', $title ) );
		$parts = preg_split( '/\s+/', $model_text );
		$vehicle['model'] = array_shift( $parts );
		$vehicle['version'] = trim( implode( ' ', $parts ) );
		$vehicle['style'] = $this->infer_style( $title );
		$complement = trim( $xpath->evaluate( 'string(//b[preceding-sibling::text()[contains(.,"Complemento")]][1])' ) );
		if ( $complement ) { $vehicle['description'] = sanitize_text_field( $complement ); }
		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			$src = trim( $anchor->getAttribute( 'href' ) );
			if ( ! preg_match( '~s3\.amazonaws\.com/.+-veiculos-imagens/.+/\d+-[^/]*\.(?:jpe?g|png|webp)~i', $src ) ) { continue; }
			if ( ! preg_match( '~/\d+-big/\d+/\d+-~', $src ) ) { continue; }
			if ( ! in_array( $src, $vehicle['images'], true ) ) { $vehicle['images'][] = $src; }
		}
		usort( $vehicle['images'], function( $a, $b ) { preg_match( '~/([0-9]+)-[^/]*\.(?:jpe?g|png|webp)$~i', $a, $left ); preg_match( '~/([0-9]+)-[^/]*\.(?:jpe?g|png|webp)$~i', $b, $right ); return ( (int) ( $left[1] ?? 999 ) ) <=> ( (int) ( $right[1] ?? 999 ) ); } );
		$vehicle['images'] = array_slice( $vehicle['images'], 0, 12 );
		return $vehicle;
	}

	private function infer_style( $title ) {
		$title = strtolower( $title );
		if ( preg_match( '/\b(suv|tiggo|song plus|compass|creta|duster|kicks|renegade|tracker|evoque|territory)\b/u', $title ) ) { return 'SUV'; }
		if ( preg_match( '/\b(picape|s10|ranger|hilux|amarok|toro|montana|frontier|rampage)\b/u', $title ) ) { return 'Picape'; }
		if ( preg_match( '/\b(sedan|328i|corolla|civic|cruze|cronos|onix plus|prisma|virtus|voyage)\b/u', $title ) ) { return 'Sedan'; }
		if ( preg_match( '/\b(hatch|agile|onix|argo|mobi|fiesta|polo|hb20|uno)\b/u', $title ) ) { return 'Hatch'; }
		return 'Outros';
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
