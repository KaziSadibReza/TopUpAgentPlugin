<?php

namespace TopUpAgent\Settings;

defined('ABSPATH') || exit;

class Tools
{
    /**
     * @var array
     */
    private $settings;

    /**
     * Tools constructor.
     */
    public function __construct()
    {
        $this->settings = get_option('tua_settings_tools', array());

        /**
         * @see https://developer.wordpress.org/reference/functions/register_setting/#parameters
         */
        $args = array(
            'sanitize_callback' => array($this, 'sanitize')
        );

        // Register the initial settings group.
        register_setting('tua_settings_group_tools', 'tua_settings_tools', $args);

        // Initialize the individual sections
        $this->initSectionExport();
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function sanitize($settings)
    {
        if ($settings === null) {
            return array();
        }

        return $settings;
    }

    /**
     * Initializes the "tua_license_keys" section.
     *
     * @return void
     */
    private function initSectionExport()
    {
        // Add the settings sections.
        add_settings_section(
            'export_section',
            __('License key export', 'top-up-agent'),
            null,
            'tua_export'
        );

        // tua_export section fields.
        add_settings_field(
            'tua_csv_export_columns',
            __('CSV Export Columns', 'top-up-agent'),
            array($this, 'fieldCsvExportColumns'),
            'tua_export',
            'export_section'
        );
        // Add the settings sections.
    }
    

    public function fieldCsvExportColumns()
    {
        $field   = 'tua_csv_export_columns';
        $value   = array();
        $columns = array(
            array(
                'slug' => 'id',
                'name' => __('ID', 'top-up-agent')
            ),
            array(
                'slug' => 'order_id',
                'name' => __('Order ID', 'top-up-agent')
            ),
            array(
                'slug' => 'product_id',
                'name' => __('Product ID', 'top-up-agent')
            ),
            array(
                'slug' => 'user_id',
                'name' => __('User ID', 'top-up-agent')
            ),
            array(
                'slug' => 'license_key',
                'name' => __('License key', 'top-up-agent')
            ),
            array(
                'slug' => 'expires_at',
                'name' => __('Expires at', 'top-up-agent')
            ),
            array(
                'slug' => 'valid_for',
                'name' => __('Valid for', 'top-up-agent')
            ),
            array(
                'slug' => 'status',
                'name' => __('Status', 'top-up-agent')
            ),
            array(
                'slug' => 'times_activated',
                'name' => __('Times activated', 'top-up-agent')
            ),
            array(
                'slug' => 'times_activated_max',
                'name' => __('Times activated (max.)', 'top-up-agent')
            ),
            array(
                'slug' => 'created_at',
                'name' => __('Created at', 'top-up-agent')
            ),
            array(
                'slug' => 'created_by',
                'name' => __('Created by', 'top-up-agent')
            ),
            array(
                'slug' => 'updated_at',
                'name' => __('Updated at', 'top-up-agent')
            ),
            array(
                'slug' => 'updated_by',
                'name' => __('Updated by', 'top-up-agent')
            )
        );

        if (array_key_exists($field, $this->settings)) {
            $value = $this->settings[$field];
        }

        $html = '<fieldset>';

        foreach ($columns as $column) {
            $checked = false;

            if (array_key_exists($column['slug'], $value) && $value[$column['slug']] === '1') {
                $checked = true;
            }

            $html .= sprintf('<label for="%s-%s">', $field, $column['slug']);
            $html .= sprintf(
                '<input id="%s-%s" type="checkbox" name="tua_settings_tools[%s][%s]" value="1" %s>',
                $field,
                $column['slug'],
                $field,
                $column['slug'],
                checked(true, $checked, false)
            );
            $html .= sprintf('<span>%s</span>', $column['name']);

            $html .= '</label>';
            $html .= '<br>';
        }

        $html .= sprintf(
            '<p class="description" style="margin-top: 1em;">%s</p>',
            __('The selected columns will appear on the CSV export for license keys.', 'top-up-agent')
        );
        $html .= '</fieldset>';

        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }

}
