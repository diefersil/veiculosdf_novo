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
	wp_enqueue_style( 'motors-marketplace', get_theme_file_uri( '/style.css' ), array(), file_exists( $css_path ) ? (string) filemtime( $css_path ) : $theme_version );
	wp_enqueue_script( 'motors-marketplace-menu', get_theme_file_uri( '/assets/menu.js' ), array(), $theme_version, true );
}
add_action( 'wp_enqueue_scripts', 'motors_marketplace_assets' );

function motors_marketplace_flush_rewrites() {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'motors_marketplace_flush_rewrites' );

function motors_marketplace_vehicle_card( $post_id ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$title = get_the_title( $post_id );
	$link = get_permalink( $post_id );
	$image = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( ! $image ) {
		$gallery = (array) get_post_meta( $post_id, '_cv_vehicle_gallery', true );
		$image = ! empty( $gallery[0] ) ? wp_get_attachment_image_url( absint( $gallery[0] ), 'large' ) : '';
	}
	$brand = wp_get_object_terms( $post_id, 'marca', array( 'fields' => 'names' ) );
	$model = wp_get_object_terms( $post_id, 'modelo', array( 'fields' => 'names' ) );
	$year = get_post_meta( $post_id, '_cv_ano_modelo', true );
	$mileage = get_post_meta( $post_id, '_cv_quilometragem', true );
	$price = get_post_meta( $post_id, '_cv_preco', true );
	?>
	<article class="mm-vehicle-card">
		<a class="mm-vehicle-photo" href="<?php echo esc_url( $link ); ?>">
			<?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php else : ?><span>Foto do veículo</span><?php endif; ?>
		</a>
		<div class="mm-vehicle-card-content">
			<p class="mm-vehicle-overline"><?php echo esc_html( implode( ' ', array_filter( array_merge( is_array( $brand ) ? $brand : array(), is_array( $model ) ? $model : array() ) ) ) ); ?></p>
			<h2><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></h2>
			<div class="mm-vehicle-specs"><?php if ( $year ) : ?><span><?php echo esc_html( $year ); ?></span><?php endif; ?><?php if ( $mileage ) : ?><span><?php echo esc_html( number_format_i18n( (int) $mileage ) ); ?> km</span><?php endif; ?></div>
			<?php if ( $price ) : ?><strong class="mm-vehicle-price">R$ <?php echo esc_html( number_format_i18n( (float) $price, 0 ) ); ?></strong><?php endif; ?>
		</div>
	</article>
	<?php
}
