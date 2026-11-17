<?php
/** Motors Marketplace theme setup. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function motors_marketplace_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 280, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus( array( 'primary' => 'Menu principal', 'footer' => 'Menu do rodapé' ) );
}
add_action( 'after_setup_theme', 'motors_marketplace_setup' );

function motors_marketplace_assets() {
	$theme_version = wp_get_theme()->get( 'Version' );
	$css_path = get_theme_file_path( '/style.css' );
	wp_enqueue_style( 'motors-marketplace-google-sans', 'https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'motors-marketplace', get_theme_file_uri( '/style.css' ), array( 'motors-marketplace-google-sans' ), file_exists( $css_path ) ? (string) filemtime( $css_path ) : $theme_version );
	wp_enqueue_script( 'motors-marketplace-menu', get_theme_file_uri( '/assets/menu.js' ), array(), $theme_version, true );
	if ( is_singular( 'veiculos' ) ) {
		$gallery_path = get_theme_file_path( '/assets/gallery.js' );
		wp_enqueue_script( 'motors-marketplace-gallery', get_theme_file_uri( '/assets/gallery.js' ), array(), file_exists( $gallery_path ) ? (string) filemtime( $gallery_path ) : $theme_version, true );
	}
}
add_action( 'wp_enqueue_scripts', 'motors_marketplace_assets' );

function motors_marketplace_flush_rewrites() {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'motors_marketplace_flush_rewrites' );

function motors_marketplace_vehicle_term_names( $post_id, $taxonomy ) {
	$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}

function motors_marketplace_vehicle_year_label( $post_id ) {
	$manufacture_year = get_post_meta( $post_id, '_cv_ano_fabricacao', true );
	$model_year       = get_post_meta( $post_id, '_cv_ano_modelo', true );
	$years            = array();

	foreach ( array( $manufacture_year, $model_year ) as $year ) {
		if ( $year ) {
			$years[] = substr( (string) $year, -2 );
		}
	}

	return implode( '/', $years );
}

function motors_marketplace_vehicle_type_link( $category ) {
	$terms = get_terms( array( 'taxonomy' => 'tipo_veiculo', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$slug = sanitize_title( remove_accents( $term->slug . ' ' . $term->name ) );
			$matches = ( 'carros' === $category && preg_match( '/carro|automovel|auto/', $slug ) )
				|| ( 'motos' === $category && preg_match( '/moto/', $slug ) )
				|| ( 'caminhoes' === $category && preg_match( '/caminh|truck/', $slug ) );
			if ( $matches ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}
	}
	return get_post_type_archive_link( 'veiculos' ) ?: home_url( '/?post_type=veiculos' );
}

function motors_marketplace_title_case_vehicle_words( $label ) {
	return preg_replace_callback( '/[\p{L}\p{N}]+/u', function( $matches ) {
		$word = $matches[0];
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $word, 'UTF-8' ) <= 3 ) { return $word; }
		if ( ! function_exists( 'mb_convert_case' ) ) { return ucfirst( strtolower( $word ) ); }
		return mb_convert_case( mb_strtolower( $word, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
	}, (string) $label );
}

function motors_marketplace_clean_vehicle_card_label( $label, $transmission = '' ) {
	$label = (string) $label;
	$label = preg_replace( '/\b(?:19|20)\d{2}(?:\s*[\/-]\s*(?:(?:19|20)?\d{2}))?\b/u', ' ', $label );
	$label = preg_replace( '/\b[2-6]\s*(?:portas?|p)\b/iu', ' ', $label );
	$label = preg_replace( '/\b(?:manual|autom(?:[a\x{00e1}]tic[oa]|atizad[oa])|cvt|dct|flex|gasolina|etanol|(?:a|\x{00e1})lcool|diesel|gnv|h(?:i|\x{00ed})brido|el(?:e|\x{00e9})tric[oa])\b/iu', ' ', $label );
	if ( preg_match( '/manual/i', (string) $transmission ) ) {
		$label = preg_replace( '/\bMT\b(?![- ]?\d)/i', ' ', $label );
	} elseif ( preg_match( '/autom|cvt|dct/i', (string) $transmission ) ) {
		$label = preg_replace( '/\b(?:AT|AUT\.?)\b/i', ' ', $label );
	}
	$label = preg_replace( '/\s*[-\x{2013}\x{2014}|\x{00b7},\/]+\s*/u', ' ', $label );
	return motors_marketplace_title_case_vehicle_words( trim( preg_replace( '/\s{2,}/u', ' ', $label ) ) );
}

function motors_marketplace_format_vehicle_title( $title, $post_id = 0 ) {
	if ( 'veiculos' !== get_post_type( $post_id ) ) { return $title; }
	return motors_marketplace_clean_vehicle_card_label( $title, get_post_meta( $post_id, '_cv_cambio', true ) );
}
add_filter( 'the_title', 'motors_marketplace_format_vehicle_title', 20, 2 );

function motors_marketplace_vehicle_card( $post_id ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$title = get_the_title( $post_id );
	$link = get_permalink( $post_id );
	$image = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( ! $image ) {
		$gallery = (array) get_post_meta( $post_id, '_cv_vehicle_gallery', true );
		$image = ! empty( $gallery[0] ) ? wp_get_attachment_image_url( absint( $gallery[0] ), 'full' ) : '';
	}
	$brand = motors_marketplace_vehicle_term_names( $post_id, 'marca' );
	$model = motors_marketplace_vehicle_term_names( $post_id, 'modelo' );
	$transmission = get_post_meta( $post_id, '_cv_cambio', true );
	$vehicle_name = motors_marketplace_clean_vehicle_card_label( implode( ' ', array_filter( array_merge( $brand, $model ) ) ), $transmission );
	$vehicle_name = $vehicle_name ? $vehicle_name : motors_marketplace_clean_vehicle_card_label( $title, $transmission );
	$version = motors_marketplace_vehicle_term_names( $post_id, 'versao' );
	$overline = $version ? implode( ' ', $version ) : implode( ' · ', array_filter( array(
		get_post_meta( $post_id, '_cv_motor', true ),
		get_post_meta( $post_id, '_cv_combustivel', true ),
	) ) );
	$overline = motors_marketplace_clean_vehicle_card_label( $overline, $transmission );
	$year = motors_marketplace_vehicle_year_label( $post_id );
	$mileage = get_post_meta( $post_id, '_cv_quilometragem', true );
	$price = get_post_meta( $post_id, '_cv_preco', true );
	?>
	<article class="mm-vehicle-card">
		<a class="mm-vehicle-photo" href="<?php echo esc_url( $link ); ?>">
			<?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php else : ?><span>Foto do veículo</span><?php endif; ?>
		</a>
		<div class="mm-vehicle-card-content">
			<p class="mm-vehicle-overline"><?php echo esc_html( $overline ); ?></p>
			<h2><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $vehicle_name ); ?></a></h2>
			<div class="mm-vehicle-specs"><?php if ( $year ) : ?><span><?php echo esc_html( $year ); ?></span><?php endif; ?><?php if ( $mileage ) : ?><span><?php echo esc_html( number_format_i18n( (int) $mileage ) ); ?> km</span><?php endif; ?></div>
			<?php if ( $price ) : ?><strong class="mm-vehicle-price">R$ <?php echo esc_html( number_format_i18n( (float) $price, 0 ) ); ?></strong><?php endif; ?>
		</div>
	</article>
	<?php
}
