<?php
declare(strict_types=1);
require __DIR__.'/config.php';
require_login();
$module = $_GET['module'] ?? 'dashboard';
$modules = [
 'households'=>['title'=>'Households','pk'=>'household_id','fields'=>['household_head'=>['text','Household head',true],'address'=>['text','Address',true],'sitio_zone'=>['text','Sitio / Zone',true],'contact_number'=>['tel','Contact number',false]]],
 'patients'=>['title'=>'Patients','pk'=>'patient_id','fields'=>['household_id'=>['households','Household',true],'first_name'=>['text','First name',true],'last_name'=>['text','Last name',true],'birthdate'=>['date','Birthdate',true],'sex'=>['sex','Sex',true],'contact_number'=>['tel','Contact number',false]]],
 'visits'=>['title'=>'Patient Visits','pk'=>'visit_id','fields'=>['patient_id'=>['patients','Patient',true],'user_id'=>['users','Recorded by',true],'visit_date'=>['datetime-local','Visit date',true],'diagnosis'=>['text','Diagnosis / reason',true],'notes'=>['textarea','Notes',false]]],
 'sessions'=>['title'=>'Patient Sessions','pk'=>'session_id','fields'=>['patient_id'=>['patients','Patient',true],'user_id'=>['users','Recorded by',true],'session_date'=>['datetime-local','Session date',true],'session_type'=>['text','Session type',true],'outcome'=>['text','Outcome',false],'session_status'=>['session_status','Status',true],'notes'=>['textarea','Notes',false]]],
 'transactions'=>['title'=>'Transactions','pk'=>'transaction_id','fields'=>['transaction_date'=>['date','Transaction date',true],'transaction_type'=>['transaction_type','Transaction type',true],'patient_id'=>['patients','Patient',false],'user_id'=>['users','Recorded by',true],'medicine_id'=>['medicines','Medicine',false],'quantity'=>['number','Quantity',false],'description'=>['text','Description',true],'amount'=>['money','Amount',true],'payment_status'=>['payment_status','Payment status',true]]],
 'immunizations'=>['title'=>'Immunizations','pk'=>'immunization_id','fields'=>['patient_id'=>['patients','Patient',true],'administered_by'=>['users','Administered by',true],'vaccine_name'=>['text','Vaccine name',true],'dose_number'=>['number','Dose number',true],'date_administered'=>['date','Date administered',true],'next_due_date'=>['date','Next due date',false]]],
 'medicines'=>['title'=>'Medicine Inventory','pk'=>'medicine_id','fields'=>['medicine_name'=>['text','Medicine name',true],'unit'=>['text','Unit (tablet, bottle, etc.)',true],'stock_quantity'=>['number','Stock quantity',true],'expiration_date'=>['date','Expiration date',true]]],
 'medicine_dispensed'=>['title'=>'Medicine Dispensing','pk'=>'dispense_id','fields'=>['visit_id'=>['visits','Patient visit',true],'medicine_id'=>['medicines','Medicine',true],'quantity'=>['number','Quantity dispensed',true],'date_dispensed'=>['date','Date dispensed',true]]],
 'appointments'=>['title'=>'Appointments','pk'=>'appointment_id','fields'=>['patient_id'=>['patients','Patient',true],'user_id'=>['users','Assigned health worker',true],'appointment_date'=>['datetime-local','Appointment date',true],'purpose'=>['text','Purpose',true],'status'=>['status','Status',true]]],
 'users'=>['title'=>'System Users','pk'=>'user_id','fields'=>['username'=>['text','Username',true],'full_name'=>['text','Full name',true],'role'=>['role','Role',true],'contact_number'=>['tel','Contact number',false],'password'=>['password','New password',false]]],
 'activity_logs'=>['title'=>'Activity Logs','pk'=>'log_id','fields'=>[]],
];
if (!array_key_exists($module,$modules) && !in_array($module,['dashboard','reports','profile'],true)) $module='dashboard';
if (in_array($module,['users','activity_logs'],true)) admin_only();
$meta=$modules[$module]??null;
$action=$_GET['action']??'list';
$editing=null;
$fkLabels=['households'=>'CONCAT(household_head," (",sitio_zone,")")','patients'=>'CONCAT(first_name," ",last_name)','users'=>'CONCAT(full_name," (",role,")")','visits'=>'CONCAT("Visit #",visit_id," - ",DATE_FORMAT(visit_date,"%b %d, %Y"))','medicines'=>'CONCAT(medicine_name," (",stock_quantity," ",unit," left)")'];
function options_for(string $type): array {
 global $fkLabels;
 $pk=['households'=>'household_id','patients'=>'patient_id','users'=>'user_id','visits'=>'visit_id','medicines'=>'medicine_id'][$type];
 return rows("SELECT `$pk` AS id, {$fkLabels[$type]} AS label FROM `$type` ORDER BY label");
}
function value_html(string $field,mixed $value): string {
 if ($value===null || $value==='') return '<span class="muted">—</span>';
 if ($field==='next_due_date') {
  $today=date('Y-m-d'); $future=date('Y-m-d',strtotime('+7 days'));
  $cls=$value<$today?'danger':($value<=$future?'warn':'');
  return '<span class="pill '.$cls.'">'.e($value).'</span>';
 }
 if ($field==='stock_quantity' && (int)$value <= 10) return '<span class="pill warn">'.e($value).' — Low</span>';
 if ($field==='expiration_date' && $value<date('Y-m-d')) return '<span class="pill danger">'.e($value).' — Expired</span>';
 return e($value);
}
function referential_error(Throwable $t): string {
 if ($t instanceof PDOException && $t->getCode()==='23000') return 'This record is referenced elsewhere or a unique value already exists. Remove dependent records first or choose a different value.';
 return 'Could not save your changes. Check the entered values and try again.';
}
function transaction_stock_delta(array $record): int {
 if (($record['transaction_type']??'')==='medicine_dispense') return -(int)$record['quantity'];
 if (($record['transaction_type']??'')==='medicine_restock') return (int)$record['quantity'];
 return 0;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $mode=$_POST['mode']??'';
 try {
  if ($mode==='save_profile') {
   $name=trim($_POST['full_name']??'');$phone=trim($_POST['contact_number']??'');$pass=$_POST['password']??'';
   if(strlen($name)<2 || ($pass!=='' && strlen($pass)<8)) throw new RuntimeException('Name is required, and any new password must have at least 8 characters.');
   if($pass!=='') { $q=db()->prepare('UPDATE users SET full_name=?,contact_number=?,password_hash=? WHERE user_id=?');$q->execute([$name,$phone,password_hash($pass,PASSWORD_DEFAULT),user()['user_id']]); }
   else { $q=db()->prepare('UPDATE users SET full_name=?,contact_number=? WHERE user_id=?');$q->execute([$name,$phone,user()['user_id']]); }
   $_SESSION['user']['full_name']=$name; log_action('Updated profile');flash('Profile updated.');redirect('index.php?module=profile');
  }
  if (!$meta || $module==='activity_logs') throw new RuntimeException('Invalid action.');
  $pk=$meta['pk'];$table=$module;
  $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0;
   $creatingHousehold=$table==='patients' && !$id;
  if($table==='users') admin_only();
  if($mode==='delete') {
   if($id<1) throw new RuntimeException('Invalid record.');
   if($table==='users' && $id==(int)user()['user_id']) throw new RuntimeException('You cannot delete your own account.');
     if($table==='transactions') {
    db()->beginTransaction();
        $old=one('SELECT * FROM transactions WHERE transaction_id=? FOR UPDATE',[$id]);
    if(!$old) throw new RuntimeException('Record not found.');
        $delta=transaction_stock_delta($old);
        if($delta!==0) {
         $medicine=one('SELECT stock_quantity FROM medicines WHERE medicine_id=? FOR UPDATE',[$old['medicine_id']]);
         if(!$medicine || (int)$medicine['stock_quantity']-$delta<0) throw new RuntimeException('This stock movement cannot be removed because later transactions used that stock.');
         db()->prepare('UPDATE medicines SET stock_quantity=stock_quantity-? WHERE medicine_id=?')->execute([$delta,$old['medicine_id']]);
        }
        db()->prepare('DELETE FROM transactions WHERE transaction_id=?')->execute([$id]);
    db()->commit();
   } else {
    $q=db()->prepare("DELETE FROM `$table` WHERE `$pk`=?");$q->execute([$id]);
   }
   log_action("Deleted $table #$id");flash('Record deleted.');redirect('index.php?module='.$table);
  }
  if($mode!=='save') throw new RuntimeException('Invalid action.');
  $data=[];
  foreach($meta['fields'] as $field=>$spec) {
   if($field==='password' || ($table==='patients' && $field==='household_id')) continue;
   $v=trim((string)($_POST[$field]??''));
   if($spec[2] && $v==='') throw new RuntimeException($spec[1].' is required.');
    if($spec[0]==='number' && $v!=='' && (!ctype_digit($v) || (int)$v<($field==='dose_number'||$field==='quantity'?1:0))) throw new RuntimeException($spec[1].' must be a valid positive number.');
   if(in_array($spec[0],['date','datetime-local'],true) && $v!=='') {
    $format=$spec[0]==='date'?'Y-m-d':'Y-m-d\TH:i';
    $dt=DateTimeImmutable::createFromFormat('!'.$format,$v);
    if(!$dt || $dt->format($format)!==$v) throw new RuntimeException('Invalid date in '.$spec[1]);
    if($spec[0]==='datetime-local') $v=str_replace('T',' ',$v).':00';
   }
   if($field==='sex' && !in_array($v,['Male','Female'],true)) throw new RuntimeException('Invalid sex.');
   if($field==='role' && !in_array($v,['admin','midwife','bhw'],true)) throw new RuntimeException('Invalid role.');
   if($field==='status' && !in_array($v,['Scheduled','Completed','Cancelled'],true)) throw new RuntimeException('Invalid status.');
    if($field==='session_status' && !in_array($v,['Completed','Follow-up','Referred'],true)) throw new RuntimeException('Invalid session status.');
    if($field==='transaction_type' && !in_array($v,['service','medicine_dispense','medicine_restock'],true)) throw new RuntimeException('Invalid transaction type.');
    if($field==='payment_status' && !in_array($v,['Paid','Unpaid'],true)) throw new RuntimeException('Invalid payment status.');
    if($spec[0]==='money' && (!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$v) || (float)$v<0)) throw new RuntimeException($spec[1].' must be a valid non-negative amount.');
   $data[$field]=$v===''?null:$v;
  }
   if($creatingHousehold) {
    $newHousehold=[
      'household_head'=>trim($_POST['household_head']??''),
      'address'=>trim($_POST['household_address']??''),
      'sitio_zone'=>trim($_POST['household_sitio_zone']??''),
      'contact_number'=>trim($_POST['household_contact_number']??'')
    ];
    if($newHousehold['household_head']==='' || $newHousehold['address']==='' || $newHousehold['sitio_zone']==='') throw new RuntimeException('Household head, address, and sitio/zone are required.');
    if(strlen($newHousehold['household_head'])>150 || strlen($newHousehold['address'])>255 || strlen($newHousehold['sitio_zone'])>100 || strlen($newHousehold['contact_number'])>30) throw new RuntimeException('One or more household details exceed the allowed length.');
    $newHousehold['contact_number']=$newHousehold['contact_number']?:null;
   }
  if($table==='transactions') {
    $movement=transaction_stock_delta($data)!==0;
    if($movement && (empty($data['medicine_id']) || empty($data['quantity']))) throw new RuntimeException('Choose a medicine and enter a positive quantity for stock transactions.');
    if(!$movement) { $data['medicine_id']=null;$data['quantity']=null; }
    if($data['transaction_type']!=='medicine_restock' && empty($data['patient_id'])) throw new RuntimeException('Choose a patient for services and medicine dispensing.');
    if($data['transaction_type']==='medicine_restock') $data['patient_id']=null;
  }
  if($table==='users') {
   $pass=(string)($_POST['password']??'');
   if(!$id && strlen($pass)<8) throw new RuntimeException('A new user needs a password of at least 8 characters.');
   if($pass!=='' && strlen($pass)<8) throw new RuntimeException('Password must have at least 8 characters.');
   if($pass!=='') $data['password_hash']=password_hash($pass,PASSWORD_DEFAULT);
   if($id==(int)user()['user_id'] && ($data['role']??'')!=='admin') throw new RuntimeException('You cannot remove your own administrator access.');
  }
    if($table==='transactions') {
   db()->beginTransaction();
     $old=$id?one('SELECT * FROM transactions WHERE transaction_id=? FOR UPDATE',[$id]):false;
     if($id && !$old) throw new RuntimeException('Transaction not found.');
     $medicineIds=[];
     if($old && transaction_stock_delta($old)!==0) $medicineIds[]=(int)$old['medicine_id'];
     if(transaction_stock_delta($data)!==0) $medicineIds[]=(int)$data['medicine_id'];
     $medicineIds=array_values(array_unique($medicineIds));sort($medicineIds);
     $stocks=[];$medicines=[];
     foreach($medicineIds as $medicineId) {
        $med=one('SELECT * FROM medicines WHERE medicine_id=? FOR UPDATE',[$medicineId]);
        if(!$med) throw new RuntimeException('Medicine not found.');
        $stocks[$medicineId]=(int)$med['stock_quantity'];$medicines[$medicineId]=$med;
     }
     if($old) {
        $oldDelta=transaction_stock_delta($old);
        if($oldDelta!==0) $stocks[(int)$old['medicine_id']]-=$oldDelta;
     }
     $newDelta=transaction_stock_delta($data);
     if($newDelta!==0) {
        $medicineId=(int)$data['medicine_id'];
        if($medicines[$medicineId]['expiration_date']<$data['transaction_date']) throw new RuntimeException('Cannot transact expired medicine.');
        $stocks[$medicineId]+=$newDelta;
     }
     foreach($stocks as $medicineId=>$quantity) {
        if($quantity<0) throw new RuntimeException('Insufficient stock for this transaction.');
        db()->prepare('UPDATE medicines SET stock_quantity=? WHERE medicine_id=?')->execute([$quantity,$medicineId]);
     }
  }
   if($creatingHousehold) {
    db()->beginTransaction();
    $q=db()->prepare('INSERT INTO households(household_head,address,sitio_zone,contact_number) VALUES(?,?,?,?)');
    $q->execute(array_values($newHousehold));
    $data['household_id']=(int)db()->lastInsertId();
   }
   $fields=array_keys($data);
  if($id) {
   $sql='UPDATE `'.$table.'` SET '.implode(',',array_map(fn($f)=>"`$f`=?",$fields))." WHERE `$pk`=?";
   db()->prepare($sql)->execute([...array_values($data),$id]);
  } else {
   $sql='INSERT INTO `'.$table.'` ('.implode(',',array_map(fn($f)=>"`$f`",$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';
   db()->prepare($sql)->execute(array_values($data));$id=(int)db()->lastInsertId();
  }
   if($table==='transactions' || $creatingHousehold) db()->commit();
  log_action(($editing?'Updated ':($_POST['id']?'Updated ':'Created '))."$table #$id");
  flash('Record saved successfully.'); redirect('index.php?module='.$table);
 } catch(Throwable $t) {
  if(db()->inTransaction()) db()->rollBack();
  $error=$t instanceof RuntimeException?$t->getMessage():referential_error($t);
 }
}
if($meta && $action==='edit') {
 $id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT)?:0;
 if(!$id) redirect('index.php?module='.$module);
 if($module==='medicine_dispensed' || $module==='activity_logs') redirect('index.php?module='.$module);
 $editing=one("SELECT * FROM `$module` WHERE `{$meta['pk']}`=?",[$id]);
 if(!$editing) redirect('index.php?module='.$module);
}
if($module==='reports' && isset($_GET['export'])) {
 $kind=$_GET['export'];$month=$_GET['month']??date('Y-m');
 if(!preg_match('/^\d{4}-\d{2}$/',$month)) $month=date('Y-m');
 $exports=[
  'visits'=>['SELECT v.visit_id,CONCAT(p.first_name," ",p.last_name) AS patient,v.visit_date,v.diagnosis,v.notes,u.full_name AS recorded_by FROM visits v JOIN patients p ON p.patient_id=v.patient_id JOIN users u ON u.user_id=v.user_id WHERE DATE_FORMAT(v.visit_date,"%Y-%m")=? ORDER BY v.visit_date',[$month]],
  'immunizations'=>['SELECT i.immunization_id,CONCAT(p.first_name," ",p.last_name) AS patient,i.vaccine_name,i.dose_number,i.date_administered,i.next_due_date,u.full_name AS administered_by FROM immunizations i JOIN patients p ON i.patient_id=p.patient_id JOIN users u ON i.administered_by=u.user_id WHERE DATE_FORMAT(i.date_administered,"%Y-%m")=? ORDER BY i.date_administered',[$month]],
  'medicines'=>['SELECT medicine_id,medicine_name,unit,stock_quantity,expiration_date FROM medicines',[]],
  'appointments'=>['SELECT a.appointment_id,CONCAT(p.first_name," ",p.last_name) AS patient,a.appointment_date,a.purpose,a.status FROM appointments a JOIN patients p ON p.patient_id=a.patient_id WHERE DATE_FORMAT(a.appointment_date,"%Y-%m")=?',[$month]]
 ];
 if(!isset($exports[$kind])) {http_response_code(400);exit('Invalid export.');}
 [$sql,$params]=$exports[$kind]; $records=rows($sql,$params);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="bhirms-'.$kind.'-'.$month.'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 if($records) { fputcsv($out,array_keys($records[0]));foreach($records as $r) fputcsv($out,array_values($r)); }
 else fputcsv($out,['No records for selected period']);
 fclose($out);exit;
}
$title=$meta['title']??(['dashboard'=>'Dashboard','reports'=>'Reports & Exports','profile'=>'My Profile'][$module]??'Dashboard');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> • BHIRMS</title><link rel="stylesheet" href="style.css"></head><body><div class="shell"><aside class="sidebar"><div class="brand">✚ BHIRMS</div><nav>
<?php foreach(['dashboard'=>'Dashboard','households'=>'Households','patients'=>'Patients','visits'=>'Visits','sessions'=>'Sessions','transactions'=>'Transactions','immunizations'=>'Immunizations','medicines'=>'Medicines','medicine_dispensed'=>'Dispensing','appointments'=>'Appointments','reports'=>'Reports','profile'=>'My Profile','users'=>'Users','activity_logs'=>'Activity Logs'] as $key=>$label): if(in_array($key,['users','activity_logs'],true) && user()['role']!=='admin')continue; ?>
<a class="<?=$module===$key?'active':''?>" href="index.php?module=<?=e($key)?>"><?=e($label)?></a>
<?php endforeach;?></nav><div class="small">Health records • Local deployment</div></aside><main class="main"><header class="top"><div><h1><?=e($title)?></h1><p><?=e(user()['full_name'])?> · <?=e(strtoupper(user()['role']))?></p></div><a class="btn" href="logout.php">Sign out</a></header>
<?php if(isset($error)):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if(isset($_SESSION['flash'])):[$msg,$kind]=$_SESSION['flash'];unset($_SESSION['flash']);?><div class="alert <?=e($kind)?>"><?=e($msg)?></div><?php endif;?>
<?php if($module==='dashboard'):
 $stats=[
 'Registered Patients'=>(int)db()->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
 'Visits Today'=>(int)db()->query('SELECT COUNT(*) FROM visits WHERE DATE(visit_date)=CURDATE()')->fetchColumn(),
 'Vaccines Due (7 days)'=>(int)db()->query('SELECT COUNT(*) FROM immunizations WHERE next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)')->fetchColumn(),
 'Low Stock Medicines'=>(int)db()->query('SELECT COUNT(*) FROM medicines WHERE stock_quantity<=10')->fetchColumn()
 ];
 ?><div class="stats"><?php foreach($stats as $label=>$num):?><div class="stat"><div class="muted"><?=e($label)?></div><div class="number"><?=$num?></div></div><?php endforeach;?></div>
 <div class="grid2"><section class="card"><h2>Upcoming / Overdue Vaccinations</h2><div class="table-wrap"><table><tr><th>Patient</th><th>Vaccine</th><th>Due</th></tr><?php $data=rows('SELECT CONCAT(p.first_name," ",p.last_name) patient,i.vaccine_name,i.next_due_date FROM immunizations i JOIN patients p ON p.patient_id=i.patient_id WHERE i.next_due_date IS NOT NULL AND i.next_due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY i.next_due_date LIMIT 10');foreach($data as $r):?><tr><td><?=e($r['patient'])?></td><td><?=e($r['vaccine_name'])?></td><td><?=value_html('next_due_date',$r['next_due_date'])?></td></tr><?php endforeach;if(!$data):?><tr><td colspan="3" class="empty">No due immunization records.</td></tr><?php endif;?></table></div></section>
 <section class="card"><h2>Stock & Expiry Alerts</h2><div class="table-wrap"><table><tr><th>Medicine</th><th>Stock</th><th>Expires</th></tr><?php $data=rows('SELECT medicine_name,stock_quantity,expiration_date FROM medicines WHERE stock_quantity<=10 OR expiration_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY expiration_date LIMIT 10');foreach($data as $r):?><tr><td><?=e($r['medicine_name'])?></td><td><?=value_html('stock_quantity',$r['stock_quantity'])?></td><td><?=value_html('expiration_date',$r['expiration_date'])?></td></tr><?php endforeach;if(!$data):?><tr><td colspan="3" class="empty">No stock or expiry alerts.</td></tr><?php endif;?></table></div></section></div><div class="card"><h2>Quick Actions</h2><div class="row"><a class="btn primary" href="index.php?module=patients&action=new">+ Register patient</a><a class="btn" href="index.php?module=visits&action=new">+ Record visit</a><a class="btn" href="index.php?module=immunizations&action=new">+ Record vaccine</a><a class="btn" href="index.php?module=appointments&action=new">+ Schedule appointment</a></div></div>
