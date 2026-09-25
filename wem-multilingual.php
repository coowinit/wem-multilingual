<?php
/**
 * Plugin Name: WEM Multilingual
 * Description: Experimental SEO-first multilingual core for WordPress B2B websites.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WEM
 * Text Domain: wem-multilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WEM_ML_VERSION', '0.1.0' );
define( 'WEM_ML_DB_VERSION', '0.1.0' );
define( 'WEM_ML_FILE', __FILE__ );
define( 'WEM_ML_DIR', plugin_dir_path( __FILE__ ) );

require_once WEM_ML_DIR . 'includes/class-wem-ml-schema.php';

register_activation_hook( __FILE__, array( 'WEM_ML_Schema', 'install' ) );
