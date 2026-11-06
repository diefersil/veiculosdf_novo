<?php
get_header();
$brands = get_terms( array( 'taxonomy' => 'marca', 'hide_empty' => false ) );
$models = get_terms( array( 'taxonomy' => 'modelo', 'hide_empty' => false ) );
?>
<main id="main-content" class="mm-site-main body">
	<div class="mm-container mm-archive-header">
		<h1>Veículos à venda</h1>
		<p>Encontre o veículo certo para você.</p>
		<form class="mm-archive-search" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'veiculos' ) ); ?>">
			<label>Marca<select name="cv_marca"><option value="">Todas as marcas</option><?php if ( ! is_wp_error( $brands ) ) : foreach ( $brands as $brand ) : ?><option value="<?php echo (int) $brand->term_id; ?>" <?php selected( isset( $_GET['cv_marca'] ) ? absint( $_GET['cv_marca'] ) : 0, $brand->term_id ); ?>><?php echo esc_html( $brand->name ); ?></option><?php endforeach; endif; ?></select></label>
			<label>Modelo<select name="cv_modelo"><option value="">Todos os modelos</option><?php if ( ! is_wp_error( $models ) ) : foreach ( $models as $model ) : ?><option value="<?php echo (int) $model->term_id; ?>" <?php selected( isset( $_GET['cv_modelo'] ) ? absint( $_GET['cv_modelo'] ) : 0, $model->term_id ); ?>><?php echo esc_html( $model->name ); ?></option><?php endforeach; endif; ?></select></label>
			<button type="submit">Buscar</button>
		</form>
	</div>
	<div class="mm-container">
		<?php if ( have_posts() ) : ?><div class="mm-vehicle-grid"><?php while ( have_posts() ) : the_post(); motors_marketplace_vehicle_card( get_the_ID() ); endwhile; ?></div>
			<div class="mm-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?><p class="mm-empty">Nenhum veículo encontrado. Altere os filtros e tente novamente.</p><?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
