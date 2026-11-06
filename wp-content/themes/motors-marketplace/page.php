<?php
get_header();
?>
<main id="main-content" class="mm-site-main body <?php echo is_page( 'minha-conta' ) ? 'mm-account-page' : ''; ?>">
	<div class="mm-container mm-page-content">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( ! is_page( 'minha-conta' ) ) : ?><h1 class="mm-page-title"><?php the_title(); ?></h1><?php endif; ?>
			<?php the_content(); ?>
		<?php endwhile; ?>
	</div>
</main>
<?php get_footer(); ?>
