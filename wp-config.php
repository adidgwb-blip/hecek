<?php
define('WP_CACHE', true); // Added by SpeedyCache

/**
 * The base configuration for WordPress
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'jagoanpe_wp68796' );

/** Database username */
define( 'DB_USER', 'jagoanpe_wp68796' );

/** Database password */
define( 'DB_PASSWORD', '7H6)08.9Sp' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '?:&@|kEY>E@3dZQ,Sw(ojPG@pZ@,ZJUabJH,.|>smCpcug.6x1=hQ@D+Fl1):ul#');
define('SECURE_AUTH_KEY', '7AU>U*poUI?>aDJH,9p8xe#cjMA^[F?{pv#FeR?2qAqd@I]ab_5^}I[Kh?MiNZ%#');
define('LOGGED_IN_KEY', '@jTZd[OTL=@G0$8w+HZ>CqyY8#*d*19c.E;xi7_^&fg0$Q]|C8IOqcewt9{I^):*');
define('NONCE_KEY', '>Z#TT4nwWtM(0y0Zmn6LzH]?ow*tD6C^1%>EAT;bE*Id[l3|F.N4SF4K*VC$0>l?');
define('AUTH_SALT', ']Ia<0#48(m7]yQ!9f=$^Rf-U.PN&96I2@.-YDbF4l,7}ovK{T<lg.ccUvj|z.{.@');
define('SECURE_AUTH_SALT', 'S8802(fba}(K;7$1H^*&][}]@u^gr[_!Dj.G)?F!-KEM8K{V<WYaL|wkk!R*6%4S');
define('LOGGED_IN_SALT', 'u9aNxeLO85ZQ6Q>B8,0eDyyi.gL5XhRyO-XRHR:yFFx-<c_^,AT$>s#tQHC]=$AM');
define('NONCE_SALT', 'yn9IN9vuq@}*G1Df+oN:{r[,kYh(50sq}F^vL289P.>*v7yFGg#|N%W6tfPq#:WS');

/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wpfs_';

/**
 * For developers: WordPress debugging mode.
 */
define( 'WP_DEBUG', false );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );

/* Add any custom values between this line and the "stop editing" line. */

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

define('DISALLOW_FILE_EDIT', false);
