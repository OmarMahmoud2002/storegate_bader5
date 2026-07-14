<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');
class MY_Controller extends CI_Controller
{
    function __construct ()
    {
        parent::__construct();
        if($this->config->item('mod_rewrite') == 'Off') {
			define('MOD_VALUE', 'index.php/');
		} else {
			define('MOD_VALUE', '');
		}

        $this->load->model('Model_admin');
        $this->load->model('Model_permissions', 'Permissions');
    }

    protected function get_admins() {
		$ids = array();
		foreach ($this->Model_admin->getadmins() as $admin) {
			array_push($ids, strval($admin['user_id']));
		}
		return $ids;
	}

	public function check_permission($permission) {
		$user = $this->Model_admin->get_user_by_id($this->session->userdata('user_id'));
        if (empty($user['user_role_id'])) {
            return true;
        }
		if (!$this->Permissions->has_permission($user['user_role_id'], $this->Permissions->get_permission_by_key($permission)['id'])) {
			$this->session->set_flashdata('error', 'ليس لديك صلاحية الوصول إلى الصفحة المطلوبة');
			redirect(base_url() . MOD_VALUE . 'admin/products/search');
			exit;
		}
	}

    public function get_permission_enabled($permission)
    {
        $user = $this->Model_admin->get_user_by_id($this->session->userdata('user_id'));
        if (empty($user['user_role_id'])) {
            return true;
        }

        $permissionRecord = $this->Permissions->get_permission_by_key($permission);
        if (empty($permissionRecord) || !isset($permissionRecord['id'])) {
            return true;
        }

        return $this->Permissions->has_permission($user['user_role_id'], $permissionRecord['id']);
    }
}