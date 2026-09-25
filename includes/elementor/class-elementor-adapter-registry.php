<?php
/**
 * Elementor Adapter Registry for WEM Multilingual.
 *
 * v0.1.1 Step 2: describes supported translatable widget fields.
 * The registry does not read/write translations and does not mutate Elementor data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WEM_ML_Elementor_Adapter_Registry {

    /**
     * Return supported widget field definitions.
     *
     * @return array<string,array<string,array<string,string>>>
     */
    public static function get_registry() {
        return array(
            'heading' => array(
                'title' => array(
                    'mode'  => 'translate',
                    'label' => 'Heading Title',
                ),
            ),
            'button' => array(
                'text' => array(
                    'mode'  => 'translate',
                    'label' => 'Button Text',
                ),
            ),
        );
    }

    /**
     * Return field definitions for one widget type.
     *
     * @param string $widget_type Elementor widget type.
     * @return array<string,array<string,string>>
     */
    public static function get_fields_for_widget( $widget_type ) {
        $registry    = self::get_registry();
        $widget_type = sanitize_key( (string) $widget_type );

        return isset( $registry[ $widget_type ] ) ? $registry[ $widget_type ] : array();
    }

    /**
     * Discover supported source fields from one Elementor widget settings array.
     *
     * Step 2 only reads exact registered setting paths.
     * Unknown settings are ignored rather than guessed.
     *
     * @param string $widget_type Widget type.
     * @param array  $settings    Elementor widget settings.
     * @return array<int,array<string,string>>
     */
    public static function discover_fields( $widget_type, $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $fields = self::get_fields_for_widget( $widget_type );
        $found  = array();

        foreach ( $fields as $setting_path => $definition ) {
            if ( ! array_key_exists( $setting_path, $settings ) ) {
                continue;
            }

            $value = $settings[ $setting_path ];

            // v0.1.1 Step 2 only accepts scalar textual values.
            if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
                continue;
            }

            $source_text = (string) $value;

            if ( '' === trim( $source_text ) ) {
                continue;
            }

            $found[] = array(
                'setting_path' => $setting_path,
                'mode'         => isset( $definition['mode'] ) ? (string) $definition['mode'] : 'translate',
                'label'        => isset( $definition['label'] ) ? (string) $definition['label'] : $setting_path,
                'source_text'  => $source_text,
            );
        }

        return $found;
    }
}
