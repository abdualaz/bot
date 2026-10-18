<?php
include_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

function h($v){
return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fnum($v,$d=2){
return number_format((float)$v,$d,'.',',');
}

function time_text($ts){
$ts = (int)$ts;
if($ts <= 0){
return '-';
}
return date('Y-m-d H:i:s',$ts);
}

function table_exists($db,$table){
$stmt = $db->prepare('SELECT 1 FROM sqlite_master WHERE type = "table" AND name = :name LIMIT 1');
if(!$stmt){
return false;
}
$stmt->bindValue(':name',(string)$table,SQLITE3_TEXT);
$res = $stmt->execute();
$row = $res ? $res->fetchArray(SQLITE3_NUM) : false;
if($res){
$res->finalize();
}
return !empty($row);
}

function load_document_balances($db){
$balances = [];
if(!table_exists($db,'documents')){
return $balances;
}
$res = $db->query('SELECT path,payload FROM documents');
while($res && ($row = $res->fetchArray(SQLITE3_ASSOC))){
$path = (string)($row['path'] ?? '');
if(!preg_match('#^EMILS/([^/]+)/(rubles|points)\\.txt$#',$path,$m)){
continue;
}
$email = strtolower(trim((string)($m[1] ?? '')));
$kind = (string)($m[2] ?? '');
if($email === ''){
continue;
}
if(!isset($balances[$email])){
$balances[$email] = ['email'=>$email,'points'=>null,'rubles'=>null];
}
$value = (string)($row['payload'] ?? '');
$num = is_numeric($value) ? (float)$value : 0.0;
if($kind === 'rubles'){
$balances[$email]['rubles'] = $num;
}else{
$balances[$email]['points'] = $num;
}
}
if($res){
$res->finalize();
}

foreach($balances as $email => $row){
$points = $row['points'];
$rubles = $row['rubles'];
$balances[$email] = [
'email' => $email,
'balance_rub' => $points !== null ? (float)$points : (float)($rubles ?? 0.0),
];
}

return $balances;
}

function merge_duplicate_users_by_email($db){
if(!table_exists($db,'users')){
return;
}
$dup = $db->query('SELECT LOWER(TRIM(account_email)) AS em, COUNT(*) AS c FROM users WHERE TRIM(COALESCE(account_email,"")) <> "" GROUP BY LOWER(TRIM(account_email)) HAVING COUNT(*) > 1');
while($dup && ($d = $dup->fetchArray(SQLITE3_ASSOC))){
$em = (string)($d['em'] ?? '');
if($em === ''){
continue;
}
$rows = [];
$stmt = $db->prepare('SELECT id, username, telegram_username, account_email, account_password, COALESCE(login_count,0) AS login_count, COALESCE(last_login_at,0) AS last_login_at, COALESCE(balance_rub,0) AS balance_rub FROM users WHERE LOWER(TRIM(account_email)) = :em ORDER BY login_count DESC, last_login_at DESC, id ASC');
if(!$stmt){
continue;
}
$stmt->bindValue(':em',$em,SQLITE3_TEXT);
$rs = $stmt->execute();
while($rs && ($r = $rs->fetchArray(SQLITE3_ASSOC))){
$rows[] = $r;
}
if($rs){
$rs->finalize();
}
if(count($rows) < 2){
continue;
}
$keep = $rows[0];
$keepId = (int)$keep['id'];
$bestUsername = trim((string)($keep['username'] ?? ''));
$bestTg = trim((string)($keep['telegram_username'] ?? ''));
$bestPass = trim((string)($keep['account_password'] ?? ''));
$bestEmail = trim((string)($keep['account_email'] ?? ''));
$bestLogin = (int)($keep['login_count'] ?? 0);
$bestLast = (int)($keep['last_login_at'] ?? 0);
$bestRub = (float)($keep['balance_rub'] ?? 0);

foreach(array_slice($rows,1) as $drop){
$dropId = (int)($drop['id'] ?? 0);
if($dropId <= 0 || $dropId === $keepId){
continue;
}

if($bestUsername === '' && trim((string)($drop['username'] ?? '')) !== '') $bestUsername = trim((string)$drop['username']);
if($bestTg === '' && trim((string)($drop['telegram_username'] ?? '')) !== '') $bestTg = trim((string)$drop['telegram_username']);
if($bestPass === '' && trim((string)($drop['account_password'] ?? '')) !== '') $bestPass = trim((string)$drop['account_password']);
if($bestEmail === '' && trim((string)($drop['account_email'] ?? '')) !== '') $bestEmail = trim((string)$drop['account_email']);
if((int)($drop['login_count'] ?? 0) > $bestLogin) $bestLogin = (int)$drop['login_count'];
if((int)($drop['last_login_at'] ?? 0) > $bestLast) $bestLast = (int)$drop['last_login_at'];
if((float)($drop['balance_rub'] ?? 0) > $bestRub) $bestRub = (float)$drop['balance_rub'];

if(table_exists($db,'user_movements')) $db->exec('UPDATE user_movements SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(table_exists($db,'payment_transactions')) $db->exec('UPDATE payment_transactions SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(table_exists($db,'active_orders')) $db->exec('UPDATE active_orders SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(table_exists($db,'balance_transfers')){
$db->exec('UPDATE balance_transfers SET from_user_id = ' . $keepId . ' WHERE from_user_id = ' . $dropId);
$db->exec('UPDATE balance_transfers SET to_user_id = ' . $keepId . ' WHERE to_user_id = ' . $dropId);
}
$db->exec('DELETE FROM users WHERE id = ' . $dropId);
}

$up = $db->prepare('UPDATE users SET username = :username, telegram_username = :tg, account_email = :email, account_password = :pass, login_count = :login_count, last_login_at = :last_login_at, balance_rub = :balance_rub, balance_usd = 0 WHERE id = :id');
if($up){
$up->bindValue(':username',$bestUsername,SQLITE3_TEXT);
$up->bindValue(':tg',$bestTg,SQLITE3_TEXT);
$up->bindValue(':email',$bestEmail !== '' ? $bestEmail : $em,SQLITE3_TEXT);
$up->bindValue(':pass',$bestPass,SQLITE3_TEXT);
$up->bindValue(':login_count',$bestLogin,SQLITE3_INTEGER);
$up->bindValue(':last_login_at',$bestLast,SQLITE3_INTEGER);
$up->bindValue(':balance_rub',$bestRub,SQLITE3_FLOAT);
$up->bindValue(':id',$keepId,SQLITE3_INTEGER);
$up->execute();
}
}
if($dup){
$dup->finalize();
}
}

function sync_users_from_documents($db){
$balances = load_document_balances($db);
if(empty($balances) || !table_exists($db,'users')){
return;
}
$now = time();
foreach($balances as $rec){
$email = (string)($rec['email'] ?? '');
if($email === ''){
continue;
}
$rub = (float)($rec['balance_rub'] ?? 0);
$stmt = $db->prepare('UPDATE users SET balance_rub = :rub, balance_usd = 0, updated_at = :updated_at WHERE LOWER(COALESCE(account_email,"")) = LOWER(:email)');
if($stmt){
$stmt->bindValue(':rub',$rub,SQLITE3_FLOAT);
$stmt->bindValue(':updated_at',$now,SQLITE3_INTEGER);
$stmt->bindValue(':email',$email,SQLITE3_TEXT);
$stmt->execute();
}
}
}

$uid = (int)($_GET['uid'] ?? 0);
$sig = trim((string)($_GET['sig'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$adminIds = array_values(array_filter([
(int)($iDadmin ?? 0),
(int)($iDadmin2 ?? 0),
], function($v){ return $v > 0; }));

$secretCandidates = [];
foreach([
getenv('TELEGRAM_WEBHOOK_SECRET'),
getenv('BOT_WEBHOOK_SECRET'),
$apiInternalSecret ?? '',
getenv('TELEGRAM_BOT_TOKEN'),
$botToken ?? '',
] as $candidateSecret){
$val = trim((string)$candidateSecret);
if($val !== ''){
$secretCandidates[] = $val;
}
}
$secretCandidates = array_values(array_unique($secretCandidates));

$authorized = false;
if($sig !== '' && !empty($adminIds) && !empty($secretCandidates)){
$candidateIds = [];
if($uid > 0){
$candidateIds[] = $uid;
}
$candidateIds = array_values(array_unique(array_merge($candidateIds,$adminIds)));
foreach($candidateIds as $candidate){
if(!in_array((int)$candidate,$adminIds,true)){
continue;
}
foreach($secretCandidates as $secret){
$expected = hash_hmac('sha256',(string)$candidate,$secret);
if(hash_equals($expected,$sig)){
$authorized = true;
$uid = (int)$candidate;
break;
}
}
if($authorized){
break;
}
}
}

if(!$authorized){
http_response_code(403);
echo '<!doctype html><html lang="ar"><head><meta charset="utf-8"><title>403</title></head><body style="font-family:Tahoma,sans-serif;background:#0f172a;color:#e2e8f0;padding:24px">ليس لديك صلاحية للوصول لهذه الصفحة.</body></html>';
exit;
}

$db = new SQLite3(__DIR__ . '/bot_settings.db');
$db->busyTimeout(4000);

if(function_exists('bot_backfill_users_from_documents')){
bot_backfill_users_from_documents();
}
merge_duplicate_users_by_email($db);
sync_users_from_documents($db);
merge_duplicate_users_by_email($db);

$overview = [
'users_count' => 0,
'users_rub' => 0,
'tx_count' => 0,
'tx_rub' => 0,
'transfer_count' => 0,
'transfer_rub' => 0,
'transfer_pending' => 0,
'transfer_claimed' => 0,
'orders_pending' => 0,
'orders_paid' => 0,
'orders_cancelled' => 0,
'today_tx_count' => 0,
'today_tx_rub' => 0,
];

$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(balance_rub),0) AS rub FROM users');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$overview['users_count'] = (int)($row['c'] ?? 0);
$overview['users_rub'] = (float)($row['rub'] ?? 0);
}
$r->finalize();
}

$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$overview['tx_count'] = (int)($row['c'] ?? 0);
$overview['tx_rub'] = (float)($row['rub'] ?? 0);
}
$r->finalize();
}

