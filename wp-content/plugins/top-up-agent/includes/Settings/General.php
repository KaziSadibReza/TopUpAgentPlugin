<?php

namespace TopUpAgent\Settings;

defined('ABSPATH') || exit;

class General
{
    /**
     * @var array
     */
    private $settings;

    /**
     * General constructor.
     */
    public function __construct()
    {
        $this->settings = get_option('tua_settings_general', array());

        /**
         * @see https://developer.wordpress.org/reference/functions/register_setting/#parameters
         */
        $args = array(
            'sanitize_callback' => array($this, 'sanitize')
        );
    

        // Register the initial settings group.
        register_setting('tua_settings_group_general', 'tua_settings_general', $args) ;

        // Initialize the individual sections
        $this->initSectionLicenseKeys();
        $this->initSectionAPI();
        
    }

    /**
     * Sanitizes the settings input.
     *
     * @param array $settings
     *
     * @return array
     */
    public function sanitize($settings)
    {
    
        return $settings;
    }

    

    /**
     * Initializes the "tua_license_keys" section.
     *
     * @return void
     */
    private function initSectionLicenseKeys()
    {
        // Add the settings sections.
        add_settings_section(
            'license_keys_section',
            __('License keys', 'top-up-agent'),
            null,
            'tua_license_keys'
        );

        // tua_security section fields.
        add_settings_field(
            'tua_hide_license_keys',
            __('Obscure licenses', 'top-up-agent'),
            array($this, 'fieldHideLicenseKeys'),
            'tua_license_keys',
            'license_keys_section'
        );

        add_settings_field(
            'tua_allow_duplicates',
            __('Allow duplicates', 'top-up-agent'),
            array($this, 'fieldAllowDuplicates'),
            'tua_license_keys',
            'license_keys_section'
        );
        add_settings_field(
            'tua_expire_format',
            __('License expiration format', 'top-up-agent'),
            array($this, 'fieldExpireFormat'),
            'tua_license_keys',
            'license_keys_section'
        );
    }

    public function fieldExpireFormat()
    {
        $field = 'tua_expire_format';
        $value = isset($this->settings[$field]) ? $this->settings[$field] : '';
        $html = '<fieldset>';
        $html .= sprintf(
            '<input type="text" id="%s" name="tua_settings_general[%s]" value="%s" >',
            esc_attr($field), // Escape field ID
            esc_attr($field), // Escape field name
            esc_attr($value)  // Escape field value
        );
        $html .= '<br><br>';
        $html .= sprintf(
            /* translators: %1$s: date format merge code, %2$s: time format merge code, %3$s: general settings URL, %4$s: link to date and time formatting documentation */
            __(
                '<code>%1$s</code> and <code>%2$s</code> will be replaced by formats from <a href="%3$s">Administration > Settings > General</a>. %4$s',
                'top-up-agent'
            ),
            '{{DATE_FORMAT}}',
            '{{TIME_FORMAT}}',
            esc_url(admin_url('options-general.php')), // Escape admin URL
            __(
                '<a href="https://wordpress.org/support/article/formatting-date-and-time/">Documentation on date and time formatting</a>.'
            )
        );
        $html .= '</fieldset>';
        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }
    

    
    

    /**
     * Initializes the "tua_rest_api" section.
     *
     * @return void
     */
    private function initSectionAPI()
    {
        // Add the settings sections.
        add_settings_section(
            'tua_rest_api_section',
            __('REST API', 'top-up-agent'),
            null,
            'tua_rest_api'
        );

        add_settings_field(
            'tua_disable_api_ssl',
            __('API & SSL', 'top-up-agent'),
            array($this, 'fieldEnableApiOnNonSsl'),
            'tua_rest_api',
            'tua_rest_api_section'
        );

        add_settings_field(
            'tua_enabled_api_routes',
            __('Enable/disable API routes', 'top-up-agent'),
            array($this, 'fieldEnabledApiRoutes'),
            'tua_rest_api',
            'tua_rest_api_section'
        );
    }

