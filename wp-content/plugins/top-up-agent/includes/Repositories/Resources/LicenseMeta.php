<?php

namespace TopUpAgent\Repositories\Resources;

use TopUpAgent\Abstracts\ResourceRepository as AbstractResourceRepository;
use TopUpAgent\Enums\ColumnType;
use TopUpAgent\Interfaces\ResourceRepository as ResourceRepositoryInterface;
use TopUpAgent\Models\Resources\LicenseMeta as LicenseMetaResourceModel;

defined('ABSPATH') || exit;

class LicenseMeta extends AbstractResourceRepository implements ResourceRepositoryInterface
{
    /**
     * @var string
     */
    const TABLE = 'tua_licenses_meta';

    /**
     * Country constructor.
     */
    public function __construct()
    {
        global $wpdb;

        $this->table      = $wpdb->prefix . self::TABLE;
        $this->primaryKey = 'meta_id';
        $this->model      = LicenseMetaResourceModel::class;
        $this->mapping    = array(
            'license_id' => ColumnType::BIGINT,
            'meta_key'   => ColumnType::VARCHAR,
            'meta_value' => ColumnType::LONGTEXT,
        );
    }
}
