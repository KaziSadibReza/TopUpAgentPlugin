<?php

namespace TopUpAgent\Abstracts;

use TopUpAgent\Enums\ColumnType as ColumnTypeEnum;
use TopUpAgent\Interfaces\ResourceRepository as RepositoryInterface;
use TopUpAgent\Setup;

defined('ABSPATH') || exit;

abstract class ResourceRepository extends Singleton implements RepositoryInterface
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $primaryKey;

    /**
     * @var string
     */
    protected $model;

    /**
     * @var array
     */
    protected $mapping;

    
    protected $useCreatedBy = true;

    protected $useCreatedAt = true;

    protected $useUpdatedAt = true;

    protected $useUpdatedBy = true;

    /**
     * Sanitizes the user data when adding or updating entities.
     *
     * @param array $data
     *
     * @return void
     */
    function sanitize(&$data)
    {
        foreach ($data as $column => $value) {

            switch ($this->mapping[$column]) {

                case ColumnTypeEnum::CHAR:
                case ColumnTypeEnum::VARCHAR:
                case ColumnTypeEnum::LONGTEXT:
                case ColumnTypeEnum::DATETIME:
                case ColumnTypeEnum::SERIALIZED:

                    if ($data[$column] !== null) {
                        $data[$column] = ($value);
                    }
                    break;
                case ColumnTypeEnum::INT:
                case ColumnTypeEnum::TINYINT:
                case ColumnTypeEnum::BIGINT:
                    if ($data[$column] !== null) {
                        $data[$column] = intval($value);
                    }
                    break;
            }
        }
    }

   

    /**
     * Adds a new entry to the table.
     *
     * @param array $data
     *
     * @return bool|ResourceModel
     */
    public function insert($data)
    {
        global $wpdb;
    
        // Prepare meta data
        $meta = array(
            'created_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => get_current_user_id()
        );
    
        // Sanitize input data
        $this->sanitize($data);
    
        // Combine data and meta for insertion
        $data = array_merge($data, $meta);
    
        // Perform the insertion using $wpdb->insert()
        $inserted = $wpdb->insert($this->table, $data);
    
        if (!$inserted) {
            return false; // Insertion failed
        }
    
        // Return the inserted record by its ID
        return $this->find($wpdb->insert_id);
    }
    
    


    /**
     * Retrieves a single table row by its ID.
     *
     * @param int $id
     *
     * @return bool|ResourceModel
     */
    public function find($id)
    {
        if (!class_exists($this->model)) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = %d;",
                $id
            )
        );

        if (!$result) {
            return false;
        }

        return new $this->model($result);
    }

    /**
     * Retrieves a single table row by the query parameter.
     *
     * @param array $query
     *
     * @return bool|ResourceModel
     */
    public function findBy($query)
    {
        if (!class_exists($this->model) || !$query || !is_array($query) || count($query) <= 0) {
            return false;
        }

        global $wpdb;

        $sqlQuery = "SELECT * FROM {$this->table} WHERE 1=1 ";
        $sqlQuery .= $this->parseQueryConditions($query);
        $sqlQuery .= ';';

        $result = $wpdb->get_row($sqlQuery);

        if (!$result) {
            return false;
        }

        return new $this->model($result);
    }

    /**
     * Retrieves all table rows as an array.
     *
     * @return bool|ResourceModel[]
     */
    public function findAll()
    {
        global $wpdb;

        $returnValue = array();
        $result = $wpdb->get_results("SELECT {$this->primaryKey} FROM {$this->table};");

        if (!$result) {
            return false;
        }

        foreach ($result as $row) {
            $returnValue[] = $this->find($row->id);
        }

        return $returnValue;
    }

    /**
     * Retrieves multiple table rows as an array, filtered by the query.
     *
     * @param array       $query
     * @param null|string $orderBy
     * @param null|string $sort
     *
     * @return bool|ResourceModel[]
     */
    public function findAllBy($query, $orderBy = null, $sort = null)
    {
        if (!class_exists($this->model) || !$query || !is_array($query) || count($query) <= 0) {
            return false;
        }

        global $wpdb;

        $result   = array();
        $sqlQuery = "SELECT * FROM {$this->table} WHERE 1=1 ";
        $sqlQuery .= $this->parseQueryConditions($query);

        if ($orderBy && is_string($orderBy)) {
            $sqlQuery .= "ORDER BY {$orderBy} ";
        }

        if ($sort && is_string($sort)) {
            $sqlQuery .= "{$sort} ";
        }

        $sqlQuery .= ';';

        foreach ($wpdb->get_results($sqlQuery) as $row) {
            $result[] = new $this->model($row);
        }

        return $result;
    }



    /**
     * Updates a single table row by its ID.
     *
     * @param int $id
     * @param array $data
     *
     * @return bool|AbstractResourceModel
     */
    public function update( $id, $data ) {
        global $wpdb;

        $meta = array();
        if ( $this->useUpdatedAt ) {
            $meta['updated_at'] = gmdate( 'Y-m-d H:i:s' );
        }
        if ( $this->useUpdatedBy ) {
            $meta['updated_by'] = get_current_user_id();
        }

        // Pass the data by reference and sanitize its contents
        $this->sanitize( $data );

        $updated = $wpdb->update(
            $this->table,
            array_merge( $data, $meta ),
            array( 'id' => $id )
        );

        if ( ! $updated ) {
            return false;
        }

        return $this->find( $id );
    }

    /**
     * Updates one or multiple table rows by the query.
     *
     * @param array $query
     * @param array $data
     *
     * @return bool|int
     */
    public function updateBy($query, $data)
    {
        if (!$query || !is_array($query) || count($query) <= 0) {
            return false;
        }

        if (!$data || !is_array($data) || count($data) <= 0) {
            return false;
        }

        global $wpdb;

        $sqlQuery = "UPDATE {$this->table} SET ";

        $sqlQuery .= $wpdb->prepare(' updated_at = %s,', gmdate('Y-m-d H:i:s'));
        $sqlQuery .= $wpdb->prepare(' updated_by = %d,', get_current_user_id());

        foreach ($data as $columnName => $value) {
            if (is_numeric($value)) {
                $sqlQuery .= " {$columnName} = {$value},";
            }

            elseif (is_string($value)) {
                $sqlQuery .= " {$columnName} = '{$value}',";
            }

            elseif ($value === null) {
                $sqlQuery .= " {$columnName} = NULL,";
            }
        }

        $sqlQuery = rtrim($sqlQuery, ',');

        $sqlQuery .= ' WHERE 1=1 ';
        $sqlQuery .= $this->parseQueryConditions($query);

        $sqlQuery .= ';';

        return $wpdb->query($sqlQuery);
    }

    /**
     * Removes multiple table rows by their ID's.
     *
     * @param array $ids
     *
     * @return bool|int
     */
    public function delete($ids)
    {

        global $wpdb;

        if ( ! is_array( $ids ) ) {
            $ids = (array) $ids;
        }

        $ids = implode(', ', array_map('absint', $ids));
        $sqlQuery = "DELETE FROM {$this->table} WHERE {$this->primaryKey} IN ({$ids});";
        
        return $wpdb->query($sqlQuery);
    }

    /**
     * Deletes one or more table rows by the query parameter.
     *
     * @param array $query
     *
     * @return bool|int
     */
    public function deleteBy($query)
    {

        if (!$query || !is_array($query) || count($query) <= 0) {
            return false;
        }
         

        global $wpdb;

        $sqlQuery = "DELETE FROM {$this->table} WHERE 1=1 ";
        $sqlQuery .= $this->parseQueryConditions($query);
        $sqlQuery .= ';';
      
        return $wpdb->query($sqlQuery);
    }

    /**
     * Retrieves the total count of table entries.
     *
     * @return int
     */
    public function count()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table};");

        return intval($count);
    }

    /**
     * Retrieves the total count of table entries, filtered by the query parameter.
     *
     * @param array $query
     *
     * @return int
     */
    public function countBy($query)
    {
        if (!$query || !is_array($query) || count($query) <= 0) {
            return false;
        }

        global $wpdb;

        $sqlQuery = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1 ";
        $sqlQuery .= $this->parseQueryConditions($query);
        $sqlQuery .= ';';

        $count = $wpdb->get_var($sqlQuery);

        return intval($count);
    }

    /**
     * Performs a general query on the table.
     *
     * @param string $sqlQuery
     * @param string $output
     *
     * @return array|object|null
     */
    public function query($sqlQuery, $output = OBJECT)
    {
        global $wpdb;

        return $wpdb->get_results($sqlQuery, $output);
    }

    /**
     * Truncates the table.
     *
     * @return bool|int
     */
    public function truncate()
    {
        global $wpdb;

        return $wpdb->query("TRUNCATE TABLE {$this->table};");
    }

    /**
     * @param array $query
     *
     * @return string
     */
    private function parseQueryConditions($query)
    {
        $result = '';

        foreach ($query as $columnName => $value) {

            if (is_array($value)) {
                $valuesIn = implode(', ', array_map('absint', $value));
                $result .= "AND {$columnName} IN ({$valuesIn}) ";
            }

            elseif (is_string($value)) {
                $result .= "AND {$columnName} = '{$value}' ";
            }

            elseif (is_numeric($value)) {
                $value = absint($value);
                $result .= "AND {$columnName} = {$value} ";
            }

            elseif ($value === null) {
                $result .= "AND {$columnName} IS NULL ";
            }
        }
      
        return $result;

    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return array
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * Checks whether an array has string keys.
     *
     * @param array $array
     * @return bool
     */
    private function hasStringKeys($array) {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * Determines if the given string is a valid MySQL logical operator.
     * @see https://www.scommerce-mage.com/blog/magento2-condition-type-search-filter.html
     *
     * @param string $string
     *
     * @return bool
     */
    private function isLogicalOperator($string)
    {
        return in_array(strtoupper($string), array('AND', 'OR', 'IN', 'NOT IN', 'NOT LIKE'));
    }
}
