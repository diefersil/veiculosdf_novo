<?php
get_header();
?>
<main id="main-content" class="mm-site-main body">
	<div class="mm-container mm-page-content">
		<?php if ( have_posts() ) : ?><div class="mm-vehicle-grid"><?php while ( have_posts() ) : the_post(); if ( 'veiculos' === get_post_type() ) { motors_marketplace_vehicle_card( get_the_ID() ); } else { ?><article class="mm-vehicle-card mm-vehicle-card-content"><p class="mm-vehicle-overline"><?php echo esc_html( get_the_date() ); ?></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?></article><?php } endwhile; ?></div><?php else : ?><p class="mm-empty">Nenhum conteúdo encontrado.</p><?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