if(table_exists($db,'balance_transfers')){
$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(amount_sent_rub),0) AS rub, COALESCE(SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END),0) AS pending_count, COALESCE(SUM(CASE WHEN status = "claimed" THEN 1 ELSE 0 END),0) AS claimed_count FROM balance_transfers');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$overview['transfer_count'] = (int)($row['c'] ?? 0);
$overview['transfer_rub'] = (float)($row['rub'] ?? 0);
$overview['transfer_pending'] = (int)($row['pending_count'] ?? 0);
$overview['transfer_claimed'] = (int)($row['claimed_count'] ?? 0);
}
$r->finalize();
}
}

$startToday = strtotime(date('Y-m-d 00:00:00'));
$stmtToday = $db->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions WHERE created_at >= :start');
if($stmtToday){
$stmtToday->bindValue(':start',(int)$startToday,SQLITE3_INTEGER);
$rsToday = $stmtToday->execute();
$row = $rsToday ? $rsToday->fetchArray(SQLITE3_ASSOC) : false;
if($rsToday){
$rsToday->finalize();
}
if(is_array($row)){
$overview['today_tx_count'] = (int)($row['c'] ?? 0);
$overview['today_tx_rub'] = (float)($row['rub'] ?? 0);
}
}

$r = $db->query('SELECT status, COUNT(*) AS c FROM active_orders GROUP BY status');
while($r && ($row = $r->fetchArray(SQLITE3_ASSOC))){
$status = strtolower((string)($row['status'] ?? ''));
$c = (int)($row['c'] ?? 0);
if($status === 'pending') $overview['orders_pending'] = $c;
if($status === 'paid') $overview['orders_paid'] = $c;
if($status === 'cancelled') $overview['orders_cancelled'] = $c;
}
if($r){
$r->finalize();
}

