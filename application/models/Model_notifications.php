<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_notifications extends CI_Model {
    protected $table;
    public function __construct()
    {
        parent::__construct();
        $this->table = 'tbl_notifications';
    }

    public function get_notifications($where = array())
    {
        $this->db->order_by('id', 'asc');
        $this->db->where($where);
        $query = $this->db->get($this->table);
        return $query->result();
    }

    function get_notifications_length($where = array()) {
        return count($this->get_notifications($where));
    }

    public function get_one_notification_by_id($id)
    {
        $this->db->where(array('id' => $id));
        $this->db->order_by('id', 'asc');
        $query = $this->db->get($this->table);
        return $query->row();
    }

    public function add_notification($data)
    {
        $this->db->insert($this->table, $data);
    }

    public function update_notification($id, $data)
    {
        $this->db->update($this->table, $data, array('id' => $id));
    }

    /**
     * Marks one notification as read. Scoped by user_id so a session can only
     * ever flip its own rows, and already-read rows are left untouched.
     */
    public function mark_notification_read($id, $user_id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('user_id', (int) $user_id);
        $this->db->where('status', 'unread');
        $this->db->update($this->table, array('status' => 'read'));
        return $this->db->affected_rows() === 1;
    }

    public function mark_all_notifications_read($user_id)
    {
        $this->db->where('user_id', (int) $user_id);
        $this->db->where('status', 'unread');
        $this->db->update($this->table, array('status' => 'read'));
        return $this->db->affected_rows();
    }

    public function delete_notification($data)
    {
        $rows = $this->get_notifications($data);
        $this->db->delete($this->table, $data);
        return $rows;
    }

}

?>