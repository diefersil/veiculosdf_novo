<?php
get_header();
?>
<main id="main-content" class="mm-site-main body">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php the_content(); ?>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