<?php elseif($module==='profile'):
 $p=one('SELECT full_name,username,contact_number FROM users WHERE user_id=?',[user()['user_id']]);?>
<section class="card"><h2>Update personal details</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="save_profile"><div class="form-grid"><label>Username<input value="<?=e($p['username'])?>" disabled></label><label>Full name<input name="full_name" value="<?=e($p['full_name'])?>" required></label><label>Contact number<input name="contact_number" value="<?=e($p['contact_number'])?>"></label><label>New password (optional)<input type="password" name="password" minlength="8" placeholder="Leave blank to keep current password"></label></div><div class="form-actions"><button class="btn primary">Save profile</button></div></form></section>
<?php elseif($module==='reports'):
 $month=$_GET['month']??date('Y-m');if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
 $summary=[
 'Visits'=>(int)(one('SELECT COUNT(*) n FROM visits WHERE DATE_FORMAT(visit_date,"%Y-%m")=?',[$month])['n']),
 'Vaccinations Given'=>(int)(one('SELECT COUNT(*) n FROM immunizations WHERE DATE_FORMAT(date_administered,"%Y-%m")=?',[$month])['n']),
 'Appointments'=>(int)(one('SELECT COUNT(*) n FROM appointments WHERE DATE_FORMAT(appointment_date,"%Y-%m")=?',[$month])['n']),
 'Medicines Dispensed'=>(int)(one('SELECT COALESCE(SUM(quantity),0) n FROM medicine_dispensed WHERE DATE_FORMAT(date_dispensed,"%Y-%m")=?',[$month])['n'])
 ];?>
 <div class="card"><form class="row" method="get"><input type="hidden" name="module" value="reports"><label>Report month<input type="month" name="month" value="<?=e($month)?>"></label><button class="btn primary" style="margin-top:18px">Apply</button><button class="btn" type="button" style="margin-top:18px" onclick="window.print()">Print report / Save PDF</button></form></div><div class="stats"><?php foreach($summary as $label=>$num):?><div class="stat"><div class="muted"><?=e($label)?></div><div class="number"><?=$num?></div></div><?php endforeach;?></div><section class="card"><h2>Export CSV Reports</h2><p>Download CSV files that open in Excel. Monthly filters apply to visits, vaccinations, and appointments; inventory is a current snapshot.</p><div class="row no-print"><?php foreach(['visits'=>'Visits','immunizations'=>'Immunizations','appointments'=>'Appointments','medicines'=>'Medicine inventory'] as $key=>$label):?><a class="btn" href="index.php?module=reports&export=<?=$key?>&month=<?=e($month)?>">↓ <?=e($label)?></a><?php endforeach;?></div></section>
