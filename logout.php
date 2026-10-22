<?php
require __DIR__.'/config.php';
if(user()) log_action('Signed out');
$_SESSION=[];
session_destroy();
redirect('login.php');
