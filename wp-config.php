<?php
/**
 * As configurações básicas do WordPress
 *
 * O script de criação wp-config.php usa esse arquivo durante a instalação.
 * Você não precisa usar o site, você pode copiar este arquivo
 * para "wp-config.php" e preencher os valores.
 *
 * Este arquivo contém as seguintes configurações:
 *
 * * Configurações do banco de dados
 * * Chaves secretas
 * * Prefixo do banco de dados
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Configurações do banco de dados - Você pode pegar estas informações com o serviço de hospedagem ** //
/** O nome do banco de dados do WordPress */
define( 'DB_NAME', 'motors' );

/** Usuário do banco de dados MySQL */
define( 'DB_USER', 'root' );

/** Senha do banco de dados MySQL */
define( 'DB_PASSWORD', '' );

/** Nome do host do MySQL */
define( 'DB_HOST', 'localhost' );

/** Charset do banco de dados a ser usado na criação das tabelas. */
define( 'DB_CHARSET', 'utf8mb4' );

/** O tipo de Collate do banco de dados. Não altere isso se tiver dúvidas. */
define( 'DB_COLLATE', '' );

/**#@+
 * Chaves únicas de autenticação e salts.
 *
 * Altere cada chave para um frase única!
 * Você pode gerá-las
 * usando o {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org
 * secret-key service}
 * Você pode alterá-las a qualquer momento para invalidar quaisquer
 * cookies existentes. Isto irá forçar todos os
 * usuários a fazerem login novamente.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '|I`5HN#p74GX~)50(J*A,e_A>a{oAsOFIcdYYbr4>A5d%3`)n_TP q!+m)TP=ug2' );
define( 'SECURE_AUTH_KEY',  'Q/Seh+3Pa+})f]d[)]&w&1FBg(,/2X!)xaAp6nWqhUDZ(LX p-qS>*Q`fc0g/STa' );
define( 'LOGGED_IN_KEY',    'p4gqZd:d9&ro;25dzjfw<lqzx[wCRk]/M2!J}p.@;[$p;gZ{gP[msJX8qu3e!G=m' );
define( 'NONCE_KEY',        'taJ*&J)v`k,.%QvQQmJh8]j4RR2d_V*MQAkMk3mcQR~Ky5/3:~[c5RvyPj6$QW>z' );
define( 'AUTH_SALT',        '{hZH|I0S:.:V]=_SgD%LS6&wf &[Po{mg854=-kgN.;UGWvjjo`an;&?I&tGIi4P' );
define( 'SECURE_AUTH_SALT', 'JeUIELt_>]P5+eg20G:+@Iq-Yo/KH%Ywq#J=[m4H1+NS]Xg@o6:9OA{3n!kyQ0LL' );
define( 'LOGGED_IN_SALT',   'v`cs@+qk-Jd7%q@tRZp8A0oI<+WLL@()#a8wqt[bbLa5/Jna.nBH;K`GjS5rMXGC' );
define( 'NONCE_SALT',       '# ]B{CoU8q| b5l@;QK22DN3C,x=.rjAd9}1hw#FMcA;|0?z2L},P^9ko]|ftfvN' );

/**#@-*/

/**
 * Prefixo da tabela do banco de dados do WordPress.
 *
 * Você pode ter várias instalações em um único banco de dados se você der
 * um prefixo único para cada um. Somente números, letras e sublinhados!
 */
$table_prefix = 'wp_';

/**
 * Para desenvolvedores: Modo de debug do WordPress.
 *
 * Altere isto para true para ativar a exibição de avisos
 * durante o desenvolvimento. É altamente recomendável que os
 * desenvolvedores de plugins e temas usem o WP_DEBUG
 * em seus ambientes de desenvolvimento.
 *
 * Para informações sobre outras constantes que podem ser utilizadas
 * para depuração, visite o Codex.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Adicione valores personalizados entre esta linha até "Isto é tudo". */



/* Isto é tudo, pode parar de editar! :) */

/** Caminho absoluto para o diretório WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura as variáveis e arquivos do WordPress. */
require_once ABSPATH . 'wp-settings.php';
