<?php
get_header();
$brands  = get_terms( array( 'taxonomy' => 'marca', 'hide_empty' => true ) );
$models  = get_terms( array( 'taxonomy' => 'modelo', 'hide_empty' => true ) );
$versions = get_terms( array( 'taxonomy' => 'versao', 'hide_empty' => false ) );
$brand_id = isset( $_GET['cv_marca'] ) ? absint( wp_unslash( $_GET['cv_marca'] ) ) : 0;
$model_filter = isset( $_GET['cv_modelo'] ) ? sanitize_text_field( wp_unslash( $_GET['cv_modelo'] ) ) : '';
$model_is_family = 0 === strpos( $model_filter, 'family:' );
$model_id = $model_is_family ? absint( substr( $model_filter, 7 ) ) : absint( $model_filter );
$version_id = isset( $_GET['cv_versao'] ) ? absint( wp_unslash( $_GET['cv_versao'] ) ) : 0;
if ( ! $model_id && $version_id ) {
	$model_id = (int) get_term_meta( $version_id, '_cv_termo_pai', true );
}
if ( ! $brand_id && $model_id ) {
	$brand_id = (int) get_term_meta( $model_id, '_cv_termo_pai', true );
}
$models_by_brand = array();
if ( ! is_wp_error( $models ) ) {
	foreach ( $models as $model ) {
		$parent_id = (int) get_term_meta( $model->term_id, '_cv_termo_pai', true );
		$models_by_brand[ $parent_id ][] = $model;
	}
}
?>
<main id="main-content" class="mm-site-main body">
	<div class="mm-container mm-archive-header">
		<h1>Veículos à venda</h1>
		<p>Encontre o veículo certo para você.</p>
		<form class="mm-archive-search" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'veiculos' ) ); ?>">
			<label>Marca<select name="cv_marca" data-taxonomy="marca"><option value="">Todas as marcas</option><?php if ( ! is_wp_error( $brands ) ) : foreach ( $brands as $brand ) : ?><option value="<?php echo (int) $brand->term_id; ?>" <?php selected( $brand_id, $brand->term_id ); ?>><?php echo esc_html( $brand->name ); ?></option><?php endforeach; endif; ?></select></label>
			<label>Modelo<select name="cv_modelo" data-taxonomy="modelo"><option value="">Todos os modelos</option><?php if ( $model_is_family && $model_id ) : $family_term = get_term( $model_id, 'modelo' ); if ( $family_term && ! is_wp_error( $family_term ) ) : ?><option value="family:<?php echo (int) $model_id; ?>" selected><?php echo esc_html( $family_term->name ); ?></option><?php endif; endif; ?><?php if ( ! is_wp_error( $brands ) ) : foreach ( $brands as $brand ) : if ( empty( $models_by_brand[ $brand->term_id ] ) ) { continue; } ?><optgroup label="<?php echo esc_attr( $brand->name ); ?>"><?php foreach ( $models_by_brand[ $brand->term_id ] as $model ) : ?><option value="<?php echo (int) $model->term_id; ?>" <?php selected( $model_is_family ? 0 : $model_id, (int) $model->term_id ); ?>><?php echo esc_html( $model->name ); ?></option><?php endforeach; ?></optgroup><?php endforeach; endif; ?><?php if ( ! empty( $models_by_brand[0] ) ) : ?><optgroup label="Outras marcas"><?php foreach ( $models_by_brand[0] as $model ) : ?><option value="<?php echo (int) $model->term_id; ?>" <?php selected( $model_is_family ? 0 : $model_id, (int) $model->term_id ); ?>><?php echo esc_html( $model->name ); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
			<label>Versão<select name="cv_versao" data-taxonomy="versao"><option value="">Todas as versões</option><?php if ( ! is_wp_error( $versions ) ) : foreach ( $versions as $version ) : ?><option value="<?php echo (int) $version->term_id; ?>" <?php selected( $version_id, $version->term_id ); ?>><?php echo esc_html( $version->name ); ?></option><?php endforeach; endif; ?></select></label>
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
