<?php
define('ENVIRONMENT', 'development');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ob_start();
require 'index.php';
$CI =& get_instance();
$CI->db->query("ALTER TABLE current_store_items ADD COLUMN poster_number VARCHAR(255) NULL DEFAULT ''");
echo "Done";
