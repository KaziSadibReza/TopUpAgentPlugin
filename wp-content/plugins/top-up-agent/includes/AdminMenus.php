<?php

namespace TopUpAgent;

use TopUpAgent\Enums\LicenseStatus;
use TopUpAgent\Lists\APIKeyList;
use TopUpAgent\Lists\LicensesList;
use TopUpAgent\Models\Resources\ApiKey as ApiKeyResourceModel;
use TopUpAgent\Models\Resources\License as LicenseResourceModel;
use TopUpAgent\Repositories\Resources\ApiKey as ApiKeyResourceRepository;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;

defined('ABSPATH') || exit;

if (class_exists('AdminMenus', false)) {
    return new AdminMenus();
}

class AdminMenus
{
    /**
     * @var array
     */
    private $tabWhitelist;

    /**
	 * Product page slug.
	 */
	const PRODUCT_PAGE = 'edit.php?post_type=product';

    /**
     * Main menu page slug.
     */
    const MAIN_PAGE = 'top-up-agent';

    /**
     * Licenses page slug.
     */
    const LICENSES_PAGE = 'tua_licenses';

    /**
     * Settings page slug.
     */
    const SETTINGS_PAGE = 'tua_settings';

    /**
     * @var LicensesList
     */
    private $licenses;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->tabWhitelist = array('general', 'rest_api', 'tools');

        // Plugin pages.
        add_action('admin_menu', array($this, 'createPluginPages'), 10);
        add_action('admin_init', array($this, 'initSettingsAPI'));

        // Screen options
        add_filter('set-screen-option', array($this, 'setScreenOption'), 10, 3);

        // Footer text
        add_filter('admin_footer_text', array($this, 'adminFooterText'), 1);
    }

    /**
     * Returns an array of all plugin pages.
     *
     * @return array
     */
    public function getPluginPageIDs()
    {
        return array(
            'toplevel_page_top-up-agent',
            'top-up-agent_page_tua_licenses',
            'top-up-agent_page_tua_settings'
        );
    }

    /**
     * Sets up all necessary plugin pages.
     */
    public function createPluginPages()
    {
        // Create the main menu page with a nice icon
        $iconSvg = 'data:image/svg+xml;base64,' . base64_encode('<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 1.25H11.9426C9.63423 1.24999 7.82519 1.24998 6.41371 1.43975C4.96897 1.63399 3.82895 2.03933 2.93414 2.93414C2.03933 3.82895 1.63399 4.96897 1.43975 6.41371C1.24998 7.82519 1.24999 9.63423 1.25 11.9426V12.0574C1.24999 14.3658 1.24998 16.1748 1.43975 17.5863C1.63399 19.031 2.03933 20.1711 2.93414 21.0659C3.82895 21.9607 4.96897 22.366 6.41371 22.5603C7.82519 22.75 9.63423 22.75 11.9426 22.75H12.0574C14.3658 22.75 16.1748 22.75 17.5863 22.5603C19.031 22.366 20.1711 21.9607 21.0659 21.0659C21.9607 20.1711 22.366 19.031 22.5603 17.5863C22.75 16.1748 22.75 14.3658 22.75 12.0574V12C22.75 11.5858 22.4142 11.25 22 11.25C21.5858 11.25 21.25 11.5858 21.25 12C21.25 14.3782 21.2484 16.0864 21.0736 17.3864C20.9018 18.6648 20.5749 19.4355 20.0052 20.0052C19.4355 20.5749 18.6648 20.9018 17.3864 21.0736C16.0864 21.2484 14.3782 21.25 12 21.25C9.62178 21.25 7.91356 21.2484 6.61358 21.0736C5.33517 20.9018 4.56445 20.5749 3.9948 20.0052C3.42514 19.4355 3.09825 18.6648 2.92637 17.3864C2.75159 16.0864 2.75 14.3782 2.75 12C2.75 9.62178 2.75159 7.91356 2.92637 6.61358C3.09825 5.33517 3.42514 4.56445 3.9948 3.9948C4.56445 3.42514 5.33517 3.09825 6.61358 2.92637C7.91356 2.75159 9.62178 2.75 12 2.75C12.4142 2.75 12.75 2.41421 12.75 2C12.75 1.58579 12.4142 1.25 12 1.25Z"/><path d="M21.5303 3.53033C21.8232 3.23744 21.8232 2.76256 21.5303 2.46967C21.2374 2.17678 20.7626 2.17678 20.4697 2.46967L12.75 10.1893V6.65625C12.75 6.24204 12.4142 5.90625 12 5.90625C11.5858 5.90625 11.25 6.24204 11.25 6.65625V12C11.25 12.4142 11.5858 12.75 12 12.75H17.3438C17.758 12.75 18.0938 12.4142 18.0938 12C18.0938 11.5858 17.758 11.25 17.3438 11.25H13.8107L21.5303 3.53033Z"/></svg>');

        $mainMenuHook = add_menu_page(
            esc_html__('Top Up Agent', 'top-up-agent'),           // Page title
            esc_html__('Top Up Agent', 'top-up-agent'),           // Menu title
            'manage_options',                                      // Capability
            self::MAIN_PAGE,                                      // Menu slug
            array($this, 'dashboardPage'),                        // Function callback
            $iconSvg,                                             // Icon
            26                                                    // Position (after Comments)
        );

        // Add Licenses submenu
        $licensesHook = add_submenu_page(
            self::MAIN_PAGE,
            esc_html__('License Keys', 'top-up-agent'),
            esc_html__('License Keys', 'top-up-agent'),
            'manage_options',
            self::LICENSES_PAGE,
            array($this, 'licensesPage')
        );

        // Add Settings submenu
        $settingsHook = add_submenu_page(
            self::MAIN_PAGE,
            esc_html__('Settings', 'top-up-agent'),
            esc_html__('Settings', 'top-up-agent'),
            'manage_options',
            self::SETTINGS_PAGE,
            array($this, 'settingsPage')
        );

        add_action('load-' . $licensesHook, array($this, 'licensesPageScreenOptions'));
    }

    /**
     * Adds the supported screen options for the licenses list.
     */
    public function licensesPageScreenOptions()
    {
        $option = 'per_page';
        $args = array(
            'label' => esc_html__('License keys per page', 'top-up-agent'),
            'default' => 10,
            'option' => 'tua_licenses_per_page'
        );

        add_screen_option($option, $args);

        $this->licenses = new LicensesList();
    }
