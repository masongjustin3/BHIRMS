<?php
require __DIR__.'/config.php';
try {
    $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Throwable $e) {
    exit('Database not ready. Import database.sql in phpMyAdmin first, then open setup.php.');
}
if ($count > 0) exit('Setup already complete. <a href="login.php">Go to login</a>. Delete setup.php from the server.');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $name=trim($_POST['name']??''); $username=trim($_POST['username']??''); $pass=$_POST['password']??'';
    if (strlen($name)<2 || !preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$username) || strlen($pass)<8) $error='Enter a name, username (3+ characters), and password (8+ characters).';
    else {
        $q=db()->prepare("INSERT INTO users(username,password_hash,full_name,role) VALUES(?,?,?,'admin')");
        $q->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$name]);
        redirect('login.php?created=1');
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="style.css"><title>BHIRMS Setup</title></head><body class="auth"><form class="auth-card" method="post"><div class="brand large">✚ BHIRMS</div><h1>Create administrator</h1><p>One-time setup for the health center.</p><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Full name<input name="name" required></label><label>Username<input name="username" required minlength="3"></label><label>Password<input name="password" type="password" required minlength="8"></label><button class="btn primary" type="submit">Create admin account</button></form></body></html>
