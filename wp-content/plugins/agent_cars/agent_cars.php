<?php
/**
 * Plugin Name: Agent Cars
 * Description: Agenda a coleta horária de URLs de lojas configuradas no .env.
 * Version: 0.1.0
 * Text Domain: agent-cars
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Agent_Cars {
	const HOOK = 'agent_cars_hourly_import';
	const ENV_FILE = '.env';

	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	public static function activate() {
		self::ensure_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['agent_cars_hourly'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'A cada hora (Agent Cars)', 'agent-cars' ),
		);
		return $schedules;
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'agent_cars_hourly', self::HOOK );
		}
	}

	private static function config() {
		$values = array();
		$file   = trailingslashit( ABSPATH ) . self::ENV_FILE;
		if ( is_readable( $file ) ) {
			$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) {
					continue;
				}
				list( $key, $value ) = explode( '=', $line, 2 );
				$key   = trim( $key );
				$value = trim( $value );
				if ( strlen( $value ) > 1 && ( ( '"' === $value[0] && '"' === substr( $value, -1 ) ) || ( "'" === $value[0] && "'" === substr( $value, -1 ) ) ) ) {
					$value = substr( $value, 1, -1 );
				}
				$values[ $key ] = $value;
			}
		}

		$sources = isset( $values['AGENT_CARS_SOURCES'] ) ? json_decode( $values['AGENT_CARS_SOURCES'], true ) : array();
		if ( ! is_array( $sources ) ) {
			return new WP_Error( 'agent_cars_invalid_sources', 'AGENT_CARS_SOURCES precisa ser uma lista JSON de URLs.' );
		}
		return array_values( array_filter( $sources, 'wp_http_validate_url' ) );
	}

	public static function run() {
		if ( ! post_type_exists( 'veiculos' ) ) {
			self::log( 'O post type veiculos não está registrado; importação ignorada.' );
			return;
		}
		$sources = self::config();
		if ( is_wp_error( $sources ) ) {
			self::log( $sources->get_error_message() );
			return;
		}
		if ( ! $sources ) {
			self::log( 'Nenhuma URL configurada em AGENT_CARS_SOURCES.' );
			return;
		}

		foreach ( $sources as $url ) {
			$response = wp_safe_remote_get( $url, array(
				'timeout'     => 30,
				'redirection' => 5,
				'user-agent'  => 'Agent Cars/0.1 WordPress vehicle inventory importer',
			) );
			if ( is_wp_error( $response ) ) {
				self::log( sprintf( 'Falha ao consultar %s: %s', $url, $response->get_error_message() ) );
				continue;
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				self::log( sprintf( 'A loja retornou HTTP %d em %s.', $code, $url ) );
				continue;
			}
			self::log( sprintf( 'Página consultada (%d bytes): %s. O extrator desta fonte ainda não está configurado.', strlen( wp_remote_retrieve_body( $response ) ), $url ) );
		}
	}

	private static function log( $message ) {
		error_log( '[Agent Cars] ' . $message );
	}
}

Agent_Cars::boot();
register_activation_hook( __FILE__, array( 'Agent_Cars', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Agent_Cars', 'deactivate' ) );