<?php else: // Data-driven CRUD forms and listing ?>
<?php if($action==='new' || $action==='edit'): ?>
<section class="card"><div class="row between"><h2><?=$editing?'Edit':'Add'?> <?=e($title)?></h2><a class="btn" href="index.php?module=<?=e($module)?>">← Back</a></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="save"><input type="hidden" name="id" value="<?=e($editing[$meta['pk']]??0)?>"><div class="form-grid">
<?php if($module==='patients' && !$editing):?><div class="form-grid"><label>Household head *<input name="household_head" maxlength="150" required></label><label>Address *<input name="household_address" maxlength="255" required></label><label>Sitio / Zone *<input name="household_sitio_zone" maxlength="100" required></label><label>Household contact number<input type="tel" name="household_contact_number" maxlength="30"></label></div><?php elseif($module==='patients' && $editing): $household=one('SELECT * FROM households WHERE household_id=?',[$editing['household_id']]);?><div class="notice">Household: <?=e($household['household_head']??'Unknown')?> · <?=e($household['address']??'')?> · <?=e($household['sitio_zone']??'')?> <a href="index.php?module=households&action=edit&id=<?=e($editing['household_id'])?>">Edit household</a></div><?php endif;?>
<?php foreach($meta['fields'] as $field=>$spec): [$type,$label,$required]=$spec; $val=$editing[$field]??'';
 if($module==='patients' && $field==='household_id')continue;
 if($field==='password' && $editing)$required=false;
 if(!$editing && $type==='datetime-local')$val=date('Y-m-d\TH:i');
 if(!$editing && $type==='date' && in_array($field,['visit_date','date_administered','date_dispensed','transaction_date'],true))$val=date('Y-m-d');
 if(!$editing && in_array($field,['user_id','administered_by'],true))$val=user()['user_id'];
 if($type==='datetime-local' && $val)$val=str_replace(' ','T',substr((string)$val,0,16));
 ?><label class="<?=$type==='textarea'?'full':''?>"><?=e($label)?><?= $required?' *':''?>
