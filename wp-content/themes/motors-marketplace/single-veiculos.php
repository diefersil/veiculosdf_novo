<?php
get_header();
the_post();
$post_id = get_the_ID();
$gallery = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_cv_vehicle_gallery', true ) ) ) );
$featured_image_id = (int) get_post_thumbnail_id( $post_id );
$gallery_image_ids = array_values( array_unique( array_filter( array_merge( $featured_image_id ? array( $featured_image_id ) : array(), $gallery ) ) ) );
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
	'Motor' => get_post_meta( $post_id, '_cv_motor', true ),
	'Combustível' => get_post_meta( $post_id, '_cv_combustivel', true ),
	'Câmbio' => get_post_meta( $post_id, '_cv_cambio', true ),
	'Cor' => get_post_meta( $post_id, '_cv_cor', true ),
);
$price = get_post_meta( $post_id, '_cv_preco', true );
$author_id = (int) get_post_field( 'post_author', $post_id );
$advertiser = get_userdata( $author_id );
$advertiser_name = $advertiser && $advertiser->display_name ? $advertiser->display_name : 'Loja Exemplo';
$whatsapp = get_user_meta( $author_id, 'cv_whatsapp', true );
$phone = get_user_meta( $author_id, 'cv_telefone', true );
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
				<div class="mm-gallery" data-mm-gallery>
					<?php if ( $gallery_image_ids ) : foreach ( $gallery_image_ids as $index => $image_id ) : $large_image = wp_get_attachment_image_src( $image_id, 'large' ); $full_image = wp_get_attachment_image_url( $image_id, 'full' ); if ( ! $large_image || ! $full_image ) { continue; } ?>
						<button class="mm-gallery-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-gallery-open="<?php echo (int) $index; ?>" data-full-image="<?php echo esc_url( $full_image ); ?>" aria-label="Abrir foto <?php echo (int) ( $index + 1 ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>><img src="<?php echo esc_url( $large_image[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) . ' — foto ' . ( $index + 1 ) ); ?>"></button>
					<?php endforeach; if ( count( $gallery_image_ids ) > 1 ) : ?><button class="mm-gallery-arrow mm-gallery-previous" type="button" data-gallery-step="-1" aria-label="Foto anterior">&#8249;</button><button class="mm-gallery-arrow mm-gallery-next" type="button" data-gallery-step="1" aria-label="PrÃ³xima foto">&#8250;</button><span class="mm-gallery-counter" data-gallery-counter>1 / <?php echo count( $gallery_image_ids ); ?></span><?php endif; else : ?><div class="mm-gallery-empty">Foto do veÃ­culo nÃ£o disponÃ­vel</div><?php endif; ?>
				</div>
				<?php if ( $gallery_image_ids ) : ?><div class="mm-lightbox" data-mm-lightbox role="dialog" aria-modal="true" aria-label="Galeria de fotos" hidden><button class="mm-lightbox-close" type="button" data-lightbox-close aria-label="Fechar galeria">&times;</button><button class="mm-lightbox-arrow mm-lightbox-previous" type="button" data-lightbox-step="-1" aria-label="Foto anterior">&#8249;</button><figure class="mm-lightbox-main"><img data-lightbox-image src="" alt=""><figcaption data-lightbox-caption></figcaption></figure><button class="mm-lightbox-arrow mm-lightbox-next" type="button" data-lightbox-step="1" aria-label="PrÃ³xima foto">&#8250;</button><?php if ( count( $gallery_image_ids ) > 1 ) : ?><div class="mm-lightbox-thumbnails" data-lightbox-thumbnails><?php foreach ( $gallery_image_ids as $index => $image_id ) : $thumb_image = wp_get_attachment_image_url( $image_id, 'thumbnail' ); $full_image = wp_get_attachment_image_url( $image_id, 'full' ); if ( ! $thumb_image || ! $full_image ) { continue; } ?><button class="mm-lightbox-thumbnail<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-lightbox-thumb="<?php echo (int) $index; ?>" data-full-image="<?php echo esc_url( $full_image ); ?>" aria-label="Ver foto <?php echo (int) ( $index + 1 ); ?>"><img src="<?php echo esc_url( $thumb_image ); ?>" alt=""></button><?php endforeach; ?></div><?php endif; ?><span class="mm-lightbox-counter" data-lightbox-counter></span></div><?php endif; ?>
				<section class="mm-details"><h2>Detalhes do veículo</h2><p class="mm-price"><?php echo $price ? 'R$ ' . esc_html( number_format_i18n( (float) $price, 0 ) ) : 'Consulte o preço'; ?></p><dl class="mm-facts"><?php foreach ( $fields as $label => $value ) : if ( '' === (string) $value ) { continue; } ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endforeach; ?></dl><div class="mm-description"><?php the_content(); ?></div></section>
			</div>
			<aside class="mm-contact-stack" id="contato-anunciante">
				<section class="mm-contact-card mm-advertiser-card">
				<div class="mm-advertiser-profile"><?php echo get_avatar( $author_id, 56, 'retro', $advertiser_name, array( 'class' => 'mm-advertiser-logo', 'alt' => $advertiser_name ) ); ?><strong><?php echo esc_html( $advertiser_name ); ?></strong></div>
				<div class="mm-contact-channels">
					<?php if ( $has_phone ) : ?><a class="mm-contact-channel mm-contact-phone" data-cv-track-phone href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2 11 11 0 0 0 3.5.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17.9 17.9 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .6 3.5 1 1 0 0 1-.2 1l-2.3 2.3Z"/></svg><span><?php echo esc_html( $phone ); ?></span></a><?php else : ?><span class="mm-contact-channel mm-contact-phone is-unavailable" title="Número fictício para demonstração"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2 11 11 0 0 0 3.5.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17.9 17.9 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .6 3.5 1 1 0 0 1-.2 1l-2.3 2.3Z"/></svg><span><?php echo esc_html( $phone ); ?></span></span><?php endif; ?>
					<?php if ( $has_whatsapp ) : ?><a class="mm-contact-channel mm-contact-whatsapp" data-cv-whatsapp-trigger href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D+/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a9.8 9.8 0 0 0-8.4 14.9L2.3 22l5.3-1.4A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.3.1-.4l.4-.5.2-.4c.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3s-.8.8-.8 1.9.8 2.2.9 2.4a9.5 9.5 0 0 0 3.7 3.3c.5.2.9.4 1.2.5.5.2 1 .2 1.3.1.4-.1 1.5-.6 1.7-1.2s.2-1 .2-1.1-.2-.2-.4-.3Z"/></svg><span><?php echo esc_html( $whatsapp ); ?></span></a><?php else : ?><span class="mm-contact-channel mm-contact-whatsapp is-unavailable" title="Número fictício para demonstração"><svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a9.8 9.8 0 0 0-8.4 14.9L2.3 22l5.3-1.4A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3.1.8.8-3-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.4 7.4 0 0 1-1.4-1.7c-.1-.2 0-.3.1-.4l.4-.5.2-.4c.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3s-.8.8-.8 1.9.8 2.2.9 2.4a9.5 9.5 0 0 0 3.7 3.3c.5.2.9.4 1.2.5.5.2 1 .2 1.3.1.4-.1 1.5-.6 1.7-1.2s.2-1 .2-1.1-.2-.2-.4-.3Z"/></svg><span><?php echo esc_html( $whatsapp ); ?></span></span><?php endif; ?>
				</div>
				</section>
				<section class="mm-contact-card mm-contact-form-card">
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
				</section>
			</aside>
		</div>
		<?php if ( $has_whatsapp ) : ?><div class="mm-lead-modal" data-cv-whatsapp-modal hidden aria-hidden="true"><div class="mm-lead-modal-backdrop" data-cv-modal-close></div><section class="mm-lead-modal-panel" role="dialog" aria-modal="true" aria-labelledby="mm-lead-modal-title"><button class="mm-lead-modal-close" type="button" data-cv-modal-close aria-label="Fechar">&times;</button><p class="mm-lead-modal-eyebrow">Contato</p><h2 id="mm-lead-modal-title">Falar com <?php echo esc_html( $advertiser_name ); ?></h2><form data-cv-whatsapp-form><input type="hidden" name="vehicle_id" value="<?php echo (int) $post_id; ?>"><input type="hidden" name="record_id" value=""><label>Seu Nome<input type="text" name="name" autocomplete="name" placeholder="Nome" required></label><label>Seu Email<input type="email" name="email" autocomplete="email" placeholder="Email" required></label><label>Seu WhatsApp<input type="tel" name="phone" autocomplete="tel" placeholder="WhatsApp" required></label><p class="mm-lead-modal-error" data-cv-lead-error role="alert" hidden></p><button class="mm-lead-whatsapp-submit" type="submit">Falar pelo WhatsApp</button></form></section></div><?php endif; ?>
	</article>
</main>
<?php get_footer(); ?>
