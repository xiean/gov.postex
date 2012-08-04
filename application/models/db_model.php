<?php

class db_model extends CI_Model {
    private $table = FALSE;
    private $id = FALSE;

    public $conn = FALSE;

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function init($table = FALSE, $id = FALSE) {
        if( $table ) {
            $this->table = $table;
        }
        if( $id ) {
            $this->id = $id;
        }
    }

    public function count($where = FALSE) {
        if( $where ) {
            $this->db->where($where);
        }
        $result = $this->db
                ->from($this->table)
                ->select("COUNT(*) AS sum")
                ->get()->row_array();

        return $result['sum'];
    }

    public function set($data, $where = FALSE) {
        if( $where ) {
            // update
            $this->db
                    ->where($where)
                    ->update($this->table, $data);
            return $this->db->affected_rows();
        } else {
            // insert
            $this->db
                    ->insert($this->table, $data);
            return $this->db->insert_id();
        }
    }

    public function rm($where, $limit = 1) {
        $this->db
                ->where($where)
                ->limit($limit)
                ->delete($this->table);
        return $this->db->last_query();
    }

    public function select($select, $where = FALSE, $order = FALSE, $limit = FALSE) {
        $this->db->select($select, FALSE);

        return $this->get($where, $order, $limit);
    }

    public function get($where = FALSE, $order = FALSE, $limit = FALSE) {
        if( $where ) {
            $this->db->where($where);
        }
        if( $order ) {
            $this->db->order_by($order);
        }
        if( $limit ) {
            $this->db->limit($limit);
        }

        return $this->db
                ->from($this->table)
                ->get()->result_array();
    }

    public function getById($id) {
        return $this->db
                ->from($this->table)
                ->where(array(
                    $this->id => $id,
                ))
                ->get()->row_array();
    }


    /*
     * 配置库专用
     */
    public function getConf($key) {
        $result = $this->getById($key);
        return $result['name'];
    }
}

?>
