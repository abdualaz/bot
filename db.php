<?php

include_once __DIR__ . '/config.php';

function bot_db_ensure_parent_dir($path){

// Filesystem directory creation disabled by design.

}

if(!class_exists('BotDatabaseManager')){

class BotDatabaseManager{

private $db;

public function __construct($path){

bot_db_ensure_parent_dir($path);

$this->db = new SQLite3($path);

$this->db->busyTimeout(5000);

$this->db->exec('PRAGMA journal_mode=WAL;');

$this->db->exec('PRAGMA synchronous=NORMAL;');

$this->db->exec('PRAGMA foreign_keys=ON;');

$this->initializeSchema();

}

private function initializeSchema(){

$this->db->exec('CREATE TABLE IF NOT EXISTS settings (

  key TEXT PRIMARY KEY,

  value TEXT NOT NULL,

  value_type TEXT NOT NULL DEFAULT "string",

  updated_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS documents (

  path TEXT PRIMARY KEY,

  payload TEXT NOT NULL,

  updated_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS users (

  id INTEGER PRIMARY KEY,

  username TEXT,

  telegram_username TEXT NOT NULL DEFAULT "",

  account_email TEXT NOT NULL DEFAULT "",

  account_password TEXT NOT NULL DEFAULT "",

  first_name TEXT,

  last_name TEXT,

  payload TEXT,

  last_login_at INTEGER NOT NULL DEFAULT 0,

  login_count INTEGER NOT NULL DEFAULT 0,

  profile_payload TEXT NOT NULL DEFAULT "",

  created_at INTEGER NOT NULL,

  updated_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS admin_settings (

  id INTEGER PRIMARY KEY CHECK (id = 1),

  binance_api_key TEXT NOT NULL DEFAULT "",

  binance_api_secret TEXT NOT NULL DEFAULT "",

  binance_pay_id TEXT NOT NULL DEFAULT "",

  usd_to_rub_rate REAL NOT NULL DEFAULT 60,

  min_deposit REAL NOT NULL DEFAULT 1,

  max_deposit REAL NOT NULL DEFAULT 1000,

  security_fee_pct REAL NOT NULL DEFAULT 4,

  updated_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS payment_methods (

  id INTEGER PRIMARY KEY AUTOINCREMENT,

  coin_name TEXT NOT NULL,

  network TEXT NOT NULL,

  wallet_address TEXT NOT NULL,

  status INTEGER NOT NULL DEFAULT 1,

  created_at INTEGER NOT NULL,

  updated_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS active_orders (

  order_id TEXT PRIMARY KEY,

  user_id INTEGER NOT NULL,

  chat_id INTEGER NOT NULL,

  amount_usd REAL NOT NULL,

  decimal_fraction INTEGER NOT NULL,

  currency TEXT NOT NULL DEFAULT "USD",

  coin_name TEXT NOT NULL,

  network TEXT NOT NULL,

  wallet_address TEXT NOT NULL,

  pay_amount REAL NOT NULL,

  exchange_rate REAL NOT NULL,

  security_fee_pct REAL NOT NULL,

  status TEXT NOT NULL DEFAULT "pending",

  created_at INTEGER NOT NULL,

  expire_at INTEGER NOT NULL,

  message_id INTEGER NOT NULL DEFAULT 0,

  verification_id TEXT NOT NULL DEFAULT "",

  updated_at INTEGER NOT NULL,

  paid_at INTEGER NOT NULL DEFAULT 0,

  cancelled_at INTEGER NOT NULL DEFAULT 0

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS blacklist (

  tx_id TEXT PRIMARY KEY,

  order_id TEXT NOT NULL,

  user_id INTEGER NOT NULL,

  created_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS payment_transactions (

  id INTEGER PRIMARY KEY AUTOINCREMENT,

  order_id TEXT NOT NULL,

  user_id INTEGER NOT NULL,

  tx_id TEXT NOT NULL,

  source_type TEXT NOT NULL,

  amount_usd REAL NOT NULL,

  amount_rub REAL NOT NULL,

  coin_name TEXT NOT NULL,

  network TEXT NOT NULL,

  created_at INTEGER NOT NULL

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS user_movements (

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

$this->db->exec('CREATE TABLE IF NOT EXISTS balance_transfers (

  id INTEGER PRIMARY KEY AUTOINCREMENT,

  transfer_code TEXT NOT NULL UNIQUE,

  reference_id TEXT NOT NULL DEFAULT "",

  from_user_id INTEGER NOT NULL DEFAULT 0,

  from_email TEXT NOT NULL DEFAULT "",

  from_username TEXT NOT NULL DEFAULT "",

  to_user_id INTEGER NOT NULL DEFAULT 0,

  to_email TEXT NOT NULL DEFAULT "",

  to_username TEXT NOT NULL DEFAULT "",

  amount_sent_rub REAL NOT NULL DEFAULT 0,

  amount_received_rub REAL NOT NULL DEFAULT 0,

  fee_rub REAL NOT NULL DEFAULT 0,

  status TEXT NOT NULL DEFAULT "pending",

  meta_json TEXT NOT NULL DEFAULT "",

  created_at INTEGER NOT NULL,

  claimed_at INTEGER NOT NULL DEFAULT 0,

  cancelled_at INTEGER NOT NULL DEFAULT 0

)');

$this->db->exec('CREATE TABLE IF NOT EXISTS processing_locks (

  lock_key TEXT PRIMARY KEY,

  created_at INTEGER NOT NULL

)');

$this->db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_active_orders_user_pending ON active_orders(user_id) WHERE status = "pending"');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_active_orders_expire_at ON active_orders(expire_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_payment_methods_enabled ON payment_methods(status)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_transactions_order ON payment_transactions(order_id)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_user_movements_user ON user_movements(user_id, created_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_user_movements_email ON user_movements(email, created_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_user_movements_event ON user_movements(event_type, created_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_balance_transfers_from ON balance_transfers(from_user_id, created_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_balance_transfers_to ON balance_transfers(to_user_id, created_at)');
$this->db->exec('CREATE INDEX IF NOT EXISTS idx_balance_transfers_status ON balance_transfers(status, created_at)');

$this->migrateUsersTable();
$this->seedUserMovementsFromTransactions();

$now = time();
$this->db->exec('INSERT INTO admin_settings (id, updated_at) VALUES (1, ' . (int)$now . ') ON CONFLICT(id) DO NOTHING');

}

private function tableHasColumn($table,$column){

$stmt = $this->db->prepare('PRAGMA table_info(' . $table . ')');
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

private function migrateUsersTable(){

if(!$this->tableHasColumn('users','balance_rub')){
$this->db->exec('ALTER TABLE users ADD COLUMN balance_rub REAL NOT NULL DEFAULT 0');
}

if(!$this->tableHasColumn('users','balance_usd')){
$this->db->exec('ALTER TABLE users ADD COLUMN balance_usd REAL NOT NULL DEFAULT 0');
}

if(!$this->tableHasColumn('users','telegram_username')){
$this->db->exec('ALTER TABLE users ADD COLUMN telegram_username TEXT NOT NULL DEFAULT ""');
}

if(!$this->tableHasColumn('users','account_email')){
$this->db->exec('ALTER TABLE users ADD COLUMN account_email TEXT NOT NULL DEFAULT ""');
}

if(!$this->tableHasColumn('users','account_password')){
$this->db->exec('ALTER TABLE users ADD COLUMN account_password TEXT NOT NULL DEFAULT ""');
}

if(!$this->tableHasColumn('users','last_login_at')){
$this->db->exec('ALTER TABLE users ADD COLUMN last_login_at INTEGER NOT NULL DEFAULT 0');
}

if(!$this->tableHasColumn('users','login_count')){
$this->db->exec('ALTER TABLE users ADD COLUMN login_count INTEGER NOT NULL DEFAULT 0');
}

if(!$this->tableHasColumn('users','profile_payload')){
$this->db->exec('ALTER TABLE users ADD COLUMN profile_payload TEXT NOT NULL DEFAULT ""');
}

}

private function seedUserMovementsFromTransactions(){

$result = $this->db->query('SELECT COUNT(*) AS c FROM user_movements');
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
if((int)($row['c'] ?? 0) > 0){
return;
}

$result = $this->db->query('SELECT COUNT(*) AS c FROM payment_transactions');
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
if((int)($row['c'] ?? 0) <= 0){
return;
}

$this->db->exec('BEGIN IMMEDIATE');
$source = $this->db->query('SELECT pt.order_id, pt.user_id, pt.tx_id, pt.source_type, pt.amount_usd, pt.amount_rub, pt.coin_name, pt.network, pt.created_at, COALESCE(u.account_email, "") AS account_email, COALESCE(u.telegram_username, u.username, "") AS telegram_username FROM payment_transactions pt LEFT JOIN users u ON u.id = pt.user_id ORDER BY pt.created_at ASC');
if($source){
while($row = $source->fetchArray(SQLITE3_ASSOC)){
$insert = $this->db->prepare('INSERT INTO user_movements (user_id,email,telegram_username,event_type,event_label,amount_rub,amount_usd,balance_rub,balance_usd,source_path,reference_id,meta_json,created_at) VALUES (:user_id,:email,:telegram_username,:event_type,:event_label,:amount_rub,:amount_usd,:balance_rub,:balance_usd,:source_path,:reference_id,:meta_json,:created_at)');
if(!$insert){
continue;
}
$meta = [
'order_id' => (string)($row['order_id'] ?? ''),
'coin_name' => (string)($row['coin_name'] ?? ''),
'network' => (string)($row['network'] ?? ''),
'source_type' => (string)($row['source_type'] ?? ''),
];
$insert->bindValue(':user_id',(int)($row['user_id'] ?? 0),SQLITE3_INTEGER);
$insert->bindValue(':email',(string)($row['account_email'] ?? ''),SQLITE3_TEXT);
$insert->bindValue(':telegram_username',(string)($row['telegram_username'] ?? ''),SQLITE3_TEXT);
$insert->bindValue(':event_type','payment_credit',SQLITE3_TEXT);
$insert->bindValue(':event_label',(string)($row['source_type'] ?? 'payment_credit'),SQLITE3_TEXT);
$insert->bindValue(':amount_rub',(float)($row['amount_rub'] ?? 0),SQLITE3_FLOAT);
$insert->bindValue(':amount_usd',(float)($row['amount_usd'] ?? 0),SQLITE3_FLOAT);
$insert->bindValue(':balance_rub',(float)($row['amount_rub'] ?? 0),SQLITE3_FLOAT);
$insert->bindValue(':balance_usd',(float)($row['amount_usd'] ?? 0),SQLITE3_FLOAT);
$insert->bindValue(':source_path','payment_transactions',SQLITE3_TEXT);
$insert->bindValue(':reference_id',(string)($row['tx_id'] ?? ''),SQLITE3_TEXT);
$insert->bindValue(':meta_json',json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$insert->bindValue(':created_at',(int)($row['created_at'] ?? time()),SQLITE3_INTEGER);
$insert->execute();
}
$source->finalize();
}
$this->db->exec('COMMIT');

}

public function connection(){

return $this->db;

}

public function hasSetting($key){

$stmt = $this->db->prepare('SELECT 1 FROM settings WHERE key = :key LIMIT 1');

$stmt->bindValue(':key',(string)$key,SQLITE3_TEXT);

$result = $stmt->execute();

$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;

if($result){

$result->finalize();

}

return !empty($row);

}

public function getSetting($key,$default=''){

$stmt = $this->db->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
$stmt->bindValue(':key',(string)$key,SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
return is_array($row) && array_key_exists('value',$row) ? $row['value'] : $default;

}

public function setSetting($key,$value,$type='string'){

$now = time();
$stmt = $this->db->prepare('INSERT INTO settings (key, value, value_type, updated_at) VALUES (:key, :value, :value_type, :updated_at) ON CONFLICT(key) DO UPDATE SET value = excluded.value, value_type = excluded.value_type, updated_at = excluded.updated_at');
$stmt->bindValue(':key',(string)$key,SQLITE3_TEXT);
$stmt->bindValue(':value',(string)$value,SQLITE3_TEXT);
$stmt->bindValue(':value_type',(string)$type,SQLITE3_TEXT);
$stmt->bindValue(':updated_at',$now,SQLITE3_INTEGER);
$stmt->execute();

}

public function deleteSetting($key){

$stmt = $this->db->prepare('DELETE FROM settings WHERE key = :key');
if(!$stmt){
return;
}
$stmt->bindValue(':key',(string)$key,SQLITE3_TEXT);
$stmt->execute();

}

public function getSettings($defaults = []){

$rows = [];
foreach(array_keys((array)$defaults) as $key){
$rows[$key] = $this->getSetting($key,$defaults[$key] ?? '');
}
return $rows;

}

public function seedSettings($defaults = []){

foreach((array)$defaults as $key => $value){
if(!$this->hasSetting($key)){
$type = 'string';
if(is_int($value)){
$type = 'int';
}elseif(is_float($value)){
$type = 'float';
}
$this->setSetting($key,$value,$type);
}
}

}

public function hasDocument($path){

$stmt = $this->db->prepare('SELECT 1 FROM documents WHERE path = :path LIMIT 1');

$stmt->bindValue(':path',(string)$path,SQLITE3_TEXT);

$result = $stmt->execute();

$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;

if($result){

$result->finalize();

}

return !empty($row);

}

public function getDocument($path,$default=''){

$stmt = $this->db->prepare('SELECT payload FROM documents WHERE path = :path LIMIT 1');

$stmt->bindValue(':path',(string)$path,SQLITE3_TEXT);

$result = $stmt->execute();

$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

if($result){

$result->finalize();

}

return is_array($row) && array_key_exists('payload',$row) ? $row['payload'] : $default;

}

public function setDocument($path,$payload){

$stmt = $this->db->prepare('INSERT INTO documents (path, payload, updated_at) VALUES (:path, :payload, :updated_at) ON CONFLICT(path) DO UPDATE SET payload = excluded.payload, updated_at = excluded.updated_at');

$stmt->bindValue(':path',(string)$path,SQLITE3_TEXT);

$stmt->bindValue(':payload',(string)$payload,SQLITE3_TEXT);

$stmt->bindValue(':updated_at',time(),SQLITE3_INTEGER);

$stmt->execute();

}

public function deleteDocument($path){

$stmt = $this->db->prepare('DELETE FROM documents WHERE path = :path');

$stmt->bindValue(':path',(string)$path,SQLITE3_TEXT);

$stmt->execute();

}

public function getUserById($userId){

$stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->bindValue(':id',(int)$userId,SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
return is_array($row) ? $row : null;

}

public function getUserByEmail($email){

$stmt = $this->db->prepare('SELECT * FROM users WHERE LOWER(account_email) = LOWER(:email) LIMIT 1');
$stmt->bindValue(':email',trim((string)$email),SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
return is_array($row) ? $row : null;

}

public function upsertUserProfile($userId,$username='',$firstName='',$lastName='',$email='',$password='',$loginAt=null,$profilePayload=[]){

$now = is_numeric($loginAt) ? (int)$loginAt : time();
$stmt = $this->db->prepare('INSERT INTO users (id, username, telegram_username, account_email, account_password, first_name, last_name, payload, last_login_at, login_count, profile_payload, created_at, updated_at) VALUES (:id, :username, :telegram_username, :account_email, :account_password, :first_name, :last_name, :payload, :last_login_at, 1, :profile_payload, :created_at, :updated_at) ON CONFLICT(id) DO UPDATE SET username = excluded.username, telegram_username = CASE WHEN excluded.telegram_username <> "" THEN excluded.telegram_username ELSE users.telegram_username END, account_email = CASE WHEN excluded.account_email <> "" THEN excluded.account_email ELSE users.account_email END, account_password = CASE WHEN excluded.account_password <> "" THEN excluded.account_password ELSE users.account_password END, first_name = excluded.first_name, last_name = excluded.last_name, payload = CASE WHEN excluded.payload <> "" THEN excluded.payload ELSE users.payload END, last_login_at = CASE WHEN excluded.last_login_at > 0 THEN excluded.last_login_at ELSE users.last_login_at END, login_count = COALESCE(users.login_count,0) + 1, profile_payload = CASE WHEN excluded.profile_payload <> "" THEN excluded.profile_payload ELSE users.profile_payload END, updated_at = excluded.updated_at');
$stmt->bindValue(':id',(int)$userId,SQLITE3_INTEGER);
$stmt->bindValue(':username',trim((string)$username),SQLITE3_TEXT);
$stmt->bindValue(':telegram_username',trim((string)$username),SQLITE3_TEXT);
$stmt->bindValue(':account_email',trim((string)$email),SQLITE3_TEXT);
$stmt->bindValue(':account_password',trim((string)$password),SQLITE3_TEXT);
$stmt->bindValue(':first_name',trim((string)$firstName),SQLITE3_TEXT);
$stmt->bindValue(':last_name',trim((string)$lastName),SQLITE3_TEXT);
$stmt->bindValue(':payload',trim((string)$email),SQLITE3_TEXT);
$stmt->bindValue(':last_login_at',$now,SQLITE3_INTEGER);
$stmt->bindValue(':profile_payload',json_encode(is_array($profilePayload) ? $profilePayload : [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$stmt->bindValue(':created_at',$now,SQLITE3_INTEGER);
$stmt->bindValue(':updated_at',$now,SQLITE3_INTEGER);
$stmt->execute();

}

public function recordUserMovement($userId,$email,$telegramUsername,$eventType,$eventLabel,$amountRub,$amountUsd,$balanceRub,$balanceUsd,$sourcePath='',$referenceId='',$meta=[]){

$stmt = $this->db->prepare('INSERT INTO user_movements (user_id,email,telegram_username,event_type,event_label,amount_rub,amount_usd,balance_rub,balance_usd,source_path,reference_id,meta_json,created_at) VALUES (:user_id,:email,:telegram_username,:event_type,:event_label,:amount_rub,:amount_usd,:balance_rub,:balance_usd,:source_path,:reference_id,:meta_json,:created_at)');
$stmt->bindValue(':user_id',(int)$userId,SQLITE3_INTEGER);
$stmt->bindValue(':email',trim((string)$email),SQLITE3_TEXT);
$stmt->bindValue(':telegram_username',trim((string)$telegramUsername),SQLITE3_TEXT);
$stmt->bindValue(':event_type',trim((string)$eventType),SQLITE3_TEXT);
$stmt->bindValue(':event_label',trim((string)$eventLabel),SQLITE3_TEXT);
$stmt->bindValue(':amount_rub',(float)$amountRub,SQLITE3_FLOAT);
$stmt->bindValue(':amount_usd',(float)$amountUsd,SQLITE3_FLOAT);
$stmt->bindValue(':balance_rub',(float)$balanceRub,SQLITE3_FLOAT);
$stmt->bindValue(':balance_usd',(float)$balanceUsd,SQLITE3_FLOAT);
$stmt->bindValue(':source_path',trim((string)$sourcePath),SQLITE3_TEXT);
$stmt->bindValue(':reference_id',trim((string)$referenceId),SQLITE3_TEXT);
$stmt->bindValue(':meta_json',json_encode(is_array($meta) ? $meta : [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$stmt->bindValue(':created_at',time(),SQLITE3_INTEGER);
$stmt->execute();

}

public function recentUserMovements($limit = 100){

$limit = max(1,min(300,(int)$limit));
$result = $this->db->query('SELECT * FROM user_movements ORDER BY created_at DESC LIMIT ' . $limit);
$rows = [];
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
$rows[] = $row;
}
if($result){
$result->finalize();
}
return $rows;

}

public function userMovementsForUser($userId,$limit = 100){

$limit = max(1,min(300,(int)$limit));
$stmt = $this->db->prepare('SELECT * FROM user_movements WHERE user_id = :id ORDER BY created_at DESC LIMIT ' . $limit);
$stmt->bindValue(':id',(int)$userId,SQLITE3_INTEGER);
$result = $stmt->execute();
$rows = [];
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
$rows[] = $row;
}
if($result){
$result->finalize();
}
return $rows;

}

public function recordBalanceTransfer($transferCode,$referenceId,$fromUserId,$fromEmail,$fromUsername,$toUserId,$toEmail,$toUsername,$amountSentRub,$amountReceivedRub,$feeRub,$status='pending',$meta=[]){

$transferCode = trim((string)$transferCode);
if($transferCode === ''){
return false;
}

$stmt = $this->db->prepare('INSERT INTO balance_transfers (transfer_code,reference_id,from_user_id,from_email,from_username,to_user_id,to_email,to_username,amount_sent_rub,amount_received_rub,fee_rub,status,meta_json,created_at) VALUES (:transfer_code,:reference_id,:from_user_id,:from_email,:from_username,:to_user_id,:to_email,:to_username,:amount_sent_rub,:amount_received_rub,:fee_rub,:status,:meta_json,:created_at) ON CONFLICT(transfer_code) DO UPDATE SET reference_id = excluded.reference_id, from_user_id = excluded.from_user_id, from_email = excluded.from_email, from_username = excluded.from_username, to_user_id = excluded.to_user_id, to_email = excluded.to_email, to_username = excluded.to_username, amount_sent_rub = excluded.amount_sent_rub, amount_received_rub = excluded.amount_received_rub, fee_rub = excluded.fee_rub, status = excluded.status, meta_json = excluded.meta_json');
if(!$stmt){
return false;
}

$stmt->bindValue(':transfer_code',$transferCode,SQLITE3_TEXT);
$stmt->bindValue(':reference_id',trim((string)$referenceId),SQLITE3_TEXT);
$stmt->bindValue(':from_user_id',(int)$fromUserId,SQLITE3_INTEGER);
$stmt->bindValue(':from_email',trim((string)$fromEmail),SQLITE3_TEXT);
$stmt->bindValue(':from_username',trim((string)$fromUsername),SQLITE3_TEXT);
$stmt->bindValue(':to_user_id',(int)$toUserId,SQLITE3_INTEGER);
$stmt->bindValue(':to_email',trim((string)$toEmail),SQLITE3_TEXT);
$stmt->bindValue(':to_username',trim((string)$toUsername),SQLITE3_TEXT);
$stmt->bindValue(':amount_sent_rub',(float)$amountSentRub,SQLITE3_FLOAT);
$stmt->bindValue(':amount_received_rub',(float)$amountReceivedRub,SQLITE3_FLOAT);
$stmt->bindValue(':fee_rub',(float)$feeRub,SQLITE3_FLOAT);
$stmt->bindValue(':status',trim((string)$status),SQLITE3_TEXT);
$stmt->bindValue(':meta_json',json_encode(is_array($meta) ? $meta : [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$stmt->bindValue(':created_at',time(),SQLITE3_INTEGER);
$stmt->execute();
return true;

}

public function markBalanceTransferClaimed($transferCode,$meta=[]){

$transferCode = trim((string)$transferCode);
if($transferCode === ''){
return false;
}

$stmt = $this->db->prepare('UPDATE balance_transfers SET status = "claimed", claimed_at = :claimed_at, meta_json = :meta_json WHERE transfer_code = :transfer_code');
if(!$stmt){
return false;
}
$stmt->bindValue(':claimed_at',time(),SQLITE3_INTEGER);
$stmt->bindValue(':meta_json',json_encode(is_array($meta) ? $meta : [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),SQLITE3_TEXT);
$stmt->bindValue(':transfer_code',$transferCode,SQLITE3_TEXT);
$stmt->execute();
return true;

}

public function backfillUsersFromDocuments(){
$result = $this->db->query('SELECT path, payload FROM documents');
if(!$result){
return;
}
$userBalances = [];
while($row = $result->fetchArray(SQLITE3_ASSOC)){
$path = (string)($row['path'] ?? '');
$content = (string)($row['payload'] ?? '');
if(!preg_match('#^EMILS/([^/]+)/(rubles|points)\.txt$#',$path,$m)){
continue;
}
$email = strtolower(trim((string)($m[1] ?? '')));
$kind = (string)($m[2] ?? '');
if($email === ''){
continue;
}
if(!isset($userBalances[$email])){
$userBalances[$email] = ['email'=>$email,'points'=>null,'rubles'=>null];
}
$value = is_numeric($content) ? (float)$content : 0.0;
if($kind === 'rubles'){
$userBalances[$email]['rubles'] = $value;
}elseif($kind === 'points'){
$userBalances[$email]['points'] = $value;
}
}
$result->finalize();

foreach($userBalances as $email => $rec){
$points = $rec['points'];
$rubles = $rec['rubles'];
$rub = $points !== null ? (float)$points : (float)($rubles ?? 0.0);
if($email === ''){
continue;
}

$now = time();
$stmt = $this->db->prepare('UPDATE users SET balance_rub = :rub, balance_usd = 0, updated_at = :now WHERE LOWER(COALESCE(account_email,"")) = LOWER(:email)');
if($stmt){
$stmt->bindValue(':rub',$rub,SQLITE3_FLOAT);
$stmt->bindValue(':now',$now,SQLITE3_INTEGER);
$stmt->bindValue(':email',$email,SQLITE3_TEXT);
$stmt->execute();
}
}
}

}

}

function bot_crypto_master_secret(){

$internal = (string)($GLOBALS['apiInternalSecret'] ?? '');
if(trim($internal) !== ''){
return trim($internal);
}

$token = (string)($GLOBALS['botToken'] ?? '');
if(trim($token) !== ''){
return trim($token);
}

return 'fallback-static-secret-change-me';

}

function bot_crypto_key(){

return hash('sha256',bot_crypto_master_secret(),true);

}

function bot_encrypt_secret($plain){

$plain = (string)$plain;
if($plain === ''){
return '';
}

if(!function_exists('openssl_encrypt')){
return $plain;
}

$iv = random_bytes(12);
$tag = '';
$cipher = openssl_encrypt($plain,'aes-256-gcm',bot_crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'',16);
if($cipher === false){
return $plain;
}

return 'enc:v1:' . base64_encode($iv . $tag . $cipher);

}

function bot_decrypt_secret($encrypted){

$encrypted = (string)$encrypted;
if($encrypted === ''){
return '';
}

if(strpos($encrypted,'enc:v1:') !== 0){
return $encrypted;
}

if(!function_exists('openssl_decrypt')){
return '';
}

$raw = base64_decode(substr($encrypted,7),true);
if($raw === false || strlen($raw) < 28){
return '';
}

$iv = substr($raw,0,12);
$tag = substr($raw,12,16);
$cipher = substr($raw,28);

$plain = openssl_decrypt($cipher,'aes-256-gcm',bot_crypto_key(),OPENSSL_RAW_DATA,$iv,$tag,'');
return $plain === false ? '' : (string)$plain;

}

if(!class_exists('BinanceAutoPayRepository')){

class BinanceAutoPayRepository{

private $db;

public function __construct(){

$this->db = bot_db_manager()->connection();
$this->bootstrapAdminSettings();

}

private function bootstrapAdminSettings(){

$stmt = $this->db->prepare('SELECT * FROM admin_settings WHERE id = 1 LIMIT 1');
$result = $stmt ? $stmt->execute() : false;
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}

$apiKeyLegacy = trim((string)bot_setting_get('binanceApiKey',''));
$apiSecretLegacy = trim((string)bot_setting_get('binanceSecret',''));
$rateLegacy = (float)bot_setting_get('usdToRubRate','60');

$apiKeyToSave = (string)($row['binance_api_key'] ?? '');
$apiSecretToSave = (string)($row['binance_api_secret'] ?? '');
$rateToSave = (float)($row['usd_to_rub_rate'] ?? 0);

if($apiKeyToSave === '' && $apiKeyLegacy !== ''){
$apiKeyToSave = bot_encrypt_secret($apiKeyLegacy);
}
if($apiSecretToSave === '' && $apiSecretLegacy !== ''){
$apiSecretToSave = bot_encrypt_secret($apiSecretLegacy);
}
if($rateToSave <= 0 && $rateLegacy > 0){
$rateToSave = $rateLegacy;
}
if($rateToSave <= 0){
$rateToSave = 60;
}

$stmt2 = $this->db->prepare('UPDATE admin_settings SET binance_api_key = :k, binance_api_secret = :s, usd_to_rub_rate = :r, updated_at = :u WHERE id = 1');
$stmt2->bindValue(':k',(string)$apiKeyToSave,SQLITE3_TEXT);
$stmt2->bindValue(':s',(string)$apiSecretToSave,SQLITE3_TEXT);
$stmt2->bindValue(':r',(float)$rateToSave,SQLITE3_FLOAT);
$stmt2->bindValue(':u',time(),SQLITE3_INTEGER);
$stmt2->execute();

}

private function ensureDefaultPaymentMethods(){

$result = $this->db->query('SELECT COUNT(*) AS c FROM payment_methods WHERE status = 1');
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}

$enabledCount = is_array($row) ? (int)($row['c'] ?? 0) : 0;
if($enabledCount > 0){
return;
}

$legacyAddress = trim((string)bot_setting_get('binanceTonAddress',''));
$legacyNetwork = strtoupper(trim((string)bot_setting_get('binanceTonNetwork','TON')));
if($legacyNetwork === ''){
$legacyNetwork = 'TON';
}

$fallbackAddress = ($legacyAddress !== '') ? $legacyAddress : 'SET_WALLET_ADDRESS';
$now = time();

$stmt1 = $this->db->prepare('INSERT INTO payment_methods (coin_name, network, wallet_address, status, created_at, updated_at) VALUES ("USDT","TRC20",:w1,1,:t,:t)');
$stmt1->bindValue(':w1',$fallbackAddress,SQLITE3_TEXT);
$stmt1->bindValue(':t',$now,SQLITE3_INTEGER);
$stmt1->execute();

$stmt2 = $this->db->prepare('INSERT INTO payment_methods (coin_name, network, wallet_address, status, created_at, updated_at) VALUES ("TON",:n,:w2,1,:t,:t)');
$stmt2->bindValue(':n',$legacyNetwork,SQLITE3_TEXT);
$stmt2->bindValue(':w2',$fallbackAddress,SQLITE3_TEXT);
$stmt2->bindValue(':t',$now,SQLITE3_INTEGER);
$stmt2->execute();

}

private function beginImmediate(){
$this->db->exec('BEGIN IMMEDIATE');
}

private function commit(){
$this->db->exec('COMMIT');
}

private function rollback(){
$this->db->exec('ROLLBACK');
}

public function getAdminSettings(){

$stmt = $this->db->prepare('SELECT * FROM admin_settings WHERE id = 1 LIMIT 1');
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : [];
if($result){
$result->finalize();
}

if(!is_array($row)){
$row = [];
}

return [
'binance_api_key' => bot_decrypt_secret((string)($row['binance_api_key'] ?? '')),
'binance_api_secret' => bot_decrypt_secret((string)($row['binance_api_secret'] ?? '')),
'binance_pay_id' => (string)($row['binance_pay_id'] ?? ''),
'usd_to_rub_rate' => (float)($row['usd_to_rub_rate'] ?? 60),
'min_deposit' => (float)($row['min_deposit'] ?? 1),
'max_deposit' => (float)($row['max_deposit'] ?? 1000),
'security_fee_pct' => (float)($row['security_fee_pct'] ?? 4),
];

}

public function saveAdminSettings($input){

$current = $this->getAdminSettings();
$next = [
'binance_api_key' => trim((string)($input['binance_api_key'] ?? $current['binance_api_key'])),
'binance_api_secret' => trim((string)($input['binance_api_secret'] ?? $current['binance_api_secret'])),
'binance_pay_id' => trim((string)($input['binance_pay_id'] ?? $current['binance_pay_id'])),
'usd_to_rub_rate' => (float)($input['usd_to_rub_rate'] ?? $current['usd_to_rub_rate']),
'min_deposit' => (float)($input['min_deposit'] ?? $current['min_deposit']),
'max_deposit' => (float)($input['max_deposit'] ?? $current['max_deposit']),
'security_fee_pct' => (float)($input['security_fee_pct'] ?? $current['security_fee_pct']),
];

if($next['usd_to_rub_rate'] <= 0){
$next['usd_to_rub_rate'] = 60;
}
if($next['min_deposit'] <= 0){
$next['min_deposit'] = 1;
}
if($next['max_deposit'] < $next['min_deposit']){
$next['max_deposit'] = $next['min_deposit'];
}
if($next['security_fee_pct'] < 0){
$next['security_fee_pct'] = 0;
}

$stmt = $this->db->prepare('UPDATE admin_settings SET binance_api_key = :k, binance_api_secret = :s, binance_pay_id = :p, usd_to_rub_rate = :r, min_deposit = :min_d, max_deposit = :max_d, security_fee_pct = :fee, updated_at = :u WHERE id = 1');
$stmt->bindValue(':k',bot_encrypt_secret($next['binance_api_key']),SQLITE3_TEXT);
$stmt->bindValue(':s',bot_encrypt_secret($next['binance_api_secret']),SQLITE3_TEXT);
$stmt->bindValue(':p',$next['binance_pay_id'],SQLITE3_TEXT);
$stmt->bindValue(':r',$next['usd_to_rub_rate'],SQLITE3_FLOAT);
$stmt->bindValue(':min_d',$next['min_deposit'],SQLITE3_FLOAT);
$stmt->bindValue(':max_d',$next['max_deposit'],SQLITE3_FLOAT);
$stmt->bindValue(':fee',$next['security_fee_pct'],SQLITE3_FLOAT);
$stmt->bindValue(':u',time(),SQLITE3_INTEGER);
$stmt->execute();

return $next;

}

public function listPaymentMethods($enabledOnly = false){

$sql = 'SELECT * FROM payment_methods';
$filters = ['wallet_address <> "SET_WALLET_ADDRESS"'];
if($enabledOnly){
$filters[] = 'status = 1';
}
if(!empty($filters)){
$sql .= ' WHERE ' . implode(' AND ',$filters);
}
$sql .= ' ORDER BY id DESC';

$result = $this->db->query($sql);
$rows = [];
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
$rows[] = $row;
}
if($result){
$result->finalize();
}
return $rows;

}

public function savePaymentMethod($coinName,$network,$walletAddress,$status = 1,$id = 0){

$coinName = strtoupper(trim((string)$coinName));
$network = strtoupper(trim((string)$network));
$walletAddress = trim((string)$walletAddress);
$status = (int)$status === 1 ? 1 : 0;
$now = time();

if($id > 0){
$stmt = $this->db->prepare('UPDATE payment_methods SET coin_name = :c, network = :n, wallet_address = :w, status = :s, updated_at = :u WHERE id = :id');
$stmt->bindValue(':id',(int)$id,SQLITE3_INTEGER);
}else{
$stmt = $this->db->prepare('INSERT INTO payment_methods (coin_name, network, wallet_address, status, created_at, updated_at) VALUES (:c,:n,:w,:s,:u,:u)');
}

$stmt->bindValue(':c',$coinName,SQLITE3_TEXT);
$stmt->bindValue(':n',$network,SQLITE3_TEXT);
$stmt->bindValue(':w',$walletAddress,SQLITE3_TEXT);
$stmt->bindValue(':s',$status,SQLITE3_INTEGER);
$stmt->bindValue(':u',$now,SQLITE3_INTEGER);
$stmt->execute();

}

public function deletePaymentMethod($id){
$stmt = $this->db->prepare('DELETE FROM payment_methods WHERE id = :id');
$stmt->bindValue(':id',(int)$id,SQLITE3_INTEGER);
$stmt->execute();
}

public function getPendingOrderForUser($userId){

$stmt = $this->db->prepare('SELECT * FROM active_orders WHERE user_id = :u AND status = "pending" ORDER BY created_at DESC LIMIT 1');
$stmt->bindValue(':u',(int)$userId,SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
return is_array($row) ? $row : null;

}

public function getOrderById($orderId){

$stmt = $this->db->prepare('SELECT * FROM active_orders WHERE order_id = :o LIMIT 1');
$stmt->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
return is_array($row) ? $row : null;

}

private function allocateDecimalFraction($amountUsd){

for($i=0;$i<50;$i++){
$fraction = random_int(1,9999);
$payAmount = round((float)$amountUsd + ($fraction / 10000),4);
$stmt = $this->db->prepare('SELECT 1 FROM active_orders WHERE status = "pending" AND currency = "USD" AND ABS(pay_amount - :p) < 0.00001 LIMIT 1');
$stmt->bindValue(':p',$payAmount,SQLITE3_FLOAT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;
if($result){
$result->finalize();
}
if(!$row){
return [$fraction,$payAmount];
}
}

return [0,round((float)$amountUsd,4)];

}

public function createPendingOrder($chatId,$userId,$amountUsd,$currency = 'USD',$paymentFlow = 'crypto',$paymentMethodId = 0){

$amountUsd = (float)$amountUsd;
if($amountUsd <= 0){
return ['ok'=>false,'error'=>'المبلغ غير صالح'];
}

$settings = $this->getAdminSettings();
if($amountUsd < (float)$settings['min_deposit']){
return ['ok'=>false,'error'=>'المبلغ أقل من الحد الأدنى'];
}
if($amountUsd > (float)$settings['max_deposit']){
return ['ok'=>false,'error'=>'المبلغ أعلى من الحد الأعلى'];
}

$effectiveRate = (float)$settings['usd_to_rub_rate'];
if($effectiveRate <= 0){
$effectiveRate = 60;
}

$paymentFlow = trim((string)$paymentFlow);
if(!in_array($paymentFlow,['binance_id','crypto'],true)){
$paymentFlow = 'crypto';
}

if($paymentFlow === 'binance_id'){
$payId = trim((string)($settings['binance_pay_id'] ?? ''));
if($payId === ''){
return ['ok'=>false,'error'=>'لم يتم ضبط Binance Pay ID في الإعدادات'];
}
$method = [
'coin_name'=>'USDT',
'network'=>'BINANCE_ID',
'wallet_address'=>$payId,
];
}else{
$methods = $this->listPaymentMethods(true);
if(empty($methods)){
return ['ok'=>false,'error'=>'لا توجد طريقة دفع مفعلة'];
}
$paymentMethodId = (int)$paymentMethodId;
$method = null;
if($paymentMethodId > 0){
foreach($methods as $rowMethod){
if((int)($rowMethod['id'] ?? 0) === $paymentMethodId){
$method = $rowMethod;
break;
}
}
if($method === null){
return ['ok'=>false,'error'=>'العملة المختارة غير متاحة حالياً'];
}
}else{
$method = $methods[0];
}
}

$cryptoTransferFeePct = 2.0;
$cryptoTransferFeeFactor = 1 + ($cryptoTransferFeePct / 100);
$cryptoMarketPrice = 1.0;

try{
$this->beginImmediate();

$existing = $this->getPendingOrderForUser($userId);
if($existing){
$this->rollback();
return ['ok'=>false,'error'=>'لديك فاتورة دفع معلقة','code'=>'HAS_PENDING','order'=>$existing];
}

if($paymentFlow === 'binance_id'){
$fraction = 0;
$payAmount = round((float)$amountUsd,4);
}else{
$fraction = 0;
$coin = strtoupper(trim((string)($method['coin_name'] ?? 'USDT')));
if($coin === '' || $coin === 'USDT'){
$cryptoMarketPrice = 1.0;
$payAmount = round(((float)$amountUsd * $cryptoTransferFeeFactor),4);
}else{
$symbol = $coin . 'USDT';
$cryptoMarketPrice = (float)bot_binance_client()->tickerPrice($symbol);
if($cryptoMarketPrice <= 0){
$this->rollback();
return ['ok'=>false,'error'=>'تعذر جلب سعر صرف حي للعملة المختارة'];
}
$baseCoinAmount = ((float)$amountUsd / $cryptoMarketPrice);
$payAmount = round(($baseCoinAmount * $cryptoTransferFeeFactor),6);
}
if($payAmount <= 0){
$this->rollback();
return ['ok'=>false,'error'=>'تعذر احتساب مبلغ الدفع بالعملة المختارة'];
}
}

$orderId = 'BNP' . strtoupper(substr(md5(uniqid((string)$userId,true)),0,11));
$now = time();
$expireAt = $now + (15 * 60);

$stmt = $this->db->prepare('INSERT INTO active_orders (order_id,user_id,chat_id,amount_usd,decimal_fraction,currency,coin_name,network,wallet_address,pay_amount,exchange_rate,security_fee_pct,status,created_at,expire_at,updated_at) VALUES (:o,:u,:c,:a,:d,:cur,:coin,:net,:wal,:pay,:rate,:fee,"pending",:t,:e,:t)');
$stmt->bindValue(':o',$orderId,SQLITE3_TEXT);
$stmt->bindValue(':u',(int)$userId,SQLITE3_INTEGER);
$stmt->bindValue(':c',(int)$chatId,SQLITE3_INTEGER);
$stmt->bindValue(':a',round($amountUsd,2),SQLITE3_FLOAT);
$stmt->bindValue(':d',(int)$fraction,SQLITE3_INTEGER);
$stmt->bindValue(':cur',(string)$currency,SQLITE3_TEXT);
$stmt->bindValue(':coin',(string)$method['coin_name'],SQLITE3_TEXT);
$stmt->bindValue(':net',(string)$method['network'],SQLITE3_TEXT);
$stmt->bindValue(':wal',(string)$method['wallet_address'],SQLITE3_TEXT);
$stmt->bindValue(':pay',(float)$payAmount,SQLITE3_FLOAT);
$stmt->bindValue(':rate',(float)$effectiveRate,SQLITE3_FLOAT);
$stmt->bindValue(':fee',(float)$settings['security_fee_pct'],SQLITE3_FLOAT);
$stmt->bindValue(':t',$now,SQLITE3_INTEGER);
$stmt->bindValue(':e',$expireAt,SQLITE3_INTEGER);
$stmt->execute();

$this->db->exec('INSERT INTO users (id,created_at,updated_at,balance_rub,balance_usd) VALUES (' . (int)$userId . ',' . (int)$now . ',' . (int)$now . ',0,0) ON CONFLICT(id) DO NOTHING');

$this->commit();
return ['ok'=>true,'order'=>$this->getOrderById($orderId),'settings'=>$settings];

}catch(Throwable $e){
$this->rollback();
return ['ok'=>false,'error'=>'تعذر إنشاء الطلب حاليا'];
}

}

public function setOrderMessageId($orderId,$messageId){
$stmt = $this->db->prepare('UPDATE active_orders SET message_id = :m, updated_at = :u WHERE order_id = :o');
$stmt->bindValue(':m',(int)$messageId,SQLITE3_INTEGER);
$stmt->bindValue(':u',time(),SQLITE3_INTEGER);
$stmt->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt->execute();
}

public function renewOrder($orderId,$userId){

$order = $this->getOrderById($orderId);
if(!$order || (int)$order['user_id'] !== (int)$userId){
return ['ok'=>false,'error'=>'الطلب غير موجود'];
}
if((string)$order['status'] !== 'pending'){
return ['ok'=>false,'error'=>'لا يمكن تجديد هذا الطلب'];
}

$remaining = ((int)$order['expire_at']) - time();
if($remaining > 180){
return ['ok'=>false,'error'=>'يمكن التجديد عند تبقي أقل من 3 دقائق'];
}

$now = time();
$expireAt = $now + (15 * 60);
$stmt = $this->db->prepare('UPDATE active_orders SET expire_at = :e, updated_at = :u WHERE order_id = :o');
$stmt->bindValue(':e',$expireAt,SQLITE3_INTEGER);
$stmt->bindValue(':u',$now,SQLITE3_INTEGER);
$stmt->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt->execute();

return ['ok'=>true,'order'=>$this->getOrderById($orderId)];

}

public function cancelOrder($orderId,$userId){

$stmt = $this->db->prepare('UPDATE active_orders SET status = "cancelled", cancelled_at = :t, updated_at = :t WHERE order_id = :o AND user_id = :u AND status = "pending"');
$stmt->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt->bindValue(':u',(int)$userId,SQLITE3_INTEGER);
$stmt->bindValue(':t',time(),SQLITE3_INTEGER);
$stmt->execute();
return $this->db->changes() > 0;

}

public function isBlacklisted($txId){
$stmt = $this->db->prepare('SELECT 1 FROM blacklist WHERE tx_id = :t LIMIT 1');
$stmt->bindValue(':t',trim((string)$txId),SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;
if($result){
$result->finalize();
}
return !empty($row);
}

public function listBlacklist($limit = 100){
$limit = max(1,min(500,(int)$limit));
$result = $this->db->query('SELECT * FROM blacklist ORDER BY created_at DESC LIMIT ' . $limit);
$rows = [];
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
$rows[] = $row;
}
if($result){
$result->finalize();
}
return $rows;
}

public function removeBlacklist($txId){
$stmt = $this->db->prepare('DELETE FROM blacklist WHERE tx_id = :t');
$stmt->bindValue(':t',trim((string)$txId),SQLITE3_TEXT);
$stmt->execute();
}

public function acquireProcessingLock($lockKey){
$stmt = $this->db->prepare('INSERT OR IGNORE INTO processing_locks (lock_key, created_at) VALUES (:k,:t)');
$stmt->bindValue(':k',(string)$lockKey,SQLITE3_TEXT);
$stmt->bindValue(':t',time(),SQLITE3_INTEGER);
$stmt->execute();
return $this->db->changes() > 0;
}

public function releaseProcessingLock($lockKey){
$stmt = $this->db->prepare('DELETE FROM processing_locks WHERE lock_key = :k');
$stmt->bindValue(':k',(string)$lockKey,SQLITE3_TEXT);
$stmt->execute();
}

public function finalizeSuccessfulPayment($orderId,$txId,$sourceType){

$txId = trim((string)$txId);
if($txId === ''){
return ['ok'=>false,'error'=>'مرجع المعاملة فارغ'];
}

try{
$this->beginImmediate();

$order = $this->getOrderById($orderId);
if(!$order || (string)$order['status'] !== 'pending'){
$this->rollback();
return ['ok'=>false,'error'=>'الطلب غير صالح'];
}

if($this->isBlacklisted($txId)){
$this->rollback();
return ['ok'=>false,'error'=>'هذه المعاملة مستخدمة مسبقا'];
}

$creditedUsd = (float)$order['amount_usd'];
$grossRub = $creditedUsd * (float)$order['exchange_rate'];
$netRub = $grossRub * (1 - (((float)$order['security_fee_pct']) / 100));

$stmt = $this->db->prepare('INSERT INTO blacklist (tx_id, order_id, user_id, created_at) VALUES (:t,:o,:u,:c)');
$stmt->bindValue(':t',$txId,SQLITE3_TEXT);
$stmt->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt->bindValue(':u',(int)$order['user_id'],SQLITE3_INTEGER);
$stmt->bindValue(':c',time(),SQLITE3_INTEGER);
$stmt->execute();

$stmt2 = $this->db->prepare('UPDATE users SET balance_rub = COALESCE(balance_rub,0) + :rub, balance_usd = COALESCE(balance_usd,0) + :usd, updated_at = :u WHERE id = :id');
$stmt2->bindValue(':rub',(float)$netRub,SQLITE3_FLOAT);
$stmt2->bindValue(':usd',(float)$creditedUsd,SQLITE3_FLOAT);
$stmt2->bindValue(':u',time(),SQLITE3_INTEGER);
$stmt2->bindValue(':id',(int)$order['user_id'],SQLITE3_INTEGER);
$stmt2->execute();

$stmt3 = $this->db->prepare('UPDATE active_orders SET status = "paid", verification_id = :v, paid_at = :p, updated_at = :p WHERE order_id = :o');
$stmt3->bindValue(':v',$txId,SQLITE3_TEXT);
$stmt3->bindValue(':p',time(),SQLITE3_INTEGER);
$stmt3->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt3->execute();

$stmt4 = $this->db->prepare('INSERT INTO payment_transactions (order_id,user_id,tx_id,source_type,amount_usd,amount_rub,coin_name,network,created_at) VALUES (:o,:u,:t,:s,:usd,:rub,:coin,:net,:c)');
$stmt4->bindValue(':o',(string)$orderId,SQLITE3_TEXT);
$stmt4->bindValue(':u',(int)$order['user_id'],SQLITE3_INTEGER);
$stmt4->bindValue(':t',$txId,SQLITE3_TEXT);
$stmt4->bindValue(':s',(string)$sourceType,SQLITE3_TEXT);
$stmt4->bindValue(':usd',(float)$creditedUsd,SQLITE3_FLOAT);
$stmt4->bindValue(':rub',(float)$netRub,SQLITE3_FLOAT);
$stmt4->bindValue(':coin',(string)$order['coin_name'],SQLITE3_TEXT);
$stmt4->bindValue(':net',(string)$order['network'],SQLITE3_TEXT);
$stmt4->bindValue(':c',time(),SQLITE3_INTEGER);
$stmt4->execute();

$userProfile = $this->getUserById((int)$order['user_id']);
$userEmail = is_array($userProfile) ? (string)($userProfile['email'] ?? '') : '';
$userUsername = is_array($userProfile) ? (string)($userProfile['username'] ?? '') : '';
$this->recordUserMovement((int)$order['user_id'],$userEmail,$userUsername,'payment_credit','deposit_verified',$netRub,$creditedUsd,$netRub,$creditedUsd,(string)$orderId,(string)$txId,[
'source_type' => (string)$sourceType,
'coin_name' => (string)$order['coin_name'],
'network' => (string)$order['network'],
]);

$this->commit();

return ['ok'=>true,'order'=>$this->getOrderById($orderId),'credited_rub'=>$netRub,'credited_usd'=>$creditedUsd];

}catch(Throwable $e){
$this->rollback();
return ['ok'=>false,'error'=>'فشل إتمام العملية'];
}

}

public function transactionsSummary(){

$summary = [
'count' => 0,
'total_usd' => 0,
'total_rub' => 0,
];

$result = $this->db->query('SELECT COUNT(*) AS c, COALESCE(SUM(amount_usd),0) AS usd, COALESCE(SUM(amount_rub),0) AS rub FROM payment_transactions');
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
if($result){
$result->finalize();
}
if(is_array($row)){
$summary['count'] = (int)($row['c'] ?? 0);
$summary['total_usd'] = (float)($row['usd'] ?? 0);
$summary['total_rub'] = (float)($row['rub'] ?? 0);
}

return $summary;

}

public function recentTransactions($limit = 15){

$limit = max(1,min(100,(int)$limit));
$result = $this->db->query('SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT ' . $limit);
$rows = [];
while($result && ($row = $result->fetchArray(SQLITE3_ASSOC))){
$rows[] = $row;
}
if($result){
$result->finalize();
}
return $rows;

}

}

}

if(!class_exists('BinanceApiClient')){

class BinanceApiClient{

private $apiKey;
private $apiSecret;

public function __construct($apiKey,$apiSecret){
$this->apiKey = trim((string)$apiKey);
$this->apiSecret = trim((string)$apiSecret);
}

private function http($url,$headers = []){

$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
curl_setopt($ch,CURLOPT_TIMEOUT,20);
if(!empty($headers)){
curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
}
$raw = curl_exec($ch);
if($raw === false){
return null;
}
$decoded = json_decode($raw,true);
return is_array($decoded) ? $decoded : null;

}

public function tickerPrice($symbol){
$data = $this->http('https://api.binance.com/api/v3/ticker/price?symbol=' . urlencode((string)$symbol));
if(!is_array($data) || !isset($data['price']) || !is_numeric($data['price'])){
return 0;
}
return (float)$data['price'];
}

private function signedGet($path,$params = []){

if($this->apiKey === '' || $this->apiSecret === ''){
return ['ok'=>false,'error'=>'Binance API غير مضبوط'];
}

$params['timestamp'] = (int)(microtime(true) * 1000);
$params['recvWindow'] = 15000;
$query = http_build_query($params,'','&',PHP_QUERY_RFC3986);
$signature = hash_hmac('sha256',$query,$this->apiSecret);

$url = 'https://api.binance.com' . $path . '?' . $query . '&signature=' . $signature;
$data = $this->http($url,['X-MBX-APIKEY: ' . $this->apiKey]);
if(!is_array($data)){
return ['ok'=>false,'error'=>'فشل الاتصال بـ Binance'];
}
if(isset($data['code']) && (int)$data['code'] < 0){
return ['ok'=>false,'error'=>(string)($data['msg'] ?? 'خطأ Binance')];
}
return ['ok'=>true,'data'=>$data];

}

public function verifyPaymentReference($reference,$order){

$ref = trim((string)$reference);
$amountExpected = round((float)($order['pay_amount'] ?? 0),4);
$coin = strtoupper((string)($order['coin_name'] ?? 'USDT'));
$network = strtoupper((string)($order['network'] ?? ''));
$isInternal = in_array($network,['BINANCE_ID','INTERNAL'],true);

if($ref === ''){
if($isInternal){
return ['ok'=>false,'error'=>'أدخل Binance Order ID'];
}
return ['ok'=>false,'error'=>'أدخل TXID/Hash'];
}

if($isInternal){

$pay = $this->signedGet('/sapi/v1/pay/transactions',['limit'=>100]);
bot_logger_write('binance.pay_api_response', ['ok'=>($pay['ok'] ?? false), 'error'=>($pay['error'] ?? null)]);
if(($pay['ok'] ?? false) === true && is_array($pay['data'])){
$rows = isset($pay['data']['data']) && is_array($pay['data']['data']) ? $pay['data']['data'] : $pay['data'];
bot_logger_write('binance.pay_found_rows', ['count'=>count($rows), 'searching_for'=>$ref]);
foreach($rows as $idx => $item){
bot_logger_write('binance.row_'.$idx, ['keys'=>array_keys($item), 'orderId'=>($item['orderId'] ?? '?'), 'tranId'=>($item['tranId'] ?? '?'), 'transactionId'=>($item['transactionId'] ?? '?')]);
        $candFields = ['orderId','tranId','transactionId','outTradeNo','externalId','tradeId','order_id','tran_id'];
        $candId = '';
        foreach($candFields as $f){
          if(isset($item[$f]) && trim((string)$item[$f]) !== ''){
            $candId = trim((string)$item[$f]);
            break;
          }
        }
        $amt = (float)($item['amount'] ?? ($item['totalAmount'] ?? 0));
        $asset = strtoupper((string)($item['currency'] ?? ($item['asset'] ?? '')));

        $normalize = function($s){
          $s = trim((string)$s);
          return strtolower(preg_replace('/[^a-z0-9]+/i','',$s));
        };

        $match = false;
        if($candId !== ''){
          if(strcasecmp($candId,$ref) === 0){
            $match = true;
            bot_logger_write('binance.exact_match', ['candId'=>$candId, 'ref'=>$ref]);
          }else{
            $normalized_cand = $normalize($candId);
            $normalized_ref = $normalize($ref);
            if($normalized_cand === $normalized_ref){
              $match = true;
              bot_logger_write('binance.normalized_match', ['candId'=>$candId, 'ref'=>$ref, 'norm_cand'=>$normalized_cand, 'norm_ref'=>$normalized_ref]);
            }else{
              bot_logger_write('binance.no_match', ['candId'=>$candId, 'ref'=>$ref, 'norm_cand'=>$normalized_cand, 'norm_ref'=>$normalized_ref]);
            }
          }
        }else{
          bot_logger_write('binance.no_candid_found', ['checked_fields'=>$candFields, 'item_keys'=>array_keys($item)]);
        }

        if($match){
          if($asset !== '' && $asset !== $coin){
            bot_logger_write('binance.verify_mismatch', ['ref'=>$ref,'candidate'=>$candId,'reason'=>'currency_mismatch','item'=>$item,'expected_coin'=>$coin,'found_coin'=>$asset]);
            return ['ok'=>false,'error'=>'Order ID موجود لكن العملة مختلفة'];
          }
          if(abs($amt - $amountExpected) > 0.00011){
            bot_logger_write('binance.verify_mismatch', ['ref'=>$ref,'candidate'=>$candId,'reason'=>'amount_mismatch','item'=>$item,'expected'=>$amountExpected,'found'=>$amt]);
            return ['ok'=>false,'error'=>'Order ID موجود لكن المبلغ لا يطابق الفاتورة'];
          }
          bot_logger_write('binance.verify_match', ['ref'=>$ref,'candidate'=>$candId,'item'=>$item,'amount'=>$amt]);
          return ['ok'=>true,'source'=>'binance_internal','amount'=>$amt,'network'=>$network];
        }
}
}

bot_logger_write('binance.verify_pay_not_found', ['ref'=>$ref,'expected_amount'=>$amountExpected,'coin'=>$coin,'total_rows_checked'=>count($rows),'sample_rows'=>array_slice($rows,0,3)]);

return ['ok'=>false,'error'=>'لم يتم العثور على Order ID في سجلات Binance Pay'];

}

$dep = $this->signedGet('/sapi/v1/capital/deposit/hisrec',['coin'=>$coin,'status'=>1,'limit'=>100]);
if(($dep['ok'] ?? false) === true && is_array($dep['data'])){
foreach($dep['data'] as $item){
$tx = trim((string)($item['txId'] ?? ''));
$amt = (float)($item['amount'] ?? 0);
$net = strtoupper((string)($item['network'] ?? ''));
if($tx !== '' && strcasecmp($tx,$ref) === 0){
if(abs($amt - $amountExpected) > 0.00011){
return ['ok'=>false,'error'=>'تم العثور على TXID لكن المبلغ لا يطابق الفاتورة'];
}
if($network !== '' && $net !== '' && $network !== $net){
return ['ok'=>false,'error'=>'TXID صحيح لكن الشبكة لا تطابق الفاتورة'];
}
return ['ok'=>true,'source'=>'chain','amount'=>$amt,'network'=>$net];
}
}
}

bot_logger_write('binance.verify_deposit_not_found',['ref'=>$ref,'expected_amount'=>$amountExpected,'coin'=>$coin,'sample_deposits'=>array_slice($dep['data'] ?? [],0,6)]);

return ['ok'=>false,'error'=>'لم يتم العثور على TXID في سجلات الإيداع'];

}

public function accountAssets(){
$resp = $this->signedGet('/api/v3/account',[]);
if(($resp['ok'] ?? false) !== true){
return ['ok'=>false,'error'=>$resp['error'] ?? ''];
}

$balances = [];
$totalUsdt = 0.0;
foreach(($resp['data']['balances'] ?? []) as $b){
$free = (float)($b['free'] ?? 0);
$locked = (float)($b['locked'] ?? 0);
$total = $free + $locked;
if($total <= 0){
continue;
}
$asset = (string)($b['asset'] ?? '');
$balances[] = ['asset'=>$asset,'amount'=>$total];
if($asset === 'USDT'){
$totalUsdt += $total;
}
}

return ['ok'=>true,'total_usdt'=>$totalUsdt,'assets'=>$balances];
}

private function normalizeHistoryRows($data){

if(isset($data['rows']) && is_array($data['rows'])){

return $data['rows'];

}

if(isset($data['data']) && is_array($data['data'])){

return $data['data'];

}

return is_array($data) ? $data : [];

}

private function fetchPagedHistory($path,$params = [],$timeKeys = ['time'],$limit = 1000,$maxPages = 25){

$all = [];
$cursor = 0;
$limit = (int)$limit;
if($limit <= 0){
$limit = 100;
}
$limit = min($limit,1000);

for($page = 0; $page < $maxPages; $page++){

$query = $params;
$query['limit'] = $limit;
if($cursor > 0){

$query['startTime'] = $cursor;

}

$resp = $this->signedGet($path,$query);
if(($resp['ok'] ?? false) !== true || !is_array($resp['data'])){

return ['ok'=>false,'error'=>$resp['error'] ?? ''];

}

$rows = $this->normalizeHistoryRows($resp['data']);
if(empty($rows)){

break;

}

foreach($rows as $row){

$all[] = $row;

}

$lastCursor = $cursor;
foreach(array_reverse($rows) as $row){

foreach($timeKeys as $key){

if(isset($row[$key]) && is_numeric($row[$key])){

$lastCursor = max($lastCursor,(int)$row[$key]);

break;

}

}

}

if(count($rows) < $limit){

break;

}

if($lastCursor <= $cursor){

break;

}

$cursor = $lastCursor + 1;

}

return ['ok'=>true,'data'=>$all];

}

public function exportTransactionHistory(){

$deposits = $this->fetchPagedHistory('/sapi/v1/capital/deposit/hisrec',['status'=>1],['insertTime','time','updateTime'],1000,25);
if(($deposits['ok'] ?? false) !== true){

return $deposits;

}

$withdrawals = $this->fetchPagedHistory('/sapi/v1/capital/withdraw/history',[],['applyTime','completeTime','time','updateTime'],1000,25);
if(($withdrawals['ok'] ?? false) !== true){

return $withdrawals;

}

$payTransactions = $this->fetchPagedHistory('/sapi/v1/pay/transactions',[],['transactionTime','createTime','time','updateTime'],100,25);
if(($payTransactions['ok'] ?? false) !== true){

return $payTransactions;

}

return [

'ok' => true,

'generated_at' => time(),

'deposits' => $deposits['data'],

'withdrawals' => $withdrawals['data'],

'pay_transactions' => $payTransactions['data'],

];

}

}

}

function bot_auto_pay_repo(){
static $repo = null;
if($repo === null){
$repo = new BinanceAutoPayRepository();
}
return $repo;
}

function bot_binance_client(){
$s = bot_auto_pay_repo()->getAdminSettings();
return new BinanceApiClient((string)$s['binance_api_key'],(string)$s['binance_api_secret']);
}

function bot_setting_schema(){

return [

'bot' => ['label' => 'اسم مجلد البوت', 'group' => 'config', 'type' => 'string', 'editable' => true],

'chall' => ['label' => 'يوزر القناة الرسمية (بدون @)', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'ch_sub' => ['label' => 'يوزر قناة التفعيلات (بدون @)', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'available' => ['label' => 'يوزر قناة المتوفر (بدون @)', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'hsag' => ['label' => 'يوزر قناة الشروحات (بدون @)', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'ch1' => ['label' => 'رابط تسجيلات الدخول', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'ch2' => ['label' => 'رابط الأرقام الملغية', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'ch3' => ['label' => 'رابط الأرقام المكتملة', 'group' => 'channels', 'type' => 'string', 'editable' => true],

'tafielid' => ['label' => 'آيدي قناة التفعيلات', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'sim' => ['label' => 'آيدي القناة الرسمية', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'sim2' => ['label' => 'آيدي قناة المتوفر', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'sim3' => ['label' => 'آيدي قناة الشروحات', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'PAY' => ['label' => 'آيدي الأرقام المكتملة', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'ess' => ['label' => 'آيدي عمليات شراء الأرقام', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'eer' => ['label' => 'آيدي إشعارات التحويل والبريد', 'group' => 'channels', 'type' => 'int', 'editable' => false],

'namebot' => ['label' => 'اسم البوت', 'group' => 'info', 'type' => 'string', 'editable' => true],

'userbot' => ['label' => 'يوزر البوت', 'group' => 'info', 'type' => 'string', 'editable' => true],

'asar' => ['label' => 'رابط الأسعار', 'group' => 'info', 'type' => 'string', 'editable' => true],

'talemat' => ['label' => 'رابط التعليمات', 'group' => 'info', 'type' => 'string', 'editable' => true],

'startbot' => ['label' => 'تاريخ إنشاء البوت', 'group' => 'technical', 'type' => 'string', 'editable' => false],

'iDadmin' => ['label' => 'آيدي الأدمن الرئيسي', 'group' => 'config', 'type' => 'int', 'editable' => true],

'useradmin' => ['label' => 'يوزر الأدمن', 'group' => 'config', 'type' => 'string', 'editable' => true],

'iDadmin2' => ['label' => 'آيدي المدير الثاني', 'group' => 'config', 'type' => 'int', 'editable' => true],

'botLogFile' => ['label' => 'مسار ملف اللوج', 'group' => 'config', 'type' => 'string', 'editable' => true],

'botToken' => ['label' => 'توكن البوت', 'group' => 'config', 'type' => 'string', 'editable' => true],

'binanceApiKey' => ['label' => 'Binance API Key', 'group' => 'config', 'type' => 'string', 'editable' => true],

'binanceSecret' => ['label' => 'Binance Secret', 'group' => 'config', 'type' => 'string', 'editable' => true],

'binanceTonAddress' => ['label' => 'عنوان استلام TON', 'group' => 'config', 'type' => 'string', 'editable' => true],

'binanceTonNetwork' => ['label' => 'شبكة استلام TON', 'group' => 'config', 'type' => 'string', 'editable' => true],

'assignru' => ['label' => 'نسبة ربح رابط الدعوة', 'group' => 'finance', 'type' => 'float', 'editable' => true],

'usdToRubRate' => ['label' => 'سعر صرف الروبل مقابل الدولار', 'group' => 'finance', 'type' => 'float', 'editable' => true],

'manualPayCommission' => ['label' => 'عمولة التحويل %', 'group' => 'finance', 'type' => 'float', 'editable' => true],

'apiInternalSecret' => ['label' => 'سر API الداخلي', 'group' => 'technical', 'type' => 'string', 'editable' => false],

];

}

function bot_settings_groups(){

return [

'channels' => ['label' => 'القنوات والاشتراك الإجباري', 'keys' => ['chall','ch_sub','available','hsag','ch1','ch2','ch3','PAY','ess','eer']],

'info' => ['label' => 'معلومات البوت', 'keys' => ['namebot','userbot','asar','talemat']],

'finance' => ['label' => 'الإعدادات المالية', 'keys' => ['assignru','manualPayCommission']],

'config' => ['label' => 'إعدادات الكونفيق', 'keys' => ['bot','iDadmin','useradmin','iDadmin2','botLogFile','botToken']],

];

}

function bot_default_settings(){

return [

'bot' => (string)($GLOBALS['bot'] ?? 'BOT'),

'chall' => (string)($GLOBALS['chall'] ?? ''),

'ch_sub' => (string)($GLOBALS['ch_sub'] ?? ''),

'available' => (string)($GLOBALS['available'] ?? ''),

'hsag' => (string)($GLOBALS['hsag'] ?? ''),

'ch1' => (string)($GLOBALS['ch1'] ?? ''),

'ch2' => (string)($GLOBALS['ch2'] ?? ''),

'ch3' => (string)($GLOBALS['ch3'] ?? ''),

'namebot' => (string)($GLOBALS['namebot'] ?? ''),

'userbot' => (string)($GLOBALS['userbot'] ?? ''),

'asar' => (string)($GLOBALS['asar'] ?? ''),

'talemat' => (string)($GLOBALS['talemat'] ?? ''),

'startbot' => (string)($GLOBALS['startbot'] ?? ''),

'iDadmin' => (string)($GLOBALS['iDadmin'] ?? 0),

'useradmin' => (string)($GLOBALS['useradmin'] ?? ''),

'iDadmin2' => (string)($GLOBALS['iDadmin2'] ?? 0),

'botLogFile' => (string)($GLOBALS['botLogFile'] ?? (__DIR__ . '/bot-runtime.log')),

'botToken' => (string)($GLOBALS['botToken'] ?? ''),

'binanceApiKey' => (string)($GLOBALS['binanceApiKey'] ?? ''),

'binanceSecret' => (string)($GLOBALS['binanceSecret'] ?? ''),

'binanceTonAddress' => (string)($GLOBALS['binanceTonAddress'] ?? ''),

'binanceTonNetwork' => (string)($GLOBALS['binanceTonNetwork'] ?? 'TON'),

'tafielid' => (string)($GLOBALS['tafielid'] ?? 0),

'sim' => (string)($GLOBALS['sim'] ?? 0),

'sim2' => (string)($GLOBALS['sim2'] ?? 0),

'sim3' => (string)($GLOBALS['sim3'] ?? 0),

'PAY' => (string)($GLOBALS['PAY'] ?? 0),

'ess' => (string)($GLOBALS['ess'] ?? 0),

'eer' => (string)($GLOBALS['eer'] ?? 0),

'assignru' => (string)($GLOBALS['assignru'] ?? 0),

'usdToRubRate' => (string)($GLOBALS['usdToRubRate'] ?? 60),

'manualPayCommission' => (string)($GLOBALS['manualPayCommission'] ?? 5),

'apiInternalSecret' => (string)($GLOBALS['apiInternalSecret'] ?? ''),

];

}

function bot_config_locked_keys(){

return [
'bot',
'iDadmin',
'useradmin',
'iDadmin2',
'userbot',
'botLogFile',
'botToken',
'apiInternalSecret',
];

}

function bot_is_config_locked_key($key){

return in_array((string)$key, bot_config_locked_keys(), true);

}

function bot_db_manager(){

static $manager = null;

if($manager === null){

$manager = new BotDatabaseManager(__DIR__ . '/bot_settings.db');

}

return $manager;

}

function bot_setting_meta($key){

$schema = bot_setting_schema();

return $schema[$key] ?? null;

}

function bot_setting_type($key){

$meta = bot_setting_meta($key);

return is_array($meta) && isset($meta['type']) ? (string)$meta['type'] : 'string';

}

function bot_setting_cast($key,$value){

$type = bot_setting_type($key);

if($type === 'int'){

return (int)$value;

}

if($type === 'float'){

return (float)$value;

}

return (string)$value;

}

function bot_setting_normalize($key,$value,&$error = null){

$type = bot_setting_type($key);

$value = trim((string)$value);

if($type === 'int'){

if(!preg_match('/^-?\d+$/',$value)){

$error = 'يجب إدخال رقم صحيح';

return null;

}

return (string)(int)$value;

}

if($type === 'float'){

if(!is_numeric($value)){

$error = 'يجب إدخال رقم صالح';

return null;

}

return $value;

}

return $value;

}

function bot_channel_username_clean($value){

$value = trim((string)$value);

$value = preg_replace('#^https?://t\.me/#i','',$value);

$value = preg_replace('#^t\.me/#i','',$value);

$value = ltrim((string)$value,'@');

$value = preg_replace('#/.*$#','',$value);

$value = trim((string)$value);

return $value;

}

function bot_verify_channel_and_admin($username,&$error = null){

$username = bot_channel_username_clean($username);

if($username === '' || !preg_match('/^[A-Za-z0-9_]{4,}$/',$username)){
$error = 'أرسل يوزر قناة صحيح بدون @ مثل: mychannel';
return null;
}

if(!function_exists('bot')){
$error = 'تعذر التحقق الآن، حاول مرة أخرى.';
return null;
}

$chat = bot('getChat',['chat_id'=>'@' . $username]);

if(!is_object($chat) || empty($chat->ok) || !isset($chat->result->id)){
$error = 'القناة غير موجودة أو اليوزر غير صحيح.';
return null;
}

$chatId = (int)($chat->result->id ?? 0);

if($chatId === 0){
$error = 'تعذر قراءة بيانات القناة.';
return null;
}

$me = bot('getMe',[]);

if(!is_object($me) || empty($me->ok) || !isset($me->result->id)){
$error = 'تعذر التحقق من حساب البوت.';
return null;
}

$botId = (int)($me->result->id ?? 0);

if($botId <= 0){
$error = 'تعذر التحقق من حساب البوت.';
return null;
}

$member = bot('getChatMember',['chat_id'=>$chatId,'user_id'=>$botId]);

$status = is_object($member) && !empty($member->ok) ? (string)($member->result->status ?? '') : '';

if(!in_array($status,['administrator','creator'],true)){
$error = 'البوت ليس مشرفًا في هذه القناة. أضفه كمشرف أولاً.';
return null;
}

return ['username'=>$username,'id'=>(string)$chatId];

}

function bot_bootstrap_settings(){

$defaults = bot_default_settings();
$lockedKeys = bot_config_locked_keys();
$dbDefaults = array_diff_key($defaults, array_flip($lockedKeys));

$manager = bot_db_manager();

$manager->seedSettings($dbDefaults);

$settings = $manager->getSettings($dbDefaults);

foreach($settings as $key => $value){

$GLOBALS[$key] = bot_setting_cast($key,$value);

}

// Locked keys are read from config.php only (never from DB).
foreach($lockedKeys as $lockedKey){
$manager->deleteSetting($lockedKey);
$cfgVal = $defaults[$lockedKey] ?? '';
$settings[$lockedKey] = $cfgVal;
$GLOBALS[$lockedKey] = bot_setting_cast($lockedKey,$cfgVal);
}

$GLOBALS['bot_settings'] = $settings;

return $settings;

}

if(!function_exists('bot_json_path_key')){
function bot_json_path_key($path){

return preg_replace('/[^a-zA-Z0-9_\.\-\/]/','_', (string)$path);

}
}

if(!function_exists('bot_json_get')){
function bot_json_get($path,$default=[]){

$raw = bot_db_manager()->getDocument($path, null);

if($raw === null){

return $default;

}

$decoded = json_decode($raw, true);

return is_array($decoded) ? $decoded : $default;

}
}

if(!function_exists('bot_json_set')){
function bot_json_set($path,$value){

bot_db_manager()->setDocument($path, json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

}
}

if(!function_exists('bot_json_delete')){
function bot_json_delete($path){

bot_db_manager()->deleteDocument($path);

}
}

if(!function_exists('bot_store_get')){
function bot_store_get($path,$default=''){
return bot_db_manager()->getDocument((string)$path,(string)$default);

}
}

if(!function_exists('bot_backfill_users_from_documents')){
function bot_backfill_users_from_documents(){
bot_db_manager()->backfillUsersFromDocuments();
}
}

if(!function_exists('bot_record_balance_transfer')){
function bot_record_balance_transfer($transferCode,$referenceId,$fromUserId,$fromEmail,$fromUsername,$toUserId,$toEmail,$toUsername,$amountSentRub,$amountReceivedRub,$feeRub,$status='pending',$meta=[]){
return bot_db_manager()->recordBalanceTransfer($transferCode,$referenceId,$fromUserId,$fromEmail,$fromUsername,$toUserId,$toEmail,$toUsername,$amountSentRub,$amountReceivedRub,$feeRub,$status,$meta);
}
}

if(!function_exists('bot_mark_balance_transfer_claimed')){
function bot_mark_balance_transfer_claimed($transferCode,$meta=[]){
return bot_db_manager()->markBalanceTransferClaimed($transferCode,$meta);
}
}

if(!function_exists('bot_store_set')){
function bot_store_set($path,$content,$append=false){

$path = (string)$path;
$content = (string)$content;
$before = bot_db_manager()->getDocument($path,null);

if($append){

$existing = is_string($before) ? $before : '';
$content = (string)$existing . $content;

}

bot_db_manager()->setDocument($path,$content);

if(preg_match('#^EMILS/([^/]+)/(points|rubles)\.txt$#',$path,$match)){
$email = strtolower(trim((string)($match[1] ?? '')));
$kind = (string)($match[2] ?? 'points');
$oldValue = is_numeric($before) ? (float)$before : 0.0;
$newValue = is_numeric($content) ? (float)$content : $oldValue;
if($email !== '' && $oldValue !== $newValue){
$profile = bot_db_manager()->getUserByEmail($email);
$userId = is_array($profile) ? (int)($profile['id'] ?? 0) : 0;
$username = is_array($profile) ? (string)($profile['username'] ?? '') : '';
bot_db_manager()->recordUserMovement($userId,$email,$username,'balance_change',$kind . '_updated',$newValue - $oldValue,0.0,$newValue,0.0,$path,'',['old'=>$oldValue,'new'=>$newValue,'kind'=>$kind]);
}
}

}
}

if(!function_exists('bot_sync_user_profile')){
function bot_sync_user_profile($userId,$username='',$firstName='',$lastName='',$email='',$password='',$loginAt=null,$profilePayload=[]){

bot_db_manager()->upsertUserProfile((int)$userId,(string)$username,(string)$firstName,(string)$lastName,(string)$email,(string)$password,$loginAt,$profilePayload);

}
}

if(!function_exists('bot_store_delete')){
function bot_store_delete($path){

bot_db_manager()->deleteDocument((string)$path);

}
}

function bot_setting_get($key,$default=''){

if(bot_is_config_locked_key($key)){
if(array_key_exists((string)$key,$GLOBALS)){
return bot_setting_cast($key,$GLOBALS[(string)$key]);
}
return bot_setting_cast($key,$default);
}

return bot_setting_cast($key, bot_db_manager()->getSetting($key,$default));

}

function bot_setting_set($key,$value){

if(bot_is_config_locked_key($key)){
return ['ok' => false, 'error' => 'هذا الإعداد مقفل من config.php. عدّل الملف مباشرة.'];
}

$channelMap = [
'chall' => ['id_key'=>'sim','username_key'=>'chall'],
'ch_sub' => ['id_key'=>'tafielid','username_key'=>'ch_sub'],
'available' => ['id_key'=>'sim2','username_key'=>'available'],
'hsag' => ['id_key'=>'sim3','username_key'=>'hsag'],
];

if(isset($channelMap[$key]) && is_array($channelMap[$key])){
$error = null;
$resolved = bot_verify_channel_and_admin((string)$value,$error);
if(!is_array($resolved)){
return ['ok' => false, 'error' => (string)($error ?? 'تعذر حفظ القناة')];
}

$username = (string)($resolved['username'] ?? '');
$channelId = (string)($resolved['id'] ?? '0');
$idKey = (string)($channelMap[$key]['id_key'] ?? $key);
$usernameKey = $channelMap[$key]['username_key'] ?? null;

if(is_string($usernameKey) && $usernameKey !== ''){
bot_db_manager()->setSetting($usernameKey,$username,'string');
}
bot_db_manager()->setSetting($idKey,$channelId,'int');

$GLOBALS[$key] = (bot_setting_type($key) === 'int') ? (int)$channelId : $username;
$GLOBALS[$idKey] = (int)$channelId;
if(is_string($usernameKey) && $usernameKey !== ''){
$GLOBALS[$usernameKey] = $username;
}

$synced = [$idKey => (int)$channelId];
if(is_string($usernameKey) && $usernameKey !== ''){
$synced[$usernameKey] = $username;
}

return ['ok' => true, 'value' => $username, 'synced' => $synced];
}

$error = null;

$normalized = bot_setting_normalize($key,$value,$error);

if($normalized === null && $error !== null){

return ['ok' => false, 'error' => $error];

}

$type = bot_setting_type($key);

bot_db_manager()->setSetting($key,$normalized,$type);

$GLOBALS[$key] = bot_setting_cast($key,$normalized);

return ['ok' => true, 'value' => $GLOBALS[$key]];

}

function bot_setting_label($key){

$meta = bot_setting_meta($key);

return is_array($meta) && isset($meta['label']) ? (string)$meta['label'] : $key;

}

function bot_setting_group($key){

$meta = bot_setting_meta($key);

return is_array($meta) && isset($meta['group']) ? (string)$meta['group'] : '';

}

function bot_setting_editable($key){

$meta = bot_setting_meta($key);

return is_array($meta) ? !empty($meta['editable']) : false;

}

function bot_settings_group_title($group){

$groups = bot_settings_groups();

return isset($groups[$group]['label']) ? (string)$groups[$group]['label'] : '';

}

function bot_settings_group_keys($group){

$groups = bot_settings_groups();

return isset($groups[$group]['keys']) && is_array($groups[$group]['keys']) ? $groups[$group]['keys'] : [];

}

function bot_settings_main_keyboard(){

$groups = bot_settings_groups();

return [

'inline_keyboard' => [


[['text' => '📡 ' . $groups['channels']['label'], 'callback_data' => 'settings-channels']],

[['text' => '🧩 ' . $groups['info']['label'], 'callback_data' => 'settings-info']],

[['text' => '💰 ' . $groups['finance']['label'], 'callback_data' => 'settings-finance']],

[['text' => '🛠️ ' . $groups['config']['label'], 'callback_data' => 'settings-config']],

[['text' => '↩️ رجوع للقائمة', 'callback_data' => 'c']],

]

];

}

function bot_settings_group_keyboard($group){

$rows = [];

$pending = [];

foreach(bot_settings_group_keys($group) as $key){

if(!bot_setting_editable($key)){

continue;

}

$pending[] = [

'text' => '⚙️ ' . bot_setting_label($key),

'callback_data' => 'settings-edit-' . $key,

];

if(count($pending) === 2){

$rows[] = $pending;

$pending = [];

}

}

if(!empty($pending)){

$rows[] = $pending;

}

if((string)$group === 'finance'){

$rows[] = [['text' => '💳 إعدادات طريقة الدفع التلقائية 《باينانس》', 'callback_data' => 'autopay-settings-main']];

$rows[] = [['text' => '💱 إدارة العملات في البوت', 'callback_data' => 'curm:admin']];

}

$rows[] = [['text' => '↩️ رجوع للأقسام', 'callback_data' => 'settings-main']];

return ['inline_keyboard' => $rows];

}

function bot_setting_preview_value($key){

$value = trim((string)bot_db_manager()->getSetting($key, ''));

if($value === ''){

return 'فارغ';

}

if(function_exists('mb_strlen') && mb_strlen($value) > 40){

return mb_substr($value, 0, 40) . '...';

}

if(strlen($value) > 40){

return substr($value, 0, 40) . '...';

}

return $value;

}


function bot_setting_prompt_text($key){

$label = bot_setting_label($key);
$current = bot_setting_preview_value($key);
$statusIcon = bot_setting_is_configured($key) ? '✅' : '❌';
$statusText = bot_setting_is_configured($key) ? 'مضبط' : 'غير مضبط';
$hint = 'أرسل القيمة الجديدة الآن.';

if(bot_setting_type($key) === 'int'){

$hint = 'أرسل رقمًا صحيحًا فقط.';

}elseif(bot_setting_type($key) === 'float'){

$hint = 'أرسل رقمًا صالحًا مثل 0.3.';

}

if(in_array((string)$key,['chall','ch_sub','available','hsag'],true)){

$hint = 'أرسل يوزر القناة فقط بدون @، وسيتم التحقق تلقائيًا أن البوت مشرف ثم الحفظ.';

}

return "<b>تعديل: {$label}</b>\nالحالة: {$statusIcon} {$statusText}\nالقيمة الحالية: <code>{$current}</code>\n{$hint}";

}

function bot_setting_is_configured($key){

$raw = trim((string)bot_db_manager()->getSetting($key, ''));
$type = bot_setting_type($key);

if($type === 'int'){
if($raw === '' || !preg_match('/^-?\d+$/',$raw)){
return false;
}
return ((int)$raw) !== 0;
}

if($type === 'float'){
return $raw !== '' && is_numeric($raw);
}

return $raw !== '';

}

function bot_setting_status_line($key){

$icon = bot_setting_is_configured($key) ? '✅' : '❌';
$state = bot_setting_is_configured($key) ? 'مضبط' : 'غير مضبط';
return "{$icon} " . bot_setting_label($key) . " : {$state}";

}

function bot_settings_group_status_text($group){

$lines = [];
foreach(bot_settings_group_keys($group) as $key){
if(!bot_setting_editable($key)){
continue;
}
$lines[] = bot_setting_status_line($key);
}
if(empty($lines)){
return '⚪ لا توجد إعدادات قابلة للتعديل في هذا القسم.';
}
return implode("\n",$lines);

}

function bot_settings_group_status_summary($group){

$editable = 0;
$configured = 0;
foreach(bot_settings_group_keys($group) as $key){
if(!bot_setting_editable($key)){
continue;
}
$editable++;
if(bot_setting_is_configured($key)){
$configured++;
}
}
return "{$configured}/{$editable}";

}
