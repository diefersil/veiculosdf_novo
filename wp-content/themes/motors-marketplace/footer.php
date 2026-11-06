<footer class="mm-site-footer">
	<div class="mm-container mm-footer-inner">
		<div><div class="mm-footer-brand"><?php echo esc_html( get_bloginfo( 'name' ) ?: 'Motors' ); ?></div><p>Encontre seu próximo veículo ou anuncie o seu.</p></div>
		<nav class="mm-footer-nav" aria-label="Navegação do rodapé">
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'items_wrap' => '%3$s', 'fallback_cb' => false ) ); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'veiculos' ) ?: home_url( '/?post_type=veiculos' ) ); ?>">Veículos</a>
				<a href="<?php echo esc_url( home_url( '/minha-conta/' ) ); ?>">Minha conta</a>
			<?php endif; ?>
		</nav>
	</div>
	<div class="mm-container mm-copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