<?php if(isset($fkLabels[$type])):?>
<select name="<?=e($field)?>" <?=$required?'required':''?>><option value="">Select <?=e(strtolower($label))?></option><?php foreach(options_for($type) as $option):?><option value="<?=e($option['id'])?>" <?=((string)$val===(string)$option['id'])?'selected':''?>><?=e($option['label'])?></option><?php endforeach;?></select>
<?php elseif(in_array($type,['sex','status','role','session_status','transaction_type','payment_status'],true)): $choices=['sex'=>['Male','Female'],'status'=>['Scheduled','Completed','Cancelled'],'role'=>['bhw','midwife','admin'],'session_status'=>['Completed','Follow-up','Referred'],'transaction_type'=>['service','medicine_dispense','medicine_restock'],'payment_status'=>['Not applicable','Paid','Unpaid']][$type];?>
<select name="<?=e($field)?>" <?=$required?'required':''?>><?php foreach($choices as $choice):?><option value="<?=e($choice)?>" <?=($val===$choice||(!$val && $choice===$choices[0]))?'selected':''?>><?=e(ucwords(str_replace('_',' ',$choice)))?></option><?php endforeach;?></select>
<?php elseif($type==='textarea'):?><textarea name="<?=e($field)?>"><?=e($val)?></textarea>
<?php else:?><input type="<?=e($type==='money'?'number':$type)?>" name="<?=e($field)?>" value="<?=e($val)?>" <?=$required?'required':''?> <?=$type==='number'?'min="0"':($type==='money'?'min="0" step="0.01"':'')?> <?=$type==='password'?'minlength="8"':''?>><?php endif;?></label>
<?php endforeach;?></div><div class="form-actions"><button class="btn primary">Save record</button> <a class="btn" href="index.php?module=<?=e($module)?>">Cancel</a></div></form></section>
<?php else:
 $cols=array_keys($meta['fields']);if($module==='users')$cols=array_values(array_filter($cols,fn($k)=>$k!=='password'));
 $joins=['patients'=>['households','household_id','household_head'],'visits'=>['patients','patient_id','first_name'],'immunizations'=>['patients','patient_id','first_name'],'medicine_dispensed'=>['medicines','medicine_id','medicine_name'],'appointments'=>['patients','patient_id','first_name']];
 $search=trim($_GET['q']??'');
 $sql="SELECT * FROM `$module`";$params=[];
 if($module==='users')$sql='SELECT user_id,username,full_name,role,contact_number FROM users';
 if($module==='activity_logs')$sql='SELECT l.log_id,l.user_id,l.action,l.timestamp,u.full_name AS actor FROM activity_logs l JOIN users u ON l.user_id=u.user_id';
 if($search!=='' && $module!=='activity_logs') {
  $allowed=array_filter($cols,fn($f)=>!in_array($f,['password','role','status'],true));
  $sql.=' WHERE ('.implode(' OR ',array_map(fn($f)=>"CAST(`$f` AS CHAR) LIKE ?",$allowed)).')';
  $params=array_fill(0,count($allowed),'%'.$search.'%');
 }
 $sql.=' ORDER BY '.($module==='activity_logs'?'l.log_id':"`{$meta['pk']}`").' DESC LIMIT 300';
 $records=rows($sql,$params);
 ?><section class="card"><div class="row between"><h2>Records <span class="muted">(<?=count($records)?> shown)</span></h2><?php if($module!=='activity_logs'):?><a class="btn primary" href="index.php?module=<?=e($module)?>&action=new">+ Add record</a><?php endif;?></div><form class="toolbar" method="get"><input type="hidden" name="module" value="<?=e($module)?>"><input name="q" placeholder="Search records..." value="<?=e($search)?>"><button class="btn">Search</button><?php if($search!==''):?><a class="btn" href="index.php?module=<?=e($module)?>">Clear</a><?php endif;?></form><div class="table-wrap"><table><thead><tr><th>ID</th><?php if($module==='activity_logs'):?><th>User</th><th>Action</th><th>Timestamp</th><?php else:foreach($cols as $field):?><th><?=e(ucwords(str_replace('_',' ',$field)))?></th><?php endforeach;?><th>Actions</th><?php endif;?></tr></thead><tbody>
<?php foreach($records as $r):?><tr><td><?=e($r[$meta['pk']])?></td><?php if($module==='activity_logs'):?><td><?=e($r['actor'])?></td><td><?=e($r['action'])?></td><td><?=e($r['timestamp'])?></td><?php else:foreach($cols as $field):?><td><?=value_html($field,$r[$field]??null)?></td><?php endforeach;?><td><div class="actions"><?php if($module!=='medicine_dispensed'):?><a class="btn sm" href="index.php?module=<?=e($module)?>&action=edit&id=<?=e($r[$meta['pk']])?>">Edit</a><?php endif;?><form method="post" onsubmit="return confirm('Delete this record? Related records may prevent deletion.');"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="mode" value="delete"><input type="hidden" name="id" value="<?=e($r[$meta['pk']])?>"><button class="btn danger sm" type="submit">Delete</button></form></div></td><?php endif;?></tr><?php endforeach;?><?php if(!$records):?><tr><td class="empty" colspan="<?=count($cols)+2?>">No records found.</td></tr><?php endif;?></tbody></table></div><p class="notice">Displays up to 300 most recent records. To avoid data loss, records connected to other tables cannot be deleted until dependent records are handled.</p></section>
<?php endif;endif; ?></main></div></body></html>
