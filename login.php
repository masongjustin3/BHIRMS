<?php
require __DIR__.'/config.php';
if(user()) redirect('index.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $record=one('SELECT * FROM users WHERE username=?',[trim($_POST['username']??'')]);
 if($record && password_verify((string)($_POST['password']??''),$record['password_hash'])) {
  session_regenerate_id(true);
  $_SESSION['user']=array_intersect_key($record,array_flip(['user_id','username','full_name','role']));
  log_action('Signed in'); redirect('index.php');
 }
 $error='Incorrect username or password.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="style.css"><title>Login • BHIRMS</title></head><body class="auth"><form class="auth-card" method="post"><div class="brand large">✚ BHIRMS</div><h1>Welcome back</h1><p>Barangay Health Information and Record Management System</p><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><?php if(isset($_GET['created'])):?><div class="alert">Account created. Sign in below.</div><?php endif;?><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Username<input name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="btn primary" type="submit">Sign in</button><p class="auth-switch">Don't have an account? <a href="register.php">Create account</a></p></form></body></html>