$sourceBreakdown = [];
$r = $db->query('SELECT source_type, COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions GROUP BY source_type ORDER BY c DESC');
while($r && ($row = $r->fetchArray(SQLITE3_ASSOC))){
$sourceBreakdown[] = [
'source' => (string)($row['source_type'] ?? 'unknown'),
'count' => (int)($row['c'] ?? 0),
'rub' => (float)($row['rub'] ?? 0),
];
}
if($r){
$r->finalize();
}

$coinBreakdown = [];
$r = $db->query('SELECT coin_name, network, COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions GROUP BY coin_name,network ORDER BY c DESC');
while($r && ($row = $r->fetchArray(SQLITE3_ASSOC))){
$coinBreakdown[] = [
'coin' => (string)($row['coin_name'] ?? '-'),
'network' => (string)($row['network'] ?? '-'),
'count' => (int)($row['c'] ?? 0),
'rub' => (float)($row['rub'] ?? 0),
];
}
if($r){
$r->finalize();
}

$dailySeries = [];
$r = $db->query("SELECT strftime('%Y-%m-%d', created_at, 'unixepoch', 'localtime') AS day, COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions GROUP BY day ORDER BY day DESC LIMIT 14");
while($r && ($row = $r->fetchArray(SQLITE3_ASSOC))){
$dailySeries[] = [
'day' => (string)($row['day'] ?? '-'),
'count' => (int)($row['c'] ?? 0),
'rub' => (float)($row['rub'] ?? 0),
];
}
if($r){
$r->finalize();
}