/**
	 * Sets up the license page.
	 */
    public function licensesPage() {
		$tua_data = $_REQUEST;
		$default = 'list';
		$action = $this->getCurrentAction($default);
		$licenses = $this->licenses;
		$addLicenseUrl = admin_url(
			sprintf(
				'admin.php?page=%s&action=add&_wpnonce=%s',
				self::LICENSES_PAGE,
				wp_create_nonce('add')
			)
		);
		$importLicenseUrl = admin_url(
			sprintf(
				'admin.php?page=%s&action=import&_wpnonce=%s',
				self::LICENSES_PAGE,
				wp_create_nonce('import')
			)
		);

		// Edit license keys
		if ( 'edit' === $action ) {
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('Insufficient permission', 'top-up-agent'));
			}

			/**
			 *  LicenseResourceRepository find license
			 * 
			 * @var LicenseResourceModel $license 
			**/
			$license = LicenseResourceRepository::instance()->find(absint($tua_data['id']));
			$expiresAt = null;

			if ($license && $license->getExpiresAt()) {
				try {
					$expiresAtDateTime = new \DateTime($license->getExpiresAt());
					$expiresAt = $expiresAtDateTime->format('Y-m-d');
				} catch (\Exception $e) {
					$expiresAt = null;
				}
			}

			if (!$license) {
				wp_die(esc_html__('Invalid license key ID', 'top-up-agent'));
			}

			$licenseKey = $license->getDecryptedLicenseKey();
		}

		// Edit, add or import license keys
		if ( 'edit' === $action   || 'add' === $action   || 'import'  === $action ) {
			wp_enqueue_style('tua-jquery-ui-datepicker');
			wp_enqueue_script('jquery-ui-datepicker');
			$statusOptions = LicenseStatus::dropdown();
		}

		include \TUA_TEMPLATES_DIR . 'page-licenses.php';
	}

    /**
     * Sets up the dashboard page.
     */
    public function dashboardPage()
    {
        // Get license statistics
        $totalLicenses = LicenseResourceRepository::instance()->count();
        $activeLicenses = LicenseResourceRepository::instance()->countBy(array('status' => LicenseStatus::ACTIVE));
        $inactiveLicenses = LicenseResourceRepository::instance()->countBy(array('status' => LicenseStatus::INACTIVE));
        
        // Calculate percentages
        $activePercentage = $totalLicenses > 0 ? round(($activeLicenses / $totalLicenses) * 100, 1) : 0;
        $inactivePercentage = $totalLicenses > 0 ? round(($inactiveLicenses / $totalLicenses) * 100, 1) : 0;

        // Recent licenses (last 10)
        $recentLicenses = LicenseResourceRepository::instance()->findAllBy(
            array(),
            'id',
            'DESC',
            10
        );

        include \TUA_TEMPLATES_DIR . 'page-dashboard.php';
    }

    /**
     * Sets up the settings page.
     */
    public function settingsPage()
    {
        $section            = $this->getCurrentSection();
        $urlGeneral     = admin_url( sprintf( 'admin.php?page=%s&section=general',      self::SETTINGS_PAGE ) );
        $urlRestApi     = admin_url( sprintf( 'admin.php?page=%s&section=rest_api',     self::SETTINGS_PAGE ) );
        $urlTools       = admin_url( sprintf( 'admin.php?page=%s&section=tools',        self::SETTINGS_PAGE ) );

        if ($section == 'rest_api') {
            if (isset($_GET['create_key'])) {
                $action = 'create';
            } elseif (isset($_GET['edit_key'])) {
                $action = 'edit';
            } elseif (isset($_GET['show_key'])) {
                $action = 'show';
            } else {
                $action = 'list';
            }

            switch ($action) {
                case 'create':
                case 'edit':
                    $keyId   = 0;
                    $keyData = new ApiKeyResourceModel();
                    $userId  = null;
                    $date    = null;

                    if (array_key_exists('edit_key', $_GET)) {
                        $keyId = absint($_GET['edit_key']);
                    }

                if ($keyId !== 0) {
                        /** @var ApiKeyResourceModel $keyData */
                        $keyData = ApiKeyResourceRepository::instance()->find($keyId);

                        if ($keyData !== null) {
                            $userId  = (int)$keyData->getUserId();

                            $lastAccess = $keyData->getLastAccess();
                            if ($lastAccess !== null) {
                                $date = sprintf(
                                    // Translators: Date and time format for displaying last access date.
                                    esc_html__('%1$s at %2$s', 'top-up-agent'),
                                    date_i18n(wc_date_format(), strtotime($lastAccess)),
                                    date_i18n(wc_time_format(), strtotime($lastAccess))
                                );
                            } 
                        } 
                    }

                    $users       = apply_filters('tua_get_users', get_users(array( 'fields' => array( 'user_login', 'user_email', 'ID' ))));
                    $permissions = array(
                        'read'       => esc_html__('Read', 'top-up-agent'),
                        'write'      => esc_html__('Write', 'top-up-agent'),
                        'read_write' => esc_html__('Read/Write', 'top-up-agent'),
                    );

                    if ($keyId && $userId && ! current_user_can('edit_user', $userId)) {
                        if (get_current_user_id() !== $userId) {
                            wp_die(
                                esc_html__(
                                    'You do not have permission to edit this API Key',
                                    'top-up-agent'
                                )
                            );
                        }
                    }
                    break;
                case 'list':
                    $keys = new APIKeyList();
                    break;
                case 'show':
                    $keyData     = get_transient('tua_api_key');
                    $consumerKey = get_transient('tua_consumer_key');

                    delete_transient('tua_api_key');
                    delete_transient('tua_consumer_key');
                    break;
            }

            // Add screen option.
            add_screen_option(
                'per_page',
                array(
                    'default' => 10,
                    'option'  => 'tua_keys_per_page',
                )
            );
        }

        include \TUA_TEMPLATES_DIR . 'page-settings.php';
    }

    /**
     * Initialized the plugin Settings API.
     */
    public function initSettingsAPI()
    {
        new Settings();
    }

    /**
     * Displays the new screen options.
     *
     * @param bool   $keep
     * @param string $option
     * @param int    $value
     *
     * @return int
     */
    public function setScreenOption($keep, $option, $value)
    {
        return $value;
    }

    /**
     * Sets the custom footer text for the plugin pages.
     *
     * @param string $footerText
     *
     * @return string
     */
    public function adminFooterText($footerText)
    {
        if (!current_user_can('manage_options') || !function_exists('wc_get_screen_ids')) {
            return $footerText;
        }

        $currentScreen = get_current_screen();

        // Check to make sure we're on a Top Up Agent plugin page.
        if (isset($currentScreen->id) && in_array($currentScreen->id, $this->getPluginPageIDs())) {
            // Change the footer text
            $footerText = sprintf(
                // Translators: Placeholder 1 is replaced with "License Manager for WooCommerce" (HTML strong tag), Placeholder 2 is replaced with a link to rate the plugin with 5 stars.
                __('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'top-up-agent'),
                sprintf('<strong>%s</strong>', esc_html__('Top Up Agent', 'top-up-agent')),
                '<a href="https://wordpress.org/support/plugin/top-up-agent/reviews/?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__('Thanks :)', 'top-up-agent') . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
            
        }

        return $footerText;
    }

    /**
     * Retrieves the currently active tab.
     *
     * @return string
     */
    protected function getCurrentSection()
    {
        $section = 'general';

        if (isset($_GET['section']) && in_array($_GET['section'], $this->tabWhitelist)) {
            $section = sanitize_text_field($_GET['section']);
        }

        return $section;
    }

    /**
     * Returns the string value of the "action" GET parameter.
     *
     * @param string $default
     *
     * @return string
     */
    protected function getCurrentAction($default)
    {
        $action = $default;
        
        if (!isset($_GET['action']) || !is_string($_GET['action'])) {
            return $action;
        }

        return sanitize_text_field($_GET['action']);
    }

}