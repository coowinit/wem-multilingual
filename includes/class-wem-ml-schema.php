<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WEM_ML_Schema {

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $strings_table      = $wpdb->prefix . 'wem_ml_strings';
        $translations_table = $wpdb->prefix . 'wem_ml_translations';
        $state_table        = $wpdb->prefix . 'wem_ml_object_state';
        $slugs_table        = $wpdb->prefix . 'wem_ml_object_slugs';

        $sql_strings = "CREATE TABLE {$strings_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_language varchar(10) NOT NULL DEFAULT 'en',
            source_text longtext NOT NULL,
            source_hash char(64) NOT NULL,
            normalized_hash char(64) NOT NULL,
            context_type varchar(50) NOT NULL,
            context_key varchar(191) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            field_key varchar(100) NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY context_key (context_key),
            KEY object_field (object_type, object_id, field_key)
        ) {$charset_collate};";

        $sql_translations = "CREATE TABLE {$translations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            string_id bigint(20) unsigned NOT NULL,
            target_language varchar(10) NOT NULL,
            translated_text longtext NOT NULL,
            translated_from_hash char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'needs_review',
            origin varchar(20) NOT NULL DEFAULT 'manual',
            provider varchar(50) NOT NULL DEFAULT '',
            provider_model varchar(100) NOT NULL DEFAULT '',
            reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY string_language (string_id, target_language),
            KEY target_language (target_language),
            KEY status (status)
        ) {$charset_collate};";

        $sql_state = "CREATE TABLE {$state_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            language varchar(10) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_language (object_type, object_id, language),
            KEY language_status (language, status)
        ) {$charset_collate};";

        $sql_slugs = "CREATE TABLE {$slugs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            language varchar(10) NOT NULL,
            translated_slug varchar(200) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_language (object_type, object_id, language),
            UNIQUE KEY language_slug (language, translated_slug),
            KEY object_lookup (object_type, object_id)
        ) {$charset_collate};";

        dbDelta( $sql_strings );
        dbDelta( $sql_translations );
        dbDelta( $sql_state );
        dbDelta( $sql_slugs );

        update_option( 'wem_ml_db_version', WEM_ML_DB_VERSION );
    }
}
