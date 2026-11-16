<?php
/**
 * Plugin Name: Leads
 * Description: Registra cliques de WhatsApp e telefone e contatos enviados em anúncios de veículos.
 * Version: 1.0.0
 * Text Domain: leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Leads_Plugin {
	const DB_VERSION = '1.0.0';
	const TABLE_SUFFIX = 'leads_records';
	const BLOCKED_IPS_OPTION = 'leads_blocked_ips';
	const NONCE_ACTION = 'leads_capture_event';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install_database' ), 1 );
		add_action( 'init', array( __CLASS__, 'migrate_legacy_records' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'wp_ajax_leads_track_event', array( __CLASS__, 'ajax_track_event' ) );
		add_action( 'wp_ajax_nopriv_leads_track_event', array( __CLASS__, 'ajax_track_event' ) );
		add_action( 'wp_ajax_leads_submit_whatsapp', array( __CLASS__, 'ajax_submit_whatsapp' ) );
		add_action( 'wp_ajax_nopriv_leads_submit_whatsapp', array( __CLASS__, 'ajax_submit_whatsapp' ) );
		add_action( 'leads_record_email_submission', array( __CLASS__, 'record_email_submission' ), 10, 6 );
		add_filter( 'cv_contact_form_allowed', array( __CLASS__, 'filter_contact_form_allowed' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_style' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_action' ) );
	}

	public static function activate() {
		self::create_table();
	}

	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function maybe_install_database() {
		if ( get_option( 'leads_db_version' ) !== self::DB_VERSION ) {
			self::create_table();
		}
	}

	private static function create_table() {
		global $wpdb;
		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			vehicle_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			seller_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(190) NOT NULL DEFAULT '',
			visitor_name VARCHAR(190) NOT NULL DEFAULT '',
			visitor_email VARCHAR(190) NOT NULL DEFAULT '',
			visitor_phone VARCHAR(50) NOT NULL DEFAULT '',
			message TEXT NULL,
			channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
			status VARCHAR(20) NOT NULL DEFAULT 'click',
			PRIMARY KEY  (id),
			KEY vehicle_id (vehicle_id),
			KEY seller_id (seller_id),
			KEY created_at (created_at),
			KEY channel (channel),
			KEY status (status),
			KEY ip (ip)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'leads_db_version', self::DB_VERSION, false );
	}

	public static function migrate_legacy_records() {
		if ( get_option( 'leads_legacy_cpt_migrated' ) ) {
			return;
		}
		global $wpdb;
		$legacy_posts = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_date FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY ID ASC", 'cv_lead', 'private' )
		);
		foreach ( (array) $legacy_posts as $legacy ) {
			$vehicle_id = (int) get_post_meta( $legacy->ID, '_cv_lead_vehicle_id', true );
			if ( ! $vehicle_id || get_post_type( $vehicle_id ) !== 'veiculos' ) {
				continue;
			}
			$old_channel = get_post_meta( $legacy->ID, '_cv_lead_type', true );
			$channel = 'email' === $old_channel ? 'email' : ( 'phone' === $old_channel ? 'phone' : 'whatsapp' );
			self::insert_record( array(
				'vehicle_id' => $vehicle_id,
				'created_at' => $legacy->post_date,
				'ip' => '',
				'seller_id' => (int) get_post_meta( $legacy->ID, '_cv_lead_seller_id', true ),
				'category' => self::vehicle_category( $vehicle_id ),
				'visitor_name' => get_post_meta( $legacy->ID, '_cv_lead_name', true ),
				'visitor_email' => get_post_meta( $legacy->ID, '_cv_lead_email', true ),
				'visitor_phone' => get_post_meta( $legacy->ID, '_cv_lead_phone', true ),
				'message' => get_post_meta( $legacy->ID, '_cv_lead_message', true ),
				'channel' => $channel,
				'status' => get_post_meta( $legacy->ID, '_cv_lead_status', true ),
			) );
		}
		update_option( 'leads_legacy_cpt_migrated', 1, false );
	}

	public static function enqueue_frontend_assets() {
		if ( ! is_singular( 'veiculos' ) ) {
			return;
		}
		$script = plugin_dir_path( __FILE__ ) . 'assets/leads.js';
		wp_enqueue_script( 'leads-frontend', plugin_dir_url( __FILE__ ) . 'assets/leads.js', array(), file_exists( $script ) ? (string) filemtime( $script ) : self::DB_VERSION, true );
		wp_localize_script( 'leads-frontend', 'LeadsFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'vehicleId' => get_queried_object_id(),
		) );
	}

	private static function current_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function blocked_ips() {
		$ips = get_option( self::BLOCKED_IPS_OPTION, array() );
		return is_array( $ips ) ? $ips : array();
	}

	private static function is_blocked_ip( $ip ) {
		$ip = trim( (string) $ip );
		$ips = self::blocked_ips();
		return $ip && isset( $ips[ $ip ] );
	}

	public static function filter_contact_form_allowed( $allowed, $vehicle_id ) {
		return self::is_blocked_ip( self::current_ip() ) ? false : $allowed;
	}

	private static function vehicle_category( $vehicle_id ) {
		$terms = wp_get_post_terms( $vehicle_id, 'tipo_veiculo', array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? '' : implode( ', ', $terms );
	}

	private static function insert_record( $record ) {
		global $wpdb;
		$vehicle_id = isset( $record['vehicle_id'] ) ? absint( $record['vehicle_id'] ) : 0;
		if ( ! $vehicle_id || 'veiculos' !== get_post_type( $vehicle_id ) ) {
			return 0;
		}
		$defaults = array(
			'vehicle_id' => $vehicle_id,
			'created_at' => current_time( 'mysql' ),
			'ip' => self::current_ip(),
			'seller_id' => (int) get_post_field( 'post_author', $vehicle_id ),
			'category' => self::vehicle_category( $vehicle_id ),
			'visitor_name' => '',
			'visitor_email' => '',
			'visitor_phone' => '',
			'message' => '',
			'channel' => 'whatsapp',
			'status' => 'click',
		);
		$record = wp_parse_args( $record, $defaults );
		$inserted = $wpdb->insert( self::table_name(), $record, array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	public static function ajax_track_event() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( self::is_blocked_ip( self::current_ip() ) ) {
			wp_send_json_error( array( 'message' => 'Acesso bloqueado.' ), 403 );
		}
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		if ( ! in_array( $channel, array( 'whatsapp', 'phone' ), true ) || 'publish' !== get_post_status( $vehicle_id ) || 'veiculos' !== get_post_type( $vehicle_id ) ) {
			wp_send_json_error( array( 'message' => 'Contato inválido.' ), 400 );
		}
		$record_id = self::insert_record( array( 'vehicle_id' => $vehicle_id, 'channel' => $channel, 'status' => 'click' ) );
		if ( ! $record_id ) {
			wp_send_json_error( array( 'message' => 'Não foi possível registrar o clique.' ), 500 );
		}
		wp_send_json_success( array( 'record_id' => $record_id ) );
	}

	public static function ajax_submit_whatsapp() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( self::is_blocked_ip( self::current_ip() ) ) {
			wp_send_json_error( array( 'message' => 'Acesso bloqueado.' ), 403 );
		}
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? absint( wp_unslash( $_POST['record_id'] ) ) : 0;
		if ( 'publish' !== get_post_status( $vehicle_id ) || 'veiculos' !== get_post_type( $vehicle_id ) || ! $name || ! is_email( $email ) || strlen( preg_replace( '/\D+/', '', $phone ) ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Preencha seu nome, um e-mail válido e um WhatsApp.' ), 400 );
		}
		global $wpdb;
		$existing = $record_id ? $wpdb->get_row( $wpdb->prepare( "SELECT id, ip FROM " . self::table_name() . " WHERE id = %d AND vehicle_id = %d AND channel = %s AND status = %s", $record_id, $vehicle_id, 'whatsapp', 'click' ) ) : null;
		if ( $existing && $existing->ip === self::current_ip() ) {
			$wpdb->update( self::table_name(), array(
				'visitor_name' => $name,
				'visitor_email' => $email,
				'visitor_phone' => $phone,
				'status' => 'lead',
			), array( 'id' => $record_id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$record_id = self::insert_record( array( 'vehicle_id' => $vehicle_id, 'channel' => 'whatsapp', 'status' => 'lead', 'visitor_name' => $name, 'visitor_email' => $email, 'visitor_phone' => $phone ) );
		}
		if ( ! $record_id ) {
			wp_send_json_error( array( 'message' => 'Não foi possível registrar seus dados.' ), 500 );
		}
		$seller_id = (int) get_post_field( 'post_author', $vehicle_id );
		$number = preg_replace( '/\D+/', '', (string) get_user_meta( $seller_id, 'cv_whatsapp', true ) );
		if ( $number && 0 !== strpos( $number, '55' ) ) {
			$number = '55' . $number;
		}
		if ( ! $number ) {
			wp_send_json_error( array( 'message' => 'O anunciante ainda não cadastrou um WhatsApp.' ), 400 );
		}
		$message = sprintf( "Olá, estou no site do Veículos DF e tenho interesse no anúncio: %1$s.\n%2$s", get_the_title( $vehicle_id ), get_permalink( $vehicle_id ) );
		wp_send_json_success( array( 'whatsapp_url' => add_query_arg( 'text', $message, 'https://wa.me/' . $number ) ) );
	}

	public static function record_email_submission( $vehicle_id, $name, $email, $phone, $message, $sent ) {
		if ( self::is_blocked_ip( self::current_ip() ) || ! $name || ! is_email( $email ) || ! $message ) {
			return;
		}
		self::insert_record( array(
			'vehicle_id' => $vehicle_id,
			'visitor_name' => sanitize_text_field( $name ),
			'visitor_email' => sanitize_email( $email ),
			'visitor_phone' => sanitize_text_field( $phone ),
			'message' => sanitize_textarea_field( $message ),
			'channel' => 'email',
			'status' => $sent ? 'lead' : 'error',
		) );
	}

	public static function add_admin_menu() {
		add_menu_page( 'Leads', 'Lead', 'manage_options', 'leads', array( __CLASS__, 'render_admin_page' ), 'dashicons-chart-line', 25 );
	}

	public static function enqueue_admin_style( $hook ) {
		if ( 'toplevel_page_leads' !== $hook ) {
			return;
		}
		$style_path = plugin_dir_path( __FILE__ ) . 'assets/admin.css';
		wp_enqueue_style( 'leads-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), file_exists( $style_path ) ? (string) filemtime( $style_path ) : self::DB_VERSION );
	}

	public static function handle_admin_action() {
		if ( empty( $_POST['leads_admin_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'leads_admin_action', 'leads_admin_nonce' );
		$action = sanitize_key( wp_unslash( $_POST['leads_admin_action'] ) );
		$record_id = isset( $_POST['record_id'] ) ? absint( wp_unslash( $_POST['record_id'] ) ) : 0;
		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		$ips = self::blocked_ips();
		if ( 'delete' === $action && $record_id ) {
			global $wpdb;
			$wpdb->delete( self::table_name(), array( 'id' => $record_id ), array( '%d' ) );
		}
		if ( 'block_ip' === $action && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ips[ $ip ] = array( 'date' => current_time( 'mysql' ) );
			update_option( self::BLOCKED_IPS_OPTION, $ips, false );
		}
		if ( 'unblock_ip' === $action && isset( $ips[ $ip ] ) ) {
			unset( $ips[ $ip ] );
			update_option( self::BLOCKED_IPS_OPTION, $ips, false );
		}
		$return = isset( $_POST['return_url'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), admin_url( 'admin.php?page=leads' ) ) : admin_url( 'admin.php?page=leads' );
		wp_safe_redirect( $return );
		exit;
	}

	private static function valid_admin_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		$date = DateTime::createFromFormat( 'Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table = self::table_name();
		$seller_raw = isset( $_GET['seller_id'] ) ? sanitize_text_field( wp_unslash( $_GET['seller_id'] ) ) : '';
		$seller_id = '' === $seller_raw ? null : absint( $seller_raw );
		$channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
		if ( ! in_array( $channel, array( '', 'whatsapp', 'phone', 'email' ), true ) ) {
			$channel = '';
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! in_array( $status, array( '', 'click', 'lead', 'error' ), true ) ) {
			$status = '';
		}
		$from = isset( $_GET['date_from'] ) ? self::valid_admin_date( wp_unslash( $_GET['date_from'] ) ) : '';
		$to = isset( $_GET['date_to'] ) ? self::valid_admin_date( wp_unslash( $_GET['date_to'] ) ) : '';
		$where = array( '1=1' );
		$params = array();
		if ( null !== $seller_id ) { $where[] = 'seller_id = %d'; $params[] = $seller_id; }
		if ( $channel ) { $where[] = 'channel = %s'; $params[] = $channel; }
		if ( $status ) { $where[] = 'status = %s'; $params[] = $status; }
		if ( $from ) { $where[] = 'created_at >= %s'; $params[] = $from . ' 00:00:00'; }
		if ( $to ) { $where[] = 'created_at <= %s'; $params[] = $to . ' 23:59:59'; }
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$filled = 0;
		$clicks = 0;
		if ( 'lead' === $status ) {
			$filled = $total;
		} elseif ( 'click' === $status ) {
			$clicks = $total;
		} elseif ( 'error' !== $status ) {
			$lead_where = array_merge( $where, array( 'status = %s' ) );
			$click_where = array_merge( $where, array( 'status = %s' ) );
			$filled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $lead_where ), array_merge( $params, array( 'lead' ) ) ) );
			$clicks = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $click_where ), array_merge( $params, array( 'click' ) ) ) );
		}
		$conversion = ( $filled + $clicks ) ? number_format_i18n( 100 * $filled / ( $filled + $clicks ), 1 ) . '%' : '0%';
		$per_page = 50;
		$paged = max( 1, isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 );
		$query_params = $params;
		$query_params[] = $per_page;
		$query_params[] = ( $paged - 1 ) * $per_page;
		$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $query_params ) );
		$seller_ids = $wpdb->get_col( "SELECT DISTINCT seller_id FROM {$table} ORDER BY seller_id ASC" );
		$sellers = $seller_ids ? get_users( array( 'include' => array_map( 'absint', $seller_ids ), 'orderby' => 'display_name' ) ) : array();
		$blocked_ips = self::blocked_ips();
		$return_url = remove_query_arg( 'paged' );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		echo '<div class="wrap leads-admin"><h1>Leads</h1><form class="leads-filters" method="get"><input type="hidden" name="page" value="leads"><label>Anunciante<select name="seller_id"><option value="">Todos</option>';
		foreach ( $sellers as $seller ) { echo '<option value="' . (int) $seller->ID . '" ' . selected( $seller_id, $seller->ID, false ) . '>' . esc_html( $seller->display_name ) . '</option>'; }
		echo '</select></label><label>Canal<select name="channel"><option value="">Todos</option><option value="phone" ' . selected( $channel, 'phone', false ) . '>Clique no telefone</option><option value="whatsapp" ' . selected( $channel, 'whatsapp', false ) . '>WhatsApp</option><option value="email" ' . selected( $channel, 'email', false ) . '>Envio de e-mail</option></select></label><label>Status<select name="status"><option value="">Todos</option><option value="lead" ' . selected( $status, 'lead', false ) . '>Lead</option><option value="click" ' . selected( $status, 'click', false ) . '>Clique</option><option value="error" ' . selected( $status, 'error', false ) . '>Falha</option></select></label><label>De<input type="date" name="date_from" value="' . esc_attr( $from ) . '"></label><label>Até<input type="date" name="date_to" value="' . esc_attr( $to ) . '"></label><button class="button button-primary">Filtrar</button><a class="button" href="' . esc_url( admin_url( 'admin.php?page=leads' ) ) . '">Limpar</a></form>';
		echo '<p class="description">Indicadores para o anunciante e período selecionados.</p><div class="leads-stats"><div><span>Registros</span><strong>' . number_format_i18n( $total ) . '</strong></div><div><span>Leads preenchidos</span><strong>' . number_format_i18n( $filled ) . '</strong></div><div><span>Cliques sem preenchimento</span><strong>' . number_format_i18n( $clicks ) . '</strong></div><div><span>Conversão</span><strong>' . esc_html( $conversion ) . '</strong></div></div>';
		echo '<div class="leads-table-wrap"><table class="widefat striped"><thead><tr><th>ID</th><th>Veículo</th><th>Data</th><th>Nome</th><th>E-mail</th><th>Telefone / WhatsApp</th><th>Mensagem</th><th>Canal</th><th>Status</th><th>Anunciante</th><th>IP</th><th>Ações</th></tr></thead><tbody>';
		if ( ! $rows ) { echo '<tr><td colspan="12">Nenhum registro encontrado.</td></tr>'; }
		foreach ( $rows as $row ) {
			$seller = get_userdata( $row->seller_id );
			$title = get_the_title( $row->vehicle_id );
			$channel_labels = array( 'whatsapp' => 'WhatsApp', 'phone' => 'Telefone', 'email' => 'E-mail' );
			$status_labels = array( 'lead' => 'Lead', 'click' => 'Clique', 'error' => 'Falha' );
			echo '<tr><td>' . (int) $row->id . '</td><td>' . ( $title ? '<a href="' . esc_url( get_permalink( $row->vehicle_id ) ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>' : 'Veículo #' . (int) $row->vehicle_id ) . '</td><td>' . esc_html( mysql2date( 'd/m/Y H:i:s', $row->created_at ) ) . '</td><td>' . esc_html( $row->visitor_name ?: '—' ) . '</td><td>' . ( $row->visitor_email ? '<a href="mailto:' . esc_attr( $row->visitor_email ) . '">' . esc_html( $row->visitor_email ) . '</a>' : '—' ) . '</td><td>' . esc_html( $row->visitor_phone ?: '—' ) . '</td><td>' . ( $row->message ? '<details><summary>Ver mensagem</summary>' . nl2br( esc_html( $row->message ) ) . '</details>' : '—' ) . '</td><td>' . esc_html( isset( $channel_labels[ $row->channel ] ) ? $channel_labels[ $row->channel ] : $row->channel ) . '</td><td>' . esc_html( isset( $status_labels[ $row->status ] ) ? $status_labels[ $row->status ] : $row->status ) . '</td><td>' . esc_html( $seller ? $seller->display_name : '—' ) . '</td><td>' . esc_html( $row->ip ?: '—' ) . ( self::is_blocked_ip( $row->ip ) ? '<br><strong>Bloqueado</strong>' : '' ) . '</td><td><div class="leads-actions">';
			echo '<form method="post"><input type="hidden" name="leads_admin_action" value="delete"><input type="hidden" name="record_id" value="' . (int) $row->id . '"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">' . wp_nonce_field( 'leads_admin_action', 'leads_admin_nonce', true, false ) . '<button class="button button-small" onclick="return confirm(\'Excluir este registro?\')">Excluir</button></form>';
			if ( $row->ip && ! self::is_blocked_ip( $row->ip ) ) { echo '<form method="post"><input type="hidden" name="leads_admin_action" value="block_ip"><input type="hidden" name="ip" value="' . esc_attr( $row->ip ) . '"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">' . wp_nonce_field( 'leads_admin_action', 'leads_admin_nonce', true, false ) . '<button class="button button-small">Bloquear IP</button></form>'; }
			if ( $row->ip && self::is_blocked_ip( $row->ip ) ) { echo '<form method="post"><input type="hidden" name="leads_admin_action" value="unblock_ip"><input type="hidden" name="ip" value="' . esc_attr( $row->ip ) . '"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">' . wp_nonce_field( 'leads_admin_action', 'leads_admin_nonce', true, false ) . '<button class="button button-small">Desbloquear IP</button></form>'; }
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div>';
		if ( $pages > 1 ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) ) . '</div></div>'; }
		if ( $blocked_ips ) {
			echo '<details class="leads-blocked-ips"><summary>IPs bloqueados (' . number_format_i18n( count( $blocked_ips ) ) . ')</summary><table class="widefat striped"><thead><tr><th>IP</th><th>Bloqueado em</th><th>Ação</th></tr></thead><tbody>';
			foreach ( $blocked_ips as $ip => $details ) {
				echo '<tr><td>' . esc_html( $ip ) . '</td><td>' . esc_html( isset( $details['date'] ) ? mysql2date( 'd/m/Y H:i:s', $details['date'] ) : '—' ) . '</td><td><form method="post"><input type="hidden" name="leads_admin_action" value="unblock_ip"><input type="hidden" name="ip" value="' . esc_attr( $ip ) . '"><input type="hidden" name="return_url" value="' . esc_attr( $return_url ) . '">' . wp_nonce_field( 'leads_admin_action', 'leads_admin_nonce', true, false ) . '<button class="button button-small">Desbloquear</button></form></td></tr>';
			}
			echo '</tbody></table></details>';
		}
		echo '</div>';
	}
}

Leads_Plugin::init();
register_activation_hook( __FILE__, array( 'Leads_Plugin', 'activate' ) );
