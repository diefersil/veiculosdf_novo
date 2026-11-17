<?php
get_header();
the_post();
$post_id = get_the_ID();
$gallery = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_cv_vehicle_gallery', true ) ) ) );
$gallery = motors_marketplace_order_imported_gallery( $gallery );
$featured_image_id = (int) get_post_thumbnail_id( $post_id );
$gallery_image_ids = array_values( array_unique( array_filter( $gallery ) ) );
if ( $featured_image_id && ! in_array( $featured_image_id, $gallery_image_ids, true ) ) {
	array_unshift( $gallery_image_ids, $featured_image_id );
}
$gallery_image_ids = array_values( array_filter( $gallery_image_ids, function( $image_id ) {
	return wp_get_attachment_image_src( $image_id, 'large' ) && wp_get_attachment_image_url( $image_id, 'full' ) && wp_get_attachment_image_url( $image_id, 'thumbnail' );
} ) );
$year_manufacture = get_post_meta( $post_id, '_cv_ano_fabricacao', true );
$year_model = get_post_meta( $post_id, '_cv_ano_modelo', true );
$year_display = $year_manufacture && $year_model
	? $year_manufacture . '/' . $year_model
	: ( $year_manufacture ? $year_manufacture : $year_model );
$fields = array(
	'Ano' => $year_display,
	'Quilometragem' => get_post_meta( $post_id, '_cv_quilometragem', true ) ? number_format_i18n( (int) get_post_meta( $post_id, '_cv_quilometragem', true ) ) . ' km' : '',
	'Estilo' => implode( ', ', motors_marketplace_vehicle_term_names( $post_id, 'estilo' ) ),
	'Motor' => get_post_meta( $post_id, '_cv_motor', true ),
	'Combustível' => get_post_meta( $post_id, '_cv_combustivel', true ),
	'Câmbio' => get_post_meta( $post_id, '_cv_cambio', true ),
	'Cor' => get_post_meta( $post_id, '_cv_cor', true ),
);
$fields = array_map( 'motors_marketplace_title_case_vehicle_words', $fields );
$price = get_post_meta( $post_id, '_cv_preco', true );
$author_id = (int) get_post_field( 'post_author', $post_id );
$advertiser = get_userdata( $author_id );
$source_url = get_post_meta( $post_id, '_cv_source_url', true );
$advertiser_name = get_post_meta( $post_id, '_cv_advertiser_name', true );
$advertiser_name = $advertiser_name ? $advertiser_name : ( $advertiser && $advertiser->display_name ? $advertiser->display_name : 'Loja Exemplo' );
$whatsapp = get_post_meta( $post_id, '_cv_advertiser_whatsapp', true );
$whatsapp = $whatsapp ? $whatsapp : get_user_meta( $author_id, 'cv_whatsapp', true );
$phone = get_post_meta( $post_id, '_cv_advertiser_phone', true );
$phone = $phone ? $phone : get_user_meta( $author_id, 'cv_telefone', true );
$has_whatsapp = ! empty( $whatsapp );
$has_phone = ! empty( $phone );
$whatsapp = $has_whatsapp ? $whatsapp : '(00) 00000-0000';
$phone = $has_phone ? $phone : '(00) 0000-0000';
$brand = motors_marketplace_vehicle_term_names( $post_id, 'marca' );
$model = motors_marketplace_vehicle_term_names( $post_id, 'modelo' );
$version = motors_marketplace_vehicle_term_names( $post_id, 'versao' );
$vehicle_name = implode( ' ', array_filter( array_merge( $brand, $model ) ) );
$vehicle_name = $vehicle_name ? $vehicle_name : get_the_title( $post_id );
$version_label = implode( ' ', $version );
$has_version_in_name = $version_label && strtolower( substr( $vehicle_name, -strlen( $version_label ) ) ) === strtolower( $version_label );
$single_title = trim( $vehicle_name . ( $version_label && ! $has_version_in_name ? ' ' . $version_label : '' ) );
$transmission = get_post_meta( $post_id, '_cv_cambio', true );
$single_title = motors_marketplace_clean_vehicle_card_label( $single_title, $transmission );
$overline = $version ? '' : implode( ' · ', array_filter( array( get_post_meta( $post_id, '_cv_motor', true ), get_post_meta( $post_id, '_cv_combustivel', true ) ) ) );
$overline = motors_marketplace_clean_vehicle_card_label( $overline, $transmission );
$year_label = motors_marketplace_vehicle_year_label( $post_id );
$mileage_label = get_post_meta( $post_id, '_cv_quilometragem', true ) ? number_format_i18n( (int) get_post_meta( $post_id, '_cv_quilometragem', true ) ) . ' km' : '';
$subtitle = implode( ' · ', array_filter( array( $year_label, $mileage_label ) ) );
$contact_status = isset( $_GET['contato_status'] ) ? sanitize_key( wp_unslash( $_GET['contato_status'] ) ) : '';
$vehicle_type_ids = taxonomy_exists( 'tipo_veiculo' ) ? wp_get_object_terms( $post_id, 'tipo_veiculo', array( 'fields' => 'ids' ) ) : array();
$model_ids = taxonomy_exists( 'modelo' ) ? wp_get_object_terms( $post_id, 'modelo', array( 'fields' => 'ids' ) ) : array();
$brand_ids = taxonomy_exists( 'marca' ) ? wp_get_object_terms( $post_id, 'marca', array( 'fields' => 'ids' ) ) : array();
$related_ids = array();
$related_stages = array(
	array( 'taxonomy' => 'modelo', 'terms' => $model_ids ),
	array( 'taxonomy' => 'marca', 'terms' => $brand_ids ),
	array( 'taxonomy' => '', 'terms' => array() ),
);
if ( $vehicle_type_ids && ! is_wp_error( $vehicle_type_ids ) ) {
	foreach ( $related_stages as $stage ) {
		$remaining = 4 - count( $related_ids );
		if ( $remaining <= 0 ) { break; }
		if ( $stage['taxonomy'] && ( ! $stage['terms'] || is_wp_error( $stage['terms'] ) ) ) { continue; }
		$tax_query = array(
			'relation' => 'AND',
			array( 'taxonomy' => 'tipo_veiculo', 'field' => 'term_id', 'terms' => array_map( 'absint', $vehicle_type_ids ) ),
		);
		if ( $stage['taxonomy'] ) {
			$tax_query[] = array( 'taxonomy' => $stage['taxonomy'], 'field' => 'term_id', 'terms' => array_map( 'absint', $stage['terms'] ) );
		}
		$matches = get_posts( array(
			'post_type'           => 'veiculos',
			'post_status'         => 'publish',
			'posts_per_page'      => $remaining,
			'post__not_in'        => array_merge( array( $post_id ), $related_ids ),
			'tax_query'           => $tax_query,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		foreach ( $matches as $match ) { $related_ids[] = (int) $match->ID; }
	}
}
$related_query = new WP_Query( array(
	'post_type'           => 'veiculos',
	'post_status'         => 'publish',
	'posts_per_page'      => count( $related_ids ),
	'post__in'            => $related_ids ? $related_ids : array( 0 ),
	'orderby'             => 'post__in',
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );

function motors_marketplace_order_imported_gallery( $image_ids ) {
	$images = array();
	foreach ( $image_ids as $position => $image_id ) {
		$source = get_post_meta( $image_id, '_agent_cars_image_source', true );
		if ( ! $source ) {
			$source = get_post_meta( $image_id, '_cv_source_image_url', true );
		}
		$filename = wp_basename( (string) wp_parse_url( $source, PHP_URL_PATH ) );
		$sequence = preg_match( '/^(\d+)-/', $filename, $matches ) ? (int) $matches[1] : null;
		$images[] = array( 'id' => $image_id, 'sequence' => $sequence, 'position' => $position );
	}
	$has_sequence = count( array_filter( array_column( $images, 'sequence' ), 'is_int' ) ) > 1;
	if ( $has_sequence ) {
		usort( $images, function( $a, $b ) {
			if ( null === $a['sequence'] ) { return 1; }
			if ( null === $b['sequence'] ) { return -1; }
			return $a['sequence'] === $b['sequence'] ? $a['position'] - $b['position'] : $a['sequence'] - $b['sequence'];
		} );
	}
	return array_values( array_map( function( $image ) { return $image['id']; }, $images ) );
}
?>
<main id="main-content" class="mm-site-main body">
	<article class="mm-container mm-single">
		<div class="mm-single-heading">
			<div class="mm-single-heading-copy">
				<?php if ( $overline ) : ?><p class="mm-vehicle-overline"><?php echo esc_html( $overline ); ?></p><?php endif; ?>
				<h1 class="mm-single-title"><?php echo esc_html( $single_title ); ?></h1>
				<div class="mm-single-subtitle"><?php echo esc_html( $subtitle ); ?></div>
			</div>
			<?php if ( $price ) : ?><div class="mm-single-price-badge">R$ <?php echo esc_html( number_format_i18n( (float) $price, 0 ) ); ?></div><?php endif; ?>
		</div>
		<div class="mm-single-layout">
			<div>
				<div class="mm-gallery" data-mm-gallery>
					<?php if ( $gallery_image_ids ) : foreach ( $gallery_image_ids as $index => $image_id ) : $large_image = wp_get_attachment_image_src( $image_id, 'large' ); $full_image = wp_get_attachment_image_url( $image_id, 'full' ); if ( ! $large_image || ! $full_image ) { continue; } ?>
						<button class="mm-gallery-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-gallery-open="<?php echo (int) $index; ?>" data-full-image="<?php echo esc_url( $full_image ); ?>" aria-label="Abrir foto <?php echo (int) ( $index + 1 ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>><img src="<?php echo esc_url( $large_image[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) . ' â€” foto ' . ( $index + 1 ) ); ?>"></button>
					<?php endforeach; if ( count( $gallery_image_ids ) > 1 ) : ?><button class="mm-gallery-arrow mm-gallery-previous" type="button" data-gallery-step="-1" aria-label="Foto anterior">&#8249;</button><button class="mm-gallery-arrow mm-gallery-next" type="button" data-gallery-step="1" aria-label="Próxima foto">&#8250;</button><span class="mm-gallery-counter" data-gallery-counter>1 / <?php echo count( $gallery_image_ids ); ?></span><?php endif; else : ?><div class="mm-gallery-empty">Foto do veículo não disponível</div><?php endif; ?>
				</div>
				<?php if ( $gallery_image_ids ) : ?><div class="mm-lightbox" data-mm-lightbox role="dialog" aria-modal="true" aria-label="Galeria de fotos" hidden><button class="mm-lightbox-close" type="button" data-lightbox-close aria-label="Fechar galeria">&times;</button><button class="mm-lightbox-arrow mm-lightbox-previous" type="button" data-lightbox-step="-1" aria-label="Foto anterior">&#8249;</button><figure class="mm-lightbox-main"><img data-lightbox-image src="" alt=""><figcaption data-lightbox-caption></figcaption></figure><button class="mm-lightbox-arrow mm-lightbox-next" type="button" data-lightbox-step="1" aria-label="Próxima foto">&#8250;</button><?php if ( count( $gallery_image_ids ) > 1 ) : ?><div class="mm-lightbox-thumbnails" data-lightbox-thumbnails><?php foreach ( $gallery_image_ids as $index => $image_id ) : $thumb_image = wp_get_attachment_image_url( $image_id, 'thumbnail' ); $full_image = wp_get_attachment_image_url( $image_id, 'full' ); if ( ! $thumb_image || ! $full_image ) { continue; } ?><button class="mm-lightbox-thumbnail<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-lightbox-thumb="<?php echo (int) $index; ?>" data-full-image="<?php echo esc_url( $full_image ); ?>" aria-label="Ver foto <?php echo (int) ( $index + 1 ); ?>"><img src="<?php echo esc_url( $thumb_image ); ?>" alt=""></button><?php endforeach; ?></div><?php endif; ?><span class="mm-lightbox-counter" data-lightbox-counter></span></div><?php endif; ?>
			</div>
			<aside class="mm-contact-stack" id="contato-anunciante">
				<section class="mm-contact-card mm-advertiser-card">
				<div class="mm-advertiser-profile"><?php echo get_avatar( $author_id, 56, 'retro', $advertiser_name, array( 'class' => 'mm-advertiser-logo', 'alt' => $advertiser_name ) ); ?><strong><?php echo esc_html( $advertiser_name ); ?></strong></div>
				<div class="mm-contact-channels">
					<?php if ( $has_phone ) : ?><a class="mm-contact-channel mm-contact-phone" data-cv-track-phone href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2 11 11 0 0 0 3.5.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17.9 17.9 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .6 3.5 1 1 0 0 1-.2 1l-2.3 2.3Z"/></svg><span><?php echo esc_html( $phone ); ?></span></a><?php else : ?><span class="mm-contact-channel mm-contact-phone is-unavailable" title="Número fictício para demonstração"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2 11 11 0 0 0 3.5.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17.9 17.9 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .6 3.5 1 1 0 0 1-.2 1l-2.3 2.3Z"/></svg><span><?php echo esc_html( $phone ); ?></span></span><?php endif; ?>
					<?php if ( $has_whatsapp ) : ?><a class="mm-contact-channel mm-contact-whatsapp" data-cv-whatsapp-trigger href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a9.8 9.8 0 0 0-8.4 14.9L2.3 22l5.3-1.4A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.3.1-.4l.4-.5.2-.4c.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3s-.8.8-.8 1.9.8 2.2.9 2.4a9.5 9.5 0 0 0 3.7 3.3c.5.2.9.4 1.2.5.5.2 1 .2 1.3.1.4-.1 1.5-.6 1.7-1.2s.2-1 .2-1.1-.2-.2-.4-.3Z"/></svg><span><?php echo esc_html( $whatsapp ); ?></span></a><?php else : ?><span class="mm-contact-channel mm-contact-whatsapp is-unavailable" title="Número fictício para demonstração"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a9.8 9.8 0 0 0-8.4 14.9L2.3 22l5.3-1.4A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.3.1-.4l.4-.5.2-.4c.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3s-.8.8-.8 1.9.8 2.2.9 2.4a9.5 9.5 0 0 0 3.7 3.3c.5.2.9.4 1.2.5.5.2 1 .2 1.3.1.4-.1 1.5-.6 1.7-1.2s.2-1 .2-1.1-.2-.2-.4-.3Z"/></svg><span><?php echo esc_html( $whatsapp ); ?></span></span><?php endif; ?>
				</div>
				</section>
				<?php if ( $source_url ) : ?><section class="mm-contact-card mm-contact-form-card"><h2>Fale com o anunciante</h2><p>Consulte a disponibilidade e os detalhes atualizados diretamente na loja.</p><a class="mm-button" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer">Ver anúncio na loja</a></section><?php else : ?><section class="mm-contact-card mm-contact-form-card">
				<?php if ( 'sent' === $contact_status ) : ?><p class="mm-contact-notice" role="status">Mensagem enviada. O anunciante poderá responder pelo e-mail informado.</p><?php elseif ( 'error' === $contact_status ) : ?><p class="mm-contact-error" role="alert">Não foi possível enviar. Confira os dados e tente novamente.</p><?php endif; ?>
				<div class="mm-contact-form-wrap"><form class="mm-contact-form" method="post" action="<?php echo esc_url( get_permalink( $post_id ) . '#contato-anunciante' ); ?>">
					<?php wp_nonce_field( 'cv_vehicle_contact_' . $post_id, 'cv_vehicle_contact_nonce' ); ?>
					<input type="hidden" name="vehicle_id" value="<?php echo (int) $post_id; ?>">
					<label>Nome<input type="text" name="cv_contact_name" autocomplete="name" required></label>
					<label>Email<input type="email" name="cv_contact_email" autocomplete="email" required></label>
					<label>Fone/WhatsApp<input type="tel" name="cv_contact_phone" autocomplete="tel"></label>
					<label>Mensagem<textarea name="cv_contact_message" rows="4" required></textarea></label>
					<button class="mm-button" type="submit" name="cv_vehicle_contact_submit" value="1">Enviar mensagem</button>
				</form></div>
				</section><?php endif; ?>
			</aside>
		</div>
		<section class="mm-details mm-details-after-related" id="detalhes-veiculo"><h2>Detalhes do veículo</h2><dl class="mm-facts"><?php foreach ( $fields as $label => $value ) : if ( '' === (string) $value ) { continue; } ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endforeach; ?></dl><div class="mm-description"><?php the_content(); ?></div></section>
		<?php if ( $related_query->have_posts() ) : ?><section class="mm-related-section" aria-labelledby="mm-related-title"><div class="mm-related-heading"><h2 id="mm-related-title">Relacionados</h2><a href="<?php echo esc_url( get_post_type_archive_link( 'veiculos' ) ?: home_url( '/' ) ); ?>">Ver todos</a></div><div class="mm-related-grid"><?php while ( $related_query->have_posts() ) : $related_query->the_post(); motors_marketplace_vehicle_card( get_the_ID() ); endwhile; ?></div></section><?php endif; wp_reset_postdata(); ?>
		<?php if ( $has_whatsapp ) : ?><div class="mm-lead-modal" data-cv-whatsapp-modal hidden aria-hidden="true"><div class="mm-lead-modal-backdrop" data-cv-modal-close></div><section class="mm-lead-modal-panel" role="dialog" aria-modal="true" aria-labelledby="mm-lead-modal-title"><button class="mm-lead-modal-close" type="button" data-cv-modal-close aria-label="Fechar">&times;</button><p class="mm-lead-modal-eyebrow">Contato</p><h2 id="mm-lead-modal-title">Falar com <?php echo esc_html( $advertiser_name ); ?></h2><form data-cv-whatsapp-form><input type="hidden" name="vehicle_id" value="<?php echo (int) $post_id; ?>"><input type="hidden" name="record_id" value=""><label>Seu Nome<input type="text" name="name" autocomplete="name" placeholder="Nome" required></label><label>Seu Email<input type="email" name="email" autocomplete="email" placeholder="Email" required></label><label>Seu WhatsApp<input type="tel" name="phone" autocomplete="tel" placeholder="WhatsApp" required></label><p class="mm-lead-modal-error" data-cv-lead-error role="alert" hidden></p><button class="mm-lead-whatsapp-submit" type="submit">Falar pelo WhatsApp</button></form></section></div><?php endif; ?>
	</article>
</main>
<?php get_footer(); ?>

