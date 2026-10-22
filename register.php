<?php
require __DIR__.'/config.php';
if (user()) redirect('index.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $name=trim($_POST['name']??'');
    $username=trim($_POST['username']??'');
    $contact=trim($_POST['contact_number']??'');
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['confirm_password']??'');
    if (mb_strlen($name)<2 || mb_strlen($name)>150) {
        $error='Enter your full name (2 to 150 characters).';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$username)) {
        $error='Username must be 3 to 60 characters using letters, numbers, dots, dashes, or underscores.';
    } elseif (mb_strlen($contact)>30) {
        $error='Contact number must be 30 characters or fewer.';
    } elseif (strlen($password)<8) {
        $error='Password must be at least 8 characters.';
    } elseif ($password!==$confirm) {
        $error='Passwords do not match.';
    } else {
        try {
            $q=db()->prepare("INSERT INTO users(username,password_hash,full_name,role,contact_number) VALUES(?,?,?,'bhw',?)");
            $q->execute([$username,password_hash($password,PASSWORD_DEFAULT),$name,$contact?:null]);
            redirect('login.php?created=1');
        } catch (PDOException $e) {
           $error=$e->getCode()==='23000'?'That username is already taken. Choose another one.':'DB error: '.$e->getMessage();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="style.css"><title>Create account • BHIRMS</title></head><body class="auth"><form class="auth-card" method="post"><div class="brand large">✚ BHIRMS</div><h1>Create account</h1><p>Register for a Barangay Health Worker account.</p><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Full name<input name="name" autocomplete="name" maxlength="150" required></label><label>Username<input name="username" autocomplete="username" minlength="3" maxlength="60" pattern="[A-Za-z0-9_.-]+" required></label><label>Contact number <span class="muted">(optional)</span><input name="contact_number" type="tel" autocomplete="tel" maxlength="30"></label><label>Password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label><label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label><button class="btn primary" type="submit">Create account</button><p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p></form></body></html>