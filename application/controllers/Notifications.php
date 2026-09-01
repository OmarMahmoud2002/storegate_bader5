<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends MY_Controller {

	function __construct()
	{
        parent::__construct();
        $this->load->model('Model_notifications', 'Notifications');
    }

    
    function mark_read() {
        $user_id = $this->session->userdata('user_id');
        if (empty($user_id)) {
            echo 'denied';
            return;
        }

        $this->Notifications->mark_notification_read($this->input->post('noti_id'), $user_id);
        echo 'read';
    }

    function delete_notification() {
        $id = $this->input->post('noti_id');
        $this->Notifications->delete_notification(array('id' => $id, 'user_id' => $this->session->userdata('user_id')));
        echo 'deleted';
    }
}