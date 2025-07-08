<?php

namespace TopUpAgent\Repositories\Resources;

use TopUpAgent\Abstracts\ResourceRepository as AbstractResourceRepository;
use TopUpAgent\Enums\ColumnType as ColumnTypeEnum;
use TopUpAgent\Interfaces\ResourceRepository as ResourceRepositoryInterface;
use TopUpAgent\Models\Resources\ApiKey as ApiKeyResourceModel;

defined('ABSPATH') || exit;

class ApiKey extends AbstractResourceRepository implements ResourceRepositoryInterface
{
    /**
     * @var string
     */
    const TABLE = 'tua_api_keys';

    /**
     * Country constructor.
     */
    public function __construct()
    {
        global $wpdb;

        $this->table      = $wpdb->prefix . self::TABLE;
        $this->primaryKey = 'id';
        $this->model      = ApiKeyResourceModel::class;
        $this->mapping    = array(
            'user_id'         => ColumnTypeEnum::BIGINT,
            'description'     => ColumnTypeEnum::VARCHAR,
            'permissions'     => ColumnTypeEnum::VARCHAR,
            'consumer_key'    => ColumnTypeEnum::CHAR,
            'consumer_secret' => ColumnTypeEnum::CHAR,
            'nonces'          => ColumnTypeEnum::LONGTEXT,
            'truncated_key'   => ColumnTypeEnum::CHAR,
            'last_access'     => ColumnTypeEnum::DATETIME
        );
    }
}
