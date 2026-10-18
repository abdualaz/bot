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

function db_table_has_column($db,$table,$column){
$stmt = $db->prepare('PRAGMA table_info(' . $table . ')');
$result = $stmt ? $stmt->execute() : false;
if(!$result){
return false;
}
while($row = $result->fetchArray(SQLITE3_ASSOC)){
if(((string)($row['name'] ?? '')) === (string)$column){
$result->finalize();
return true;
}
}
$result->finalize();
return false;
}

function db_table_exists($db,$table){
$stmt = $db->prepare('SELECT 1 FROM sqlite_master WHERE type = "table" AND name = :name LIMIT 1');
if(!$stmt){
return false;
}
$stmt->bindValue(':name',(string)$table,SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;
if($result){
$result->finalize();
}
return !empty($row);
}

function db_scalar_int($db,$sql){
$result = $db->query($sql);
if(!$result){
return 0;
}
$row = $result->fetchArray(SQLITE3_ASSOC);
$result->finalize();
return (int)($row['c'] ?? 0);
}

function load_document_balances($db){
$balances = [];
if(!db_table_exists($db,'documents')){
return $balances;
}
$result = $db->query('SELECT path,payload FROM documents');
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
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
$raw = (string)($row['payload'] ?? '');
$num = is_numeric($raw) ? (float)$raw : 0.0;
if($kind === 'rubles'){
$balances[$email]['rubles'] = $num;
}else{
$balances[$email]['points'] = $num;
}
}
if($result){
$result->finalize();
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
if(!db_table_exists($db,'users')){
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

if(db_table_exists($db,'user_movements')) $db->exec('UPDATE user_movements SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(db_table_exists($db,'payment_transactions')) $db->exec('UPDATE payment_transactions SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(db_table_exists($db,'active_orders')) $db->exec('UPDATE active_orders SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
if(db_table_exists($db,'balance_transfers')){
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

function sync_users_from_documents($db,$balances){
if(empty($balances) || !db_table_exists($db,'users')){
return;
}
$now = time();
foreach($balances as $rec){
$email = (string)($rec['email'] ?? '');
if($email === ''){
continue;
}
$rub = (float)($rec['balance_rub'] ?? 0);
$up = $db->prepare('UPDATE users SET balance_rub = :rub, balance_usd = 0, updated_at = :updated_at WHERE LOWER(COALESCE(account_email,"")) = LOWER(:email)');
if($up){
$up->bindValue(':rub',$rub,SQLITE3_FLOAT);
$up->bindValue(':updated_at',$now,SQLITE3_INTEGER);
$up->bindValue(':email',$email,SQLITE3_TEXT);
$up->execute();
}
}
}

function seed_user_movements_from_document_balances($db,$balances){
if(empty($balances) || !db_table_exists($db,'users') || !db_table_exists($db,'user_movements')){
return;
}
$now = time();
foreach($balances as $rec){
$email = (string)($rec['email'] ?? '');
if($email === ''){
continue;
}
$rub = (float)($rec['balance_rub'] ?? 0);
if($rub == 0.0){
continue;
}
$stmtUser = $db->prepare('SELECT id, COALESCE(telegram_username, username, "") AS tg FROM users WHERE LOWER(COALESCE(account_email,"")) = LOWER(:email) LIMIT 1');
if(!$stmtUser){
continue;
}
$stmtUser->bindValue(':email',$email,SQLITE3_TEXT);
$rsUser = $stmtUser->execute();
$u = $rsUser ? $rsUser->fetchArray(SQLITE3_ASSOC) : false;
if($rsUser){
$rsUser->finalize();
}
$userId = (int)($u['id'] ?? 0);
if($userId <= 0){
continue;
}
$stmtCount = $db->prepare('SELECT COUNT(*) AS c FROM user_movements WHERE user_id = :uid');
if(!$stmtCount){
continue;
}
$stmtCount->bindValue(':uid',$userId,SQLITE3_INTEGER);
$rsCount = $stmtCount->execute();
$rowCount = $rsCount ? $rsCount->fetchArray(SQLITE3_ASSOC) : false;
if($rsCount){
$rsCount->finalize();
}
if((int)($rowCount['c'] ?? 0) > 0){
continue;
}
$meta = ['source' => 'documents','kind' => 'snapshot'];
$ins = $db->prepare('INSERT INTO user_movements (user_id,email,telegram_username,event_type,event_label,amount_rub,amount_usd,balance_rub,balance_usd,source_path,reference_id,meta_json,created_at) VALUES (:user_id,:email,:telegram_username,:event_type,:event_label,:amount_rub,:amount_usd,:balance_rub,:balance_usd,:source_path,:reference_id,:meta_json,:created_at)');
if(!$ins){
continue;
}
$ins->bindValue(':user_id',$userId,SQLITE3_INTEGER);
$ins->bindValue(':email',$email,SQLITE3_TEXT);
$ins->bindValue(':telegram_username',(string)($u['tg'] ?? ''),SQLITE3_TEXT);
$ins->bindValue(':event_type','balance_snapshot',SQLITE3_TEXT);
$ins->bindValue(':event_label','initial_balance_snapshot',SQLITE3_TEXT);
$ins->bindValue(':amount_rub',$rub,SQLITE3_FLOAT);
$ins->bindValue(':amount_usd',0,SQLITE3_FLOAT);
$ins->bindValue(':balance_rub',$rub,SQLITE3_FLOAT);
$ins->bindValue(':balance_usd',0,SQLITE3_FLOAT);
$ins->bindValue(':source_path','documents',SQLITE3_TEXT);
$ins->bindValue(':reference_id','snapshot:' . $email,SQLITE3_TEXT);
$ins->bindValue(':meta_json',json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$ins->bindValue(':created_at',$now,SQLITE3_INTEGER);
$ins->execute();
}
}

function backfill_user_movements_from_payments($db){
if(!db_table_exists($db,'payment_transactions') || !db_table_exists($db,'user_movements')){
return;
}

$existing = db_scalar_int($db,'SELECT COUNT(*) AS c FROM user_movements');
if($existing > 0){
return;
}

$db->exec('BEGIN IMMEDIATE');
$stmtSelect = $db->query('SELECT pt.order_id, pt.user_id, pt.tx_id, pt.source_type, pt.amount_usd, pt.amount_rub, pt.coin_name, pt.network, pt.created_at, COALESCE(u.account_email, "") AS account_email, COALESCE(u.telegram_username, u.username, "") AS telegram_username FROM payment_transactions pt LEFT JOIN users u ON u.id = pt.user_id ORDER BY pt.created_at ASC');
if($stmtSelect){
while($row = $stmtSelect->fetchArray(SQLITE3_ASSOC)){
$stmtInsert = $db->prepare('INSERT INTO user_movements (user_id,email,telegram_username,event_type,event_label,amount_rub,amount_usd,balance_rub,balance_usd,source_path,reference_id,meta_json,created_at) VALUES (:user_id,:email,:telegram_username,:event_type,:event_label,:amount_rub,:amount_usd,:balance_rub,:balance_usd,:source_path,:reference_id,:meta_json,:created_at)');
if(!$stmtInsert){
continue;
}
$meta = [
'order_id' => (string)($row['order_id'] ?? ''),
'coin_name' => (string)($row['coin_name'] ?? ''),
'network' => (string)($row['network'] ?? ''),
'source_type' => (string)($row['source_type'] ?? ''),
];
$stmtInsert->bindValue(':user_id',(int)($row['user_id'] ?? 0),SQLITE3_INTEGER);
$stmtInsert->bindValue(':email',(string)($row['account_email'] ?? ''),SQLITE3_TEXT);
$stmtInsert->bindValue(':telegram_username',(string)($row['telegram_username'] ?? ''),SQLITE3_TEXT);
$stmtInsert->bindValue(':event_type','payment_credit',SQLITE3_TEXT);
$stmtInsert->bindValue(':event_label',(string)($row['source_type'] ?? 'payment_credit'),SQLITE3_TEXT);
$stmtInsert->bindValue(':amount_rub',(float)($row['amount_rub'] ?? 0),SQLITE3_FLOAT);
$stmtInsert->bindValue(':amount_usd',(float)($row['amount_usd'] ?? 0),SQLITE3_FLOAT);
$stmtInsert->bindValue(':balance_rub',(float)($row['amount_rub'] ?? 0),SQLITE3_FLOAT);
$stmtInsert->bindValue(':balance_usd',(float)($row['amount_usd'] ?? 0),SQLITE3_FLOAT);
$stmtInsert->bindValue(':source_path','payment_transactions',SQLITE3_TEXT);
$stmtInsert->bindValue(':reference_id',(string)($row['tx_id'] ?? ''),SQLITE3_TEXT);
$stmtInsert->bindValue(':meta_json',json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$stmtInsert->bindValue(':created_at',(int)($row['created_at'] ?? time()),SQLITE3_INTEGER);
$stmtInsert->execute();
}
$stmtSelect->finalize();
}
$db->exec('COMMIT');
}

$uid = (int)($_GET['uid'] ?? 0);
$sig = trim((string)($_GET['sig'] ?? ''));
$view = trim((string)($_GET['view'] ?? 'users'));
$selectedUserId = (int)($_GET['user_id'] ?? 0);
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

if(function_exists('bot_db_manager')){
$db = bot_db_manager()->connection();
}else{
$db = new SQLite3(__DIR__ . '/bot_settings.db');
$db->busyTimeout(4000);
}

// Defensive schema sync for old databases that predate users/user_movements analytics fields.
$db->exec('CREATE TABLE IF NOT EXISTS user_movements (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL DEFAULT 0,
	email TEXT NOT NULL DEFAULT "",
	telegram_username TEXT NOT NULL DEFAULT "",
	event_type TEXT NOT NULL,
	event_label TEXT NOT NULL,
	amount_rub REAL NOT NULL DEFAULT 0,
	amount_usd REAL NOT NULL DEFAULT 0,
	balance_rub REAL NOT NULL DEFAULT 0,
	balance_usd REAL NOT NULL DEFAULT 0,
	source_path TEXT NOT NULL DEFAULT "",
	reference_id TEXT NOT NULL DEFAULT "",
	meta_json TEXT NOT NULL DEFAULT "",
	created_at INTEGER NOT NULL
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_user_movements_user ON user_movements(user_id, created_at)');

if(!db_table_has_column($db,'users','account_email')){
$db->exec('ALTER TABLE users ADD COLUMN account_email TEXT NOT NULL DEFAULT ""');
}
if(!db_table_has_column($db,'users','account_password')){
$db->exec('ALTER TABLE users ADD COLUMN account_password TEXT NOT NULL DEFAULT ""');
}
if(!db_table_has_column($db,'users','telegram_username')){
$db->exec('ALTER TABLE users ADD COLUMN telegram_username TEXT NOT NULL DEFAULT ""');
}
if(!db_table_has_column($db,'users','login_count')){
$db->exec('ALTER TABLE users ADD COLUMN login_count INTEGER NOT NULL DEFAULT 0');
}
if(!db_table_has_column($db,'users','last_login_at')){
$db->exec('ALTER TABLE users ADD COLUMN last_login_at INTEGER NOT NULL DEFAULT 0');
}
if(!db_table_has_column($db,'users','balance_rub')){
$db->exec('ALTER TABLE users ADD COLUMN balance_rub REAL NOT NULL DEFAULT 0');
}
if(!db_table_has_column($db,'users','balance_usd')){
$db->exec('ALTER TABLE users ADD COLUMN balance_usd REAL NOT NULL DEFAULT 0');
}

backfill_user_movements_from_payments($db);
$docBalances = load_document_balances($db);
merge_duplicate_users_by_email($db);
sync_users_from_documents($db,$docBalances);
merge_duplicate_users_by_email($db);
seed_user_movements_from_document_balances($db,$docBalances);

$summary = [
'users_count' => 0,
'movements_count' => 0,
'movements_rub' => 0,
'payment_count' => 0,
'payment_rub' => 0,
'today_movements' => 0,
];

$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(CASE WHEN account_email <> "" THEN 1 ELSE 0 END),0) AS m FROM users');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$summary['users_count'] = (int)($row['c'] ?? 0);
}
$r->finalize();
}

$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM user_movements');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$summary['movements_count'] = (int)($row['c'] ?? 0);
$summary['movements_rub'] = (float)($row['rub'] ?? 0);
}
$r->finalize();
}

$r = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions');
if($r){
$row = $r->fetchArray(SQLITE3_ASSOC);
if(is_array($row)){
$summary['payment_count'] = (int)($row['c'] ?? 0);
$summary['payment_rub'] = (float)($row['rub'] ?? 0);
}
$r->finalize();
}

$todayStart = strtotime(date('Y-m-d 00:00:00'));
$stmtToday = $db->prepare('SELECT COUNT(*) AS c FROM user_movements WHERE created_at >= :start');
if($stmtToday){
$stmtToday->bindValue(':start',(int)$todayStart,SQLITE3_INTEGER);
$rsToday = $stmtToday->execute();
$row = $rsToday ? $rsToday->fetchArray(SQLITE3_ASSOC) : false;
if($rsToday){
$rsToday->finalize();
}
if(is_array($row)){
$summary['today_movements'] = (int)($row['c'] ?? 0);
}
}

$usersSql = "
SELECT
u.id,
u.username,
u.telegram_username,
u.account_email,
u.account_password,
u.first_name,
u.last_name,
COALESCE(u.balance_rub,0) AS balance_rub,
COALESCE(u.login_count,0) AS login_count,
COALESCE(u.last_login_at,0) AS last_login_at,
u.updated_at,
COALESCE(m.movements_count,0) AS movements_count,
COALESCE(m.movements_rub,0) AS movements_rub,
COALESCE(m.last_movement_at,0) AS last_movement_at
FROM users u
LEFT JOIN (
SELECT user_id, COUNT(*) AS movements_count, COALESCE(SUM(amount_rub),0) AS movements_rub, MAX(created_at) AS last_movement_at FROM user_movements GROUP BY user_id
) m ON m.user_id = u.id
";
$filters = [];
if($q !== ''){
$like = '%' . $db->escapeString($q) . '%';
$filters[] = "(CAST(u.id AS TEXT) LIKE '$like' OR LOWER(COALESCE(u.username,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.telegram_username,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.account_email,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.first_name,'')) LIKE LOWER('$like') OR LOWER(COALESCE(u.last_name,'')) LIKE LOWER('$like'))";
}
if(!empty($filters)){
$usersSql .= ' WHERE ' . implode(' AND ',$filters);
}
$usersSql .= ' ORDER BY COALESCE(m.last_movement_at,u.updated_at) DESC LIMIT 300';

$users = [];
$ru = $db->query($usersSql);
while($ru && ($row = $ru->fetchArray(SQLITE3_ASSOC))){
$users[] = $row;
}
if($ru){
$ru->finalize();
}

if($selectedUserId <= 0 && !empty($users)){
$selectedUserId = (int)($users[0]['id'] ?? 0);
}

$selectedUser = null;
$selectedMovements = [];
$selectedPayments = [];
if($selectedUserId > 0){
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id',$selectedUserId,SQLITE3_INTEGER);
$rs = $stmt->execute();
$selectedUser = $rs ? $rs->fetchArray(SQLITE3_ASSOC) : null;
if($rs){
$rs->finalize();
}

$stmt = $db->prepare('SELECT * FROM user_movements WHERE user_id = :id ORDER BY created_at DESC LIMIT 120');
$stmt->bindValue(':id',$selectedUserId,SQLITE3_INTEGER);
$rs = $stmt->execute();
while($rs && ($row = $rs->fetchArray(SQLITE3_ASSOC))){
$selectedMovements[] = $row;
}
if($rs){
$rs->finalize();
}

$stmt = $db->prepare('SELECT * FROM payment_transactions WHERE user_id = :id ORDER BY created_at DESC LIMIT 60');
$stmt->bindValue(':id',$selectedUserId,SQLITE3_INTEGER);
$rs = $stmt->execute();
while($rs && ($row = $rs->fetchArray(SQLITE3_ASSOC))){
$selectedPayments[] = $row;
}
if($rs){
$rs->finalize();
}
}

$db->close();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة سجلات المستخدمين</title>
<style>
:root{--bg:#08111f;--card:#101c32;--card2:#132744;--line:#294261;--text:#e5eefb;--muted:#93a4bb;--accent:#5eead4;--chip:#17315b}
*{box-sizing:border-box}
body{margin:0;background:radial-gradient(circle at 15% 0%,#123a63 0%,var(--bg) 44%),var(--bg);color:var(--text);font-family:"Tajawal","Noto Kufi Arabic",Tahoma,sans-serif}
.wrap{max-width:1400px;margin:24px auto;padding:0 14px}
.header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.title{margin:0;font-size:28px;font-weight:900}
.sub{margin:6px 0 0;color:var(--muted)}
.search{display:flex;gap:8px;align-items:center;background:rgba(16,28,50,.82);padding:8px;border:1px solid var(--line);border-radius:12px}
input[type=text]{background:#081323;border:1px solid var(--line);color:var(--text);padding:10px 12px;border-radius:10px;min-width:270px;outline:none}
button{background:linear-gradient(135deg,#5eead4,#38bdf8);border:0;color:#02111d;font-weight:900;padding:10px 14px;border-radius:10px;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;margin:16px 0}
.card{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--line);border-radius:16px;padding:12px}
.k{color:var(--muted);font-size:13px}
.v{margin-top:6px;font-size:22px;font-weight:900}
.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
.panel{background:rgba(16,28,50,.82);border:1px solid var(--line);border-radius:16px;padding:12px}
.panel h2{margin:0 0 10px;font-size:18px}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid var(--line);padding:9px;text-align:right;font-size:13px;vertical-align:top}
th{color:#cae0f7;background:rgba(9,18,34,.7)}
.small{color:var(--muted);font-size:12px}
.badge{display:inline-block;margin:2px 2px 0 0;padding:3px 8px;border-radius:999px;background:var(--chip);border:1px solid var(--line);font-size:12px}
.row-link{color:var(--text);text-decoration:none;display:block}
.row-link:hover{background:rgba(94,234,212,.06)}
.details{display:grid;grid-template-columns:1fr;gap:14px}
.movement{border:1px solid var(--line);border-radius:14px;background:#0b1730;padding:10px;margin-bottom:10px}
.mtitle{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
.mlabel{font-weight:900}
.mmeta{color:var(--muted);font-size:12px;margin-top:4px}
.mvals{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
.mchip{padding:6px 9px;border-radius:999px;background:#102244;border:1px solid var(--line);font-size:12px}
.note{margin-top:12px;padding:10px;border:1px solid var(--line);border-radius:12px;background:#0a1832;color:#cbd5e1}
@media (max-width:1100px){.cards{grid-template-columns:repeat(3,minmax(140px,1fr))}.grid{grid-template-columns:1fr}}
@media (max-width:700px){.cards{grid-template-columns:repeat(2,minmax(135px,1fr))}input[type=text]{min-width:170px}}
</style>
</head>
<body>
<div class="wrap">
<div class="header">
<div>
<h1 class="title">إدارة سجلات المستخدمين</h1>
<p class="sub">كل مستخدم، إيميله، يوزره، وحركته التفصيلية من قاعدة البيانات مباشرة.</p>
</div>
<form class="search" method="get">
<input type="hidden" name="uid" value="<?=h($uid)?>">
<input type="hidden" name="sig" value="<?=h($sig)?>">
<input type="hidden" name="view" value="<?=h($view)?>">
<input type="text" name="q" value="<?=h($q)?>" placeholder="بحث: ايدي / ايميل / username">
<button type="submit">بحث</button>
</form>
</div>

<div class="cards">
<div class="card"><div class="k">عدد المستخدمين</div><div class="v"><?=h($summary['users_count'])?></div></div>
<div class="card"><div class="k">عدد الحركات</div><div class="v"><?=h($summary['movements_count'])?></div></div>
<div class="card"><div class="k">قيمة الحركات RUB</div><div class="v"><?=h(fnum($summary['movements_rub']))?></div></div>
<div class="card"><div class="k">عمليات الدفع</div><div class="v"><?=h($summary['payment_count'])?></div></div>
<div class="card"><div class="k">قيمة الدفع RUB</div><div class="v"><?=h(fnum($summary['payment_rub']))?></div></div>
</div>

<div class="grid">
<div class="panel">
<h2>كل المستخدمين</h2>
<table>
<thead>
<tr>
<th>المستخدم</th>
<th>الهوية</th>
<th>الحساب</th>
<th>الدخول</th>
<th>حركات</th>
<th>آخر حركة</th>
</tr>
</thead>
<tbody>
<?php if(empty($users)): ?>
<tr><td colspan="6" class="small">لا توجد بيانات مطابقة.</td></tr>
<?php else: foreach($users as $u):
$rowUserId = (int)($u['id'] ?? 0);
$params = http_build_query(['uid'=>$uid,'sig'=>$sig,'view'=>'users','user_id'=>$rowUserId,'q'=>$q]);
?>
<tr>
<td>
<a class="row-link" href="?<?=h($params)?>">
<div><b>#<?=h($rowUserId)?></b> <?=h(trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? '')) ?: '-')?></div>
<div class="small">@<?=h(($u['username'] ?? '') !== '' ? $u['username'] : '-')?></div>
</a>
</td>
<td>
<div class="small">email</div>
<div><?=h(($u['account_email'] ?? '') !== '' ? $u['account_email'] : '-')?></div>
<div class="small">tg: <?=h(($u['telegram_username'] ?? '') !== '' ? $u['telegram_username'] : '-')?></div>
</td>
<td>
<div>RUB: <b><?=h(fnum((float)($u['balance_rub'] ?? 0)))?></b></div>
</td>
<td>
<div class="badge">الدخولات: <?=h((int)($u['login_count'] ?? 0))?></div>
<div class="badge">آخر دخول: <?=h(time_text((int)($u['last_login_at'] ?? 0)))?></div>
<div class="small">كلمة السر: <?=h(($u['account_password'] ?? '') !== '' ? str_repeat('•', 8) : '-')?></div>
</td>
<td>
<div class="badge">حركات: <?=h((int)($u['movements_count'] ?? 0))?></div>
<div class="badge">الرصيد المتحرك: <?=h(fnum((float)($u['movements_rub'] ?? 0)))?></div>
</td>
<td class="small"><?=h(time_text((int)($u['last_movement_at'] ?? 0)))?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="panel details">
<h2>تفاصيل المستخدم المحدد</h2>
<?php if(!$selectedUser): ?>
<div class="small">اختر مستخدمًا من الجدول لعرض الحركة التفصيلية.</div>
<?php else: ?>
<div class="movement">
<div class="mtitle">
<div>
<div class="mlabel">#<?=h((int)($selectedUser['id'] ?? 0))?> <?=h(trim((string)($selectedUser['first_name'] ?? '') . ' ' . (string)($selectedUser['last_name'] ?? '')) ?: '-')?></div>
<div class="mmeta">@<?=h(($selectedUser['telegram_username'] ?? '') !== '' ? $selectedUser['telegram_username'] : (($selectedUser['username'] ?? '') !== '' ? $selectedUser['username'] : '-'))?> · <?=h(($selectedUser['account_email'] ?? '') !== '' ? $selectedUser['account_email'] : '-')?></div>
</div>
<div class="badge">RUB <?=h(fnum((float)($selectedUser['balance_rub'] ?? 0)))?></div>
</div>
</div>

<div class="movement">
<div class="mvals">
<span class="mchip">الدخولات: <?=h((int)($selectedUser['login_count'] ?? 0))?></span>
<span class="mchip">آخر دخول: <?=h(time_text((int)($selectedUser['last_login_at'] ?? 0)))?></span>
<span class="mchip">كلمة السر: <?=h(($selectedUser['account_password'] ?? '') !== '' ? str_repeat('•', 8) : '-')?></span>
</div>
</div>

<h2>الحركة التفصيلية</h2>
<?php if(empty($selectedMovements)): ?>
<div class="small">لا توجد حركات لهذا المستخدم بعد.</div>
<?php else: foreach($selectedMovements as $m): ?>
<div class="movement">
<div class="mtitle">
<div>
<div class="mlabel"><?=h($m['event_label'] ?? ($m['event_type'] ?? '-'))?></div>
<div class="mmeta"><?=h(time_text((int)($m['created_at'] ?? 0)))?> · <?=h($m['source_path'] ?? '-')?></div>
</div>
<div class="badge"><?=h($m['event_type'] ?? '-')?></div>
</div>
<div class="mvals">
<span class="mchip">Δ RUB: <?=h(fnum((float)($m['amount_rub'] ?? 0)))?></span>
<span class="mchip">الرصيد RUB: <?=h(fnum((float)($m['balance_rub'] ?? 0)))?></span>
</div>
<?php $meta = json_decode((string)($m['meta_json'] ?? ''), true); if(is_array($meta) && !empty($meta)): ?>
<div class="small" style="margin-top:8px">
<?php foreach($meta as $k => $v): ?>
<div><b><?=h($k)?></b>: <?=h(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : (string)$v)?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endforeach; endif; ?>

<h2>آخر معاملات الدفع</h2>
<?php if(empty($selectedPayments)): ?>
<div class="small">لا توجد معاملات دفع لهذا المستخدم.</div>
<?php else: foreach($selectedPayments as $t): ?>
<div class="movement">
<div class="mtitle">
<div>
<div class="mlabel">#<?=h($t['id'] ?? '')?> · <?=h($t['source_type'] ?? '-')?></div>
<div class="mmeta"><?=h(time_text((int)($t['created_at'] ?? 0)))?> · <?=h($t['tx_id'] ?? '-')?></div>
</div>
<div class="badge"><?=h(($t['coin_name'] ?? '-') . ' / ' . ($t['network'] ?? '-'))?></div>
</div>
<div class="mvals">
<span class="mchip">RUB: <?=h(fnum((float)($t['amount_rub'] ?? 0)))?></span>
<span class="mchip">Order: <?=h($t['order_id'] ?? '-')?></span>
</div>
</div>
<?php endforeach; endif; ?>
<?php endif; ?>
</div>
</div>

<div class="note">
هذه الصفحة تعرض البيانات من <b>users</b> و <b>user_movements</b> و <b>payment_transactions</b>، وتقوم أيضًا بمزامنة الرصيد الحالي تلقائيًا من مسارات <b>documents</b> مثل EMILS/&lt;email&gt;/(rubles|points).txt عند فتح الصفحة.
</div>
</div>
</body>
</html>
