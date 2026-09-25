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
require_once WEM_ML_DIR . 'includes/class-language-context.php';
require_once WEM_ML_DIR . 'includes/class-slug-repository.php';
require_once WEM_ML_DIR . 'includes/class-translation-repository.php';
require_once WEM_ML_DIR . 'includes/class-object-state.php';
require_once WEM_ML_DIR . 'includes/class-router.php';
require_once WEM_ML_DIR . 'includes/class-title-overlay.php';

if ( is_admin() ) {
    require_once WEM_ML_DIR . 'admin/class-admin.php';
    require_once WEM_ML_DIR . 'admin/class-diagnostics.php';
}

register_activation_hook( __FILE__, array( 'WEM_ML_Schema', 'install' ) );
register_activation_hook( __FILE__, array( 'WEM_ML_Router', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WEM_ML_Router', 'deactivate' ) );

WEM_ML_Language_Context::init();
WEM_ML_Router::init();
WEM_ML_Title_Overlay::init();

if ( is_admin() ) {
    WEM_ML_Admin::init();
    WEM_ML_Diagnostics::init();
}
