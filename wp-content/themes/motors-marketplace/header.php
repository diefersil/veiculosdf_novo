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
			<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?><span><?php echo esc_html( get_bloginfo( 'name' ) ?: 'Motors' ); ?></span><?php endif; ?>
		</a>
		<button class="mm-menu-toggle" type="button" aria-expanded="false" aria-controls="mm-primary-nav">Menu</button>
		<nav class="mm-nav" id="mm-primary-nav" aria-label="Navegação principal">
			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'items_wrap' => '%3$s', 'fallback_cb' => false ) ); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'veiculos' ) ?: home_url( '/?post_type=veiculos' ) ); ?>">Comprar</a>
				<a href="<?php echo esc_url( home_url( '/#cadastre-seu-carro' ) ); ?>">Anunciar veículo</a>
			<?php endif; ?>
			<a class="mm-account-link" href="<?php echo esc_url( home_url( '/minha-conta/' ) ); ?>"><?php echo is_user_logged_in() ? 'Minha conta' : 'Entrar'; ?></a>
		</nav>
	</div>
</header>
