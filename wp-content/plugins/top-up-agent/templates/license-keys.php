<?php
// Load and initialize the controller from the new folder structure
require_once plugin_dir_path(__FILE__) . '../includes/license-management/class-top-up-agent-license-keys-controller.php';

$controller = new Top_Up_Agent_License_Keys_Controller();
$controller->render_page();
?>