$users = [];
$like = '%' . $db->escapeString($q) . '%';
$userSql = "
SELECT
u.id,
u.username,
u.first_name,
u.last_name,
COALESCE(u.balance_rub,0) AS balance_rub,
u.updated_at,
COALESCE(tx.tx_count,0) AS tx_count,
COALESCE(tx.tx_rub,0) AS tx_rub,
COALESCE(tx.last_tx_at,0) AS last_tx_at,
COALESCE(ord.paid_count,0) AS paid_count,
COALESCE(ord.pending_count,0) AS pending_count,
COALESCE(ord.cancelled_count,0) AS cancelled_count
FROM users u
LEFT JOIN (
SELECT
user_id,
COUNT(*) AS tx_count,
COALESCE(SUM(amount_rub),0) AS tx_rub,
MAX(created_at) AS last_tx_at
FROM payment_transactions
GROUP BY user_id
) tx ON tx.user_id = u.id
LEFT JOIN (
SELECT
user_id,
SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count,
SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
FROM active_orders
GROUP BY user_id
) ord ON ord.user_id = u.id
";

$filters = [];
if($q !== ''){
$filters[] = "(CAST(u.id AS TEXT) LIKE '$like' OR LOWER(COALESCE(u.username,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.first_name,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.last_name,'')) LIKE LOWER('$like'))";
}
if(!empty($filters)){
$userSql .= ' WHERE ' . implode(' AND ',$filters);
}
$userSql .= ' ORDER BY u.updated_at DESC LIMIT 250';

$ru = $db->query($userSql);
while($ru && ($row = $ru->fetchArray(SQLITE3_ASSOC))){
$users[] = $row;
}
if($ru){
$ru->finalize();
}

$recentTx = [];
$txSql = "
SELECT
pt.id,pt.order_id,pt.user_id,pt.tx_id,pt.source_type,
pt.amount_rub,pt.coin_name,pt.network,pt.created_at,
u.username,u.first_name,u.last_name
FROM payment_transactions pt
LEFT JOIN users u ON u.id = pt.user_id
";
if($q !== ''){
$txSql .= " WHERE CAST(pt.user_id AS TEXT) LIKE '$like' OR LOWER(COALESCE(pt.source_type,'')) LIKE LOWER('$like') OR LOWER(COALESCE(pt.tx_id,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.username,'')) LIKE LOWER('$like')";
}
$txSql .= ' ORDER BY pt.created_at DESC LIMIT 150';
$rt = $db->query($txSql);
while($rt && ($row = $rt->fetchArray(SQLITE3_ASSOC))){
$recentTx[] = $row;
}
if($rt){
$rt->finalize();
}

