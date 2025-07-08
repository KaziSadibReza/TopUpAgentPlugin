<?php

namespace TopUpAgent\Api\V2;

use DateTime;
use DateTimeZone;
use Exception;
use TopUpAgent\Abstracts\RestController as tua_REST_Controller;
use TopUpAgent\Enums\LicenseSource;
use TopUpAgent\Enums\LicenseStatus;
use TopUpAgent\Models\Resources\License as LicenseResourceModel;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

class Licenses extends tua_REST_Controller
{
    /**
     * @var string
     */
    protected $namespace = 'lmfwc/v2';

    /**
     * @var string
     */
    protected $rest_base = '/licenses';

    /**
     * @var array
     */
    protected $settings = array();

    /**
     * Licenses constructor.
     */
    public function __construct()
    {
        $this->settings = (array)get_option('tua_settings_general');
    }

    /**
     * Register all the needed routes for this resource.
     */
    public function register_routes()
    {
        /**
         * GET licenses
         *
         * Retrieves all the available licenses from the database.
         */
        register_rest_route(
            $this->namespace, $this->rest_base, array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'getLicenses'),
                    'permission_callback' => array($this, 'permissionCallback')
                )
            )
        );

        /**
         * GET licenses/{license_key}
         *
         * Retrieves a single licenses from the database.
         */
        register_rest_route(
            $this->namespace, $this->rest_base . '/(?P<license_key>[\w-]+)', array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'getLicense'),
                    'permission_callback' => array($this, 'permissionCallback'),
                    'args'                => array(
                        'license_key' => array(
                            'description' => 'License Key',
                            'type'        => 'string',
                        )
                    )
                )
            )
        );

        /**
         * POST licenses
         *
         * Creates a new license in the database
         */
        register_rest_route(
            $this->namespace, $this->rest_base, array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'createLicense'),
                    'permission_callback' => array($this, 'permissionCallback')
                )
            )
        );

        /**
         * PUT licenses/{license_key}
         *
         * Updates an already existing license in the database
         */
        register_rest_route(
            $this->namespace, $this->rest_base . '/(?P<license_key>[\w-]+)', array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'updateLicense'),
                    'permission_callback' => array($this, 'permissionCallback'),
                    'args'                => array(
                        'license_key' => array(
                            'description' => 'License Key',
                            'type'        => 'string',
                        ),
                    ),
                )
            )
        );

        /**
         * DELETE licenses/{license_key}
         *
         * Updates an already existing license in the database
         */
        register_rest_route(
            $this->namespace, $this->rest_base . '/(?P<license_key>[\w-]+)', array(
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'deleteLicense' ),
                    'permission_callback' => array( $this, 'permissionCallback' ),
                    'args'                => array(
                        'license_key' => array(
                            'description' => 'License Key',
                            'type'        => 'string',
                        ),
                    ),
                )
            )
        );

        /**
         * GET licenses/activate/{license_key}
         *
         * Activates a license key
         */
        register_rest_route(
            $this->namespace, $this->rest_base . '/activate/(?P<license_key>[\w-]+)', array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'activateLicense'),
                    'permission_callback' => array($this, 'permissionCallback'),
                    'args'                => array(
                        'license_key' => array(
                            'description' => 'License Key',
                            'type'        => 'string',
                        ),
                    ),
                )
            )
        );

        /**
         * GET licenses/deactivate/{license_key}
         *
         * Deactivates a license key
         */
        register_rest_route(
            $this->namespace, $this->rest_base . '/deactivate/(?P<license_key>[\w-]+)', array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'deactivateLicense'),
                    'permission_callback' => array($this, 'permissionCallback'),
                    'args'                => array(
                        'license_key' => array(
                            'description' => 'License Key',
                            'type'        => 'string'
                        ),
                        'token' => array(
                            'description' => 'Activation Token',
                            'type'        => 'string'
                        )
                    )
                )
            )
        );
    }

    /**
     * Callback for the GET licenses route. Retrieves all license keys from the database.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getLicenses()
    {
        if (!$this->isRouteEnabled($this->settings, '010')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'read')) {
            return new WP_Error(
                'tua_rest_cannot_view',
                __('Sorry, you cannot list resources.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

        try {
            /** @var LicenseResourceModel[] $licenses */
            $licenses = LicenseResourceRepository::instance()->findAll();
        } catch (Exception $e) {
            return new WP_Error(
                'tua_rest_data_error',
                $e->getMessage(),
                array('status' => 404)
            );
        }

        if (!$licenses) {
            return new WP_Error(
                'tua_rest_data_error',
                'No License Keys available',
                array('status' => 404)
            );
        }

        $response = array();

        /** @var LicenseResourceModel $license */
        foreach ($licenses as $license) {
            $licenseData = $license->toArray();

            // Remove the hash, decrypt the license key, and add it to the response
            unset($licenseData['hash']);
            $licenseData['licenseKey'] = $license->getDecryptedLicenseKey();
            $response[] = $licenseData;
        }

        return $this->response(true, $response, 200, 'v2/licenses');
    }

    /**
     * Callback for the GET licenses/{license_key} route. Retrieves a single license key from the database.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getLicense(WP_REST_Request $request)
    {
        if (!$this->isRouteEnabled($this->settings, '011')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'read')) {
            return new WP_Error(
                'tua_rest_cannot_view',
                __('Sorry, you cannot view this resource.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

        $licenseKey = sanitize_text_field($request->get_param('license_key'));

        if (!$licenseKey) {
            return new WP_Error(
                'tua_rest_data_error',
                'License Key ID invalid.',
                array('status' => 404)
            );
        }

        try {
            /** @var LicenseResourceModel $license */
            $license = LicenseResourceRepository::instance()->findBy(
                array(
                    'hash' => apply_filters('tua_hash', $licenseKey)
                )
            );
        } catch (Exception $e) {
            return new WP_Error(
                'tua_rest_data_error',
                $e->getMessage(),
                array('status' => 404)
            );
        }
        if (!$license) {
            return new WP_Error(
                'tua_rest_data_error',
                sprintf(
                    'License Key: %s could not be found.',
                    $licenseKey
                ),
                array('status' => 404)
            );
        }
        
        $licenseData = $license->toArray();

        // Remove the hash and decrypt the license key
        unset($licenseData['hash']);
        $licenseData['licenseKey'] = $license->getDecryptedLicenseKey();

        return $this->response(true, $licenseData, 200, 'v2/licenses/{license_key}');
    }

    /**
     * Callback for the POST licenses route. Creates a new license key in the
     * database.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function createLicense(WP_REST_Request $request)
    {
        if (!$this->isRouteEnabled($this->settings, '012')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'create')) {
            return new WP_Error(
                'tua_rest_cannot_create',
                __('Sorry, you are not allowed to create resources.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

        $body = $request->get_params();

        $orderId           = isset($body['order_id'])            ? absint($body['order_id'])                 : null;
        $productId         = isset($body['product_id'])          ? absint($body['product_id'])               : null;
        $userId            = isset($body['user_id'])             ? absint($body['user_id'])                  : null;
        $licenseKey        = isset($body['license_key'])         ? sanitize_text_field($body['license_key']) : null;
        $validFor          = isset($body['valid_for'])           ? absint($body['valid_for'])                : null;
        $validFor          = $validFor                           ? $validFor                                 : null;
        $expiresAt         = isset($body['expires_at'])          ? sanitize_text_field($body['expires_at'])  : null;
        $timesActivatedMax = isset($body['times_activated_max']) ? absint($body['times_activated_max'])      : null;
        $statusEnum        = isset($body['status'])              ? sanitize_text_field($body['status'])      : null;
        $status            = null;
        
        if ($productId !== null) {
            $product = wc_get_product($productId);

            if (!$product) {
                return new WP_Error(
                    'tua_rest_data_error',
                    'Product ID is invalid.',
                    array('status' => 404)
                );
            }
        }
        if ($userId !== null) {
            $user_data = get_userdata($userId);

            if (!$user_data) {
                return new WP_Error(
                    'tua_rest_data_error',
                     __('User ID is invalid.', 'top-up-agent'),
                    array('status' => 404)
                );
            }
        }
        
        if (!$licenseKey) {
            return new WP_Error(
                'tua_rest_data_error',
                'License key is invalid.',
                array('status' => 404)
            );
        }

        if (apply_filters('tua_duplicate', $licenseKey)) {
            return new WP_Error(
                'tua_rest_data_error',
                'This license key already exists.',
                array('status' => 404)
            );
        }

        if ($statusEnum && !in_array($statusEnum, LicenseStatus::$enumArray)) {
            return new WP_Error(
                'tua_rest_data_error',
                'License Key status is invalid',
                array('status' => 404)
            );
        }

        else {
            $status = LicenseStatus::$values[$statusEnum];
        }

        if ($expiresAt) {
            try {
                $expiresAtDateTime = new \DateTime($expiresAt);
                $expiresAt = $expiresAtDateTime->format('Y-m-d H:i:s');
                $validFor  = null;
            } catch (\Exception $e) {
                return new WP_Error(
                    'tua_rest_data_error',
                    $e->getMessage(),
                    array('status' => 404)
                );
            }
        }

        try {
            /** @var LicenseResourceModel $license */
            $license = LicenseResourceRepository::instance()->insert(
                array(
                    'order_id'            => $orderId,
                    'product_id'          => $productId,
                    'user_id'             => $userId,
                    'license_key'         => apply_filters('tua_encrypt', $licenseKey),
                    'hash'                => apply_filters('tua_hash', $licenseKey),
                    'valid_for'           => $validFor,
                    'expires_at'          => $expiresAt,
                    'source'              => LicenseSource::API,
                    'status'              => $status,
                    'times_activated_max' => $timesActivatedMax
                )
            );
        } catch (Exception $e) {
            return new WP_Error(
                'tua_rest_data_error',
                $e->getMessage(),
                array('status' => 404)
            );
        }

        if (!$license) {
            return new WP_Error(
                'tua_rest_data_error',
                'The license key could not be added to the database.',
                array('status' => 404)
            );
        }

        // Update the stock
        if ($license->getProductId() !== null && $license->getStatus() === LicenseStatus::ACTIVE) {
            apply_filters('tua_stock_increase', $license->getProductId());
        }

        $licenseData = $license->toArray();

        // Remove the hash and decrypt the license key
        unset($licenseData['hash']);
        $licenseData['licenseKey'] = $license->getDecryptedLicenseKey();

        return $this->response(true, $licenseData, 200, 'v2/licenses');
    }

    /**
     * Callback for the PUT licenses/{license_key} route. Updates an existing license key in the database.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function updateLicense(WP_REST_Request $request)
    {
        if (!$this->isRouteEnabled($this->settings, '013')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'edit')) {
            return new WP_Error(
                'tua_rest_cannot_edit',
                __('Sorry, you are not allowed to edit resources.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

        $body      = null;
        $urlParams = $request->get_url_params();

        if (!array_key_exists('license_key', $urlParams)) {
            return new WP_Error(
                'tua_rest_data_error',
                'No license key was provided.',
                array('status' => 404)
            );
        }

        $licenseKey = sanitize_text_field($urlParams['license_key']);

        if (!$licenseKey) {
            return new WP_Error(
                'tua_rest_data_error',
                'License Key invalid.',
                array('status' => 404)
            );
        }

        if ($this->isJson($request->get_body())) {
            $body = json_decode($request->get_body());
        }

        // Validate basic parameters
        if (!$body) {
            return new WP_Error(
                'tua_rest_data_error',
                'No parameters were provided.',
                array('status' => 404)
            );
        }

        /** @var LicenseResourceModel $license */
        $license = LicenseResourceRepository::instance()->findBy(
            array(
                'hash' => apply_filters('tua_hash', $licenseKey)
            )
        );

        if (!$license) {
            return new WP_Error(
                'tua_rest_data_error',
                sprintf(
                    'License Key: %s could not be found.',
                    $licenseKey
                ),
                array('status' => 404)
            );
        }

        $updateData = (array)$body;

        if (empty($updateData)) {
            return new WP_Error(
                'tua_rest_data_error',
                'No parameters were provided.',
                array('status' => 404)
            );
        }

        if (array_key_exists('hash', $updateData)) {
            unset($updateData['hash']);
        }
        
        if (array_key_exists('license_key', $updateData)) {
            if (apply_filters('tua_duplicate', $updateData['license_key'], $license->getId())) {
                return new WP_Error(
                    'tua_rest_data_error',
                    'This license key already exists.',
                    array('status' => 404)
                );
            }

            $updateData['hash']        = apply_filters('tua_hash', $updateData['license_key']);
            $updateData['license_key'] = apply_filters('tua_encrypt', $updateData['license_key']);
        }

        if (array_key_exists('status', $updateData)) {
            $updateData['status'] = $this->getLicenseStatus($updateData['status']);
        }

        if (array_key_exists('expires_at', $updateData)) {
            $updateData['valid_for'] = null;
        }

        // Update the stock
        if ($license->getProductId() !== null && $license->getStatus() === LicenseStatus::ACTIVE) {
            apply_filters('tua_stock_decrease', $license->getProductId());
        }

        /** @var LicenseResourceModel $updatedLicense */
        $updatedLicense = LicenseResourceRepository::instance()->update($license->getId(), $updateData);

        if (!$updatedLicense) {
            return new WP_Error(
                'tua_rest_data_error',
                'The license key could not be updated.',
                array('status' => 404)
            );
        }

        // Update the stock
        if ($updatedLicense->getProductId() !== null && $updatedLicense->getStatus() === LicenseStatus::ACTIVE) {
            apply_filters('tua_stock_increase', $updatedLicense->getProductId());
        }

        $licenseData = $updatedLicense->toArray();

        // Remove the hash and decrypt the license key
        unset($licenseData['hash']);
        $licenseData['licenseKey'] = $updatedLicense->getDecryptedLicenseKey();

        return $this->response(true, $licenseData, 200, 'v2/licenses/{license_key}');
    }

    /**
     * Callback for the DELETE licenses/{license_key} route. Deletes an existing license key in the database.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function deleteLicense( WP_REST_Request $request ) {
        if (!$this->isRouteEnabled($this->settings, '014')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'delete')) {
            return new WP_Error(
                'tua_rest_cannot_view',
                __('Sorry, you cannot delete.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }
        $urlParams = $request->get_url_params();

        $licenseKey = isset( $urlParams['license_key'] ) ? sanitize_text_field( $urlParams['license_key'] ) : '';
        /** @var LicenseResourceModel $oldLicense */
        $oldLicense = LicenseResourceRepository::instance()->findBy(
            array(
                'hash' => apply_filters('tua_hash', $licenseKey )
            )
        );

        // Update the stock
        if ( $oldLicense && $oldLicense->getProductId() !== null && $oldLicense->getStatus() === LicenseStatus::ACTIVE ) {
            apply_filters('tua_stock_decrease', $oldLicense->getProductId());
        }

        $license = LicenseResourceRepository::instance()->deleteBy(
            array(
                'hash' => apply_filters('tua_hash', $licenseKey ),
            )
        );

        if (!$license) {
            return new WP_Error(
                'tua_rest_data_error',
                'The license key could not be found or deleted.',
                array('status' => 404)
            );
        }
        return $this->response( true, [], 200, 'v2/licenses/{license_key}' );

    }

    /**
     * Callback for the GET licenses/activate/{license_key} route. This will activate a license key (if possible)
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function activateLicense(WP_REST_Request $request)
    {
        if (!$this->isRouteEnabled($this->settings, '015')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'edit')) {
            return new WP_Error(
                'tua_rest_cannot_edit',
                __('Sorry, you are not allowed to edit this resource.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

        $licenseKey = sanitize_text_field($request->get_param('license_key'));
        $activationLabel = $request->get_param('label');
        $activationToken = $request->get_param('token');
        $activationMeta  = is_array( $request->get_param('meta') ) ? $request->get_param('meta') : array();
        $args = array(
            'label' => $activationLabel,
            'meta'  => $activationMeta,
            'token' => $activationToken
        );
        if (!$licenseKey) {
            return new WP_Error(
                'tua_rest_data_error',
                'License key is invalid.',
                array('status' => 404)
            );
        }

        $licenseData = tua_activate_license($licenseKey, $args );

        return $this->response(true, $licenseData, 200, 'v2/licenses/activate/{license_key}');
    }

    /**
     * Callback for the GET licenses/deactivate/{license_key} route. This will deactivate a license key (if possible)
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function deactivateLicense( WP_REST_Request $request )
    {
        if (!$this->isRouteEnabled($this->settings, '016')) {
            return $this->routeDisabledError();
        }

        if (!$this->permissionCheck('license', 'edit')) {
            return new WP_Error(
                'tua_rest_cannot_edit',
                __('Sorry, you are not allowed to edit this resource.', 'top-up-agent'),
                array(
                    'status' => $this->authorizationRequiredCode()
                )
            );
        }

         $licenseKey = sanitize_text_field($request->get_param('license_key'));
      

        $params = $request->get_params();
        $token = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';
        $args = array( 
            'token' => $token 
        );

        if ( empty( $licenseKey ) ) {
            return new WP_Error(
                'tua_rest_data_error',
                'License Key is invalid.',
                array('status' => 404)
            );
        }

        
        $licenseData = tua_deactivate_license( $licenseKey, $args );
        
        return $this->response(true, $licenseData, 200, 'v2/licenses/deactivate/{license_key}');
    }

    /**
     * Checks if the license has an expiry date and if it has expired already.
     *
     * @param LicenseResourceModel $license
     * @return false|WP_Error
     */
    private function hasLicenseExpired($license)
    {
        if ($expiresAt = $license->getExpiresAt()) {
            try {
                $dateExpiresAt = new DateTime($expiresAt);
                $dateNow = new DateTime('now', new DateTimeZone('UTC'));
            } catch (Exception $e) {
                return new WP_Error('tua_rest_license_expired', $e->getMessage());
            }

            if ($dateNow > $dateExpiresAt) {
                return new WP_Error(
                    'tua_rest_license_expired',
                    sprintf('The license Key expired at %s.', wp_date( tua_expiration_format(), strtotime( $license->getExpiresAt() ) )),
                    array('status' => 405)
                );
            }
        }

        return false;
    }
}