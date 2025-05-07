<?php
require 'config.php';

session_destroy();
header('Location: home_page.php');
exit;
?>