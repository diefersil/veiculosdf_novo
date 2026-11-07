<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="mm-site-header">
	<div class="mm-container mm-header-inner">
		<a class="mm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php $custom_logo_id = get_theme_mod( 'custom_logo' ); ?>
			<?php if ( $custom_logo_id ) : echo wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'class' => 'mm-brand-logo' ) ); else : ?><span><?php echo esc_html( get_bloginfo( 'name' ) ?: 'Motors' ); ?></span><?php endif; ?>
		</a>
		<div class="mm-header-menu" id="mm-primary-nav">
			<nav class="mm-nav-links" aria-label="Menu principal">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
				<a href="<?php echo esc_url( motors_marketplace_vehicle_type_link( 'carros' ) ); ?>">Carros</a>
				<a href="<?php echo esc_url( motors_marketplace_vehicle_type_link( 'motos' ) ); ?>">Motos</a>
				<a href="<?php echo esc_url( motors_marketplace_vehicle_type_link( 'caminhoes' ) ); ?>">Caminh&otilde;es</a>
			</nav>
			<div class="mm-header-actions">
				<a class="mm-account-link" href="<?php echo esc_url( home_url( '/minha-conta/' ) ); ?>"><?php echo is_user_logged_in() ? 'Minha conta' : 'Entrar'; ?></a>
				<a class="mm-sell-link" href="<?php echo esc_url( home_url( '/minha-conta/#anunciar-veiculo' ) ); ?>">Anunciar Gr&aacute;tis</a>
			</div>
		</div>
		<button class="mm-menu-toggle" type="button" aria-expanded="false" aria-controls="mm-primary-nav" aria-label="Abrir menu">Menu</button>
	</div>
</header>