<?php 

defined('BASEPATH') OR exit('No direct script access allowed');

class Model_permissions extends CI_Model {
    protected $permissions_table;
    protected $roles_permissions_table;
    protected $roles_table;
    private $leave_permissions_ready = false;

    function __construct() {
        parent::__construct();
        $this->permissions_table = 'permissions';
        $this->roles_permissions_table = 'role_permissions';
        $this->roles_table = 'roles';
    }

    function get_permissions() {
        $this->db->select('*');
        $this->db->from($this->permissions_table);
        $query = $this->db->get();
        return $query->result_array();
    }

    function get_permission_by_id($id) {
        $this->db->select('*');
        $this->db->from($this->permissions_table);
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->row_array();
    }

    function get_permission_by_key($key) {
        $this->db->select('*');
        $this->db->from($this->permissions_table);
        $this->db->where('key', $key);
        $query = $this->db->get();
        return $query->row_array();
    }

    function get_role_permissions_by_role_id($role_id) {
        # join the two tables to get the permissions for the role
        $this->db->select('p.key as permission_key');
        $this->db->from($this->roles_permissions_table . ' AS rp');
        $this->db->join($this->permissions_table . ' AS p', 'rp.permission_id = p.id', 'left');
        $this->db->where('rp.role_id', $role_id);
        $query = $this->db->get();
        return array_column($query->result_array(), 'permission_key');
    }

    function get_role_permissions_by_role_name($role_name) {
        
    }

    function create_role($name, $description) {
        $data = array(
            'name' => $name,
            'description' => $description,
            'date_created' => date('Y-m-d H:i:s')
        );
        $this->db->insert($this->roles_table, $data);
        return $this->db->insert_id();
    }


    function add_permission($data) {
        $this->db->insert($this->permissions_table, $data);
        return $this->db->insert_id();
    }

    /**
     * Register application permissions that may not exist in older databases.
     * Newly added permissions inherit the roles that already own the supplied
     * parent permission, preserving existing access until an admin changes it.
     */
    function ensure_permissions($permissions, $inherit_from_key = null) {
        if (!is_array($permissions) || empty($permissions)) {
            return;
        }

        $inherited_role_ids = array();
        if (!empty($inherit_from_key)) {
            $parent_permission = $this->get_permission_by_key($inherit_from_key);
            if (!empty($parent_permission['id'])) {
                $this->db->select('role_id');
                $this->db->distinct();
                $this->db->from($this->roles_permissions_table);
                $this->db->where('permission_id', (int) $parent_permission['id']);
                $inherited_role_ids = array_column($this->db->get()->result_array(), 'role_id');
            }
        }

        foreach ($permissions as $permission) {
            if (empty($permission['key']) || $this->get_permission_by_key($permission['key'])) {
                continue;
            }

            $this->db->query(
                'INSERT IGNORE INTO `permissions` (`key`, `label`, `type`, `page_label`, `route`, `section_name`, `section_icon`) VALUES (?, ?, ?, ?, ?, ?, ?)',
                array(
                    $permission['key'],
                    isset($permission['label']) ? $permission['label'] : '',
                    isset($permission['type']) ? $permission['type'] : 'special',
                    isset($permission['page_label']) ? $permission['page_label'] : '',
                    isset($permission['route']) ? $permission['route'] : '',
                    isset($permission['section_name']) ? $permission['section_name'] : '',
                    isset($permission['section_icon']) ? $permission['section_icon'] : '',
                )
            );

            if ($this->db->affected_rows() < 1 || empty($inherited_role_ids)) {
                continue;
            }

            $created_permission = $this->get_permission_by_key($permission['key']);
            if (empty($created_permission['id'])) {
                continue;
            }

            $role_permissions = array();
            foreach ($inherited_role_ids as $role_id) {
                $role_permissions[] = array(
                    'role_id' => (int) $role_id,
                    'permission_id' => (int) $created_permission['id'],
                );
            }
            $this->db->insert_batch($this->roles_permissions_table, $role_permissions);
        }
    }

    public function get_employee_leave_permissions() {
        $permissions = array(
            array('key' => 'employees_leaves.view', 'label' => 'عرض الإجازات', 'type' => 'view'),
            array('key' => 'employees_leaves.create', 'label' => 'إضافة إجازة', 'type' => 'create'),
            array('key' => 'employees_leaves.delete', 'label' => 'حذف إجازة', 'type' => 'delete'),
            array('key' => 'employees_leaves.limit.update', 'label' => 'تعديل العدد المسموح شهريًا', 'type' => 'update'),
        );
        foreach ($permissions as &$permission) {
            $permission += array('page_label' => 'الإجازات', 'route' => 'admin/employees_leaves',
                'section_name' => 'الرفوف والموظفين', 'section_icon' => 'bi-people');
        }
        unset($permission);
        return $permissions;
    }

    public function ensure_employee_leave_permissions() {
        if ($this->leave_permissions_ready) return;
        $permissions = $this->get_employee_leave_permissions();
        $this->ensure_permissions(array($permissions[0]), 'employees_timetable.view');
        $this->ensure_permissions(array_slice($permissions, 1), 'employees_timetable.save');
        $this->leave_permissions_ready = true;
    }

    function update_role($role_id, $role_name, $role_description) {
        $this->db->where('id', $role_id);
        $data = array(
            'name' => $role_name,
            'description' => $role_description
        );
        $this->db->update($this->roles_table, $data);
    }

    function add_role_permissions($role_id, $permissions) {
        $this->delete_role_permissions($role_id);
        $data = array();
        foreach ($permissions as $permission_key) {
            $data[] = array(
                'role_id' => $role_id,
                'permission_id' => $this->get_permission_by_key($permission_key)['id']
            );
        }
        $this->db->insert_batch('role_permissions', $data);
    }

    function get_roles() {
        $this->db->select('*');
        $this->db->from($this->roles_table);
        $this->db->order_by('date_created', 'ASC');
        $query = $this->db->get();
        return $query->result_array();
    }

    function get_role($role_id) {
        $this->db->select('*');
        $this->db->from($this->roles_table);
        $this->db->where('id', $role_id);
        $query = $this->db->get();
        return $query->row_array();
    }

    function has_permission($role_id, $permission_id) {
        $this->db->select('*');
        $this->db->from($this->roles_permissions_table);
        $this->db->where('role_id', $role_id);
        $this->db->where('permission_id', $permission_id);
        $query = $this->db->get();
        return $query->num_rows() > 0;
    }

    function delete_role_permissions($role_id) {
        $this->db->delete($this->roles_permissions_table, ['role_id' => $role_id]);
    }

    function update_role_for_admins($role_id) {
        $this->db->where(['user_type' => 'admin', 'user_role_id' => $role_id]);
        $this->db->update('tbl_user', ['user_role_id' => null]);
    }

    function delete_role($role_id) {
        $this->db->delete($this->roles_table, ['id' => $role_id]);
    }
}
