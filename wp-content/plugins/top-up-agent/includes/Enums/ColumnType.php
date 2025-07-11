<?php

namespace TopUpAgent\Enums;

defined('ABSPATH') || exit;

class ColumnType
{
    /**
     * @var string
     */
    const INT = 'INT';

     /**
     * @var string
     */
    const TEXT = 'TEXT';
    /**
     * @var string
     */
    const TINYINT = 'TINYINT';

    /**
     * @var string
     */
    const BIGINT = 'BIGINT';

    /**
     * @var string
     */
    CONST CHAR = 'CHAR';

    /**
     * @var string
     */
    CONST VARCHAR = 'VARCHAR';

    /**
     * @var string
     */
    CONST LONGTEXT = 'LONGTEXT';

    /**
     * @var string
     */
    CONST DATETIME = 'DATETIME';

    CONST SERIALIZED = 'SERIALIZED';
}
