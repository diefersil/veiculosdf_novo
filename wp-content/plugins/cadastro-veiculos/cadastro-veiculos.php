<?php
/**
 * Plugin Name: Cadastro de Veículos
 * Description: Cadastro de veículos, taxonomias relacionadas e imagens de termos.
 * Version: 1.0.0
 * Text Domain: cadastro-veiculos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cadastro_Veiculos {
	const POST_TYPE = 'veiculos';
	const TYPE_TAX  = 'tipo_veiculo';
	const META_TYPES = '_cv_tipos_veiculo';
	const META_PARENT = '_cv_termo_pai';
	const META_IMAGE = '_cv_imagem';

	private static $taxonomies = array(
		'marca'     => array( 'label' => 'Marcas', 'singular' => 'Marca', 'parent' => '' ),
		'modelo'    => array( 'label' => 'Modelos', 'singular' => 'Modelo', 'parent' => 'marca' ),
		'versao'    => array( 'label' => 'Versões', 'singular' => 'Versão', 'parent' => 'modelo' ),
		'estilo'    => array( 'label' => 'Estilos', 'singular' => 'Estilo', 'parent' => '' ),
		'opcionais' => array( 'label' => 'Opcionais', 'singular' => 'Opcional', 'parent' => '' ),
	);

	private static function color_options() {
		return array( 'Branco', 'Preto', 'Prata', 'Cinza', 'Vermelho', 'Azul', 'Verde', 'Amarelo', 'Marrom', 'Bege', 'Laranja', 'Roxo', 'Dourado', 'Vinho', 'Outra' );
	}

	private static function transmission_options() {
		return array( 'Manual', 'Automático', 'Automatizado', 'CVT', 'Elétrico', 'DCT / dupla embreagem', 'Semi-automático' );
	}

	private static function style_presets() {
		return array(
			'carro' => array( 'Sedan', 'Hatch', 'SUV', 'Picape', 'Coupé', 'Conversível', 'Perua', 'Minivan', 'Furgão' ),
			'moto' => array( 'Street', 'Trail', 'Naked', 'Custom', 'Esportiva', 'Touring', 'Scooter', 'Off-road' ),
			'geral' => array( 'Outros' ),
		);
	}

	private static function style_group_for_type( $type ) {
		$slug = is_object( $type ) && isset( $type->slug ) ? $type->slug : sanitize_title( (string) $type );
		return ( false !== strpos( $slug, 'moto' ) || false !== strpos( $slug, 'motocicleta' ) ) ? 'moto' : 'carro';
	}

	public static function seed_default_styles() {
		if ( get_option( 'cv_default_styles_seeded' ) ) {
			return;
		}
		foreach ( self::style_presets() as $group => $labels ) {
			foreach ( $labels as $label ) {
				$existing = term_exists( $label, 'estilo' );
				if ( $existing ) {
					continue;
				}
				$inserted = wp_insert_term( $label, 'estilo' );
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_id = (int) $inserted['term_id'];
				update_term_meta( $term_id, '_cv_default_style', 1 );
				update_term_meta( $term_id, '_cv_style_group', $group );
				$types = get_terms( array( 'taxonomy' => self::TYPE_TAX, 'hide_empty' => false ) );
				$matching = array();
				if ( ! is_wp_error( $types ) ) {
					foreach ( $types as $type ) {
						if ( 'geral' === $group || self::style_group_for_type( $type ) === $group ) {
							$matching[] = (int) $type->term_id;
						}
					}
				}
				update_term_meta( $term_id, self::META_TYPES, $matching );
			}
		}
		update_option( 'cv_default_styles_seeded', 1, false );
	}

	public static function assign_default_styles_to_type( $type_id ) {
		$type = get_term( $type_id, self::TYPE_TAX );
		if ( ! $type || is_wp_error( $type ) ) {
			return;
		}
		$styles = get_terms( array(
			'taxonomy' => 'estilo',
			'hide_empty' => false,
			'meta_query' => array( array( 'key' => '_cv_default_style', 'value' => 1 ) ),
		) );
		if ( is_wp_error( $styles ) ) {
			return;
		}
		$group = self::style_group_for_type( $type );
		foreach ( $styles as $style ) {
			$style_group = get_term_meta( $style->term_id, '_cv_style_group', true );
			if ( 'geral' !== $style_group && $style_group !== $group ) {
				continue;
			}
			$types = array_map( 'intval', (array) get_term_meta( $style->term_id, self::META_TYPES, true ) );
			if ( ! in_array( (int) $type_id, $types, true ) ) {
				$types[] = (int) $type_id;
				update_term_meta( $style->term_id, self::META_TYPES, $types );
			}
		}
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_content' ) );
		add_action( 'init', array( __CLASS__, 'ensure_account_page' ), 30 );
		add_action( 'init', array( __CLASS__, 'seed_default_styles' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_vehicle_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_vehicle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_media' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_help_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_type_list_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_type_list_submenu' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_rewrite_notice' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_fields' ) );
		add_shortcode( 'painel_minha_conta_veiculos', array( __CLASS__, 'render_account_dashboard' ) );
		add_shortcode( 'cv_homepage', array( __CLASS__, 'render_homepage' ) );
		add_action( 'init', array( __CLASS__, 'handle_account_vehicle_action' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_vehicle_archive' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );

		foreach ( array_merge( array( self::TYPE_TAX ), array_keys( self::$taxonomies ) ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'term_add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'term_edit_fields' ) );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_term_fields' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term_fields' ) );
		}
		add_action( 'created_' . self::TYPE_TAX, array( __CLASS__, 'assign_default_styles_to_type' ) );
	}

	public static function filter_vehicle_archive( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}
		$tax_query = array();
		foreach ( array( 'cv_marca' => 'marca', 'cv_modelo' => 'modelo' ) as $input => $taxonomy ) {
			$term_id = isset( $_GET[ $input ] ) ? absint( wp_unslash( $_GET[ $input ] ) ) : 0;
			if ( $term_id ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id );
			}
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	public static function enqueue_frontend_assets() {
		if ( is_front_page() ) {
			wp_enqueue_script( 'cv-home', plugin_dir_url( __FILE__ ) . 'assets/home.js', array(), '1.0.0', true );
			$term_data = self::all_term_data();
			wp_localize_script( 'cv-home', 'CVHomeVehicleData', array( 'modelo' => $term_data['modelo'] ) );
			wp_enqueue_style( 'cv-home', plugin_dir_url( __FILE__ ) . 'assets/home.css', array(), '1.0.0' );
		}
		if ( is_page( 'minha-conta' ) ) {
			wp_enqueue_script( 'cv-account', plugin_dir_url( __FILE__ ) . 'assets/account.js', array(), '1.0.0', true );
			wp_localize_script( 'cv-account', 'CVVehicleData', self::all_term_data() );
			wp_enqueue_script( 'cv-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), '1.2.0', true );
			wp_enqueue_media();
			wp_enqueue_style( 'cv-account', plugin_dir_url( __FILE__ ) . 'assets/account.css', array(), '1.0.0' );
		}
	}

	/** Home page: vehicle search, latest listings and a short seller intake form. */
	public static function render_homepage() {
		$archive = get_post_type_archive_link( self::POST_TYPE );
		$account = get_page_by_path( 'minha-conta' );
		$account_url = $account ? get_permalink( $account ) : home_url( '/minha-conta/' );
		$brands = get_terms( array( 'taxonomy' => 'marca', 'hide_empty' => false ) );
		$models = get_terms( array( 'taxonomy' => 'modelo', 'hide_empty' => false ) );
		$html = '<div class="cv-home">';
		$html .= '<section class="cv-home-hero"><div class="cv-home-hero-inner"><span class="cv-home-eyebrow">Seu próximo veículo está aqui</span><h1>Encontre o veículo<br>ideal para você.</h1><p>Pesquise entre os veículos cadastrados e encontre a melhor opção.</p><form class="cv-home-search" method="get" action="' . esc_url( $archive ) . '"><label>Marca<select name="cv_marca" data-taxonomy="marca"><option value="">Todas as marcas</option>';
		if ( ! is_wp_error( $brands ) ) {
			foreach ( $brands as $brand ) {
				$html .= '<option value="' . (int) $brand->term_id . '">' . esc_html( $brand->name ) . '</option>';
			}
		}
		$html .= '</select></label><label>Modelo<select name="cv_modelo" data-taxonomy="modelo"><option value="">Todos os modelos</option>';
		if ( ! is_wp_error( $models ) ) {
			foreach ( $models as $model ) {
				$html .= '<option value="' . (int) $model->term_id . '">' . esc_html( $model->name ) . '</option>';
			}
		}
		$html .= '</select></label><button type="submit">Buscar veículos</button></form><a class="cv-home-sell-link" href="#cadastre-seu-carro">Quer anunciar? Cadastre seu veículo</a></div></section>';
		$html .= '<section class="cv-home-latest"><div class="cv-home-section-heading"><div><span class="cv-home-eyebrow">Novidades</span><h2>Últimos cadastros</h2></div><a href="' . esc_url( $archive ) . '">Ver todos</a></div>';
		$latest = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 6, 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( $latest->have_posts() ) {
			$html .= '<div class="cv-home-cards">';
			foreach ( $latest->posts as $vehicle ) {
				$image = get_the_post_thumbnail_url( $vehicle, 'large' );
				if ( ! $image ) {
					$gallery = (array) get_post_meta( $vehicle->ID, '_cv_vehicle_gallery', true );
					$image = ! empty( $gallery[0] ) ? wp_get_attachment_image_url( absint( $gallery[0] ), 'large' ) : '';
				}
				$brand = wp_get_object_terms( $vehicle->ID, 'marca', array( 'fields' => 'names' ) );
				$model = wp_get_object_terms( $vehicle->ID, 'modelo', array( 'fields' => 'names' ) );
				$year = get_post_meta( $vehicle->ID, '_cv_ano_modelo', true );
				$mileage = get_post_meta( $vehicle->ID, '_cv_quilometragem', true );
				$price = get_post_meta( $vehicle->ID, '_cv_preco', true );
				$html .= '<article class="cv-home-card"><a class="cv-home-card-image" href="' . esc_url( get_permalink( $vehicle ) ) . '">' . ( $image ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( get_the_title( $vehicle ) ) . '" loading="lazy">' : '<span>Foto do veículo</span>' ) . '</a><div class="cv-home-card-body"><p>' . esc_html( implode( ' ', array_filter( array( is_array( $brand ) ? implode( ' ', $brand ) : '', is_array( $model ) ? implode( ' ', $model ) : '' ) ) ) ) . '</p><h3><a href="' . esc_url( get_permalink( $vehicle ) ) . '">' . esc_html( get_the_title( $vehicle ) ) . '</a></h3><div class="cv-home-card-meta">' . ( $year ? '<span>' . esc_html( $year ) . '</span>' : '' ) . ( $mileage ? '<span>' . esc_html( number_format_i18n( (int) $mileage ) ) . ' km</span>' : '' ) . '</div>' . ( $price ? '<strong class="cv-home-card-price">R$ ' . esc_html( number_format_i18n( (float) $price, 0 ) ) . '</strong>' : '' ) . '</div></article>';
			}
			$html .= '</div>';
		} else {
			$html .= '<p class="cv-home-empty">Os primeiros veículos estão chegando. Volte em breve para conferir.</p>';
		}
		wp_reset_postdata();
		$html .= '</section><section class="cv-home-sell" id="cadastre-seu-carro"><div><span class="cv-home-eyebrow">Anuncie grátis</span><h2>Cadastre seu carro</h2><p>Comece informando os dados principais. Você poderá completar o anúncio na próxima etapa.</p></div><form action="' . esc_url( $account_url ) . '" method="get"><label>Marca<select name="marca" data-taxonomy="marca"><option value="">Selecione a marca</option>';
		if ( ! is_wp_error( $brands ) ) {
			foreach ( $brands as $brand ) {
				$html .= '<option value="' . (int) $brand->term_id . '">' . esc_html( $brand->name ) . '</option>';
			}
		}
		$html .= '</select></label><label>Modelo<select name="modelo" data-taxonomy="modelo"><option value="">Selecione o modelo</option>';
		if ( ! is_wp_error( $models ) ) {
			foreach ( $models as $model ) {
				$html .= '<option value="' . (int) $model->term_id . '">' . esc_html( $model->name ) . '</option>';
			}
		}
		$html .= '</select></label><label>Ano de fabricação<input type="number" name="ano_fabricacao" min="1886" max="' . ( (int) current_time( 'Y' ) + 1 ) . '" placeholder="Ex.: 2020"></label><label>Ano modelo<input type="number" name="ano_modelo" min="1886" max="' . ( (int) current_time( 'Y' ) + 1 ) . '" placeholder="Ex.: 2021"></label><input type="hidden" name="iniciar_cadastro" value="1"><button type="submit">Continuar cadastro</button></form></section></div>';
		wp_enqueue_script( 'cv-home', plugin_dir_url( __FILE__ ) . 'assets/home.js', array(), '1.0.0', true );
		$term_data = self::all_term_data();
		wp_localize_script( 'cv-home', 'CVHomeVehicleData', array( 'modelo' => $term_data['modelo'] ) );
		wp_enqueue_style( 'cv-home', plugin_dir_url( __FILE__ ) . 'assets/home.css', array(), '1.0.0' );
		return $html;
	}

	/** Process owner-only front-end vehicle create, update and delete actions. */
	public static function handle_account_vehicle_action() {
		if ( empty( $_POST['cv_account_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['cv_account_action'] ) );
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( ! isset( $_POST['cv_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_account_nonce'] ) ), 'cv_account_vehicle' ) ) {
			return;
		}
		if ( 'delete' === $action ) {
			if ( $vehicle_id && (int) get_post_field( 'post_author', $vehicle_id ) === get_current_user_id() && self::POST_TYPE === get_post_type( $vehicle_id ) ) {
				wp_trash_post( $vehicle_id );
				self::account_redirect( 'deleted' );
			}
			return;
		}
		if ( ! in_array( $action, array( 'save', 'create' ), true ) ) {
			return;
		}
		if ( $vehicle_id && ( (int) get_post_field( 'post_author', $vehicle_id ) !== get_current_user_id() || self::POST_TYPE !== get_post_type( $vehicle_id ) ) ) {
			return;
		}
		$title = isset( $_POST['cv_account_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cv_account_title'] ) ) : '';
		if ( '' === $title ) {
			self::account_redirect( 'missing_title' );
		}
		$vehicle = array(
			'post_type' => self::POST_TYPE,
			'post_title' => $title,
			'post_content' => isset( $_POST['cv_account_description'] ) ? wp_kses_post( wp_unslash( $_POST['cv_account_description'] ) ) : '',
			'post_status' => 'pending',
			'post_author' => get_current_user_id(),
		);
		if ( $vehicle_id ) {
			$vehicle['ID'] = $vehicle_id;
			unset( $vehicle['post_author'] );
			unset( $vehicle['post_status'] );
			$vehicle_id = wp_update_post( wp_slash( $vehicle ), true );
		} else {
			$vehicle_id = wp_insert_post( wp_slash( $vehicle ), true );
		}
		if ( is_wp_error( $vehicle_id ) ) {
			self::account_redirect( 'error' );
		}
		$fields = isset( $_POST['cv_account_fields'] ) && is_array( $_POST['cv_account_fields'] ) ? wp_unslash( $_POST['cv_account_fields'] ) : array();
		foreach ( array( 'ano_modelo', 'ano_fabricacao', 'quilometragem' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? absint( $fields[ $key ] ) : 0;
			$value ? update_post_meta( $vehicle_id, '_cv_' . $key, $value ) : delete_post_meta( $vehicle_id, '_cv_' . $key );
		}
		$price = isset( $fields['preco'] ) ? (float) str_replace( ',', '.', preg_replace( '/[^0-9,\.]/', '', sanitize_text_field( $fields['preco'] ) ) ) : 0;
		$price > 0 ? update_post_meta( $vehicle_id, '_cv_preco', number_format( $price, 2, '.', '' ) ) : delete_post_meta( $vehicle_id, '_cv_preco' );
		foreach ( array( 'motor', 'cor', 'combustivel', 'cambio' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? sanitize_text_field( $fields[ $key ] ) : '';
			if ( 'cambio' === $key && ! in_array( $value, self::transmission_options(), true ) ) {
				$value = '';
			}
			'' !== $value ? update_post_meta( $vehicle_id, '_cv_' . $key, $value ) : delete_post_meta( $vehicle_id, '_cv_' . $key );
		}
		$terms = isset( $_POST['cv_account_terms'] ) && is_array( $_POST['cv_account_terms'] ) ? wp_unslash( $_POST['cv_account_terms'] ) : array();
		$type_id = isset( $terms['tipo_veiculo'] ) ? absint( $terms['tipo_veiculo'] ) : 0;
		wp_set_object_terms( $vehicle_id, $type_id ? array( $type_id ) : array(), self::TYPE_TAX );
		foreach ( array( 'marca', 'modelo', 'versao', 'estilo', 'opcionais' ) as $taxonomy ) {
			$ids = isset( $terms[ $taxonomy ] ) ? array_map( 'absint', (array) $terms[ $taxonomy ] ) : array();
			$valid = array();
			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$allowed_types = array_map( 'intval', (array) get_term_meta( $term_id, self::META_TYPES, true ) );
				if ( $type_id && ! in_array( $type_id, $allowed_types, true ) ) {
					continue;
				}
				$parent = (int) get_term_meta( $term_id, self::META_PARENT, true );
				if ( 'modelo' === $taxonomy && (int) ( $terms['marca'] ?? 0 ) !== $parent ) {
					continue;
				}
				if ( 'versao' === $taxonomy && (int) ( $terms['modelo'] ?? 0 ) !== $parent ) {
					continue;
				}
				$valid[] = $term_id;
			}
			wp_set_object_terms( $vehicle_id, $valid, $taxonomy );
		}
		$gallery_nonce_action = 'cv_account_gallery_' . $vehicle_id;
		if ( isset( $_POST['cv_account_gallery_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_account_gallery_nonce'] ) ), $gallery_nonce_action ) ) {
			$raw_gallery = isset( $_POST['cv_account_gallery_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['cv_account_gallery_ids'] ) ) ) : array();
			$gallery = array();
			foreach ( $raw_gallery as $image_id ) {
				$image_id = absint( $image_id );
				if ( $image_id && 'attachment' === get_post_type( $image_id ) && wp_attachment_is_image( $image_id ) && ! in_array( $image_id, $gallery, true ) ) {
					$gallery[] = $image_id;
				}
				if ( count( $gallery ) >= 12 ) {
					break;
				}
			}
			$gallery ? update_post_meta( $vehicle_id, '_cv_vehicle_gallery', $gallery ) : delete_post_meta( $vehicle_id, '_cv_vehicle_gallery' );
		}
		self::account_redirect( 'saved' );
	}

	private static function account_redirect( $notice ) {
		$target = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'veiculo_status', sanitize_key( $notice ), remove_query_arg( array( 'editar_veiculo', 'veiculo_status' ), $target ) ) );
		exit;
	}

	/** Front-end account panel. Add [painel_minha_conta_veiculos] to a page. */
	public static function render_account_dashboard() {
		if ( ! is_user_logged_in() ) {
			$return_url = add_query_arg( array_intersect_key( array_map( 'sanitize_text_field', wp_unslash( $_GET ) ), array_flip( array( 'iniciar_cadastro', 'marca', 'modelo', 'ano_fabricacao', 'ano_modelo' ) ) ), get_permalink() );
			$google_login_url = apply_filters( 'cv_google_login_url', wp_login_url( $return_url ) );
			return '<div class="cv-account cv-account-login"><h2>Minha conta</h2><p>Entre para cadastrar e gerenciar seus veículos.</p><a class="cv-account-button" href="' . esc_url( $google_login_url ) . '">Continuar com Google</a></div>';
		}
		$user_id = get_current_user_id();
		$edit_id = isset( $_GET['editar_veiculo'] ) ? absint( $_GET['editar_veiculo'] ) : 0;
		$editing = $edit_id && self::POST_TYPE === get_post_type( $edit_id ) && (int) get_post_field( 'post_author', $edit_id ) === $user_id ? get_post( $edit_id ) : null;
		$notice = isset( $_GET['veiculo_status'] ) ? sanitize_key( $_GET['veiculo_status'] ) : '';
		$messages = array( 'saved' => 'Veículo salvo e enviado para revisão.', 'deleted' => 'Veículo removido.', 'missing_title' => 'Informe o nome do veículo.', 'error' => 'Não foi possível salvar o veículo.' );
		$html = '<div class="cv-account"><h2>Minha conta</h2><p>Gerencie seus anúncios de veículos.</p>';
		if ( isset( $messages[ $notice ] ) ) {
			$html .= '<div class="cv-account-notice">' . esc_html( $messages[ $notice ] ) . '</div>';
		}
		$html .= '<div class="cv-account-layout"><section class="cv-account-list"><h3>Meus veículos</h3>';
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => array( 'publish', 'pending', 'draft', 'private' ), 'author' => $user_id, 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( $query->have_posts() ) {
			$html .= '<ul class="cv-account-vehicles">';
			foreach ( $query->posts as $post ) {
				$html .= '<li><span><strong>' . esc_html( get_the_title( $post ) ) . '</strong><small>' . esc_html( get_post_status_object( $post->post_status )->label ) . '</small></span><a href="' . esc_url( add_query_arg( 'editar_veiculo', $post->ID ) ) . '">Editar</a><form method="post" onsubmit="return confirm(\'Remover este veículo?\')">' . wp_nonce_field( 'cv_account_vehicle', 'cv_account_nonce', true, false ) . '<input type="hidden" name="vehicle_id" value="' . (int) $post->ID . '"><button name="cv_account_action" value="delete" class="cv-account-delete">Remover</button></form></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p>Você ainda não cadastrou veículos.</p>';
		}
		wp_reset_postdata();
		$html .= '</section><section class="cv-account-form"><h3>' . ( $editing ? 'Editar veículo' : 'Adicionar veículo' ) . '</h3><form method="post">' . wp_nonce_field( 'cv_account_vehicle', 'cv_account_nonce', true, false ) . '<input type="hidden" name="vehicle_id" value="' . ( $editing ? (int) $editing->ID : 0 ) . '"><input type="hidden" name="cv_account_action" value="' . ( $editing ? 'save' : 'create' ) . '">';
		$prefill_names = array();
		if ( ! $editing ) {
			foreach ( array( 'marca', 'modelo' ) as $prefill_taxonomy ) {
				$prefill_id = isset( $_GET[ $prefill_taxonomy ] ) ? absint( wp_unslash( $_GET[ $prefill_taxonomy ] ) ) : 0;
				$prefill_term = $prefill_id ? get_term( $prefill_id, $prefill_taxonomy ) : false;
				if ( $prefill_term && ! is_wp_error( $prefill_term ) ) {
					$prefill_names[] = $prefill_term->name;
				}
			}
		}
		$prefill_title = $editing ? $editing->post_title : implode( ' ', $prefill_names );
		$html .= '<label>Nome do veículo<input required type="text" name="cv_account_title" value="' . esc_attr( $prefill_title ) . '"></label><label>Descrição<textarea name="cv_account_description" rows="4">' . esc_textarea( $editing ? $editing->post_content : '' ) . '</textarea></label>';
		foreach ( array( self::TYPE_TAX => 'Tipo de veículo', 'marca' => 'Marca', 'modelo' => 'Modelo', 'versao' => 'Versão', 'estilo' => 'Estilo', 'opcionais' => 'Opcionais' ) as $taxonomy => $label ) {
			$multiple = 'opcionais' === $taxonomy;
			$selected = $editing ? wp_get_object_terms( $editing->ID, $taxonomy, array( 'fields' => 'ids' ) ) : array();
			if ( ! $editing && ! empty( $_GET[ $taxonomy ] ) && in_array( $taxonomy, array( 'marca', 'modelo' ), true ) ) {
				$selected = array( absint( wp_unslash( $_GET[ $taxonomy ] ) ) );
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			$html .= '<label>' . esc_html( $label ) . '<select class="cv-account-term cv-term-select" data-taxonomy="' . esc_attr( $taxonomy ) . '" name="cv_account_terms[' . esc_attr( $taxonomy ) . ']' . ( $multiple ? '[]' : '' ) . '"' . ( $multiple ? ' multiple size="4"' : '' ) . '>' . ( $multiple ? '' : '<option value="">Selecione</option>' );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$html .= '<option value="' . (int) $term->term_id . '" ' . selected( in_array( (int) $term->term_id, array_map( 'intval', $selected ), true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
				}
			}
			$html .= '</select></label>';
		}
		$fields = array( 'ano_modelo' => 'Ano modelo', 'ano_fabricacao' => 'Ano de fabricação', 'motor' => 'Motor / potência', 'preco' => 'Preço', 'cor' => 'Cor', 'quilometragem' => 'Quilometragem', 'combustivel' => 'Combustível' );
		foreach ( $fields as $key => $label ) {
			$type = in_array( $key, array( 'ano_modelo', 'ano_fabricacao', 'quilometragem' ), true ) ? 'number' : 'text';
			$field_value = $editing ? get_post_meta( $editing->ID, '_cv_' . $key, true ) : ( isset( $_GET[ $key ] ) && in_array( $key, array( 'ano_modelo', 'ano_fabricacao' ), true ) ? absint( wp_unslash( $_GET[ $key ] ) ) : '' );
			$html .= '<label>' . esc_html( $label ) . '<input type="' . $type . '" name="cv_account_fields[' . esc_attr( $key ) . ']" value="' . esc_attr( $field_value ) . '"' . ( 'number' === $type ? ' min="0"' : '' ) . '></label>';
		}
		$gallery = $editing ? array_values( array_filter( array_map( 'absint', (array) get_post_meta( $editing->ID, '_cv_vehicle_gallery', true ) ) ) ) : array();
		$html .= '<fieldset class="cv-account-gallery"><legend>Fotos do veículo (até 12)</legend>' . wp_nonce_field( 'cv_account_gallery_' . ( $editing ? $editing->ID : 0 ), 'cv_account_gallery_nonce', true, false ) . '<input type="hidden" id="cv_gallery_ids" name="cv_account_gallery_ids" value="' . esc_attr( implode( ',', $gallery ) ) . '"><ul class="cv-gallery-list" id="cv_gallery_list">';
		foreach ( $gallery as $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			if ( $image_url ) {
				$html .= '<li class="cv-gallery-item" data-id="' . (int) $image_id . '"><span class="cv-gallery-drag" aria-label="Arrastar para ordenar">↕</span><img src="' . esc_url( $image_url ) . '" alt=""><button type="button" class="button-link-delete cv-gallery-remove">Remover</button></li>';
			}
		}
		$html .= '</ul><button type="button" class="cv-account-button" id="cv_gallery_add">Selecionar fotos</button><span class="cv-gallery-count">' . count( $gallery ) . ' / 12</span></fieldset>';
		$selected_transmission = $editing ? get_post_meta( $editing->ID, '_cv_cambio', true ) : '';
		$html .= '<label>Câmbio<select name="cv_account_fields[cambio]"><option value="">Selecione</option>';
		foreach ( self::transmission_options() as $transmission ) {
			$html .= '<option value="' . esc_attr( $transmission ) . '" ' . selected( $selected_transmission, $transmission, false ) . '>' . esc_html( $transmission ) . '</option>';
		}
		$html .= '</select></label><button class="cv-account-button" type="submit">' . ( $editing ? 'Salvar alterações' : 'Cadastrar veículo' ) . '</button>';
		if ( $editing ) {
			$html .= ' <a href="' . esc_url( remove_query_arg( 'editar_veiculo' ) ) . '">Cancelar</a>';
		}
		$html .= '</form></section></div></div>';
		wp_enqueue_script( 'cv-account', plugin_dir_url( __FILE__ ) . 'assets/account.js', array(), '1.0.0', true );
		wp_localize_script( 'cv-account', 'CVVehicleData', self::all_term_data() );
		wp_enqueue_script( 'cv-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), '1.2.0', true );
		wp_enqueue_media();
		wp_enqueue_style( 'cv-account', plugin_dir_url( __FILE__ ) . 'assets/account.css', array(), '1.0.0' );
		return $html;
	}

	public static function register_content() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => 'Veículos', 'singular_name' => 'Veículo', 'add_new_item' => 'Adicionar veículo',
				'edit_item' => 'Editar veículo', 'new_item' => 'Novo veículo', 'view_item' => 'Ver veículo',
				'search_items' => 'Buscar veículos', 'not_found' => 'Nenhum veículo encontrado',
				'menu_name' => 'Veículos',
			),
			'public' => true,
		'has_archive' => true,
			'rewrite' => array( 'slug' => 'veiculos' ),
			'show_in_rest' => true,
			'menu_icon' => 'dashicons-car',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'menu_position' => 20,
		) );

		register_taxonomy( self::TYPE_TAX, array( self::POST_TYPE ), array(
			'labels' => array( 'name' => 'Tipos de Veículo', 'singular_name' => 'Tipo de Veículo', 'menu_name' => 'Tipos de Veículo', 'add_new_item' => 'Adicionar tipo de veículo' ),
			'public' => true, 'hierarchical' => true, 'show_in_rest' => true, 'meta_box_cb' => false,
			'rewrite' => array( 'slug' => 'tipo-veiculo' ),
		) );

		foreach ( self::$taxonomies as $slug => $config ) {
			register_taxonomy( $slug, array( self::POST_TYPE ), array(
				'labels' => array( 'name' => $config['label'], 'singular_name' => $config['singular'], 'menu_name' => $config['label'], 'add_new_item' => 'Adicionar ' . strtolower( $config['singular'] ) ),
				'public' => true, 'hierarchical' => true, 'show_in_rest' => true, 'meta_box_cb' => false,
				'rewrite' => array( 'slug' => sanitize_title( $config['label'] ) ),
			) );
		}
	}

	public static function ensure_account_page() {
		if ( get_page_by_path( 'minha-conta' ) ) {
			return;
		}
		wp_insert_post( array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Minha conta',
			'post_name' => 'minha-conta',
			'post_content' => '[painel_minha_conta_veiculos]',
			'comment_status' => 'closed',
		), true );
	}

	public static function add_vehicle_meta_box() {
		add_meta_box( 'cv_vehicle_data', 'Dados do veículo', array( __CLASS__, 'render_vehicle_meta_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'cv_vehicle_gallery', 'Galeria de fotos', array( __CLASS__, 'render_vehicle_gallery' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function render_user_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		wp_nonce_field( 'cv_save_user_fields', 'cv_user_fields_nonce' );
		$fields = array(
			'whatsapp' => array( 'WhatsApp', 'text', 'Ex.: +55 61 99999-9999' ),
			'telefone' => array( 'Telefone', 'text', 'Ex.: +55 61 3333-4444' ),
			'endereco' => array( 'Endereço', 'text', 'Rua, número e complemento' ),
			'cidade' => array( 'Cidade', 'text', '' ),
			'estado' => array( 'Estado', 'text', 'UF' ),
			'rating_google_maps' => array( 'Avaliação no Google Maps', 'number', 'Nota de 0 a 5' ),
		);
		echo '<h2>Informações de contato e localização</h2><table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $field ) {
			$value = get_user_meta( $user->ID, 'cv_' . $key, true );
			$attrs = 'number' === $field[1] ? ' min="0" max="5" step="0.1"' : '';
			$placeholder = $field[2] ? ' placeholder="' . esc_attr( $field[2] ) . '"' : '';
			echo '<tr><th><label for="cv_user_' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td><input class="regular-text" id="cv_user_' . esc_attr( $key ) . '" name="cv_user_fields[' . esc_attr( $key ) . ']" type="' . esc_attr( $field[1] ) . '" value="' . esc_attr( $value ) . '"' . $attrs . $placeholder . '></td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function save_user_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['cv_user_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_user_fields_nonce'] ) ), 'cv_save_user_fields' ) ) {
			return;
		}
		$fields = isset( $_POST['cv_user_fields'] ) && is_array( $_POST['cv_user_fields'] ) ? wp_unslash( $_POST['cv_user_fields'] ) : array();
		foreach ( array( 'whatsapp', 'telefone', 'endereco', 'cidade', 'estado' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? sanitize_text_field( $fields[ $key ] ) : '';
			if ( '' === $value ) {
				delete_user_meta( $user_id, 'cv_' . $key );
			} else {
				update_user_meta( $user_id, 'cv_' . $key, $value );
			}
		}
		$rating = isset( $fields['rating_google_maps'] ) ? (float) $fields['rating_google_maps'] : 0;
		if ( $rating < 0 || $rating > 5 ) {
			$rating = 0;
		}
		if ( $rating <= 0 ) {
			delete_user_meta( $user_id, 'cv_rating_google_maps' );
		} else {
			update_user_meta( $user_id, 'cv_rating_google_maps', number_format( $rating, 1, '.', '' ) );
		}
	}

	public static function render_vehicle_gallery( $post ) {
		$images = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_cv_vehicle_gallery', true ) ) ) );
		wp_nonce_field( 'cv_save_vehicle_gallery', 'cv_gallery_nonce' );
		echo '<p>Adicione até 12 imagens. Arraste as fotos para alterar a ordem da galeria.</p>';
		echo '<input type="hidden" id="cv_gallery_ids" name="cv_gallery_ids" value="' . esc_attr( implode( ',', $images ) ) . '">';
		echo '<ul class="cv-gallery-list" id="cv_gallery_list">';
		foreach ( $images as $image_id ) {
			$url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			if ( ! $url ) {
				continue;
			}
			echo '<li class="cv-gallery-item" data-id="' . (int) $image_id . '"><span class="cv-gallery-drag" aria-label="Arrastar para ordenar">↕</span><img src="' . esc_url( $url ) . '" alt=""><button type="button" class="button-link-delete cv-gallery-remove">Remover</button></li>';
		}
		echo '</ul><button type="button" class="button button-secondary" id="cv_gallery_add">Adicionar imagens</button><span class="cv-gallery-count">' . count( $images ) . ' / 12</span>';
	}

	private static function all_term_data() {
		$data = array();
		$taxonomies = array_merge( array( self::TYPE_TAX ), array_keys( self::$taxonomies ) );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			$data[ $taxonomy ] = array();
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$data[ $taxonomy ][] = array(
					'id' => (int) $term->term_id,
					'name' => $term->name,
					'types' => array_map( 'intval', (array) get_term_meta( $term->term_id, self::META_TYPES, true ) ),
					'parent' => (int) get_term_meta( $term->term_id, self::META_PARENT, true ),
				);
			}
		}
		return $data;
	}

	public static function render_vehicle_meta_box( $post ) {
		wp_nonce_field( 'cv_save_vehicle', 'cv_vehicle_nonce' );
		$fields = array(
			'ano_modelo' => array( 'Ano Modelo', 'number' ),
			'ano_fabricacao' => array( 'Ano de Fabricação', 'number' ),
			'motor' => array( 'Motor / Potência', 'text', 'Ex.: 1.6' ),
			'preco' => array( 'Preço', 'number' ),
			'cor' => array( 'Cor', 'text', 'Ex.: Branco, Preto, Prata' ),
			'quilometragem' => array( 'Quilometragem', 'number' ),
			'combustivel' => array( 'Combustível', 'text', 'Ex.: Flex, Gasolina, Diesel' ),
		);
		echo '<div class="cv-vehicle-fields">';
		self::render_term_select( 'tipo_veiculo', 'Tipo de Veículo', $post->ID, false, self::TYPE_TAX );
		self::render_term_select( 'marca', 'Marca', $post->ID, false, 'marca' );
		self::render_term_select( 'modelo', 'Modelo', $post->ID, false, 'modelo' );
		self::render_term_select( 'versao', 'Versão', $post->ID, false, 'versao' );
		self::render_term_select( 'estilo', 'Estilo', $post->ID, false, 'estilo' );
		$year_max = (int) current_time( 'Y' ) + 1;
		echo '<datalist id="cv-year-options">';
		for ( $year = $year_max; $year >= 2000; $year-- ) {
			echo '<option value="' . $year . '"></option>';
		}
		echo '</datalist><datalist id="cv-color-options">';
		foreach ( self::color_options() as $color ) {
			echo '<option value="' . esc_attr( $color ) . '"></option>';
		}
		echo '</datalist><datalist id="cv-fuel-options"><option value="Flex"><option value="Gasolina"><option value="Etanol"><option value="Diesel"><option value="Elétrico"><option value="Híbrido"><option value="GNV"></datalist>';
		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, '_cv_' . $key, true );
			$step = 'preco' === $key ? '0.01' : '1';
			$min = 'number' === $field[1] ? ' min="0" step="' . $step . '"' : '';
			if ( in_array( $key, array( 'ano_modelo', 'ano_fabricacao' ), true ) ) {
				$min = ' min="1886" max="' . $year_max . '" step="1" list="cv-year-options"';
			}
			if ( 'cor' === $key || 'combustivel' === $key ) {
				$min = ' list="' . ( 'cor' === $key ? 'cv-color-options' : 'cv-fuel-options' ) . '" maxlength="60"';
			}
			$placeholder = isset( $field[2] ) ? ' placeholder="' . esc_attr( $field[2] ) . '"' : '';
			echo '<p><label for="cv_' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br><input class="regular-text" id="cv_' . esc_attr( $key ) . '" name="cv_fields[' . esc_attr( $key ) . ']" type="' . esc_attr( $field[1] ) . '" value="' . esc_attr( $value ) . '"' . $min . $placeholder . '></p>';
		}
		$value = get_post_meta( $post->ID, '_cv_cambio', true );
		echo '<p><label for="cv_cambio"><strong>Câmbio</strong></label><br><select id="cv_cambio" name="cv_fields[cambio]"><option value="">Selecione</option>';
		foreach ( self::transmission_options() as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select></p>';
		self::render_term_select( 'opcionais', 'Opcionais', $post->ID, true, 'opcionais' );
		echo '</div><p class="description">As listas são filtradas pelas relações configuradas nos cadastros das taxonomias.</p>';
	}

	private static function render_term_select( $name, $label, $post_id, $multiple, $taxonomy ) {
		$selected = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $selected ) ) {
			$selected = array();
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$multiple_attr = $multiple ? ' multiple size="5"' : '';
		$input_name = $multiple ? 'cv_terms[' . $name . '][]' : 'cv_terms[' . $name . ']';
		echo '<p><label for="cv_term_' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><select class="widefat cv-term-select" id="cv_term_' . esc_attr( $name ) . '" name="' . esc_attr( $input_name ) . '" data-taxonomy="' . esc_attr( $taxonomy ) . '"' . $multiple_attr . '>';
		if ( ! $multiple ) {
			echo '<option value="">Selecione</option>';
		}
		foreach ( $terms as $term ) {
			$chosen = in_array( (int) $term->term_id, array_map( 'intval', $selected ), true );
			echo '<option value="' . (int) $term->term_id . '" ' . selected( $chosen, true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select></p>';
	}

	public static function save_vehicle( $post_id ) {
		if ( ! isset( $_POST['cv_vehicle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_vehicle_nonce'] ) ), 'cv_save_vehicle' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$terms = isset( $_POST['cv_terms'] ) && is_array( $_POST['cv_terms'] ) ? wp_unslash( $_POST['cv_terms'] ) : array();
		$chosen_type = isset( $terms['tipo_veiculo'] ) ? absint( $terms['tipo_veiculo'] ) : 0;
		$assignments = array( self::TYPE_TAX => $chosen_type ? array( $chosen_type ) : array() );
		$parents = array( 'marca' => 0, 'modelo' => 0, 'versao' => 0 );
		foreach ( array( 'marca', 'modelo', 'versao', 'estilo', 'opcionais' ) as $taxonomy ) {
			$raw = isset( $terms[ $taxonomy ] ) ? (array) $terms[ $taxonomy ] : array();
			$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
			$valid = array();
			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$term_types = array_map( 'intval', (array) get_term_meta( $term_id, self::META_TYPES, true ) );
				if ( $chosen_type && ! in_array( $chosen_type, $term_types, true ) ) {
					continue;
				}
				$parent_id = (int) get_term_meta( $term_id, self::META_PARENT, true );
				if ( 'modelo' === $taxonomy ) {
					if ( ! $parents['marca'] || ! $parent_id || $parent_id !== $parents['marca'] ) {
						continue;
					}
					$brand_types = array_map( 'intval', (array) get_term_meta( $parent_id, self::META_TYPES, true ) );
					if ( $chosen_type && ! in_array( $chosen_type, $brand_types, true ) ) {
						continue;
					}
				}
				if ( 'versao' === $taxonomy ) {
					if ( ! $parents['modelo'] || ! $parent_id || $parent_id !== $parents['modelo'] ) {
						continue;
					}
					$model_types = array_map( 'intval', (array) get_term_meta( $parent_id, self::META_TYPES, true ) );
					$model_brand = (int) get_term_meta( $parent_id, self::META_PARENT, true );
					if ( ( $chosen_type && ! in_array( $chosen_type, $model_types, true ) ) || $model_brand !== $parents['marca'] ) {
						continue;
					}
				}
				$valid[] = $term_id;
				if ( in_array( $taxonomy, array( 'marca', 'modelo' ), true ) && ! $parents[ $taxonomy ] ) {
					$parents[ $taxonomy ] = $term_id;
				}
			}
			$assignments[ $taxonomy ] = $valid;
		}
		foreach ( $assignments as $taxonomy => $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
		if ( isset( $_POST['cv_gallery_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_gallery_nonce'] ) ), 'cv_save_vehicle_gallery' ) ) {
			$raw_gallery = isset( $_POST['cv_gallery_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['cv_gallery_ids'] ) ) ) : array();
			$gallery = array();
			foreach ( $raw_gallery as $image_id ) {
				$image_id = absint( $image_id );
				if ( $image_id && 'attachment' === get_post_type( $image_id ) && wp_attachment_is_image( $image_id ) && ! in_array( $image_id, $gallery, true ) ) {
					$gallery[] = $image_id;
				}
				if ( count( $gallery ) >= 12 ) {
					break;
				}
			}
			if ( $gallery ) {
				update_post_meta( $post_id, '_cv_vehicle_gallery', $gallery );
			} else {
				delete_post_meta( $post_id, '_cv_vehicle_gallery' );
			}
		}

		$fields = isset( $_POST['cv_fields'] ) && is_array( $_POST['cv_fields'] ) ? wp_unslash( $_POST['cv_fields'] ) : array();
		foreach ( array( 'ano_modelo', 'ano_fabricacao', 'quilometragem' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? absint( $fields[ $key ] ) : '';
			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, '_cv_' . $key );
			} else {
				update_post_meta( $post_id, '_cv_' . $key, $value );
			}
		}
		$price_raw = isset( $fields['preco'] ) ? sanitize_text_field( $fields['preco'] ) : '';
		$price_raw = str_replace( ',', '.', preg_replace( '/[^0-9,\.]/', '', $price_raw ) );
		$price = is_numeric( $price_raw ) ? (float) $price_raw : 0;
		if ( $price > 0 ) {
			update_post_meta( $post_id, '_cv_preco', number_format( $price, 2, '.', '' ) );
		} else {
			delete_post_meta( $post_id, '_cv_preco' );
		}
		foreach ( array( 'motor', 'cor', 'combustivel', 'cambio' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? sanitize_text_field( $fields[ $key ] ) : '';
			if ( 'cor' === $key ) {
				$value = sanitize_text_field( substr( $value, 0, 60 ) );
			}
			if ( 'cambio' === $key && ! in_array( $value, self::transmission_options(), true ) ) {
				$value = '';
			}
			if ( '' === $value ) {
				delete_post_meta( $post_id, '_cv_' . $key );
			} else {
				update_post_meta( $post_id, '_cv_' . $key, $value );
			}
		}
	}

	public static function term_add_fields( $taxonomy ) {
		$config = isset( self::$taxonomies[ $taxonomy ] ) ? self::$taxonomies[ $taxonomy ] : array( 'parent' => '' );
		if ( self::TYPE_TAX === $taxonomy ) {
			wp_nonce_field( 'cv_save_term', 'cv_term_nonce' );
		} else {
			self::render_type_term_field( array(), 'add' );
		}
		if ( ! empty( $config['parent'] ) ) {
			self::render_parent_term_field( $taxonomy, 0, $config['parent'], 'add' );
		}
		self::render_image_term_field( 0, 'add' );
	}

	public static function term_edit_fields( $term ) {
		$taxonomy = $term->taxonomy;
		$config = isset( self::$taxonomies[ $taxonomy ] ) ? self::$taxonomies[ $taxonomy ] : array( 'parent' => '' );
		if ( self::TYPE_TAX === $taxonomy ) {
			wp_nonce_field( 'cv_save_term', 'cv_term_nonce' );
		} else {
			self::render_type_term_field( (array) get_term_meta( $term->term_id, self::META_TYPES, true ), 'edit' );
		}
		if ( ! empty( $config['parent'] ) ) {
			self::render_parent_term_field( $taxonomy, (int) get_term_meta( $term->term_id, self::META_PARENT, true ), $config['parent'], 'edit' );
		}
		self::render_image_term_field( (int) get_term_meta( $term->term_id, self::META_IMAGE, true ), 'edit' );
	}

	private static function render_type_term_field( $selected, $mode ) {
		$terms = get_terms( array( 'taxonomy' => self::TYPE_TAX, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$selected = array_map( 'intval', (array) $selected );
		if ( 'add' === $mode ) {
			echo '<div class="form-field"><label for="cv_term_types">Tipo de Veículo</label>';
		} else {
			echo '<tr class="form-field"><th scope="row"><label for="cv_term_types">Tipo de Veículo</label></th><td>';
		}
		echo '<select id="cv_term_types" class="cv-term-types" name="cv_term_types[]" multiple size="4" required style="min-width:240px">';
		foreach ( $terms as $term ) {
			echo '<option value="' . (int) $term->term_id . '" ' . selected( in_array( (int) $term->term_id, $selected, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select><p class="description">Selecione os tipos de veículo aos quais este termo pertence. Cadastre primeiro os Tipos de Veículo.</p>';
		echo 'add' === $mode ? '</div>' : '</td></tr>';
	}

	private static function render_parent_term_field( $taxonomy, $selected, $parent_taxonomy, $mode ) {
		$terms = get_terms( array( 'taxonomy' => $parent_taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$label = 'modelo' === $taxonomy ? 'Marca deste modelo' : 'Modelo desta versão';
		if ( 'add' === $mode ) {
			echo '<div class="form-field"><label for="cv_parent_term">' . esc_html( $label ) . '</label>';
		} else {
			echo '<tr class="form-field"><th scope="row"><label for="cv_parent_term">' . esc_html( $label ) . '</label></th><td>';
		}
		echo '<select id="cv_parent_term" class="cv-parent-term" name="cv_parent_term" data-parent-taxonomy="' . esc_attr( $parent_taxonomy ) . '" required><option value="">Selecione</option>';
		foreach ( $terms as $term ) {
			echo '<option value="' . (int) $term->term_id . '" ' . selected( $selected, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select><p class="description">O vínculo com ' . esc_html( strtolower( $parent_taxonomy ) ) . ' complementa a relação com o tipo de veículo.</p>';
		echo 'add' === $mode ? '</div>' : '</td></tr>';
	}

	private static function render_image_term_field( $attachment_id, $mode ) {
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		if ( 'add' === $mode ) {
			echo '<div class="form-field"><label>Imagem ou ícone (opcional)</label>';
		} else {
			echo '<tr class="form-field"><th scope="row"><label>Imagem ou ícone (opcional)</label></th><td>';
		}
		echo '<input type="hidden" class="cv-term-image-id" name="cv_term_image" value="' . (int) $attachment_id . '"><div class="cv-term-image-preview">';
		if ( $url ) {
			echo '<img src="' . esc_url( $url ) . '" alt="" style="max-width:100px;height:auto">';
		}
		echo '</div><button type="button" class="button cv-upload-term-image">Escolher imagem</button> <button type="button" class="button cv-remove-term-image">Remover</button><p class="description">A imagem é opcional.</p>';
		echo 'add' === $mode ? '</div>' : '</td></tr>';
	}

	public static function save_term_fields( $term_id ) {
		if ( ! isset( $_POST['cv_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_term_nonce'] ) ), 'cv_save_term' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		$types = array();
		if ( self::TYPE_TAX !== $term->taxonomy ) {
			$types = isset( $_POST['cv_term_types'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['cv_term_types'] ) ) ) ) ) : array();
			$valid_types = array();
			foreach ( $types as $type_id ) {
				if ( self::TYPE_TAX === get_term_field( 'taxonomy', $type_id ) ) {
					$valid_types[] = $type_id;
				}
			}
			$types = $valid_types;
		}
		$config = isset( self::$taxonomies[ $term->taxonomy ] ) ? self::$taxonomies[ $term->taxonomy ] : array( 'parent' => '' );
		if ( ! empty( $config['parent'] ) ) {
			$parent_id = isset( $_POST['cv_parent_term'] ) ? absint( wp_unslash( $_POST['cv_parent_term'] ) ) : 0;
			$parent = $parent_id ? get_term( $parent_id, $config['parent'] ) : false;
			if ( ! $parent || is_wp_error( $parent ) ) {
				$parent_id = 0;
			}
			update_term_meta( $term_id, self::META_PARENT, $parent_id );
			$parent_types = $parent_id ? array_map( 'intval', (array) get_term_meta( $parent_id, self::META_TYPES, true ) ) : array();
			$types = array_values( array_intersect( $types, $parent_types ) );
		}
		if ( self::TYPE_TAX !== $term->taxonomy ) {
			update_term_meta( $term_id, self::META_TYPES, $types );
		}
		$image_id = isset( $_POST['cv_term_image'] ) ? absint( wp_unslash( $_POST['cv_term_image'] ) ) : 0;
		if ( $image_id && 'attachment' === get_post_type( $image_id ) ) {
			update_term_meta( $term_id, self::META_IMAGE, $image_id );
		} else {
			delete_term_meta( $term_id, self::META_IMAGE );
		}
	}

	public static function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		if ( self::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			wp_enqueue_script( 'cv-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), '1.2.0', true );
			wp_localize_script( 'cv-admin', 'CVVehicleData', self::all_term_data() );
			wp_enqueue_style( 'cv-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), '1.2.0' );
		}
		if ( in_array( $screen->taxonomy, array_merge( array( self::TYPE_TAX ), array_keys( self::$taxonomies ) ), true ) ) {
			wp_enqueue_script( 'cv-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array(), '1.2.0', true );
			wp_localize_script( 'cv-admin', 'CVVehicleData', self::all_term_data() );
		}
	}

	public static function enqueue_media() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( in_array( $screen->taxonomy, array_merge( array( self::TYPE_TAX ), array_keys( self::$taxonomies ) ), true ) || ( self::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) ) ) {
			wp_enqueue_media();
		}
	}

	public static function add_help_page() {
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'Como cadastrar opções', 'Como cadastrar', 'manage_categories', 'cv-ajuda', array( __CLASS__, 'render_help_page' ) );
	}

	public static function add_type_list_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'Tipos de Veículo', 'Tipos de Veículo', 'manage_categories', 'edit-tags.php?taxonomy=' . self::TYPE_TAX . '&post_type=' . self::POST_TYPE, '__return_null' );
	}

	public static function remove_type_list_submenu() {
		remove_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'edit-tags.php?taxonomy=' . self::TYPE_TAX . '&post_type=' . self::POST_TYPE );
	}

	public static function render_help_page() {
		echo '<div class="wrap"><h1>Como cadastrar as opções dos veículos</h1><ol><li>Cadastre primeiro os Tipos de Veículo (por exemplo, Carro e Moto).</li><li>Cadastre Marcas e associe cada uma a um ou mais tipos.</li><li>Cadastre Modelos, associando cada modelo a uma marca e ao tipo correspondente.</li><li>Cadastre Versões, associando cada versão ao modelo correspondente.</li><li>Cadastre Estilos e Opcionais e marque os tipos aos quais se aplicam.</li><li>As imagens dos termos são opcionais.</li></ol><p>O formulário do veículo filtra marcas, modelos, versões, estilos e opcionais conforme as relações cadastradas.</p></div>';
	}

	public static function maybe_show_rewrite_notice() {
		if ( get_transient( 'cv_rewrite_notice' ) && current_user_can( 'manage_options' ) ) {
			delete_transient( 'cv_rewrite_notice' );
			echo '<div class="notice notice-info is-dismissible"><p>O cadastro de Veículos foi ativado. Se os endereços ainda não abrirem, acesse Configurações → Links permanentes e clique em Salvar alterações.</p></div>';
		}
	}

	public static function activate() {
		self::register_content();
		flush_rewrite_rules();
		set_transient( 'cv_rewrite_notice', 1, MINUTE_IN_SECONDS );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}

Cadastro_Veiculos::init();
register_activation_hook( __FILE__, array( 'Cadastro_Veiculos', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Cadastro_Veiculos', 'deactivate' ) );