    /**
     * Callback for the "hide_license_keys" field.
     *
     * @return void
     */
    public function fieldHideLicenseKeys()
    {
        $field = 'tua_hide_license_keys';
        (array_key_exists($field, $this->settings)) ? $value = true : $value = false;

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="checkbox" name="tua_settings_general[%s]" value="1" %s/>',
            $field,
            $field,
            checked(true, $value, false)
        );
        $html .= sprintf('<span>%s</span>', __('Hide license keys in the admin dashboard.', 'top-up-agent'));
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('All license keys will be hidden and only displayed when the \'Show\' action is clicked.', 'top-up-agent')
        );
        $html .= '</fieldset>';

        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }

    /**
     * Callback for the "tua_allow_duplicates" field.
     *
     * @return void
     */
    public function fieldAllowDuplicates()
    {
        $field = 'tua_allow_duplicates';
        (array_key_exists($field, $this->settings)) ? $value = true : $value = false;

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="checkbox" name="tua_settings_general[%s]" value="1" %s/>',
            $field,
            $field,
            checked(true, $value, false)
        );
        $html .= sprintf(
            '<span>%s</span>',
            __('Allow duplicate license keys inside the licenses database table.', 'top-up-agent')
        );
        $html .= '</label>';

        $html .= '</fieldset>';

        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }

   
    /**
     * Callback for the "tua_disable_api_ssl" field.
     *
     * @return void
     */
    public function fieldEnableApiOnNonSsl()
    {
        $field = 'tua_disable_api_ssl';
        (array_key_exists($field, $this->settings)) ? $value = true : $value = false;

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="checkbox" name="tua_settings_general[%s]" value="1" %s/>',
            $field,
            $field,
            checked(true, $value, false)
        );
        $html .= sprintf(
            '<span>%s</span>',
            __('Enable the plugin API routes over insecure HTTP connections.', 'top-up-agent')
        );
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('This should only be activated for development purposes.', 'top-up-agent')
        );
        $html .= '</fieldset>';

        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }

    /**
     * Callback for the "tua_enabled_api_routes" field.
     *
     * @return void
     */
    public function fieldEnabledApiRoutes()
    {
        $field = 'tua_enabled_api_routes';
        $value = array();
        $routes = array(
            array(
                'id'         => '010',
                'name'       => 'v2/licenses',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '011',
                'name'       => 'v2/licenses/{license_key}',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '012',
                'name'       => 'v2/licenses',
                'method'     => 'POST',
                'deprecated' => false,
            ),
            array(
                'id'         => '013',
                'name'       => 'v2/licenses/{license_key}',
                'method'     => 'PUT',
                'deprecated' => false,
            ),
             array(
                'id'         => '014',
                'name'       => 'v2/licenses/{license_key}',
                'method'     => 'DELETE',
                'deprecated' => false,
            ),
            array(
                'id'         => '015',
                'name'       => 'v2/licenses/activate/{license_key}',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '016',
                'name'       => 'v2/licenses/deactivate/{activation_token}',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '017',
                'name'       => 'v2/licenses/validate/{license_key}',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '018',
                'name'       => 'v2/generators',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '019',
                'name'       => 'v2/generators/{id}',
                'method'     => 'GET',
                'deprecated' => false,
            ),
            array(
                'id'         => '020',
                'name'       => 'v2/generators',
                'method'     => 'POST',
                'deprecated' => false,
            ),
            array(
                'id'         => '021',
                'name'       => 'v2/generators/{id}',
                'method'     => 'PUT',
                'deprecated' => false,
            ),
             array(
                'id'         => '022',
                'name'       => 'v2/generators/{id}',
                'method'     => 'DELETE',
                'deprecated' => false,
            ),
        );
        $classList = array(
            'GET'    => 'text-success',
            'PUT'    => 'text-primary',
            'POST'   => 'text-primary',
            'DELETE' => 'text-danger '
        );

        if (array_key_exists($field, $this->settings)) {
            $value = $this->settings[$field];
        }

        $html = '<fieldset>';

        foreach ($routes as $route) {
            $checked = false;

            if (array_key_exists($route['id'], $value) && $value[$route['id']] === '1') {
                $checked = true;
            }

            $html .= sprintf('<label for="%s-%s">', $field, $route['id']);
            $html .= sprintf(
                '<input id="%s-%s" type="checkbox" name="tua_settings_general[%s][%s]" value="1" %s>',
                $field,
                $route['id'],
                $field,
                $route['id'],
                checked(true, $checked, false)
            );
            $html .= sprintf('<code><b class="%s">%s</b> - %s</code>', $classList[$route['method']], $route['method'], $route['name']);

            if (true === $route['deprecated']) {
                $html .= sprintf(
                    '<code class="text-info"><b>%s</b></code>',
                    strtoupper(__('Deprecated', 'top-up-agent'))
                );
            }

            $html .= '</label>';
            $html .= '<br>';
        }

        $html .= sprintf(
            '<p class="description" style="margin-top: 1em;">%s</p>',
            sprintf(
                 /* translators: %1$s: date format merge code, %2$s: time format merge code, %3$s: general settings URL, %4$s: link to date and time formatting documentation */
                __('The complete <b>API documentation</b> can be found <a href="%s" target="_blank" rel="noopener">here</a>.', 'top-up-agent'),
                'https://www.licensemanager.at/docs/rest-api/getting-started/api-keys'
            )
        );
        
        $html .= '</fieldset>';

        echo wp_kses($html, tua_shapeSpace_allowed_html());
    }
}
