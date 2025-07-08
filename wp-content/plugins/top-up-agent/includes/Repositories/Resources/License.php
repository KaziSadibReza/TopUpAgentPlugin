<?php

namespace TopUpAgent\Repositories\Resources;

use TopUpAgent\Abstracts\ResourceRepository as AbstractResourceRepository;
use TopUpAgent\Enums\ColumnType as ColumnTypeEnum;
use TopUpAgent\Interfaces\ResourceRepository as ResourceRepositoryInterface;
use TopUpAgent\Models\Resources\License as LicenseResourceModel;

defined('ABSPATH') || exit;

class License extends AbstractResourceRepository implements ResourceRepositoryInterface
{
    /**
     * @var string
     */
    const TABLE = 'tua_licenses';

    /**
     * Country constructor.
     */
    public function __construct()
    {
        global $wpdb;

        $this->table      = $wpdb->prefix . self::TABLE;
        $this->primaryKey = 'id';
        $this->model      = LicenseResourceModel::class;
        $this->mapping    = array(
            'order_id'            => ColumnTypeEnum::BIGINT,
            'product_id'          => ColumnTypeEnum::BIGINT,
            'user_id'             => ColumnTypeEnum::BIGINT,
            'license_key'         => ColumnTypeEnum::LONGTEXT,
            'hash'                => ColumnTypeEnum::LONGTEXT,
            'expires_at'          => ColumnTypeEnum::DATETIME,
            'valid_for'           => ColumnTypeEnum::INT,
            'source'              => ColumnTypeEnum::TINYINT,
            'status'              => ColumnTypeEnum::TINYINT,
            'times_activated'     => ColumnTypeEnum::INT,
            'times_activated_max' => ColumnTypeEnum::INT,
        );
    }
}
