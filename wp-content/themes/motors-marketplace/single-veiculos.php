<?php
get_header();
the_post();
$post_id = get_the_ID();
$gallery = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_cv_vehicle_gallery', true ) ) ) );
$fields = array(
	'Ano de fabricação' => get_post_meta( $post_id, '_cv_ano_fabricacao', true ),
	'Ano modelo' => get_post_meta( $post_id, '_cv_ano_modelo', true ),
	'Quilometragem' => get_post_meta( $post_id, '_cv_quilometragem', true ) ? number_format_i18n( (int) get_post_meta( $post_id, '_cv_quilometragem', true ) ) . ' km' : '',
	'Motor' => get_post_meta( $post_id, '_cv_motor', true ),
	'Combustível' => get_post_meta( $post_id, '_cv_combustivel', true ),
	'Câmbio' => get_post_meta( $post_id, '_cv_cambio', true ),
	'Cor' => get_post_meta( $post_id, '_cv_cor', true ),
);
$price = get_post_meta( $post_id, '_cv_preco', true );
$author_id = (int) get_post_field( 'post_author', $post_id );
$whatsapp = get_user_meta( $author_id, 'cv_whatsapp', true );
$phone = get_user_meta( $author_id, 'cv_telefone', true );
?>
<main id="main-content" class="mm-site-main body">
	<article class="mm-container mm-single">
		<p class="mm-vehicle-overline"><?php echo esc_html( implode( ' ', array_filter( array_merge( wp_get_object_terms( $post_id, 'marca', array( 'fields' => 'names' ) ), wp_get_object_terms( $post_id, 'modelo', array( 'fields' => 'names' ) ), wp_get_object_terms( $post_id, 'versao', array( 'fields' => 'names' ) ) ) ) ) ); ?></p>
		<h1 class="mm-single-title"><?php the_title(); ?></h1>
		<div class="mm-single-subtitle"><?php echo esc_html( implode( ' · ', array_filter( array( get_post_meta( $post_id, '_cv_ano_modelo', true ), get_post_meta( $post_id, '_cv_quilometragem', true ) ? number_format_i18n( (int) get_post_meta( $post_id, '_cv_quilometragem', true ) ) . ' km' : '' ) ) ) ); ?></div>
		<div class="mm-single-layout"><div>
			<div class="mm-gallery">
				<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large' ); elseif ( $gallery ) : echo wp_get_attachment_image( $gallery[0], 'large' ); endif; ?>
				<?php foreach ( $gallery as $index => $image_id ) : if ( 0 === $index ) { continue; } echo wp_get_attachment_image( $image_id, 'large' ); endforeach; ?>
			</div>
			<section class="mm-details"><h2>Detalhes do veículo</h2><p class="mm-price"><?php echo $price ? 'R$ ' . esc_html( number_format_i18n( (float) $price, 0 ) ) : 'Consulte o preço'; ?></p><dl class="mm-facts"><?php foreach ( $fields as $label => $value ) : if ( '' === (string) $value ) { continue; } ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endforeach; ?></dl><div class="mm-description"><?php the_content(); ?></div></section>
		</div><aside class="mm-contact-card"><h2>Tenho interesse</h2><p>Fale com o anunciante para saber mais sobre este veículo.</p><?php if ( $whatsapp ) : ?><a class="mm-button" href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a><?php elseif ( $phone ) : ?><a class="mm-button" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">Ligar para o anunciante</a><?php else : ?><a class="mm-button" href="<?php echo esc_url( home_url( '/minha-conta/' ) ); ?>">Entrar em contato</a><?php endif; ?></aside></div>
	</article>
</main>
<?php get_footer(); ?>