$recentTransfers = [];
if(table_exists($db,'balance_transfers')){
$transferSql = 'SELECT id, transfer_code, reference_id, from_user_id, from_email, from_username, to_user_id, to_email, to_username, amount_sent_rub, amount_received_rub, fee_rub, status, created_at, claimed_at FROM balance_transfers';
if($q !== ''){
$transferSql .= " WHERE CAST(from_user_id AS TEXT) LIKE '$like' OR CAST(to_user_id AS TEXT) LIKE '$like' OR LOWER(COALESCE(from_email,'')) LIKE LOWER('$like') OR LOWER(COALESCE(to_email,'')) LIKE LOWER('$like') OR LOWER(COALESCE(from_username,'')) LIKE LOWER('$like') OR LOWER(COALESCE(to_username,'')) LIKE LOWER('$like') OR LOWER(COALESCE(transfer_code,'')) LIKE LOWER('$like')";
}
$transferSql .= ' ORDER BY created_at DESC LIMIT 150';
$rr = $db->query($transferSql);
while($rr && ($row = $rr->fetchArray(SQLITE3_ASSOC))){
$recentTransfers[] = $row;
}
if($rr){
$rr->finalize();
}
}

$db->close();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة تحليلات البوت (DB فقط)</title>
<style>
:root{
--bg:#0f172a;
--card:#111f3b;
--card2:#13264a;
--line:#2a3f68;
--text:#e2e8f0;
--muted:#94a3b8;
}
*{box-sizing:border-box}
body{margin:0;background:radial-gradient(circle at 80% -10%,#1d4ed8 0%,var(--bg) 42%),var(--bg);color:var(--text);font-family:"Tajawal","Noto Kufi Arabic",Tahoma,sans-serif}
.container{max-width:1280px;margin:24px auto;padding:0 14px}
.header{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.title{margin:0;font-size:26px;font-weight:800}
.sub{margin:6px 0 0;color:var(--muted)}
.search{display:flex;gap:8px;align-items:center;background:rgba(17,31,59,.8);padding:8px;border:1px solid var(--line);border-radius:12px}
input[type=text]{background:#0b1630;border:1px solid var(--line);color:var(--text);padding:10px 12px;border-radius:9px;min-width:260px;outline:none}
button{background:linear-gradient(135deg,#06b6d4,#14b8a6);border:0;color:#001321;font-weight:800;padding:10px 14px;border-radius:9px;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;margin:16px 0}
.card{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--line);border-radius:14px;padding:12px}
.card .k{color:var(--muted);font-size:13px}
.card .v{margin-top:6px;font-size:22px;font-weight:800}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:14px}
.panel{background:rgba(17,31,59,.82);border:1px solid var(--line);border-radius:14px;padding:12px}
.panel h2{margin:0 0 10px;font-size:18px}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid var(--line);padding:8px;text-align:right;font-size:13px;vertical-align:top}
th{color:#cbd5e1;background:rgba(15,23,42,.7)}
.small{font-size:12px;color:var(--muted)}
.note{margin-top:12px;padding:10px;border:1px solid var(--line);border-radius:10px;background:#0b1731;color:#cbd5e1}
@media (max-width:1100px){.cards{grid-template-columns:repeat(3,minmax(140px,1fr))}.grid{grid-template-columns:1fr}}
@media (max-width:700px){.cards{grid-template-columns:repeat(2,minmax(130px,1fr))}input[type=text]{min-width:180px}}
</style>
</head>
<body>
<div class="container">
<div class="header">
<div>
<h1 class="title">لوحة تحليلات البوت (DB فقط)</h1>
<p class="sub">كل القيم مأخوذة من جداول SQLite مباشرة (RUB فقط).</p>
</div>
<form class="search" method="get">
<input type="hidden" name="uid" value="<?=h($uid)?>">
<input type="hidden" name="sig" value="<?=h($sig)?>">
<input type="text" name="q" value="<?=h($q)?>" placeholder="بحث: ايدي / يوزر / tx_id / source">
<button type="submit">بحث</button>
</form>
</div>

<div class="cards">
<div class="card"><div class="k">عدد المستخدمين</div><div class="v"><?=h($overview['users_count'])?></div></div>
<div class="card"><div class="k">إجمالي الرصيد RUB</div><div class="v"><?=h(fnum($overview['users_rub']))?></div></div>
<div class="card"><div class="k">إجمالي عمليات الشحن</div><div class="v"><?=h($overview['tx_count'])?></div></div>
<div class="card"><div class="k">إجمالي قيمة الشحن RUB</div><div class="v"><?=h(fnum($overview['tx_rub']))?></div></div>
<div class="card"><div class="k">عمليات التحويل</div><div class="v"><?=h($overview['transfer_count'])?></div></div>
<div class="card"><div class="k">قيمة التحويل RUB</div><div class="v"><?=h(fnum($overview['transfer_rub']))?></div></div>
<div class="card"><div class="k">شحن اليوم (عدد)</div><div class="v"><?=h($overview['today_tx_count'])?></div></div>
<div class="card"><div class="k">شحن اليوم (RUB)</div><div class="v"><?=h(fnum($overview['today_tx_rub']))?></div></div>
<div class="card"><div class="k">طلبات pending</div><div class="v"><?=h($overview['orders_pending'])?></div></div>
<div class="card"><div class="k">طلبات paid</div><div class="v"><?=h($overview['orders_paid'])?></div></div>
<div class="card"><div class="k">طلبات cancelled</div><div class="v"><?=h($overview['orders_cancelled'])?></div></div>
<div class="card"><div class="k">تحويلات pending</div><div class="v"><?=h($overview['transfer_pending'])?></div></div>
<div class="card"><div class="k">تحويلات claimed</div><div class="v"><?=h($overview['transfer_claimed'])?></div></div>
</div>

<div class="grid">
<div class="panel">
<h2>تفاصيل المستخدمين (من قاعدة البيانات)</h2>
<table>
<thead>
<tr>
<th>المستخدم</th>
<th>الرصيد</th>
<th>الشحن المستلم</th>
<th>حالة الطلبات</th>
<th>آخر تحديث</th>
</tr>
</thead>
<tbody>
<?php if(empty($users)): ?>
<tr><td colspan="5" class="small">لا توجد بيانات مطابقة.</td></tr>
<?php else: foreach($users as $u): ?>
<tr>
<td>
<div><b>#<?=h($u['id'] ?? 0)?></b> <?=h(trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? '')) ?: '-')?></div>
<div class="small">@<?=h(($u['username'] ?? '') !== '' ? $u['username'] : '-')?></div>
</td>
<td>
<div>RUB: <b><?=h(fnum((float)($u['balance_rub'] ?? 0)))?></b></div>
</td>
<td>
<div>مرات: <b><?=h((int)($u['tx_count'] ?? 0))?></b></div>
<div>RUB: <b><?=h(fnum((float)($u['tx_rub'] ?? 0)))?></b></div>
</td>
<td>
<div>paid: <b><?=h((int)($u['paid_count'] ?? 0))?></b></div>
<div>pending: <b><?=h((int)($u['pending_count'] ?? 0))?></b></div>
<div>cancelled: <b><?=h((int)($u['cancelled_count'] ?? 0))?></b></div>
</td>
<td class="small">
<div>user: <?=h(time_text((int)($u['updated_at'] ?? 0)))?></div>
<div>tx: <?=h(time_text((int)($u['last_tx_at'] ?? 0)))?></div>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="panel">
<h2>حسب مصدر الدفع</h2>
<table>
<thead><tr><th>source_type</th><th>العدد</th><th>RUB</th></tr></thead>
<tbody>
<?php if(empty($sourceBreakdown)): ?>
<tr><td colspan="3" class="small">لا توجد معاملات حتى الآن.</td></tr>
<?php else: foreach($sourceBreakdown as $s): ?>
<tr>
<td><?=h($s['source'])?></td>
<td><?=h($s['count'])?></td>
<td><?=h(fnum($s['rub']))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<h2 style="margin-top:14px">حسب العملة/الشبكة</h2>
<table>
<thead><tr><th>coin/network</th><th>العدد</th><th>RUB</th></tr></thead>
<tbody>
<?php if(empty($coinBreakdown)): ?>
<tr><td colspan="3" class="small">لا توجد معاملات.</td></tr>
<?php else: foreach($coinBreakdown as $c): ?>
<tr>
<td><?=h($c['coin'] . ' / ' . $c['network'])?></td>
<td><?=h($c['count'])?></td>
<td><?=h(fnum($c['rub']))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>

<div class="panel" style="margin-top:14px">
<h2>حركة آخر 14 يوم</h2>
<table>
<thead><tr><th>اليوم</th><th>عدد العمليات</th><th>RUB</th></tr></thead>
<tbody>
<?php if(empty($dailySeries)): ?>
<tr><td colspan="3" class="small">لا توجد بيانات يومية.</td></tr>
<?php else: foreach($dailySeries as $d): ?>
<tr>
<td><?=h($d['day'])?></td>
<td><?=h($d['count'])?></td>
<td><?=h(fnum($d['rub']))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="panel" style="margin-top:14px">
<h2>آخر معاملات الدفع (payment_transactions)</h2>
<table>
<thead>
<tr>
<th>ID</th>
<th>user</th>
<th>source</th>
<th>coin/network</th>
<th>RUB</th>
<th>tx_id</th>
<th>الوقت</th>
</tr>
</thead>
<tbody>
<?php if(empty($recentTx)): ?>
<tr><td colspan="7" class="small">لا توجد معاملات.</td></tr>
<?php else: foreach($recentTx as $t): ?>
<tr>
<td><?=h($t['id'] ?? '')?></td>
<td>
<div>#<?=h($t['user_id'] ?? '')?></div>
<div class="small">@<?=h(($t['username'] ?? '') !== '' ? $t['username'] : '-')?></div>
</td>
<td><?=h($t['source_type'] ?? '')?></td>
<td><?=h(($t['coin_name'] ?? '-') . ' / ' . ($t['network'] ?? '-'))?></td>
<td><?=h(fnum((float)($t['amount_rub'] ?? 0)))?></td>
<td class="small"><?=h($t['tx_id'] ?? '')?></td>
<td class="small"><?=h(time_text((int)($t['created_at'] ?? 0)))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="panel" style="margin-top:14px">
<h2>آخر تحويلات الرصيد (balance_transfers)</h2>
<table>
<thead>
<tr>
<th>ID</th>
<th>from</th>
<th>to</th>
<th>amount/fee</th>
<th>code</th>
<th>status</th>
<th>الوقت</th>
</tr>
</thead>
<tbody>
<?php if(empty($recentTransfers)): ?>
<tr><td colspan="7" class="small">لا توجد تحويلات.</td></tr>
<?php else: foreach($recentTransfers as $tr): ?>
<tr>
<td><?=h($tr['id'] ?? '')?></td>
<td>
<div>#<?=h($tr['from_user_id'] ?? 0)?></div>
<div class="small"><?=h(($tr['from_email'] ?? '') !== '' ? $tr['from_email'] : '-')?></div>
</td>
<td>
<div>#<?=h($tr['to_user_id'] ?? 0)?></div>
<div class="small"><?=h(($tr['to_email'] ?? '') !== '' ? $tr['to_email'] : '-')?></div>
</td>
<td>
<div>sent: <b><?=h(fnum((float)($tr['amount_sent_rub'] ?? 0)))?></b></div>
<div>recv: <b><?=h(fnum((float)($tr['amount_received_rub'] ?? 0)))?></b></div>
<div class="small">fee: <?=h(fnum((float)($tr['fee_rub'] ?? 0)))?></div>
</td>
<td class="small">
<div><?=h($tr['transfer_code'] ?? '')?></div>
<div><?=h($tr['reference_id'] ?? '')?></div>
</td>
<td>
<div><?=h($tr['status'] ?? '-')?></div>
<div class="small">claimed: <?=h(time_text((int)($tr['claimed_at'] ?? 0)))?></div>
</td>
<td class="small"><?=h(time_text((int)($tr['created_at'] ?? 0)))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
<div class="note">
هذه الصفحة تعتمد أساسًا على جداول SQL (users / payment_transactions / active_orders / balance_transfers)، ومع ذلك تتم مزامنة الرصيد الحالي من documents لمسارات EMILS/&lt;email&gt;/(rubles|points).txt عند فتح الصفحة إذا كانت بيانات users متأخرة.
</div>
</div>
</div>
</body>
</html>
