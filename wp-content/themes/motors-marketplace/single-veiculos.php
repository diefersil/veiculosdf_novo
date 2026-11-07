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
$brand = motors_marketplace_vehicle_term_names( $post_id, 'marca' );
$model = motors_marketplace_vehicle_term_names( $post_id, 'modelo' );
$version = motors_marketplace_vehicle_term_names( $post_id, 'versao' );
$vehicle_name = implode( ' ', array_filter( array_merge( $brand, $model ) ) );
$vehicle_name = $vehicle_name ? $vehicle_name : get_the_title( $post_id );
$version_label = implode( ' ', $version );
$has_version_in_name = $version_label && strtolower( substr( $vehicle_name, -strlen( $version_label ) ) ) === strtolower( $version_label );
$single_title = trim( $vehicle_name . ( $version_label && ! $has_version_in_name ? ' ' . $version_label : '' ) );
$overline = $version ? '' : implode( ' · ', array_filter( array( get_post_meta( $post_id, '_cv_motor', true ), get_post_meta( $post_id, '_cv_combustivel', true ) ) ) );
$year_label = motors_marketplace_vehicle_year_label( $post_id );
$mileage_label = get_post_meta( $post_id, '_cv_quilometragem', true ) ? number_format_i18n( (int) get_post_meta( $post_id, '_cv_quilometragem', true ) ) . ' km' : '';
$subtitle = implode( ' · ', array_filter( array( $year_label, $mileage_label ) ) );
$contact_status = isset( $_GET['contato_status'] ) ? sanitize_key( wp_unslash( $_GET['contato_status'] ) ) : '';
?>
<main id="main-content" class="mm-site-main body">
	<article class="mm-container mm-single">
		<?php if ( $overline ) : ?><p class="mm-vehicle-overline"><?php echo esc_html( $overline ); ?></p><?php endif; ?>
		<h1 class="mm-single-title"><?php echo esc_html( $single_title ); ?></h1>
		<div class="mm-single-subtitle"><?php echo esc_html( $subtitle ); ?></div>
		<div class="mm-single-layout">
			<div>
				<div class="mm-gallery">
					<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large' ); elseif ( $gallery ) : echo wp_get_attachment_image( $gallery[0], 'large' ); endif; ?>
					<?php foreach ( $gallery as $index => $image_id ) : if ( 0 === $index ) { continue; } echo wp_get_attachment_image( $image_id, 'large' ); endforeach; ?>
				</div>
				<section class="mm-details"><h2>Detalhes do veículo</h2><p class="mm-price"><?php echo $price ? 'R$ ' . esc_html( number_format_i18n( (float) $price, 0 ) ) : 'Consulte o preço'; ?></p><dl class="mm-facts"><?php foreach ( $fields as $label => $value ) : if ( '' === (string) $value ) { continue; } ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endforeach; ?></dl><div class="mm-description"><?php the_content(); ?></div></section>
			</div>
			<aside class="mm-contact-card" id="contato-anunciante">
				<h2>Tenho interesse</h2>
				<p>Fale com o anunciante para saber mais sobre este veículo.</p>
				<?php if ( 'sent' === $contact_status ) : ?><p class="mm-contact-notice" role="status">Mensagem enviada. O anunciante poderá responder pelo e-mail informado.</p><?php elseif ( 'error' === $contact_status ) : ?><p class="mm-contact-error" role="alert">Não foi possível enviar. Confira os dados e tente novamente.</p><?php endif; ?>
				<?php if ( $phone ) : ?><a class="mm-button mm-contact-option" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">Contato por telefone</a><?php endif; ?>
				<?php if ( $whatsapp ) : ?><a class="mm-button mm-contact-option" href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener">Contato por WhatsApp</a><?php endif; ?>
				<h3>Contato por formulário</h3>
				<form class="mm-contact-form" method="post" action="<?php echo esc_url( get_permalink( $post_id ) . '#contato-anunciante' ); ?>">
					<?php wp_nonce_field( 'cv_vehicle_contact_' . $post_id, 'cv_vehicle_contact_nonce' ); ?>
					<input type="hidden" name="vehicle_id" value="<?php echo (int) $post_id; ?>">
					<label>Nome<input type="text" name="cv_contact_name" autocomplete="name" required></label>
					<label>Email<input type="email" name="cv_contact_email" autocomplete="email" required></label>
					<label>Fone/WhatsApp<input type="tel" name="cv_contact_phone" autocomplete="tel"></label>
					<label>Mensagem<textarea name="cv_contact_message" rows="4" required></textarea></label>
					<button class="mm-button" type="submit" name="cv_vehicle_contact_submit" value="1">Enviar mensagem</button>
				</form>
			</aside>
		</div>
	</article>
</main>
<?php get_footer(); ?>
