<?php
/**
 * Plugin Name: Agent Cars
 * Description: Importa e atualiza anúncios da Casa do Automóvel pelo WP-Cron.
 * Version: 0.2.0
 * Text Domain: agent-cars
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/adapters/interface-agent-cars-adapter.php';
require_once __DIR__ . '/adapters/class-casa-do-automovel-adapter.php';
require_once __DIR__ . '/adapters/class-salao-do-automovel-adapter.php';
require_once __DIR__ . '/adapters/class-brasilia-motos-adapter.php';

final class Agent_Cars {
	const HOOK      = 'agent_cars_hourly_import';
	const ENV_FILE  = '.env';

	private static function adapters() {
		static $adapters;
		if ( null === $adapters ) {
			$adapters = array( new Agent_Cars_Casa_Do_Automovel_Adapter(), new Agent_Cars_Salao_Do_Automovel_Adapter(), new Agent_Cars_Brasilia_Motos_Adapter() );
			$adapters = apply_filters( 'agent_cars_adapters', $adapters );
		}
		return $adapters;
	}

	private static function adapter_for( $url, $requested_id = '' ) {
		foreach ( self::adapters() as $adapter ) {
			if ( $requested_id && $adapter->id() !== $requested_id ) { continue; }
			if ( $adapter->supports( $url ) ) { return $adapter; }
		}
		return null;
	}

	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_post_agent_cars_run', array( __CLASS__, 'manual_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'http_request_args', array( __CLASS__, 'http_args' ), 10, 2 );
	}

	public static function activate() { self::ensure_schedule(); }
	public static function deactivate() { wp_clear_scheduled_hook( self::HOOK ); }

	public static function cron_schedules( $schedules ) {
		$schedules['agent_cars_hourly'] = array( 'interval' => HOUR_IN_SECONDS, 'display' => 'A cada hora (Agent Cars)' );
		return $schedules;
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'agent_cars_hourly', self::HOOK );
		}
	}

	public static function manual_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Apenas administradores podem executar a importação.' );
		}
		check_admin_referer( 'agent_cars_run' );
		$result = self::run();
		set_transient( 'agent_cars_last_manual_result', $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'agent_cars_run', '1', admin_url( 'plugins.php' ) ) );
		exit;
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_GET['agent_cars_run'] ) ) { return; }
		$result = get_transient( 'agent_cars_last_manual_result' );
		delete_transient( 'agent_cars_last_manual_result' );
		if ( ! is_array( $result ) ) { return; }
		printf( '<div class="notice notice-info"><p>Agent Cars: %s anúncios criados, %s atualizados, %s falhas. Detalhes em Ferramentas → Agent Cars.</p></div>', esc_html( $result['created'] ), esc_html( $result['updated'] ), esc_html( $result['errors'] ) );
	}

	public static function admin_menu() {
		add_management_page( 'Agent Cars', 'Agent Cars', 'manage_options', 'agent-cars', array( __CLASS__, 'render_tools_page' ) );
	}

	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$run_url = wp_nonce_url( admin_url( 'admin-post.php?action=agent_cars_run' ), 'agent_cars_run' );
		$last = get_option( 'agent_cars_last_run' );
			echo '<div class="wrap"><h1>Agent Cars</h1><p>Importa anúncios das origens configuradas em <code>.env</code>. O adaptador é selecionado pelo domínio; itens existentes são atualizados pela origem e pela ID do anúncio.</p><p><a class="button button-primary" href="' . esc_url( $run_url ) . '">Executar importação agora</a></p>';
		if ( is_array( $last ) ) { echo '<p>Última execução: ' . esc_html( $last['time'] ?? '' ) . ' — ' . esc_html( wp_json_encode( $last['result'] ?? array() ) ) . '</p>'; }
		echo '</div>';
	}

	public static function http_args( $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$trusted_hosts = array();
		foreach ( self::adapters() as $adapter ) { $trusted_hosts = array_merge( $trusted_hosts, (array) $adapter->trusted_hosts() ); }
		foreach ( self::adapters() as $adapter ) {
			if ( in_array( $host, (array) $adapter->trusted_hosts(), true ) ) {
				$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), (array) $adapter->request_headers() );
				if ( $adapter->user_agent() ) { $args['user-agent'] = $adapter->user_agent(); }
			}
		}
		if ( in_array( $host, $trusted_hosts, true ) ) {
			$local_bundle = trailingslashit( ABSPATH ) . '.agent-cars-local-ca.pem';
			$bundle = is_readable( $local_bundle ) ? $local_bundle : plugin_dir_path( __FILE__ ) . 'cacert.pem';
			if ( is_readable( $bundle ) ) { $args['sslcertificates'] = $bundle; }
		}
		return $args;
	}

	private static function settings() {
		$settings = array(
			'sources' => array(),
			'limit' => 5,
		);
		$file = trailingslashit( ABSPATH ) . self::ENV_FILE;
		if ( is_readable( $file ) ) {
			$values = array();
			foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
				$line = trim( $line );
				if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) { continue; }
				list( $key, $value ) = explode( '=', $line, 2 );
				$value = trim( $value );
				if ( strlen( $value ) > 1 && ( ( '"' === $value[0] && '"' === substr( $value, -1 ) ) || ( "'" === $value[0] && "'" === substr( $value, -1 ) ) ) ) { $value = substr( $value, 1, -1 ); }
				$values[ trim( $key ) ] = $value;
			}
			if ( isset( $values['AGENT_CARS_SOURCES'] ) ) {
				$sources = json_decode( $values['AGENT_CARS_SOURCES'], true );
				if ( is_array( $sources ) ) {
					$settings['sources'] = array();
					foreach ( $sources as $source ) {
						if ( is_string( $source ) ) { $source = array( 'url' => $source ); }
					if ( ! is_array( $source ) || empty( $source['url'] ) || ! wp_http_validate_url( $source['url'] ) ) { continue; }
						$settings['sources'][] = $source;
					}
				}
			}
			if ( isset( $values['AGENT_CARS_LIMIT'] ) ) { $settings['limit'] = max( 1, min( 100, absint( $values['AGENT_CARS_LIMIT'] ) ) ); }
		}
		return $settings;
	}

	public static function run() {
		$result = array( 'created' => 0, 'updated' => 0, 'errors' => 0 );
		if ( ! post_type_exists( 'veiculos' ) ) { self::log( 'Post type veiculos indisponível.' ); return $result; }
		$settings = self::settings();
		foreach ( $settings['sources'] as $source_config ) {
			$source_url = $source_config['url'];
			$adapter = self::adapter_for( $source_url, isset( $source_config['adapter'] ) ? sanitize_key( $source_config['adapter'] ) : '' );
			if ( ! $adapter ) { ++$result['errors']; self::log( 'Nenhum adaptador registrado atende a origem ' . $source_url ); continue; }
			$html = self::fetch( $source_url, $adapter );
			if ( is_wp_error( $html ) ) { ++$result['errors']; self::log( $html->get_error_message() . ' ' . $source_url ); continue; }
			$links = $adapter->listing_urls( $html, $source_url );
			if ( ! $links ) {
				$html = self::fetch( $source_url, $adapter );
				if ( ! is_wp_error( $html ) ) { $links = $adapter->listing_urls( $html, $source_url ); }
			}
			if ( ! $links ) { ++$result['errors']; self::log( 'Nenhum anúncio encontrado em ' . $source_url ); continue; }
			$limit = isset( $source_config['limit'] ) ? max( 1, min( 100, absint( $source_config['limit'] ) ) ) : $settings['limit'];
			$links = array_slice( $links, 0, $limit );
			foreach ( $links as $listing_url ) {
				$vehicle = $adapter->vehicle_from_listing( $listing_url );
				if ( null === $vehicle ) {
					$detail_html = self::fetch( $listing_url, $adapter );
					if ( is_wp_error( $detail_html ) ) { ++$result['errors']; self::log( $detail_html->get_error_message() . ' ' . $listing_url ); continue; }
					$vehicle = $adapter->parse_vehicle( $detail_html, $listing_url );
				}
				if ( is_wp_error( $vehicle ) ) { ++$result['errors']; self::log( $vehicle->get_error_message() . ' ' . $listing_url ); continue; }
				$vehicle['source_key'] = $adapter->id();
				$vehicle['seller'] = $adapter->seller_data( $source_config );
				$status = self::import_vehicle( $vehicle );
				if ( is_wp_error( $status ) ) { ++$result['errors']; self::log( $status->get_error_message() ); }
				else { ++$result[ $status ]; }
			}
		}
		update_option( 'agent_cars_last_run', array( 'time' => current_time( 'mysql' ), 'result' => $result ), false );
		self::log( sprintf( 'Execução concluída: %d novos, %d atualizados, %d falhas.', $result['created'], $result['updated'], $result['errors'] ) );
		return $result;
	}

	private static function fetch( $url, $adapter = null ) {
		$args = array( 'timeout' => 30, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; AgentCars/0.3; WordPress)' );
		if ( $adapter && $adapter->user_agent() ) { $args['user-agent'] = $adapter->user_agent(); }
		if ( $adapter ) { $args['headers'] = $adapter->request_headers(); }
		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'agent_cars_http', $response->get_error_message() ); }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) { return new WP_Error( 'agent_cars_http_status', 'HTTP ' . $code . ' ao consultar ' . $url ); }
		return wp_remote_retrieve_body( $response );
	}

	private static function import_vehicle( $vehicle ) {
		$seller = isset( $vehicle['seller'] ) && is_array( $vehicle['seller'] ) ? $vehicle['seller'] : array();
		if ( empty( $seller['email'] ) || ! is_email( $seller['email'] ) ) { return new WP_Error( 'agent_cars_seller_email', 'A fonte não forneceu um e-mail válido para o anunciante.' ); }
		$source_key = sanitize_key( $vehicle['source_key'] ?? '' );
		$existing = get_posts( array( 'post_type' => 'veiculos', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_agent_cars_source', 'value' => $source_key ), array( 'key' => '_agent_cars_source_id', 'value' => $vehicle['external_id'] ) ) ) );
		if ( ! $existing ) {
			$existing = get_posts( array( 'post_type' => 'veiculos', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_cv_source_url', 'meta_value' => $vehicle['url'] ) );
		}
		$post_id = $existing ? (int) $existing[0] : 0;
		$user_id = self::store_user( $seller );
		if ( is_wp_error( $user_id ) ) { return $user_id; }
		$postarr = array( 'post_type' => 'veiculos', 'post_title' => $vehicle['title'], 'post_status' => 'publish', 'post_author' => $user_id );
		if ( $post_id ) { $postarr['ID'] = $post_id; $post_id = wp_update_post( $postarr, true ); }
		else { $post_id = wp_insert_post( $postarr, true ); }
		if ( is_wp_error( $post_id ) ) { return $post_id; }
		update_post_meta( $post_id, '_agent_cars_source', $source_key );
		update_post_meta( $post_id, '_agent_cars_source_id', $vehicle['external_id'] );
		update_post_meta( $post_id, '_cv_source_url', esc_url_raw( $vehicle['url'] ) );
		update_post_meta( $post_id, '_cv_advertiser_name', sanitize_text_field( $seller['name'] ?? '' ) );
		update_post_meta( $post_id, '_cv_advertiser_whatsapp', sanitize_text_field( $seller['whatsapp'] ?? '' ) );
		update_post_meta( $post_id, '_cv_advertiser_phone', sanitize_text_field( $seller['phone'] ?? '' ) );
		update_post_meta( $post_id, '_cv_preco', number_format( (float) $vehicle['price'], 2, '.', '' ) );
		foreach ( $vehicle['fields'] as $key => $value ) {
			if ( '' !== (string) $value ) { update_post_meta( $post_id, '_cv_' . $key, $value ); }
			elseif ( 'ano_fabricacao' === $key ) { delete_post_meta( $post_id, '_cv_ano_fabricacao' ); }
		}
		if ( $vehicle['description'] ) { wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_kses_post( $vehicle['description'] ) ) ); }
		self::assign_term( $post_id, 'marca', $vehicle['brand'] );
		$model_id = self::assign_term( $post_id, 'modelo', $vehicle['model'], 'marca' );
		if ( $model_id && $vehicle['version'] ) { self::assign_term( $post_id, 'versao', $vehicle['version'], 'modelo' ); }
		self::assign_term( $post_id, 'estilo', $vehicle['style'] );
		self::assign_term( $post_id, 'tipo_veiculo', $vehicle['vehicle_type'] ?? 'Carros' );
		$gallery = array();
		foreach ( $vehicle['images'] as $image_url ) {
			$attachment = self::sideload_image( $image_url, $post_id );
			if ( $attachment && ! is_wp_error( $attachment ) ) { $gallery[] = $attachment; }
		}
		if ( $gallery ) { update_post_meta( $post_id, '_cv_vehicle_gallery', $gallery ); set_post_thumbnail( $post_id, $gallery[0] ); }
		return $existing ? 'updated' : 'created';
	}

	private static function sideload_image( $url, $post_id ) {
		$exists = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_agent_cars_image_source', 'meta_value' => $url ) );
		if ( $exists ) { return (int) $exists[0]; }
		if ( ! function_exists( 'download_url' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; }
		$temp = download_url( $url, 30 );
		if ( is_wp_error( $temp ) ) { self::log( 'Falha ao baixar foto ' . $url . ': ' . $temp->get_error_message() ); return $temp; }
		$name = sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
		$attachment = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $temp ), $post_id );
		if ( is_wp_error( $attachment ) ) { @unlink( $temp ); return $attachment; }
		update_post_meta( $attachment, '_agent_cars_image_source', esc_url_raw( $url ) );
		return (int) $attachment;
	}

	private static function store_user( $seller ) {
		$user = get_user_by( 'email', $seller['email'] );
		if ( ! $user ) {
			$login_source = $seller['login'] ?? sanitize_user( current( explode( '@', $seller['email'] ) ), true );
			$login = sanitize_user( preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $login_source ), true );
			if ( username_exists( $login ) ) { $login .= substr( md5( $seller['email'] ), 0, 6 ); }
			$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'user_email' => $seller['email'], 'display_name' => $seller['name'] ?? $seller['email'], 'nickname' => $seller['name'] ?? $seller['email'], 'first_name' => $seller['first_name'] ?? '', 'last_name' => $seller['last_name'] ?? '', 'role' => 'subscriber', 'user_url' => $seller['website'] ?? '' ) );
			$user = is_wp_error( $id ) ? get_user_by( 'email', $seller['email'] ) : get_user_by( 'id', $id );
		}
		if ( ! $user ) { return new WP_Error( 'agent_cars_user', 'Não foi possível criar ou localizar a conta do anunciante.' ); }
		wp_update_user( array(
			'ID'           => $user->ID,
			'first_name'   => $seller['first_name'] ?? '',
			'last_name'    => $seller['last_name'] ?? '',
			'nickname'     => $seller['name'] ?? $seller['email'],
			'display_name' => $seller['name'] ?? $seller['email'],
			'user_url'     => $seller['website'] ?? '',
			'description'  => $seller['bio'] ?? '',
		) );
		$meta = array( 'cv_whatsapp' => $seller['whatsapp'] ?? '', 'cv_telefone' => $seller['phone'] ?? '', 'cv_endereco' => $seller['address'] ?? '', 'cv_cidade' => $seller['city'] ?? '', 'cv_estado' => $seller['state'] ?? '', 'cv_rating_google_maps' => $seller['maps_rating'] ?? '' );
		foreach ( $meta as $key => $value ) { update_user_meta( $user->ID, $key, $value ); }
		return (int) $user->ID;
	}

	private static function assign_term( $post_id, $taxonomy, $name, $parent_taxonomy = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! $name ) { return 0; }
		$parent_id = 0;
		if ( $parent_taxonomy ) { $parent = wp_get_object_terms( $post_id, $parent_taxonomy, array( 'fields' => 'ids' ) ); $parent_id = $parent && ! is_wp_error( $parent ) ? (int) $parent[0] : 0; }
		$term = term_exists( $name, $taxonomy, $parent_id );
		if ( ! $term ) { $term = wp_insert_term( $name, $taxonomy, $parent_id ? array( 'parent' => $parent_id ) : array() ); }
		if ( is_wp_error( $term ) ) { return 0; }
		$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		wp_set_object_terms( $post_id, array( $term_id ), $taxonomy, false );
		return $term_id;
	}

	public static function absolute_url( $url, $base ) {
		if ( 0 === strpos( $url, '//' ) ) { return 'https:' . $url; }
		if ( preg_match( '~^https?://~i', $url ) ) { return $url; }
		$parts = wp_parse_url( $base );
		if ( ! $parts || empty( $parts['host'] ) ) { return ''; }
		$origin = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'];
		if ( 0 === strpos( $url, '/' ) ) { return $origin . $url; }
		$path = isset( $parts['path'] ) ? trailingslashit( dirname( $parts['path'] ) ) : '/';
		return $origin . $path . $url;
	}

	private static function log( $message ) { error_log( '[Agent Cars] ' . $message ); }
}

Agent_Cars::boot();
register_activation_hook( __FILE__, array( 'Agent_Cars', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Agent_Cars', 'deactivate' ) );
