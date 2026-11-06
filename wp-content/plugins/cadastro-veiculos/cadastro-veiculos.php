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

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_content' ) );
		add_action( 'init', array( __CLASS__, 'seed_default_styles' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_vehicle_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_vehicle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_media' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_help_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_type_list_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_type_list_submenu' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_rewrite_notice' ) );

		foreach ( array_merge( array( self::TYPE_TAX ), array_keys( self::$taxonomies ) ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'term_add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'term_edit_fields' ) );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_term_fields' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term_fields' ) );
		}
		add_action( 'created_' . self::TYPE_TAX, array( __CLASS__, 'assign_default_styles_to_type' ) );
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

	public static function add_vehicle_meta_box() {
		add_meta_box( 'cv_vehicle_data', 'Dados do veículo', array( __CLASS__, 'render_vehicle_meta_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'cv_vehicle_gallery', 'Galeria de fotos', array( __CLASS__, 'render_vehicle_gallery' ), self::POST_TYPE, 'normal', 'high' );
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
			'cor' => array( 'Cor', 'select' ),
			'quilometragem' => array( 'Quilometragem', 'number' ),
		);
		echo '<div class="cv-vehicle-fields">';
		self::render_term_select( 'tipo_veiculo', 'Tipo de Veículo', $post->ID, false, self::TYPE_TAX );
		self::render_term_select( 'marca', 'Marca', $post->ID, false, 'marca' );
		self::render_term_select( 'modelo', 'Modelo', $post->ID, false, 'modelo' );
		self::render_term_select( 'versao', 'Versão', $post->ID, false, 'versao' );
		self::render_term_select( 'estilo', 'Estilo', $post->ID, false, 'estilo' );
		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, '_cv_' . $key, true );
			if ( 'cor' === $key ) {
				echo '<p><label for="cv_cor"><strong>Cor</strong></label><br><select id="cv_cor" name="cv_fields[cor]"><option value="">Selecione</option>';
				foreach ( self::color_options() as $color ) {
					echo '<option value="' . esc_attr( $color ) . '" ' . selected( $value, $color, false ) . '>' . esc_html( $color ) . '</option>';
				}
				echo '</select></p>';
				continue;
			}
			$min = 'number' === $field[1] ? ' min="0" step="1"' : '';
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
		foreach ( array( 'motor', 'cor', 'cambio' ) as $key ) {
			$value = isset( $fields[ $key ] ) ? sanitize_text_field( $fields[ $key ] ) : '';
			if ( 'cor' === $key && ! in_array( $value, self::color_options(), true ) ) {
				$value = '';
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
		add_menu_page( 'Tipos de Veículo', 'Tipos de Veículo', 'manage_categories', 'edit-tags.php?taxonomy=' . self::TYPE_TAX . '&post_type=' . self::POST_TYPE, '', 'dashicons-tag', 21 );
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
