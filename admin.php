<?php
include("name.php");

$actorIdInt = (int)($actor_id ?? 0);
$chatIdInt = (int)($chat_id ?? 0);
$actorUsername = ltrim(strtolower(trim((string)($user ?? ''))),'@');
$primaryAdminUsername = ltrim(strtolower(trim((string)($useradmin ?? ''))),'@');
$is_primary_admin = false;
if($actorIdInt > 0 && $chatIdInt > 0 && $actorIdInt === $chatIdInt){
if($actorIdInt === (int)$iDadmin){
$is_primary_admin = ($primaryAdminUsername === '' || $actorUsername === $primaryAdminUsername);
}elseif($actorIdInt === (int)$iDadmin2){
$is_primary_admin = true;
}
}
$adminActionHandled = false;

$settingsEditContextFile = "data/id/$id/settings_edit.txt";
$settingsPanelMessageIdFile = "data/id/$id/settings_msgid.txt";
$settingsEditKey = trim((string)get_file($settingsEditContextFile,''));
$settingsPanelMessageId = (int)get_file($settingsPanelMessageIdFile,'0');
$autoPayStepFile = "data/id/$id/autopay_step.txt";
$autoPayStep = trim((string)get_file($autoPayStepFile,''));
$autoPayMethodDraftFile = "data/id/$id/autopay_method_draft.json";

if(!function_exists('autopay_admin_mask')){
function autopay_admin_mask($value){
$value = trim((string)$value);
if($value === ''){
return 'غير مضبوط';
}
if(strlen($value) <= 8){
return str_repeat('*',strlen($value));
}
return substr($value,0,4) . str_repeat('*',max(3,strlen($value) - 8)) . substr($value,-4);
}
}

if(!function_exists('autopay_admin_settings_text')){
function autopay_admin_settings_text(){
$settings = bot_auto_pay_repo()->getAdminSettings();
return "<b>⚙️ إدارة العملات المشفرة</b>\n\n"
."• API Key: <code>" . autopay_admin_mask($settings['binance_api_key']) . "</code>\n"
."• API Secret: <code>" . autopay_admin_mask($settings['binance_api_secret']) . "</code>\n"
."• Binance Pay ID: <code>" . (($settings['binance_pay_id'] !== '') ? $settings['binance_pay_id'] : 'غير مضبوط') . "</code>\n"
."• USD/RUB: <code>" . number_format((float)$settings['usd_to_rub_rate'],4,'.','') . "</code>\n"
."• Min Deposit: <code>" . number_format((float)$settings['min_deposit'],2,'.','') . "</code>\n"
."• Max Deposit: <code>" . number_format((float)$settings['max_deposit'],2,'.','') . "</code>\n"
."• Security Fee %: <code>" . number_format((float)$settings['security_fee_pct'],2,'.','') . "</code>\n";
}
}

if(!function_exists('autopay_admin_settings_keyboard')){
function autopay_admin_settings_keyboard(){
return ['inline_keyboard'=>[
[['text'=>'🔑 تعديل API Key','callback_data'=>'autopay-set-key'],['text'=>'🔒 تعديل API Secret','callback_data'=>'autopay-set-secret']],
[['text'=>'🆔 تعديل Binance Pay ID','callback_data'=>'autopay-set-payid']],
[['text'=>'💱 تعديل USD/RUB','callback_data'=>'autopay-set-rate']],
[['text'=>'📉 تعديل الحد الأدنى','callback_data'=>'autopay-set-min'],['text'=>'📈 تعديل الحد الأعلى','callback_data'=>'autopay-set-max']],
[['text'=>'🛡️ تعديل عمولة الأمان','callback_data'=>'autopay-set-fee']],
[['text'=>'💳 إدارة العملات المشفرة','callback_data'=>'autopay-methods']],
[['text'=>'🧾 عرض البلاك لست','callback_data'=>'autopay-blacklist']],
[['text'=>'📊 إحصائيات Binance','callback_data'=>'autopay-stats']],
[['text'=>'📥 سحب سجلات باينانس','callback_data'=>'autopay-export-history']],
[['text'=>'↩️ رجوع للإعدادات المالية','callback_data'=>'settings-finance']],
]];
}
}

if(!function_exists('autopay_admin_history_text')){
function autopay_admin_history_text($title,$rows,$timeKeys){
$lines = ["<b>{$title}</b>"];
if(empty($rows)){
$lines[] = 'لا توجد سجلات.';
return implode("\n",$lines);
}
foreach($rows as $row){
$timeValue = '';
foreach($timeKeys as $timeKey){
if(isset($row[$timeKey]) && is_numeric($row[$timeKey])){
$timeValue = date('Y-m-d H:i:s',(int)$row[$timeKey]);
break;
}
}
$lines[] = '• ' . ($timeValue !== '' ? $timeValue . ' | ' : '') . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
return implode("\n",$lines);
}
}

if(!function_exists('autopay_admin_methods_text')){
function autopay_admin_methods_text(){
$rows = bot_auto_pay_repo()->listPaymentMethods(false);
if(empty($rows)){
return "<b>💳 طرق الدفع</b>\n\nلا توجد طرق دفع مضافة حتى الآن.";
}
$out = ["<b>💳 طرق الدفع</b>"];
foreach($rows as $r){
$out[] = "• #" . (int)$r['id'] . " - " . (string)$r['coin_name'] . " / " . (string)$r['network'] . " / " . (!empty($r['status']) ? 'مفعل' : 'معطل');
$out[] = "  <code>" . (string)$r['wallet_address'] . "</code>";
}
return implode("\n",$out);
}
}

if(!function_exists('autopay_admin_methods_keyboard')){
function autopay_admin_methods_keyboard(){
$rows = bot_auto_pay_repo()->listPaymentMethods(false);
$kb = [];
$kb[] = [['text'=>'➕ إضافة عملة مشفرة للبوت','callback_data'=>'autopay-method-add']];
foreach($rows as $r){
$id = (int)$r['id'];
$toggleText = !empty($r['status']) ? '⛔ تعطيل #' . $id : '✅ تفعيل #' . $id;
$kb[] = [
['text'=>'✏️ تعديل #' . $id,'callback_data'=>'autopay-method-edit-' . $id],
['text'=>$toggleText,'callback_data'=>'autopay-method-toggle-' . $id],
['text'=>'🗑️ حذف #' . $id,'callback_data'=>'autopay-method-del-' . $id],
];
}
$kb[] = [['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']];
return ['inline_keyboard'=>$kb];
}
}

if(!function_exists('admin_api_balance_http_get')){
function admin_api_balance_http_get($url,$headers=[]){
$url = trim((string)$url);
if($url === ''){
return ['ok'=>false,'body'=>'','json'=>null,'error'=>'empty_url'];
}

$headers = is_array($headers) ? $headers : [];
$body = '';
$err = '';

if(function_exists('curl_init')){
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
if(!empty($headers)){
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}
$body = (string)curl_exec($ch);
if(curl_errno($ch)){
$err = (string)curl_error($ch);
}
curl_close($ch);
}else{
$headersText = '';
if(!empty($headers)){
$headersText = implode("\r\n",$headers) . "\r\n";
}
$ctx = stream_context_create([
'http'=>[
'method'=>'GET',
'timeout'=>6,
'ignore_errors'=>true,
'header'=>$headersText,
],
'https'=>[
'method'=>'GET',
'timeout'=>6,
'ignore_errors'=>true,
'header'=>$headersText,
],
]);
$raw = @file_get_contents($url,false,$ctx);
if($raw === false){
$err = 'request_failed';
$body = '';
}else{
$body = (string)$raw;
}
}

$json = json_decode($body,true);
if(!is_array($json)){
$json = null;
}

return [
'ok' => ($body !== '' && $err === ''),
'body' => $body,
'json' => $json,
'error' => $err,
];
}
}

if(!function_exists('admin_api_extract_balance')){
function admin_api_extract_balance($body,$json=null){
$body = trim((string)$body);
if($json === null){
$decoded = json_decode($body,true);
$json = is_array($decoded) ? $decoded : null;
}

$parseNum = function($value){
if(is_numeric($value)){
return (float)$value;
}
$v = trim((string)$value);
if($v === ''){
return null;
}
$v = str_replace(',', '.', $v);
if(preg_match('/-?[0-9]+(?:\.[0-9]+)?/', $v, $m)){
return (float)$m[0];
}
return null;
};

if(is_array($json)){
$paths = [
['balance'],
['Balance'],
['amount'],
['credit'],
['funds'],
['wallet'],
['data','balance'],
['data','Balance'],
['data','amount'],
['user','balance'],
['result','balance'],
['response','balance'],
];

foreach($paths as $path){
$cursor = $json;
$found = true;
foreach($path as $k){
if(!is_array($cursor) || !array_key_exists($k,$cursor)){
$found = false;
break;
}
$cursor = $cursor[$k];
}
if(!$found){
continue;
}
$num = $parseNum($cursor);
if($num !== null){
$currency = '';
foreach([
['currency'],['Currency'],['currency_code'],['data','currency'],['user','currency']
] as $cp){
$c = $json;
$ok = true;
foreach($cp as $k2){
if(!is_array($c) || !array_key_exists($k2,$c)){
$ok = false;
break;
}
$c = $c[$k2];
}
if($ok && trim((string)$c) !== ''){
$currency = trim((string)$c);
break;
}
}
return ['amount'=>$num,'currency'=>$currency];
}
}
}

if(preg_match('/ACCESS_BALANCE\s*[:=]\s*([0-9]+(?:[\.,][0-9]+)?)/i',$body,$m)){
return ['amount'=>(float)str_replace(',','.',$m[1]),'currency'=>''];
}
if(preg_match('/"balance"\s*:\s*"?([0-9]+(?:[\.,][0-9]+)?)/i',$body,$m)){
return ['amount'=>(float)str_replace(',','.',$m[1]),'currency'=>''];
}

return null;
}
}

if(!function_exists('admin_api_fetch_site_balance')){
function admin_api_fetch_site_balance($code,$cfg=[]){
$code = trim((string)$code);
$apiKey = trim((string)($cfg['api_key'] ?? ''));
$username = trim((string)($cfg['Username'] ?? ''));
$password = trim((string)($cfg['Password'] ?? ''));

if($apiKey === ''){
return ['ok'=>false,'message'=>'لا يوجد توكن'];
}

$requests = [];

if($code === '5sim'){
$requests[] = ['url'=>'https://5sim.biz/v1/user/profile','headers'=>['Authorization: Bearer ' . $apiKey,'Accept: application/json']];
}elseif($code === 'alisms'){
$requests[] = ['url'=>'https://api.alisms.org/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === 'man'){
$requests[] = ['url'=>'http://api.sms-man.ru/stubs/handler_api.php?action=getBalance&api_key=' . urlencode($apiKey)];
}elseif($code === 'vak'){
$requests[] = ['url'=>'https://vak-sms.com/api/getBalance/?apiKey=' . urlencode($apiKey)];
}elseif($code === 'activate'){
$requests[] = ['url'=>'https://sms-activate.io/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === 'pvapins'){
$requests[] = ['url'=>'http://api.PVAPins.com/user/api/get_balance.php?customer=' . urlencode($apiKey)];
}elseif($code === 'botsms'){
$requests[] = ['url'=>'https://numbros.shop/jj/tele/getbalance.php?key=' . urlencode($apiKey)];
}elseif($code === 'onlinesim'){
$requests[] = ['url'=>'https://onlinesim.io/api/getBalance.php?apikey=' . urlencode($apiKey)];
}elseif($code === 'supersmstech'){
$requests[] = ['url'=>'https://www.supersmstech.com/api/getbalance?secret_key=' . urlencode($apiKey)];
}elseif($code === 'viotp'){
$requests[] = ['url'=>'https://api.viotp.com/users/balance?token=' . urlencode($apiKey)];
}elseif($code === 'simsms'){
$requests[] = ['url'=>'http://simsms.org/priemnik.php?metod=get_balance&apikey=' . urlencode($apiKey)];
}elseif($code === 'grizzly'){
$requests[] = ['url'=>'https://api.grizzlysms.com/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === 'smscode'){
$requests[] = ['url'=>'https://sms-code.ru/api.php?api_key=' . urlencode($apiKey) . '&method=balance'];
}elseif($code === 'tiger'){
$requests[] = ['url'=>'https://tiger-sms.com/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === '2ndline'){
$requests[] = ['url'=>'https://2ndline.io/apiv1/user?apikey=' . urlencode($apiKey)];
}elseif($code === 'store'){
$requests[] = ['url'=>'https://receivesms.store/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === 'fastpva'){
$requests[] = ['url'=>'http://api.fastpva.com/pvapublic/getBalance?apikey=' . urlencode($apiKey)];
}elseif($code === 'dropsms'){
$requests[] = ['url'=>'https://api.dropsms.cc/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === '24sms7'){
$requests[] = ['url'=>'https://24sms7.com/stubs/handler_api.php?api_key=' . urlencode($apiKey) . '&action=getBalance'];
}elseif($code === 'sellotp'){
$requests[] = ['url'=>'https://api.sellotp.com/users/balance?token=' . urlencode($apiKey) . '&lang=en'];
}elseif($code === 'duraincloud'){
if($username !== '' && $password !== ''){
$requests[] = ['url'=>'http://api.durianrcs.com/out/ext_api/getScore?name=' . urlencode($username) . '&pwd=' . urlencode($password) . '&ApiKey=' . urlencode($apiKey)];
}
}

if(empty($requests)){
return ['ok'=>false,'message'=>'مزود غير مدعوم'];
}

foreach($requests as $rq){
$res = admin_api_balance_http_get((string)($rq['url'] ?? ''),(array)($rq['headers'] ?? []));
$parsed = admin_api_extract_balance((string)($res['body'] ?? ''),$res['json'] ?? null);
if(is_array($parsed)){
$currency = trim((string)($parsed['currency'] ?? ''));
$currencyText = $currency !== '' ? (' ' . $currency) : '';
return [
'ok'=>true,
'amount'=>(float)($parsed['amount'] ?? 0),
'currency'=>$currency,
'text'=>number_format((float)($parsed['amount'] ?? 0),2,'.','') . $currencyText,
];
}
}

return ['ok'=>false,'message'=>'تعذر جلب الرصيد'];
}
}

if(!function_exists('admin_api_sites_balance_lines')){
function admin_api_sites_balance_lines($apps){
$apps = is_array($apps) ? $apps : [];
$lines = [];
$total = 0;

$providers = [
'5sim'=>'5sim.biz',
'alisms'=>'alisms.org',
'man'=>'sms-man.ru',
'vak'=>'vak-sms.com',
'activate'=>'sms-activate.io',
'pvapins'=>'pvapins.com',
'botsms'=>'alnwerah.sms',
'onlinesim'=>'onlinesim.io',
'supersmstech'=>'supersmstech.com',
'viotp'=>'viotp.com',
'simsms'=>'simsms.org',
'grizzly'=>'grizzlysms.com',
'smscode'=>'sms-code.ru',
'tiger'=>'tiger-sms.com',
'2ndline'=>'2ndline.io',
'store'=>'receivesms.store',
'fastpva'=>'sms.fastpva.com',
'dropsms'=>'dropsms.ru',
'24sms7'=>'24sms7.com',
'sellotp'=>'sellotp.com',
'duraincloud'=>'mm.durianrcs.com',
];

foreach($providers as $code => $label){
$cfg = (isset($apps[$code]) && is_array($apps[$code])) ? $apps[$code] : [];
$apiKey = trim((string)($cfg['api_key'] ?? ''));
if($apiKey === ''){
continue;
}
$total++;
$fetch = admin_api_fetch_site_balance($code,$cfg);
if(!empty($fetch['ok'])){
$lines[] = '✅ ' . $label . ' : ' . (string)($fetch['text'] ?? '0.00');
}else{
$lines[] = '⚠️ ' . $label . ' : ' . (string)($fetch['message'] ?? 'تعذر جلب الرصيد');
}
}

foreach($apps as $code => $cfg){
$code = trim((string)$code);
if($code === '' || isset($providers[$code])){
continue;
}
if(!is_array($cfg)){
continue;
}
$apiKey = trim((string)($cfg['api_key'] ?? ''));
if($apiKey === ''){
continue;
}
$total++;
$lines[] = '⚠️ ' . $code . ' : مزود غير مدعوم حالياً لجلب الرصيد';
}

$smmSites = [
'smmtigers' => 'smmtigers.com',
'smmtarget' => 'smmtarget.net',
'smmxstar' => 'smmxstar.com',
];
foreach($smmSites as $key => $domain){
$token = trim((string)bot_store_get('data/api/' . $key . '.txt',''));
if($token === ''){
continue;
}
$total++;
$res = admin_api_balance_http_get('https://' . $domain . '/api/v2?action=balance&key=' . urlencode($token));
$parsed = admin_api_extract_balance((string)($res['body'] ?? ''),$res['json'] ?? null);
if(is_array($parsed)){
$cur = trim((string)($parsed['currency'] ?? ''));
$curText = $cur !== '' ? (' ' . $cur) : '';
$lines[] = '✅ ' . $domain . ' : ' . number_format((float)($parsed['amount'] ?? 0),2,'.','') . $curText;
}else{
$lines[] = '⚠️ ' . $domain . ' : تعذر جلب الرصيد';
}
}

if(empty($lines)){
return [
'count' => 0,
'lines' => ['لا يوجد أي موقع مضاف له API حالياً.']
];
}

return [
'count' => $total,
'lines' => $lines,
];
}
}

if($is_primary_admin && $autoPayStep !== '' && ($data ?? '') === '' && $message !== null && $text !== '' && $text !== '/start'){

$repo = bot_auto_pay_repo();
$current = $repo->getAdminSettings();
$showMethodsAfterSave = false;

if($autoPayStep === 'set_key'){
$repo->saveAdminSettings(['binance_api_key'=>$text]);
}elseif($autoPayStep === 'set_secret'){
$repo->saveAdminSettings(['binance_api_secret'=>$text]);
}elseif($autoPayStep === 'set_payid'){
$repo->saveAdminSettings(['binance_pay_id'=>$text]);
}elseif($autoPayStep === 'set_rate'){
if(!is_numeric($text) || (float)$text <= 0){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل رقم صالح أكبر من صفر']);
$adminActionHandled = true;
return;
}
$repo->saveAdminSettings(['usd_to_rub_rate'=>(float)$text]);
}elseif($autoPayStep === 'set_min'){
if(!is_numeric($text) || (float)$text <= 0){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل رقم صالح أكبر من صفر']);
$adminActionHandled = true;
return;
}
$repo->saveAdminSettings(['min_deposit'=>(float)$text,'max_deposit'=>$current['max_deposit']]);
}elseif($autoPayStep === 'set_max'){
if(!is_numeric($text) || (float)$text <= 0){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل رقم صالح أكبر من صفر']);
$adminActionHandled = true;
return;
}
$repo->saveAdminSettings(['max_deposit'=>(float)$text,'min_deposit'=>$current['min_deposit']]);
}elseif($autoPayStep === 'set_fee'){
if(!is_numeric($text) || (float)$text < 0){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل نسبة صحيحة']);
$adminActionHandled = true;
return;
}
$repo->saveAdminSettings(['security_fee_pct'=>(float)$text]);
}elseif($autoPayStep === 'method_add' || $autoPayStep === 'method_add_coin'){
$parts = explode('|',(string)$text,3);
$coin = strtoupper(trim((string)($parts[0] ?? '')));
$net = strtoupper(trim((string)($parts[1] ?? '')));
$addr = trim((string)($parts[2] ?? ''));

if($coin === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أرسل اسم العملة مثل: USDT']);
$adminActionHandled = true;
return;
}

if($net !== '' && $addr !== ''){
$repo->savePaymentMethod($coin,$net,$addr,1,0);
bot_store_delete($autoPayMethodDraftFile);
$showMethodsAfterSave = true;
}else{
bot_json_set($autoPayMethodDraftFile,['coin'=>$coin]);
bot_store_set($autoPayStepFile,'method_add_network');
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'✅ ممتاز\n\nالآن أرسل اسم الشبكة مثل: TRC20 أو BEP20']);
$adminActionHandled = true;
return;
}
}elseif($autoPayStep === 'method_add_network'){
$network = strtoupper(trim((string)$text));
if($network === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أرسل اسم الشبكة']);
$adminActionHandled = true;
return;
}

$draft = get_json($autoPayMethodDraftFile,[]);
$coin = strtoupper(trim((string)($draft['coin'] ?? '')));
if($coin === ''){
bot_store_delete($autoPayStepFile);
bot_store_delete($autoPayMethodDraftFile);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ انتهت الجلسة، اضغط إضافة عملة مشفرة مرة أخرى']);
$adminActionHandled = true;
return;
}

$draft['network'] = $network;
bot_json_set($autoPayMethodDraftFile,$draft);
bot_store_set($autoPayStepFile,'method_add_wallet');
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'✅ تمام\n\nالآن أرسل عنوان المحفظة لهذه العملة']);
$adminActionHandled = true;
return;
}elseif($autoPayStep === 'method_add_wallet'){
$wallet = trim((string)$text);
if($wallet === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أرسل عنوان محفظة صحيح']);
$adminActionHandled = true;
return;
}

$draft = get_json($autoPayMethodDraftFile,[]);
$coin = strtoupper(trim((string)($draft['coin'] ?? '')));
$network = strtoupper(trim((string)($draft['network'] ?? '')));
if($coin === '' || $network === ''){
bot_store_delete($autoPayStepFile);
bot_store_delete($autoPayMethodDraftFile);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ انتهت الجلسة، اضغط إضافة عملة مشفرة مرة أخرى']);
$adminActionHandled = true;
return;
}

$repo->savePaymentMethod($coin,$network,$wallet,1,0);
bot_store_delete($autoPayMethodDraftFile);
$showMethodsAfterSave = true;
}elseif(strpos((string)$autoPayStep,'method_edit_coin|') === 0){
$parts = explode('|',(string)$autoPayStep,2);
$methodId = (int)($parts[1] ?? 0);
$coin = strtoupper(trim((string)$text));
if($methodId <= 0 || $coin === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل اسم عملة صحيح']);
$adminActionHandled = true;
return;
}

$rows = $repo->listPaymentMethods(false);
$target = null;
foreach($rows as $r){
if((int)($r['id'] ?? 0) === $methodId){
$target = $r;
break;
}
}
if(!$target){
bot_store_delete($autoPayStepFile);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ الطريقة غير موجودة']);
$adminActionHandled = true;
return;
}

bot_json_set($autoPayMethodDraftFile,['id'=>$methodId,'coin'=>$coin,'status'=>(int)($target['status'] ?? 0)]);
bot_store_set($autoPayStepFile,'method_edit_network|' . $methodId);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'✅ تم حفظ اسم العملة\n\nأرسل اسم الشبكة الآن']);
$adminActionHandled = true;
return;
}elseif(strpos((string)$autoPayStep,'method_edit_network|') === 0){
$parts = explode('|',(string)$autoPayStep,2);
$methodId = (int)($parts[1] ?? 0);
$network = strtoupper(trim((string)$text));
if($methodId <= 0 || $network === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل اسم شبكة صحيح']);
$adminActionHandled = true;
return;
}

$draft = get_json($autoPayMethodDraftFile,[]);
if((int)($draft['id'] ?? 0) !== $methodId || trim((string)($draft['coin'] ?? '')) === ''){
bot_store_delete($autoPayStepFile);
bot_store_delete($autoPayMethodDraftFile);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ انتهت جلسة التعديل، أعد المحاولة']);
$adminActionHandled = true;
return;
}

$draft['network'] = $network;
bot_json_set($autoPayMethodDraftFile,$draft);
bot_store_set($autoPayStepFile,'method_edit_wallet|' . $methodId);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'✅ تم حفظ الشبكة\n\nأرسل عنوان المحفظة الآن']);
$adminActionHandled = true;
return;
}elseif(strpos((string)$autoPayStep,'method_edit_wallet|') === 0){
$parts = explode('|',(string)$autoPayStep,2);
$methodId = (int)($parts[1] ?? 0);
$wallet = trim((string)$text);
if($methodId <= 0 || $wallet === ''){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ أدخل عنوان محفظة صحيح']);
$adminActionHandled = true;
return;
}

$draft = get_json($autoPayMethodDraftFile,[]);
$coin = strtoupper(trim((string)($draft['coin'] ?? '')));
$network = strtoupper(trim((string)($draft['network'] ?? '')));
$status = (int)($draft['status'] ?? 1) === 1 ? 1 : 0;
if((int)($draft['id'] ?? 0) !== $methodId || $coin === '' || $network === ''){
bot_store_delete($autoPayStepFile);
bot_store_delete($autoPayMethodDraftFile);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ انتهت جلسة التعديل، أعد المحاولة']);
$adminActionHandled = true;
return;
}

$repo->savePaymentMethod($coin,$network,$wallet,$status,$methodId);
bot_store_delete($autoPayMethodDraftFile);
$showMethodsAfterSave = true;
}elseif($autoPayStep === 'blacklist_del'){
$repo->removeBlacklist($text);
}

bot_store_delete($autoPayStepFile);
if($showMethodsAfterSave){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"✅ تمت إضافة العملة المشفرة بنجاح\n\n" . autopay_admin_methods_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_methods_keyboard())
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"✅ تم الحفظ بنجاح\n\n" . autopay_admin_settings_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_settings_keyboard())
]);
}
$adminActionHandled = true;
return;
}

if($is_primary_admin && $settingsEditKey !== '' && ($data ?? '') === '' && $message !== null && $text !== '' && $text !== '/start'){

$saveResult = bot_setting_set($settingsEditKey,$text);
$savedGroup = bot_setting_group($settingsEditKey);

if(!$saveResult['ok']){

if($settingsPanelMessageId > 0){

$resp = bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$settingsPanelMessageId,

'text'=>"❌ • {$saveResult['error']}\n\n" . bot_setting_prompt_text($settingsEditKey),

'parse_mode'=>'html',

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

if(!($resp->ok ?? false)){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"❌ • {$saveResult['error']}",

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

}

}else{

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"❌ • {$saveResult['error']}",

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

}

$adminActionHandled = true;
return;

}

bot_store_delete($settingsEditContextFile);

$savedNotice = "✅ • تم حفظ " . bot_setting_label($settingsEditKey) . " بنجاح";
if(isset($saveResult['synced']) && is_array($saveResult['synced']) && !empty($saveResult['synced'])){
$syncedParts = [];
foreach($saveResult['synced'] as $syncKey => $syncValue){
$syncedParts[] = bot_setting_label((string)$syncKey) . ' = ' . (string)$syncValue;
}
if(!empty($syncedParts)){
$savedNotice .= "\n✅ • تم توليد آيدي القناة تلقائيًا: " . implode(' | ',$syncedParts);
}
}

if($settingsPanelMessageId > 0){

$resp = bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$settingsPanelMessageId,

'text'=>$savedNotice . "\n\n<b>" . bot_settings_group_title($savedGroup) . "</b>\n<b>🛠️ اختر الإعداد المطلوب تعديله:</b>\n\n" . bot_settings_group_status_text($savedGroup),

'parse_mode'=>'html',

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

if(!($resp->ok ?? false)){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>$savedNotice,

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

}

}else{

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>$savedNotice,

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsEditKey)))

]);

}

bot_store_delete("data/id/$id/step.txt");

$adminActionHandled = true;
return;

}

#=========={إعدادات البوت}==========#
if($data == 'settings-main' && $is_primary_admin){

bot_store_delete($settingsEditContextFile);
bot_store_set($settingsPanelMessageIdFile,(string)$message_id);

$groupsMeta = bot_settings_groups();
$mainSummary = [];
foreach(['channels','info','finance','config'] as $gk){
if(!isset($groupsMeta[$gk])){
continue;
}
$mainSummary[] = '• ' . $groupsMeta[$gk]['label'] . ' : ' . bot_settings_group_status_summary($gk);
}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>⚙️ لوحة إعدادات البوت</b>

<b>🎯 اختر القسم الذي تريد تعديله:</b>

<b>📊 ملخص سريع:</b>
" . implode("\n",$mainSummary) . "

",

'parse_mode'=>'html',

'reply_markup'=>json_encode(bot_settings_main_keyboard())

]);

bot_store_delete("data/id/$id/step.txt");

$adminActionHandled = true;
return;

}

if(($data == 'settings-channels' || $data == 'settings-info' || $data == 'settings-finance' || $data == 'settings-config') && $is_primary_admin){

$section = str_replace('settings-','',$data);

bot_store_delete($settingsEditContextFile);
bot_store_set($settingsPanelMessageIdFile,(string)$message_id);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>" . bot_settings_group_title($section) . "</b>

<b>🛠️ اختر الإعداد المطلوب تعديله:</b>

" . bot_settings_group_status_text($section) . "

",

'parse_mode'=>'html',

'reply_markup'=>json_encode(bot_settings_group_keyboard($section))

]);

bot_store_delete("data/id/$id/step.txt");

$adminActionHandled = true;
return;

}

if($exdata[0] == 'settings' && isset($exdata[1]) && $exdata[1] == 'edit' && isset($exdata[2]) && $is_primary_admin){

$settingsKey = $exdata[2];

if(!bot_setting_editable($settingsKey)){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>'هذا الإعداد غير قابل للتعديل',

'show_alert'=>true

]);

$adminActionHandled = true;
return;

}

bot_store_set($settingsEditContextFile,$settingsKey);
bot_store_set($settingsPanelMessageIdFile,(string)$message_id);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

" . bot_setting_prompt_text($settingsKey) . "

",

'parse_mode'=>'html',

'reply_markup'=>json_encode(bot_settings_group_keyboard(bot_setting_group($settingsKey)))

]);

bot_store_delete("data/id/$id/step.txt");

$adminActionHandled = true;
return;

}

if($data == 'autopay-settings-main' && $is_primary_admin){

bot_store_delete($settingsEditContextFile);
bot_store_delete($autoPayStepFile);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>autopay_admin_settings_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_settings_keyboard())
]);
$adminActionHandled = true;
return;

}

if($is_primary_admin && strpos((string)$data,'autopay-set-') === 0){

$target = str_replace('autopay-set-','',(string)$data);
$allowed = ['key'=>'set_key','secret'=>'set_secret','payid'=>'set_payid','rate'=>'set_rate','min'=>'set_min','max'=>'set_max','fee'=>'set_fee'];
if(!isset($allowed[$target])){
$adminActionHandled = true;
return;
}

bot_store_set($autoPayStepFile,$allowed[$target]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>أرسل القيمة الجديدة الآن:</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']]]])
]);
$adminActionHandled = true;
return;

}

if($data == 'autopay-methods' && $is_primary_admin){

bot_store_delete($autoPayStepFile);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>autopay_admin_methods_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_methods_keyboard())
]);
$adminActionHandled = true;
return;

}

if($data == 'autopay-export-history' && $is_primary_admin){

$export = bot_binance_client()->exportTransactionHistory();
if(($export['ok'] ?? false) !== true){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'❌ تعذر سحب سجلات باينانس: ' . (string)($export['error'] ?? 'غير معروف'),
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']]]])
]);
$adminActionHandled = true;
return;
}

$assetsResp = bot_binance_client()->accountAssets();
$filePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'binance_transactions_' . date('Ymd_His') . '.txt';
$content = [];
$content[] = 'Binance Transaction Export';
$content[] = 'Generated At: ' . date('c');
$content[] = 'Account USDT: ' . number_format((float)($assetsResp['total_usdt'] ?? 0), 8, '.', '');
$content[] = '';
$content[] = '=== Deposits (Incoming External) ===';
$content[] = autopay_admin_history_text('Deposits', (array)($export['deposits'] ?? []), ['insertTime','time','updateTime']);
$content[] = '';
$content[] = '=== Withdrawals (Outgoing External) ===';
$content[] = autopay_admin_history_text('Withdrawals', (array)($export['withdrawals'] ?? []), ['applyTime','completeTime','time','updateTime']);
$content[] = '';
$content[] = '=== Pay Transactions (Internal) ===';
$content[] = autopay_admin_history_text('Pay Transactions', (array)($export['pay_transactions'] ?? []), ['transactionTime','createTime','time','updateTime']);

file_put_contents($filePath, implode("\n", $content) . "\n");

$sendResp = bot_send_document_file(
$chat_id,
$filePath,
'سجلات باينانس',
['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']]]]
);

if(!($sendResp->ok ?? false)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'❌ تعذر إرسال ملف سجلات باينانس',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']]]])
]);
}

@unlink($filePath);

$adminActionHandled = true;
return;

}

if($data == 'autopay-method-add' && $is_primary_admin){

bot_store_delete($autoPayMethodDraftFile);
bot_store_set($autoPayStepFile,'method_add_coin');
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>إضافة عملة مشفرة للبوت (خطوة 1 من 3)</b>\n\nأرسل اسم العملة الآن\nمثال: <code>USDT</code>",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-methods']]]])
]);
$adminActionHandled = true;
return;

}

if($is_primary_admin && strpos((string)$data,'autopay-method-toggle-') === 0){

$methodId = (int)str_replace('autopay-method-toggle-','',(string)$data);
$repo = bot_auto_pay_repo();
$rows = $repo->listPaymentMethods(false);
foreach($rows as $r){
if((int)$r['id'] === $methodId){
$repo->savePaymentMethod((string)$r['coin_name'],(string)$r['network'],(string)$r['wallet_address'],!empty($r['status']) ? 0 : 1,$methodId);
break;
}

if($is_primary_admin && strpos((string)$data,'autopay-method-edit-') === 0){

$methodId = (int)str_replace('autopay-method-edit-','',(string)$data);
$rows = bot_auto_pay_repo()->listPaymentMethods(false);
$target = null;
foreach($rows as $r){
if((int)($r['id'] ?? 0) === $methodId){
$target = $r;
break;
}
}

if(!$target){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>'الطريقة غير موجودة',
'show_alert'=>true
]);
$adminActionHandled = true;
return;
}

bot_store_delete($autoPayMethodDraftFile);
bot_store_set($autoPayStepFile,'method_edit_coin|' . $methodId);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>تعديل طريقة الدفع #{$methodId} (خطوة 1 من 3)</b>\n\nالاسم الحالي: <code>" . htmlspecialchars((string)$target['coin_name'],ENT_QUOTES,'UTF-8') . "</code>\n\nأرسل اسم العملة الجديد:",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-methods']]]])
]);
$adminActionHandled = true;
return;

}
}

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>autopay_admin_methods_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_methods_keyboard())
]);
$adminActionHandled = true;
return;

}

if($is_primary_admin && strpos((string)$data,'autopay-method-del-') === 0){

$methodId = (int)str_replace('autopay-method-del-','',(string)$data);
bot_auto_pay_repo()->deletePaymentMethod($methodId);

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>autopay_admin_methods_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(autopay_admin_methods_keyboard())
]);
$adminActionHandled = true;
return;

}

if($data == 'autopay-blacklist' && $is_primary_admin){

$rows = bot_auto_pay_repo()->listBlacklist(20);
$lines = ["<b>🧾 قائمة البلاك لست (آخر 20)</b>"];
if(empty($rows)){
$lines[] = "لا توجد معاملات في البلاك لست.";
}else{
foreach($rows as $r){
$lines[] = "• <code>" . (string)$r['tx_id'] . "</code>";
}
}
$lines[] = "\nلحذف معرّف: اضغط الزر ثم أرسل tx/order id";

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>implode("\n",$lines),
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>'🗑️ حذف معرف من البلاك لست','callback_data'=>'autopay-blacklist-del']],
[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']],
]])
]);
$adminActionHandled = true;
return;

}

if($data == 'autopay-blacklist-del' && $is_primary_admin){

bot_store_set($autoPayStepFile,'blacklist_del');
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>أرسل الآن tx_id أو order_id لحذفه من البلاك لست:</b>",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-blacklist']]]])
]);
$adminActionHandled = true;
return;

}

if($data == 'autopay-stats' && $is_primary_admin){

$repo = bot_auto_pay_repo();
$summary = $repo->transactionsSummary();
$recent = $repo->recentTransactions(10);
$assetsResp = bot_binance_client()->accountAssets();

$lines = [];
$lines[] = "<b>📊 إحصائيات الدفع التلقائي</b>";
$lines[] = "• إجمالي العمليات: <code>" . (int)$summary['count'] . "</code>";
$lines[] = "• إجمالي USD: <code>" . number_format((float)$summary['total_usd'],2,'.','') . "</code>";
$lines[] = "• إجمالي RUB: <code>" . number_format((float)$summary['total_rub'],2,'.','') . "</code>";

if(($assetsResp['ok'] ?? false) === true){
$lines[] = "";
$lines[] = "<b>🏦 رصيد Binance الحالي</b>";
$lines[] = "• إجمالي USDT التقريبي: <code>" . number_format((float)($assetsResp['total_usdt'] ?? 0),4,'.','') . "</code>";
$lines[] = "• تفصيلي:";
foreach(array_slice((array)($assetsResp['assets'] ?? []),0,10) as $asset){
$lines[] = "  - " . (string)$asset['asset'] . ': ' . number_format((float)$asset['amount'],8,'.','');
}
}else{
$lines[] = "";
$lines[] = "⚠️ تعذر جلب رصيد Binance: " . (string)($assetsResp['error'] ?? '');
}

if(!empty($recent)){
$lines[] = "";
$lines[] = "<b>🧾 آخر العمليات</b>";
foreach($recent as $t){
$lines[] = "• #" . (int)$t['id'] . " | " . (string)$t['order_id'] . " | USD " . number_format((float)$t['amount_usd'],2,'.','') . " | RUB " . number_format((float)$t['amount_rub'],2,'.','');
}
}

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>implode("\n",$lines),
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'autopay-settings-main']]]])
]);
$adminActionHandled = true;
return;

}

#=========={النفايات}==========#
if($data == 'delPHP'){
bot('answercallbackquery',[
'callback_query_id' => $update->callback_query->id,
'text'=>'✅ • تم تنضيف البوت من النفايات 🗑',
'show_alert'=>true
]);
bot_json_delete("PHPXX.json");
bot_store_delete("data/id/$id/step.txt");
}

#=========={قفل وفتح الأقسام}==========#
$wrod = get_file("data/txt/wrod.txt",'');
if($wrod != null){
$wrods = "فتح الاكثر توفرا ✅";
$datawr = "open1";
}else{
$wrods = "قفل الاكثر توفرا ❌";
$datawr = "lock1";
}
if($openandlock['bot']['lock'] == "ok"){
$openbot = "فتح البوت ✅";
$lockbot = "bot-ok";
}else{
$openbot = "قفل البوت ❌";
$lockbot = "bot-no";
}
if($openandlock['eight']['lock'] == "ok"){
$buyGH = "فتح سيرفر واتساب ✅";
$dataGH = "eight-ok";
}else{
$buyGH = "قفل سيرفر واتساب ❌";
$dataGH = "eight-no";
}
if($openandlock['nine']['lock'] == "ok"){
$buyNE = "فتح سيرفر تيليجرام ✅";
$dataNE = "nine-ok";
}else{
$buyNE = "قفل سيرفر تيليجرام ❌";
$dataNE = "nine-no";
}
if($openandlock['Grace']['lock'] != "ok"){
$openGrace = "فتح السماح ✅";
$lockGrace = "Grace-no";
}else{
$openGrace = "قفل السماح ❌";
$lockGrace = "Grace-ok";
}
if($data == "opclo"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
عبر هذا الأزرار تستطيع التحكم بجميع الاقسام واقفالها وفتحها ♻️
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$openbot",'callback_data'=>"ameopclo-$lockbot"]],
[['text'=>"$wrods",'callback_data'=>"$datawr"],['text'=>"$openGrace",'callback_data'=>"ameopclo-$lockGrace"]],
[['text'=>"$buyGH",'callback_data'=>"ameopclo-$dataGH"],['text'=>"$buyNE",'callback_data'=>"ameopclo-$dataNE"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
if($data == "open1"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
تم بنجاح فتح قسم الاكثر توفرآ واتساب للبوت ✅
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'opclo']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_store_delete("data/txt/wrod.txt");
}
if($data == "lock1"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
الآن قم ب إرسال الرسالة التي تريدها أن تضهر عند الضغط على زر الاكثر توفرآ واتساب ♻️
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'opclo']]
]
])
]);
bot_store_set("data/id/$id/step.txt","lo");
}
if($text && $text != '/start' && $step == 'lo'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
تم بنجاح قفل قسم الاكثر توفرآ واتساب ✅
",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'opclo']]
]
])
]);
bot_store_set("data/txt/wrod.txt","$text");
bot_store_delete("data/id/$id/step.txt");
}
if($exdata[0] == "ameopclo"){
$code = $exdata[1];
$quantit = $exdata[2];
if($quantit == 'ok'){
$qu = "no";
}else{
$qu = "ok";
}
if($code == "bot"){
if($qu == 'ok'){
$openbot = "فتح البوت ✅";
$lockbot = "bot-ok";
$bb = "قفل البوت ❌";
}else{
$openbot = "قفل البوت ❌";
$lockbot = "bot-no";
$bb = "فتح البوت ✅";
}
}
if($code == "eight"){
if($qu == 'ok'){
$buyGH = "فتح سيرفر واتساب ✅";
$dataGH = "eight-ok";
$bb = "قفل سيرفر واتساب ❌";
}else{
$buyGH = "قفل سيرفر واتساب ❌";
$dataGH = "eight-no";
$bb = "فتح سيرفر وتساب ✅";
}
}
if($code == "nine"){
if($qu == 'ok'){
$buyNE = "فتح سيرفر تيليجرام ✅";
$dataNE = "nine-ok";
$bb = "قفل سيرفر تيليجرام ❌";
}else{
$buyNE = "قفل سيرفر تيليجرام ❌";
$dataNE = "nine-no";
$bb = "فتح سيرفر تيليجرام ✅";
}
}
if($code == "Grace"){
if($qu != 'ok'){
$openGrace = "فتح السماح ✅";
$lockGrace = "Grace-no";
$bb = "قفل السماح ❌";
}else{
$openGrace = "قفل السماح ❌";
$lockGrace = "Grace-ok";
$bb = "فتح السماح ✅";
}
}
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم $bb",
'show_alert'=>folse
]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
عبر هذا الأزرار تستطيع التحكم بجميع الاقسام واقفالها وفتحها ♻️
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$openbot",'callback_data'=>"ameopclo-$lockbot"]],
[['text'=>"$wrods",'callback_data'=>"$datawr"],['text'=>"$openGrace",'callback_data'=>"ameopclo-$lockGrace"]],
[['text'=>"$buyGH",'callback_data'=>"ameopclo-$dataGH"],['text'=>"$buyNE",'callback_data'=>"ameopclo-$dataNE"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
$openandlock[$code]['lock'] = $qu;
Ands($openandlock);
}

#=========={إدارة طرق الدفع}==========#
function render_payment_methods_admin_keyboard($paymentMethods){
$rows = [];
foreach($paymentMethods as $key => $item){
$status = !empty($item['enabled']) ? '✅' : '❌';
$label = ($item['name'] ?? $key) . " $status";
$rows[] = [[
'text'=>$label,
'callback_data'=>"paym-$key-toggle"
]];
}
$rows[] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']];
return ['inline_keyboard'=>$rows];
}

if($data == "payment_methods_admin"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
اختر طريقة الدفع التي تريد تشغيلها/إيقافها ♻️
",
'reply_markup'=>json_encode(render_payment_methods_admin_keyboard($paymentMethods))
]);
bot_store_delete("data/id/$id/step.txt");
}

if($exdata[0] == "paym" && isset($exdata[1]) && $exdata[2] == "toggle"){
$methodKey = $exdata[1];
if(isset($paymentMethods[$methodKey])){
$paymentMethods[$methodKey]['enabled'] = empty($paymentMethods[$methodKey]['enabled']);
PaymentMethodsSave($paymentMethods);
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم تحديث {$paymentMethods[$methodKey]['name']}",
'show_alert'=>false
]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
اختر طريقة الدفع التي تريد تشغيلها/إيقافها ♻️
",
'reply_markup'=>json_encode(render_payment_methods_admin_keyboard($paymentMethods))
]);
}else{
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"طريقة دفع غير معروفة",
'show_alert'=>true
]);
}
bot_store_delete("data/id/$id/step.txt");
}

#=========={التطبيقات}==========#
if($addblusdel['ot']['add'] == "ok"){
$addot = "• السيرفر العام ✅";
$ot = "• السيرفر العام •";
$delot = "ot-ok";
}else{
$addot = "• السيرفر العام ❌";
$ot = "";
$delot = "ot-no";
}
if($addblusdel['wa']['add'] == "ok"){
$addwa = "• واتسأب ✅";
$wa = "• واتسأب •";
$delwa = "wa-ok";
}else{
$addwa = "• واتسأب ❌";
$wa = "";
$delwa = "wa-no";
}
if($addblusdel['tg']['add'] == "ok"){
$addtg = "• تيليجرام ✅";
$tg = "• تيليجرام •";
$deltg = "tg-ok";
}else{
$addtg = "• تيليجرام ❌";
$tg = "";
$deltg = "tg-no";
}
if($addblusdel['fb']['add'] == "ok"){
$addfb = "• فيسبوك ✅";
$fb = "• فيسبوك •";
$delfb = "fb-ok";
}else{
$addfb = "• فيسبوك ❌";
$fb = "";
$delfb = "fb-no";
}
if($addblusdel['ig']['add'] == "ok"){
$addig = "• إنستقرام ✅";
$ig = "• إنستقرام •";
$delig = "ig-ok";
}else{
$addig = "• إنستقرام ❌";
$ig = "";
$delig = "ig-no";
}
if($addblusdel['tw']['add'] == "ok"){
$addtw = "• تويتر ✅";
$tw = "• تويتر •";
$deltw = "tw-ok";
}else{
$addtw = "• تويتر ❌";
$tw = "";
$deltw = "tw-no";
}
if($addblusdel['lf']['add'] == "ok"){
$addlf = "• تيك توك ✅";
$lf = "• تيك توك •";
$dellf = "lf-ok";
}else{
$addlf = "• تيك توك ❌";
$lf = "";
$dellf = "lf-no";
}
if($addblusdel['go']['add'] == "ok"){
$addgo = "• جوجل ✅";
$go = "• جوجل •";
$delgo = "go-ok";
}else{
$addgo = "• جوجل ❌";
$go = "";
$delgo = "go-no";
}
if($addblusdel['im']['add'] == "ok"){
$addim = "• إيمو ✅";
$im = "• إيمو •";
$delim = "im-ok";
}else{
$addim = "• إيمو ❌";
$im = "";
$delim = "im-no";
}
if($addblusdel['vi']['add'] == "ok"){
$addvi = "• فايبر ✅";
$vi = "• فايبر •";
$delvi = "vi-ok";
}else{
$addvi = "• فايبر ❌";
$vi = "";
$delvi = "vi-no";
}
if($addblusdel['fu']['add'] == "ok"){
$addfu = "• سناب شات ✅";
$fu = "• سناب شات •";
$delfu = "fu-ok";
}else{
$addfu = "• سناب شات ❌";
$fu = "";
$delfu = "fu-no";
}
if($addblusdel['nf']['add'] == "ok"){
$addnf = "• نيتفلكس  ✅";
$nf = "• نيتفلكس •";
$delnf = "nf-ok";
}else{
$addnf = "• نيتفلكس  ❌";
$nf = "";
$delnf = "nf-no";
}
if($addblusdel['au']['add'] == "ok"){
$addau = "• حراج  ✅";
$au = "• حراج •";
$delau = "au-ok";
}else{
$addau = "• حراج  ❌";
$au = "";
$delau = "au-no";
}
#=========={المواقع}==========#
if($addblusdel['5sim']['add'] == "ok"){
$add5sim = "5sim.biz 🌐 ✅";
$sim5 = "5sim.biz 🌐";
$del5sim = "5sim-ok";
$rate5sim = "5sim";
$plus1="➕";
$minus1="➖";
$rupl1=$addblusdel['5sim']['rupl'];
$rrupl1="{".$rupl1."}";
}else{
$add5sim = "5sim.biz 🌐 ❌";
$sim5 = "";
$del5sim = "5sim-no";
}
if($addblusdel['alisms']['add'] == "ok"){
$addsmslive = "alisms.org 🌐 ✅";
$smslive = "alisms.org 🌐";
$delsmslive = "alisms-ok";
$ratesmslive = "alisms";
$plus2="➕";
$minus2="➖";
$rupl2=$addblusdel['alisms']['rupl'];
$rrupl2="{".$rupl2."}";
}else{
$addsmslive = "alisms.org 🌐 ❌";
$smslive = "";
$delsmslive = "alisms-no";
}
if($addblusdel['man']['add'] == "ok"){
$addman = "sms-man.ru 🌐 ✅";
$man = "sms-man.ru 🌐";
$delman = "man-ok";
$rateman = "man";
$plus3="➕";
$minus3="➖";
$rupl3=$addblusdel['man']['rupl'];
$rrupl3="{".$rupl3."}";
}else{
$addman = "sms-man.ru 🌐 ❌";
$man = "";
$delman = "man-no";
}
if($addblusdel['vak']['add'] == "ok"){
$addvak = "Vak-sms.com 🌐 ✅";
$vak = "Vak-sms.com 🌐";
$delvak = "vak-ok";
$ratevak = "vak";
$plus4="➕";
$minus4="➖";
$rupl4=$addblusdel['vak']['rupl'];
$rrupl4="{".$rupl4."}";
}else{
$addvak = "Vak-sms.com 🌐 ❌";
$vak = "";
$delvak = "vak-no";
}
if($addblusdel['activate']['add'] == "ok"){
$addactivate = "sms-activate.io 🌐 ✅";
$activate = "sms-activate.io 🌐";
$delactivate = "activate-ok";
}else{
$addactivate = "sms-activate.io 🌐 ❌";
$activate = "";
$delactivate = "activate-no";
}
if($addblusdel['pvapins']['add'] == "ok"){
$addpvapins = "pvapins.com 🌐 ✅";
$pvapins = "pvapins.com 🌐";
$delpvapins = "pvapins-ok";
}else{
$addpvapins = "pvapins.com 🌐 ❌";
$pvapins = "";
$delpvapins = "pvapins-no";
}
if($addblusdel['botsms']['add'] == "ok"){
$addbotsms = "alnwerah.sms 🌐 ✅";
$botsms = "alnwerah.sms 🌐";
$delbotsms = "botsms-ok";
}else{
$addbotsms = "alnwerah.sms 🌐 ❌";
$botsms = "";
$delbotsms = "botsms-no";
}
if($addblusdel['onlinesim']['add'] == "ok"){
$addonlinesim = "onlinesim.io 🌐 ✅";
$onlinesim = "onlinesim.io 🌐";
$delonlinesim = "onlinesim-ok";
$rateonlinesim = "onlinesim";
$plus8="➕";
$minus8="➖";
$rupl8=$addblusdel['onlinesim']['rupl'];
$rrupl8="{".$rupl8."}";
}else{
$addonlinesim = "onlinesim.io 🌐 ❌";
$onlinesim = "";
$delonlinesim = "onlinesim-no";
}
if($addblusdel['supersmstech']['add'] == "ok"){
$addsupersmstech = "supersmstech.com 🌐 ✅";
$supersmstech = "supersmstech.com 🌐";
$delsupersmstech = "supersmstech-ok";
$ratesupersmstech = "supersmstech";
$plus9="➕";
$minus9="➖";
$rupl9=$addblusdel['supersmstech']['rupl'];
$rrupl9="{".$rupl9."}";
}else{
$addsupersmstech = "supersmstech.com 🌐 ❌";
$supersmstech = "";
$delsupersmstech = "supersmstech-no";
}
if($addblusdel['viotp']['add'] == "ok"){
$addviotp = "viotp.com 🌐 ✅";
$viotp = "viotp.com 🌐";
$delviotp = "viotp-ok";
}else{
$addviotp = "viotp.com 🌐 ❌";
$viotp = "";
$delviotp = "viotp-no";
}
if($addblusdel['simsms']['add'] == "ok"){
$addsimsms = "simsms.org 🌐 ✅";
$simsms = "simsms.org 🌐";
$delsimsms = "simsms-ok";
$ratesimsms = "simsms";
$plusb="➕";
$minusb="➖";
$ruplb=$addblusdel['simsms']['rupl'];
$rruplb="{".$ruplb."}";
}else{
$addsimsms = "simsms.org 🌐 ❌";
$simsms = "";
$delsimsms = "simsms-no";
}
if($addblusdel['grizzly']['add'] == "ok"){
$addgrizzly = "grizzlysms.com 🌐 ✅";
$grizzly = "grizzlysms.com 🌐";
$delgrizzly = "grizzly-ok";
$rategrizzly = "grizzly";
$plusc="➕";
$minusc="➖";
$ruplc=$addblusdel['grizzly']['rupl'];
$rruplc="{".$ruplc."}";
}else{
$addgrizzly = "grizzlysms.com 🌐 ❌";
$grizzly = "";
$delgrizzly = "grizzly-no";
}
if($addblusdel['smscode']['add'] == "ok"){
$addsmscode = "sms-code.ru 🌐 ✅";
$smscode = "sms-code.ru 🌐";
$delsmscode = "smscode-ok";
$ratesmscode = "smscode";
$plusd="➕";
$minusd="➖";
$rupld=$addblusdel['smscode']['rupl'];
$rrupld="{".$rupld."}";
}else{
$addsmscode = "sms-code.ru 🌐 ❌";
$smscode = "";
$delsmscode = "smscode-no";
}
if($addblusdel['tiger']['add'] == "ok"){
$addtiger = "tiger-sms.com 🌐 ✅";
$tiger = "tiger-sms.com 🌐";
$deltiger = "tiger-ok";
$ratetiger = "tiger";
$pluse="➕";
$minuse="➖";
$ruple=$addblusdel['tiger']['rupl'];
$rruple="{".$ruple."}";
}else{
$addtiger = "tiger-sms.com 🌐 ❌";
$tiger = "";
$deltiger = "tiger-no";
}
if($addblusdel['2ndline']['add'] == "ok"){
$addnd2line = "2ndline.io 🌐 ✅";
$nd2line = "2ndline.io 🌐";
$delnd2line = "2ndline-ok";
}else{
$addnd2line = "2ndline.io 🌐 ❌";
$nd2line = "";
$delnd2line = "2ndline-no";
}
if($addblusdel['store']['add'] == "ok"){
$addstore = "receivesms.store 🌐 ✅";
$store = "receivesms.store 🌐";
$delstore = "store-ok";
}else{
$addstore = "receivesms.store 🌐 ❌";
$store = "";
$delstore = "store-no";
}
if($addblusdel['fastpva']['add'] == "ok"){
$addfastpva = "sms.fastpva.com 🌐 ✅";
$fastpva = "sms.fastpva.com 🌐";
$delfastpva = "fastpva-ok";
}else{
$addfastpva = "sms.fastpva.com 🌐 ❌";
$fastpva = "";
$delfastpva = "fastpva-no";
}
if($addblusdel['dropsms']['add'] == "ok"){
$adddropsms = "dropsms.ru 🌐 ✅";
$dropsms = "dropsms.ru 🌐";
$deldropsms = "dropsms-ok";
$ratedropsms = "dropsms";
$plusj="➕";
$minusj="➖";
$ruplj=$addblusdel['dropsms']['rupl'];
$rruplj="{".$ruplj."}";
}else{
$adddropsms = "dropsms.ru 🌐 ❌";
$dropsms = "";
$deldropsms = "dropsms-no";
}
if($addblusdel['24sms7']['add'] == "ok"){
$add24sms7 = "24sms7.com 🌐 ✅";
$sms7 = "24sms7.com 🌐";
$del24sms7 = "24sms7-ok";
$rate24sms7 = "24sms7";
$plusk="➕";
$minusk="➖";
$ruplk=$addblusdel['24sms7']['rupl'];
$rruplk="{".$ruplk."}";
}else{
$add24sms7 = "24sms7.com 🌐 ❌";
$sms7 = "";
$del24sms7 = "24sms7-no";
}
if($addblusdel['sellotp']['add'] == "ok"){
$addsellotp = "sellotp.com 🌐 ✅";
$sellotp = "sellotp.com 🌐";
$delsellotp = "sellotp-ok";
}else{
$addsellotp = "sellotp.com 🌐 ❌";
$sellotp = "";
$delsellotp = "sellotp-no";
}
if($addblusdel['duraincloud']['add'] == "ok"){
$addduraincloud = "mm.durianrcs.com 🌐 ✅";
$duraincloud = "mm.durianrcs.com 🌐";
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms.com","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
$duraincloud = "mm.durianrcs.com 🌐";
 $delduraincloud = "duraincloud-ok";
}else{
$addduraincloud = "mm.durianrcs.com 🌐 ❌";
$duraincloud = "";
$delduraincloud = "duraincloud-no";
}
#=========={إضافة الدول}==========#
if($data == "addnumber"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 ⪼ قم ب إختيار الموقع الذي تود إضافة الدولة إلى البوت.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$sim5",'callback_data'=>"Bj-Ai"]],
[['text'=>"$smslive",'callback_data'=>"Bj-Bi"],['text'=>"$man",'callback_data'=>"Bj-Ci"]],
[['text'=>"$vak",'callback_data'=>"Bj-Di"]],
[['text'=>"$activate",'callback_data'=>"Bj-Ei"],['text'=>"$pvapins",'callback_data'=>"Bj-Fi"]],
[['text'=>"$botsms",'callback_data'=>"Bj-Gi"]],
[['text'=>"$onlinesim",'callback_data'=>"Bj-Hi"],['text'=>"$supersmstech",'callback_data'=>"Bj-Ji"]],
[['text'=>"$viotp",'callback_data'=>"Bj-Ki"]],
[['text'=>"$simsms",'callback_data'=>"Bj-Li"],['text'=>"$grizzly",'callback_data'=>"Bj-Mi"]],
[['text'=>"$smscode",'callback_data'=>"Bj-Ni"]],
[['text'=>"$tiger",'callback_data'=>"Bj-Oi"],['text'=>"$nd2line",'callback_data'=>"Bj-Pi"]],
[['text'=>"$store",'callback_data'=>"Bj-Qi"]],
[['text'=>"$fastpva",'callback_data'=>"Bj-Ri"],['text'=>"$dropsms",'callback_data'=>"Bj-Si"]],
[['text'=>"$sms7",'callback_data'=>"Bj-Ti"]],
[['text'=>"$sellotp",'callback_data'=>"Bj-Ui"],['text'=>"$duraincloud",'callback_data'=>"Bj-Vi"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
#=========={المواقع}==========#
if($text == "المواقع"){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🌐 ⪼ قم ب إختيار الموقع الذي تود إضافة الدولة إلى البوت.*
",
'parse_mode'=>'Markdown',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$add5sim",'callback_data'=>"adminadd-$del5sim"]],
[['text'=>"$addsmslive",'callback_data'=>"adminadd-$delsmslive"],['text'=>"$addman",'callback_data'=>"adminadd-$delman"]],
[['text'=>"$addvak",'callback_data'=>"adminadd-$delvak"]],
[['text'=>"$addactivate",'callback_data'=>"adminadd-$delactivate"],['text'=>"$addpvapins",'callback_data'=>"adminadd-$delpvapins"]],
[['text'=>"$addbotsms",'callback_data'=>"adminadd-$delbotsms"]],
[['text'=>"$addonlinesim",'callback_data'=>"adminadd-$delonlinesim"],['text'=>"$addsupersmstech",'callback_data'=>"adminadd-$delsupersmstech"]],
[['text'=>"$addviotp",'callback_data'=>"adminadd-$delviotp"]],
[['text'=>"$addsimsms",'callback_data'=>"adminadd-$delsimsms"],['text'=>"$addgrizzly",'callback_data'=>"adminadd-$delgrizzly"]],
[['text'=>"$addsmscode",'callback_data'=>"adminadd-$delsmscode"]],
[['text'=>"$addtiger",'callback_data'=>"adminadd-$deltiger"],['text'=>"$addnd2line",'callback_data'=>"adminadd-$delnd2line"]],
[['text'=>"$addstore",'callback_data'=>"adminadd-$delstore"]],
[['text'=>"$addfastpva",'callback_data'=>"adminadd-$delfastpva"],['text'=>"$adddropsms",'callback_data'=>"adminadd-$deldropsms"]],
[['text'=>"$add24sms7",'callback_data'=>"adminadd-$del24sms7"]],
[['text'=>"$addsellotp",'callback_data'=>"adminadd-$delsellotp"],['text'=>"$addduraincloud",'callback_data'=>"adminadd-$delduraincloud"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
#=========={التطبيقات}==========#
if($text == "التطبيقات"){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🌐 ⪼ قم ب إختيار التطبيق الذي تود إضافة الدولة إلى البوت.*
",
'parse_mode'=>'Markdown',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$addot",'callback_data'=>"adminappadd-$delot"]],
[['text'=>"$addtg",'callback_data'=>"adminappadd-$deltg"],['text'=>"$addwa",'callback_data'=>"adminappadd-$delwa"]],
[['text'=>"$addig",'callback_data'=>"adminappadd-$delig"],['text'=>"$addfb",'callback_data'=>"adminappadd-$delfb"]],
[['text'=>"$addlf",'callback_data'=>"adminappadd-$dellf"],['text'=>"$addtw",'callback_data'=>"adminappadd-$deltw"]],
[['text'=>"$addim",'callback_data'=>"adminappadd-$delim"],['text'=>"$addgo",'callback_data'=>"adminappadd-$delgo"]],
[['text'=>"$addfu",'callback_data'=>"adminappadd-$delfu"],['text'=>"$addvi",'callback_data'=>"adminappadd-$delvi"]],
[['text'=>"$addau",'callback_data'=>"adminappadd-$delau"],['text'=>"$addnf",'callback_data'=>"adminappadd-$delnf"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
#=========={النظام}==========#
if($text == "النظام"){
if($addblusdel['System'] == "direct"){
$System_direct = "نظام تلقائي ✅";
$System_not_directly = "نظام يدوي ❌";
}elseif($addblusdel['System'] == "not_directly"){
$System_direct = "نظام تلقائي ❌";
$System_not_directly = "نظام يدوي ✅";
}else{
$System_direct = "نظام تلقائي ❌";
$System_not_directly = "نظام يدوي ❌";
}
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑ ⌯ هل تريد جعل نظام البوت تلقائي او يدوي.*
",
'parse_mode'=>'Markdown',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$System_direct",'callback_data'=>"adminSystem-direct"]],
[['text'=>"$System_not_directly",'callback_data'=>"adminSystem-not_directly"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
if($exdata[0] == "adminSystem"){
$code = $exdata[1];
if($code == "direct"){
$System_direct = "نظام تلقائي ✅";
$System_not_directly = "نظام يدوي ❌";
$bb="التلقائي";
}elseif($code == "not_directly"){
$System_direct = "نظام تلقائي ❌";
$System_not_directly = "نظام يدوي ✅";
$bb="اليدوي";
}
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم تغيير النظام إلى $bb ✅",
'show_alert'=>folse
]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑ ⌯ هل تريد جعل نظام البوت تلقائي او يدوي.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$System_direct",'callback_data'=>"adminSystem-direct"]],
[['text'=>"$System_not_directly",'callback_data'=>"adminSystem-not_directly"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
$addblusdel['System'] = $code;
Bnds($addblusdel);
}
#=========={المواقع}==========#
if($exdata[0] == "adminadd"){
$code = $exdata[1];
$quantit = $exdata[2];
if($quantit == 'ok'){
$qu = "no";
}else{
$qu = "ok";
}
if($code == "5sim"){
if($qu == 'ok'){
$add5sim = "5sim.biz 🌐 ✅";
$del5sim = "5sim-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$add5sim = "5sim.biz 🌐 ❌";
$del5sim = "5sim-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "alisms"){
if($qu == 'ok'){
$addsmslive = "alisms.org 🌐 ✅";
$delsmslive = "alisms-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addsmslive = "alisms.org 🌐 ❌";
$delsmslive = "alisms-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "man"){
if($qu == 'ok'){
$addman = "sms-man.ru 🌐 ✅";
$delman = "man-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addman = "sms-man.ru 🌐 ❌";
$delman = "man-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "vak"){
if($qu == 'ok'){
$addvak = "Vak-sms.com 🌐 ✅";
$delvak = "vak-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addvak = "Vak-sms.com 🌐 ❌";
$delvak = "vak-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "activate"){
if($qu == 'ok'){
$addactivate = "sms-activate.io 🌐 ✅";
$delactivate = "activate-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addactivate = "sms-activate.io 🌐 ❌";
$delactivate = "activate-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "pvapins"){
if($qu == 'ok'){
$addpvapins = "pvapins.com 🌐 ✅";
$delpvapins = "pvapins-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addpvapins = "pvapins.com 🌐 ❌";
$delpvapins = "pvapins-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "botsms"){
if($qu == 'ok'){
$addbotsms = "alnwerah.sms 🌐 ✅";
$delbotsms = "botsms-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addbotsms = "alnwerah.sms  ❌";
$delbotsms = "botsms-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "onlinesim"){
if($qu == 'ok'){
$addonlinesim = "onlinesim.io 🌐 ✅";
$delonlinesim = "onlinesim-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addonlinesim = "onlinesim.io 🌐 ❌";
$delonlinesim = "onlinesim-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "supersmstech"){
if($qu == 'ok'){
$addsupersmstech = "supersmstech.com 🌐 ✅";
$delsupersmstech = "supersmstech-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addsupersmstech = "supersmstech.com 🌐 ❌";
$delsupersmstech = "supersmstech-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "viotp"){
if($qu == 'ok'){
$addviotp = "viotp.com 🌐 ✅";
$delviotp = "viotp-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addviotp = "viotp.com 🌐 ❌";
$delviotp = "viotp-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "simsms"){
if($qu == 'ok'){
$addsimsms = "simsms.org 🌐 ✅";
$delsimsms = "simsms-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addsimsms = "simsms.org 🌐 ❌";
$delsimsms = "simsms-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "grizzly"){
if($qu == 'ok'){
$addgrizzly = "grizzlysms.com 🌐 ✅";
$delgrizzly = "grizzly-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addgrizzly = "grizzlysms.com 🌐 ❌";
$delgrizzly = "grizzly-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "smscode"){
if($qu == 'ok'){
$addsmscode = "sms-code.ru 🌐 ✅";
$delsmscode = "smscode-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addsmscode = "sms-code.ru 🌐 ❌";
$delsmscode = "smscode-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "tiger"){
if($qu == 'ok'){
$addtiger = "tiger-sms.com 🌐 ✅";
$deltiger = "tiger-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addtiger = "tiger-sms.com 🌐 ❌";
$deltiger = "tiger-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "2ndline"){
if($qu == 'ok'){
$addnd2line = "2ndline.io 🌐 ✅";
$delnd2line = "2ndline-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addnd2line = "2ndline.io 🌐 ❌";
$delnd2line = "2ndline-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "store"){
if($qu == 'ok'){
$addstore = "receivesms.store 🌐 ✅";
$delstore = "store-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addstore = "receivesms.store 🌐 ❌";
$delstore = "store-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "fastpva"){
if($qu == 'ok'){
$addfastpva = "sms.fastpva.com 🌐 ✅";
$delfastpva = "fastpva-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addfastpva = "sms.fastpva.com 🌐 ❌";
$delfastpva = "fastpva-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "dropsms"){
if($qu == 'ok'){
$adddropsms = "dropsms.ru 🌐 ✅";
$deldropsms = "dropsms-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$adddropsms = "dropsms.ru 🌐 ❌";
$deldropsms = "dropsms-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "24sms7"){
if($qu == 'ok'){
$add24sms7 = "24sms7.com 🌐 ✅";
$del24sms7 = "24sms7-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$add24sms7 = "24sms7.com 🌐 ❌";
$del24sms7 = "24sms7-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "sellotp"){
if($qu == 'ok'){
$addsellotp = "sellotp.com 🌐 ✅";
$delsellotp = "sellotp-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addsellotp = "sellotp.com 🌐 ❌";
$delsellotp = "sellotp-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
if($code == "duraincloud"){
if($qu == 'ok'){
$addduraincloud = "mm.durianrcs.com 🌐 ✅";
$delduraincloud = "duraincloud-ok";
$bb="تم إضافة المورد بنجاح ✅";
}else{
$addduraincloud = "mm.durianrcs.com 🌐 ❌";
$delduraincloud = "duraincloud-no";
$bb="تم حذف المورد بنجاح ✅";
}
}
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"$bb",
'show_alert'=>folse
]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 ⪼ قم ب إختار الموقع الذي تود إضافة الدولة إلى البوت.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$add5sim",'callback_data'=>"adminadd-$del5sim"]],
[['text'=>"$addsmslive",'callback_data'=>"adminadd-$delsmslive"],['text'=>"$addman",'callback_data'=>"adminadd-$delman"]],
[['text'=>"$addvak",'callback_data'=>"adminadd-$delvak"]],
[['text'=>"$addactivate",'callback_data'=>"adminadd-$delactivate"],['text'=>"$addpvapins",'callback_data'=>"adminadd-$delpvapins"]],
[['text'=>"$addbotsms",'callback_data'=>"adminadd-$delbotsms"]],
[['text'=>"$addonlinesim",'callback_data'=>"adminadd-$delonlinesim"],['text'=>"$addsupersmstech",'callback_data'=>"adminadd-$delsupersmstech"]],
[['text'=>"$addviotp",'callback_data'=>"adminadd-$delviotp"]],
[['text'=>"$addsimsms",'callback_data'=>"adminadd-$delsimsms"],['text'=>"$addgrizzly",'callback_data'=>"adminadd-$delgrizzly"]],
[['text'=>"$addsmscode",'callback_data'=>"adminadd-$delsmscode"]],
[['text'=>"$addtiger",'callback_data'=>"adminadd-$deltiger"],['text'=>"$addnd2line",'callback_data'=>"adminadd-$delnd2line"]],
[['text'=>"$addstore",'callback_data'=>"adminadd-$delstore"]],
[['text'=>"$addfastpva",'callback_data'=>"adminadd-$delfastpva"],['text'=>"$adddropsms",'callback_data'=>"adminadd-$deldropsms"]],
[['text'=>"$add24sms7",'callback_data'=>"adminadd-$del24sms7"]],
[['text'=>"$addsellotp",'callback_data'=>"adminadd-$delsellotp"],['text'=>"$addduraincloud",'callback_data'=>"adminadd-$delduraincloud"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
$addblusdel[$code]['add'] = $qu;
Bnds($addblusdel);
}
#=========={التطبيقات}==========#
if($exdata[0] == "adminappadd"){
$code = $exdata[1];
$quantit = $exdata[2];
if($quantit == 'ok'){
$qu = "no";
}else{
$qu = "ok";
}
if($code == "ot"){
if($qu == 'ok'){
$addot = "• السيرفر العام • ✅";
$delot = "ot-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addot = "• السيرفر العام • ❌";
$delot = "ot-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "wa"){
if($qu == 'ok'){
$addwa = "• واتسأب • ✅";
$delwa = "wa-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addwa = "• واتسأب • ❌";
$delwa = "wa-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "tg"){
if($qu == 'ok'){
$addtg = "• تيليجرام • ✅";
$deltg = "tg-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addtg = "• تيليجرام • ❌";
$deltg = "tg-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "fb"){
if($qu == 'ok'){
$addfb = "• فيسبوك • ✅";
$delfb = "fb-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addfb = "• فيسبوك • ❌";
$delfb = "fb-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "ig"){
if($qu == 'ok'){
$addig = "• إنستقرام • ✅";
$delig = "ig-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addig = "• إنستقرام • ❌";
$delig = "ig-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "tw"){
if($qu == 'ok'){
$addtw = "• تويتر • ✅";
$deltw = "tw-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addtw = "• تويتر • ❌";
$deltw = "tw-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "lf"){
if($qu == 'ok'){
$addlf = "• تيك توك • ✅";
$dellf = "lf-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addlf = "• تيك توك • ❌";
$dellf = "lf-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "go"){
if($qu == 'ok'){
$addgo = "• جوجل • ✅";
$delgo = "go-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addgo = "• جوجل • ❌";
$delgo = "go-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "im"){
if($qu == 'ok'){
$addim = "• إيمو • ✅";
$delim = "im-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addim = "• إيمو • ❌";
$delim = "im-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "vi"){
if($qu == 'ok'){
$addvi = "• فايبر • ✅";
$delvi = "vi-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addvi = "• فايبر • ❌";
$delvi = "vi-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "fu"){
if($qu == 'ok'){
$addfu = "• سناب شات • ✅";
$delfu = "fu-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addfu = "• سناب شات • ❌";
$delfu = "fu-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "nf"){
if($qu == 'ok'){
$addnf = "• نيتفلكس • ✅";
$delnf = "nf-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addnf = "• نيتفلكس • ❌";
$delnf = "nf-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
if($code == "au"){
if($qu == 'ok'){
$addau = "• حراج • ✅";
$delau = "au-ok";
$bb="تم إضافة التطبيق بنجاح ✅";
}else{
$addau = "• حراج • ❌";
$delau = "au-no";
$bb="تم حذف التطبيق بنجاح ✅";
}
}
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"$bb",
'show_alert'=>folse
]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 ⪼ قم ب إختيار التطبيق الذي تود إضافة الدولة إلى البوت.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$addot",'callback_data'=>"adminappadd-$delot"]],
[['text'=>"$addtg",'callback_data'=>"adminappadd-$deltg"],['text'=>"$addwa",'callback_data'=>"adminappadd-$delwa"]],
[['text'=>"$addig",'callback_data'=>"adminappadd-$delig"],['text'=>"$addfb",'callback_data'=>"adminappadd-$delfb"]],
[['text'=>"$addlf",'callback_data'=>"adminappadd-$dellf"],['text'=>"$addtw",'callback_data'=>"adminappadd-$deltw"]],
[['text'=>"$addim",'callback_data'=>"adminappadd-$delim"],['text'=>"$addgo",'callback_data'=>"adminappadd-$delgo"]],
[['text'=>"$addfu",'callback_data'=>"adminappadd-$delfu"],['text'=>"$addvi",'callback_data'=>"adminappadd-$delvi"]],
[['text'=>"$addau",'callback_data'=>"adminappadd-$delau"],['text'=>"$addnf",'callback_data'=>"adminappadd-$delnf"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
$addblusdel[$code]['add'] = $qu;
Bnds($addblusdel);
}
#=========={التطبيقات}==========#
if($exdata[0] == "Bj"){
$api = $exdata[1];
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
$keybo=str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["iA","iA","iA","iB","iC","iD","iE","iF","iG","iE","iH","iJ","iK","iL","iM","iN","iO","iP","iQ","iE","iR"],$api);
$array=["Ai","Bi","Ci","Di","Hi","Ji","Li","Mi","Ni","Oi","Si","Ti"];
if(in_array($api,$array) and $addblusdel['System'] == "direct"){
$keybo = "iV";
}else{
$keybo = $keybo;
}
if($addblusdel['System'] == "direct"){
$System="تلقائي";
}elseif($addblusdel['System'] == "not_directly"){
$System="يدوي";
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 الموقع:* $API
*👨🏻‍💻 نظام البوت: $System ☑️

📲 قم بإختيار التطبيق المراد إضافة الرقم اليه ☺️
من الكيبورد أدناه 👇*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$ot",'callback_data'=>"$keybo-ot-14-$api"]],
[['text'=>"$tg",'callback_data'=>"Fx-3-$api"],['text'=>"$wa",'callback_data'=>"Fx-2-$api"]],
[['text'=>"$ig",'callback_data'=>"$keybo-ig-5-$api"],['text'=>"$fb",'callback_data'=>"$keybo-fb-4-$api"]],
[['text'=>"$lf",'callback_data'=>"$keybo-lf-7-$api"],['text'=>"$tw",'callback_data'=>"$keybo-tw-6-$api"]],
[['text'=>"$im",'callback_data'=>"$keybo-im-9-$api"],['text'=>"$go",'callback_data'=>"$keybo-go-8-$api"]],
[['text'=>"$fu",'callback_data'=>"$keybo-fu-11-$api"],['text'=>"$vi",'callback_data'=>"$keybo-vi-10-$api"]],
[['text'=>"$au",'callback_data'=>"$keybo-au-13-$api"],['text'=>"$nf",'callback_data'=>"$keybo-nf-12-$api"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'addnumber']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
#=========={واتساب+تليجرام}==========#
if($exdata[0] == "Fx"){
$add = $exdata[1];
$api = $exdata[2];
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
$keybo=str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["iA","iA","iA","iB","iC","iD","iE","iF","iG","iE","iH","iJ","iK","iL","iM","iN","iO","iP","iQ","iE","iR"],$api);
$array=["Ai","Bi","Ci","Di","Hi","Ji","Li","Mi","Ni","Oi","Si","Ti"];
if(in_array($api,$array) and $addblusdel['System'] == "direct"){
$keybo = "iV";
}else{
$keybo = $keybo;
}
if($addblusdel['System'] == "direct"){
$System="تلقائي";
}elseif($addblusdel['System'] == "not_directly"){
$System="يدوي";
}
if($add == 2){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 ⪼ الموقع:* $API
*👨🏻‍💻 ⌯ نظام البوت: $System ☑️

💹 ⌯ الآن قم بإختيار القسم الذي تريد إضافتها لتطبيق واتساب 🌀*
",
'parse_mode'=>"MarkDown",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'إضافة للدول القآرات 🔰','callback_data'=>"$keybo-wa-2-$api"]],
[['text'=>'• الشـboyـراء التلـقـ🛰ـائي الذكي •','callback_data'=>"$keybo-wa-1-$api"]],
[['text'=>'‌• 🛍 العروض قسم 🚀 •','callback_data'=>"$keybo-wa-31-$api"]],
[['text'=>'‌• قسم العشوائي رقم 21 •','callback_data'=>"$keybo-wa-21-$api"]],
[['text'=>'‌• قسم العشوائي رقم 22 •','callback_data'=>"$keybo-wa-22-$api"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Bj-$api"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
if($add == 3){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌐 ⪼ الموقع:* $API
*👨🏻‍💻 ⌯ نظام البوت: $System ☑️

💹 ⌯ الآن قم بإختيار القسم الذي تريد إضافتها لتطبيق تيليجرام 🌀*
",
'parse_mode'=>"MarkDown",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'إضافة للدول القآرات 🔰','callback_data'=>"$keybo-tg-3-$api"]],
[['text'=>'• 🔥 عروض تليجرام 🚀 •','callback_data'=>"$keybo-tg-36-$api"]],
[['text'=>'• قسم العشوائي رقم 26 •','callback_data'=>"$keybo-tg-26-$api"]],
[['text'=>'• قسم العشوائي رقم 27 •','callback_data'=>"$keybo-tg-27-$api"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Bj-$api"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_json_delete("zzz.json");
}
}
#=========={صنع كرت}==========#
if($data == 'card'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
*💯 حسنا مطوري
قم بإرسال سعر الكرت الآن ( ارقام فقط )*
',
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","card");
}
if($text && $step == 'card'){
if(!preg_match("/(\D)/",$text)){
$CARD = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ12345689807'),1,16);
$cardbot2=$cardbot+1;
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*✅ ⪼ تم صنع الكرت بنجاح*

*💢 ⌯ الكرت :* `$CARD`
*💰 ⌯ القيمة : $text روبل*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$eer,
'text'=>"
*☑ ⪼  كرت جديد تم صنعة من قبل*
*🅰 ⌯ الادارة :* [$first](tg://user?id=$id)

*💢 ⌯ الكرت : $CARD*
*💰 ⌯ القيمة : $text روبل*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ✅ ⌯ معرف الذي صنع الكرت ⌯ ✅ •",'url'=>"tg://openmessage?user_id=$id"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
$sool['card'][$CARD][amount] = $text;
$sool['card'][$CARD]['idcar'] = "ok";
$sool['card'][$CARD]['num'] = null;
$sool['card'][$CARD]['name'] = $first;
$sool['card'][$CARD]['id'] = $id;
$sool['card'][$CARD]['idcard'] = $cardbot2;
$sool['card'][$CARD]['DAY'] = $DAY;
Sool($sool);
$ORDERALL[card] +=1;
OrdAll($ORDERALL);
}else{
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"
*🔰 ⌯ يرجى إرسال أرقام فقط ❌*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"c"]]
]
])
]);
}
}
#=========={country[1+2+3]}==========#
if($exdata[0] == "iA"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Bi" or $api == "Ci"){
if($app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $AdminONE;  }
if($con == 2) { $continent     = $AdminTOW;  }
if($con == 3) { $continent     = $AdminTHREE;  }
if($con == 4) { $continent     = $AdminFOUR;  }
if($con == 5) { $continent     = $AdminFIVE;  }
if($con == 6) { $continent     = $AdminSIX;  }
if($con == 7) { $continent     = $AdminSEVEN;  }
if($con == 8) { $continent     = $AdminEIGHT;  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con >= 8){
$u = $con-1;
}elseif($con >= 1){
$o = $con+1;
$u = $con-1;
}
if($con == 1){
$t = "• التالي ⬅️ •";
$s = null;
$v = "• الأخير ⬅️ •";
}elseif($con == 2 or $con == 3 or $con == 4 or $con == 5 or $con == 6 or $con == 7){
$t = "• التالي ⬅️ •";
$s = "• السابق ⬅️ •";
$v = null;
$n = null;
}elseif($con == 8){
$t = null;
$s = "• السابق ⬅️ •";
$n = "الأول. ⏭";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $ar=>$zero){
$API=str_replace(["Ai","Bi","Ci"],["5sim.biz","alisms.org","sms-man.ru"],$api);
if($api == "Ai"){
$b="addservice-$app-$add-$api-$zero";
}else{
$b="addprice-$app-$add-$api-$zero-any";
}
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"$b"];
if($e%3 == 0) $i++;
}
$key['inline_keyboard'][0] = [['text'=>'☑️ ⌯ جميع الدول في الأسفل ⬇️','callback_data'=>"no"]];
$key['inline_keyboard'][] = [['text'=>"$t",'callback_data'=>"iA-$app-$add-$api-$o"],['text'=>"$s",'callback_data'=>"iA-$app-$add-$api-$u"],['text'=>"$v",'callback_data'=>"iA-$app-$add-$api-8"],['text'=>"$n",'callback_data'=>"iA-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[4]}==========#
if($exdata[0] == "iB"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Di"){
if($app=="im" or $app=="vi" or $app=="fu" or $app=="au" or $app=="ot"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country3] as $zero=>$ar){
$API=str_replace(["Di"],["Vak-sms.com"],$api);
$b++;
if($b%3!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[5]}==========#
if($exdata[0] == "iC"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Ei"){
if($app=="au" or $app=="ot"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country4] as $zero=>$ar){
$API=str_replace(["Ei"],["sms-activate.io"],$api);
$b++;
if($b%3!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[6]}==========#
if($exdata[0] == "iD"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Fi"){
if($app=="fu" or $app=="au" or $app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country5];  }
if($con == 2) { $continent     = $_co[country5_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Fi"],["pvapins.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iD-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iD-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[7+10+20]}==========#
if($exdata[0] == "iE"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Gi"){
if($app=="ot"){
$app="NO";
}
}
if($api == "Ki"){
if($app=="fu" or $app=="au"){
$app="NO";
}
}
if($api == "Ui"){
if($app=="fu" or $app=="au"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country8] as $zero=>$ar){
$API=str_replace(["Gi","Ki","Ui"],["alnwerah.sms","viotp.com","sellotp.com"],$api);
$b++;
if($b%3!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addservice2-$app-$add-$api-$zero"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addservice2-$app-$add-$api-$zero"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[8]}==========#
if($exdata[0] == "iF"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Hi"){
if($app=="ot"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country6] as $zero=>$ar){
$API=str_replace(["Hi"],["onlinesim.io"],$api);
$b++;
if($b%3!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[9]}==========#
if($exdata[0] == "iG"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Ji"){
if($app=="au" or $app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country7];  }
if($con == 2) { $continent     = $_co[country7_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Ji"],["supersmstech.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addservice3-$app-$add-$api-$zero"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iG-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iG-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[11]}==========#
if($exdata[0] == "iH"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Li"){
if($app=="au" or $app=="ot"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country9] as $zero=>$ar){
$API=str_replace(["Li"],["simsms.org"],$api);
$b++;
if($b%3!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[12]}==========#
if($exdata[0] == "iJ"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Mi"){
if($app=="fu" or $app=="au" or $app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country10];  }
if($con == 2) { $continent     = $_co[country10_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Mi"],["grizzlysms.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iJ-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iJ-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[13]}==========#
if($exdata[0] == "iK"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Ni"){
if($app=="au"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country11];  }
if($con == 2) { $continent     = $_co[country11_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Ni"],["sms-code.ru"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iK-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iK-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[14]}==========#
if($exdata[0] == "iL"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Oi"){
if($app=="au"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country12];  }
if($con == 2) { $continent     = $_co[country12_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Oi"],["tiger-sms.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iL-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iL-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[15]}==========#
if($exdata[0] == "iM"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Pi"){
if($app=="im" or $app=="au"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country13];  }
if($con == 2) { $continent     = $_co[country13_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Pi"],["2ndline.io"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addservice2-$app-$add-$api-$zero"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iM-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iM-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[16]}==========#
if($exdata[0] == "iN"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Qi"){
if($app=="lf" or $app=="im" or $app=="fu" or $app=="au" or $app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country14];  }
if($con == 2) { $continent     = $_co[country14_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Qi"],["receivesms.store"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iN-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iN-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[17]}==========#
if($exdata[0] == "iO"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Ri"){
if($app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country15];  }
if($con == 2) { $continent     = $_co[country15_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Ri"],["sms.fastpva.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addservice4-$app-$add-$api-$zero"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iO-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iO-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[18]}==========#
if($exdata[0] == "iP"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
if($api == "Si"){
if($app=="tg" or $app=="tw" or $app=="lf" or $app=="go" or $app=="im" or $app=="vi" or $app=="fu" or $app=="nf" or $app=="au" or $app=="ot"){
$app="NO";
}
}
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}else{
$a=0;//keyboard
$b=0;//count
foreach ($_co[country16] as $zero=>$ar){
$API=str_replace(["Si"],["dropsms.ru"],$api);
$b++;
if($b%2!=0){
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}else{
$a++;//لنزول سطر
$key[inline_keyboard][$a][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
}
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
reply_markup=>json_encode($key)
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[19]}==========#
if($exdata[0] == "iQ"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($con == 1) { $continent     = $_co[country17];  }
if($con == 2) { $continent     = $_co[country17_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Ti"],["24sms7.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iQ-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iQ-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={country[21]}==========#
if($exdata[0] == "iR"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($api == "Vi"){
if($app=="ot"){
$app="NO";
}
}
if($con == 1) { $continent     = $_co[country18];  }
if($con == 2) { $continent     = $_co[country18_2];  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con == 1){
$t = "• التالي ⬅️ •";
}elseif($con == 2){
$s = "• السابق ⬅️ •";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $zero=>$ar){
$API=str_replace(["Vi"],["mm.durianrcs.com"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"addprice-$app-$add-$api-$zero-any"];
if($e%3 == 0) $i++;
}
$key[inline_keyboard][]=[['text'=>"$t",'callback_data'=>"iR-$app-$add-$api-2"],['text'=>"$s",'callback_data'=>"iR-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={countryall}==========#
if($exdata[0] == "iV"){
$app = $exdata[1];
$add = $exdata[2];
$api = $exdata[3];
$con=$exdata[4];
if($con==null){
$con=1;
}
if($con == 1) { $continent     = $AdminONE;  }
if($con == 2) { $continent     = $AdminTOW;  }
if($con == 3) { $continent     = $AdminTHREE;  }
if($con == 4) { $continent     = $AdminFOUR;  }
if($con == 5) { $continent     = $AdminFIVE;  }
if($con == 6) { $continent     = $AdminSIX;  }
if($con == 7) { $continent     = $AdminSEVEN;  }
if($con == 8) { $continent     = $AdminEIGHT;  }
if($app == 'NO'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
- عذرا مطوري هذا التطبيق غير متوفر للوقع المطلوب ❌
",
'show_alert'=>false
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($continent == null){
bot_store_delete("data/id/$id/step.txt");
}else{
if($con >= 8){
$u = $con-1;
}elseif($con >= 1){
$o = $con+1;
$u = $con-1;
}
if($con == 1){
$t = "• التالي ⬅️ •";
$s = null;
$v = "• الأخير ⬅️ •";
}elseif($con == 2 or $con == 3 or $con == 4 or $con == 5 or $con == 6 or $con == 7){
$t = "• التالي ⬅️ •";
$s = "• السابق ⬅️ •";
$v = null;
$n = null;
}elseif($con == 8){
$t = null;
$s = "• السابق ⬅️ •";
$n = "الأول. ⏭";
}
$i = 0;
$e = 0;
$key     = [];
foreach ($continent as $ar=>$zero){
$API = str_replace(["Ai","Bi","Ci","Di","Hi","Ji","Li","Mi","Ni","Si"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","onlinesim.io","supersmstech.com","simsms.org","grizzlysms.com","sms-code.ru","dropsms.ru"],$api);
$e++;
$key[inline_keyboard][$i][]=[text=>"$ar",callback_data=>"allservice-$app-$add-$api-$zero"];
if($e%3 == 0) $i++;
}
$key['inline_keyboard'][0] = [['text'=>'☑️ ⌯ جميع الدول في الأسفل ⬇️','callback_data'=>"no"]];
$key['inline_keyboard'][] = [['text'=>"$t",'callback_data'=>"iV-$app-$add-$api-$o"],['text'=>"$s",'callback_data'=>"iV-$app-$add-$api-$u"],['text'=>"$v",'callback_data'=>"iV-$app-$add-$api-8"],['text'=>"$n",'callback_data'=>"iV-$app-$add-$api-1"]];
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"Bj-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❇️ ⌯ هذة جميع الدول المتوفرة في الموقع $API*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={servicall}==========#
if($exdata[0] == "allservice" and $addblusdel['System'] == "direct"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
$apps = str_replace(["B","C","D","E","F","G","H","I","J","K","L","M","N"],["whatsapp","telegram","facebook","instagram","twitter","tiktok","google","imo","viber","snapchat","netflix","haraj","other"],$status);
$APP = str_replace(["B","C","D","E","F","G","H","I","J","K","L","M","N"],["واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام"],$status);
$app_5sim = str_replace(["B","C","D","E","F","G","H","I","J","K","L","M","N"],["whatsapp","telegram","facebook","instagram","twitter","tiktok","google","imo","viber","snapchat","netflix","haraj","other"],$status);#5sim
$APP_S = str_replace(["wa","tg","fb","ig","tw","lf","go","im","vi","fu","nf","au","ot"],["WhatsApp","Telegram","Facebook","Instagram","Twitter","Tiktok","Google","Imo","Viber","Snapchat","Netflix","Haraj","Other"],$app);#APP
$name = $_co['country'][$country];
$zx = $o_co['country'][$country];
$all=json_decode(file_get_contents("https://5sim.biz/v1/guest/prices?country=$country&product=$app_5sim"),1);
$api="Ai";
$site="5sim";
$rupl=$addblusdel[$site]['rupl'];
$opt = "019";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
$api_key = $APPS[$site][api_key];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=94;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "activ";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=95;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "altel";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=96;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "beeline";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=97;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "claro";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=98;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "ee";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=99;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "globe";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=100;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "kcell";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=101;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "lycamobile";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=102;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "matrix";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=103;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "megafon";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=104;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "mts";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=105;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "orange";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=106;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "pildyk";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=107;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "play";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=108;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "redbullmobile";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=109;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "rostelecom";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=110;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "smart";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=111;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "sun";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=112;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "tele2";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=113;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "three";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=114;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "tigo";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=115;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "tmobile";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=116;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "tnt";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=117;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virginmobile";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=118;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual2";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=119;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual4";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=120;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual5";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=121;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual7";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=122;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual8";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=123;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual12";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=124;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual15";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=125;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual16";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=126;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual17";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=127;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual18";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=128;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual19";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=129;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual20";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=130;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual21";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=131;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual22";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=132;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual23";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=133;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual24";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=134;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual25";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=135;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual26";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=136;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual27";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=137;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual28";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=138;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual29";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=139;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual30";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=140;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual31";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=141;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual32";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=142;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual33";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=143;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual34";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=144;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual35";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=145;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual36";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=146;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "virtual37";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=147;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "vodafone";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=148;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "yota";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=149;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$opt = "zz";
$adds=$all[$zx][$app_5sim][$opt][count];
$price=$all[$zx][$app_5sim][$opt][cost];
if($api_key != null and $adds >= 3 and $price> 0 and $addblusdel['5sim']['add'] == "ok"){
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$operator=150;
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Bi";
$site="alisms";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=1;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $adds >= 5 and $price > 0 and $addblusdel['alisms']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ci";
$site="man";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=2;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $adds >= 10 and $price > 0 and $addblusdel['man']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
$buy['number'][$code]['app'] = $app;
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Di";
$site="vak";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=3;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $adds >= 10 and $price > 0 and $o_co['country3'][$country] != null and $addblusdel['vak']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Hi";
$site="onlinesim";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=6;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $adds >= 10 and $price > 0 and $o_co['country6'][$country] != null and $addblusdel['onlinesim']['add'] == "ok"){
$price=$price*$Exchange;
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ji";
$site="supersmstechtech";
$opt = "1";
$api_key = $APPS[$site][api_key];
$operator=48;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country7'][$country] != null and $addblusdel['supersmstech']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ji";
$site="supersmstechtech";
$opt = "3";
$api_key = $APPS[$site][api_key];
$operator=49;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country7'][$country] != null and $addblusdel['supersmstech']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ji";
$site="supersmstechtech";
$opt = "4";
$api_key = $APPS[$site][api_key];
$operator=50;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country7'][$country] != null and $addblusdel['supersmstech']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ji";
$site="supersmstechtech";
$opt = "5";
$api_key = $APPS[$site][api_key];
$operator=51;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country7'][$country] != null and $addblusdel['supersmstech']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ji";
$site="supersmstechtech";
$opt = "7";
$api_key = $APPS[$site][api_key];
$operator=52;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country7'][$country] != null and $addblusdel['supersmstech']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Li";
$site="simsms";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=7;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country9'][$country] != null and $addblusdel['simsms']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Mi";
$site="grizzly";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=8;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country10'][$country] != null and $addblusdel['grizzly']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ni";
$site="smscode";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=9;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $price > 0 and $o_co['country11'][$country] != null and $addblusdel['smscode']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Oi";
$site="tiger";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=10;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
$nams=$_co['country12'][$country];
if($nams == null){
$nams = $_co['country12_2'][$country];
}
if($api_key != null and $price > 0 and $nams != null and $addblusdel['tiger']['add'] == "ok"){
$price=$price*$Exchange;
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Si";
$site="dropsms";
$opt = "any";
$api_key = $APPS[$site][api_key];
$operator=12;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
if($api_key != null and $price > 0 and $addblusdel['dropsms']['add'] == "ok"){
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$price="$price";
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
$api="Ti";
$site="24sms7";
$opt = "any";
$api_key = $APPS[$site][api_key];
$zx = $o_co['country17'][$country];
$operator=13;
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
$price=$api_price[price];
$adds=$api_price[add];
if($api_key != null and $adds >= 5 and $price > 0 and $o_co['country17'][$country] != null and $addblusdel['24sms7']['add'] == "ok"){
$price=$price*$Exchange;
$rupl=$addblusdel[$site]['rupl'];
$price=$rupl+$price;
$exn=explode(".", $price);
if($exn[1] > 0){
$price="$exn[0].5";
}else{
$price="$exn[0]";
}
$code = "$country$app$operator$add";
$code = md5($code);
unset($buy['number'][$code]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "direct";
$buy['number'][$code]['price'] = $price;
if($buy['number'][$code] == null){
$buy['country_app']["$add-$country"] += 1;
}
$i++;
Addserver($buy);
}
if(count($buy['country_app']["$add-$country"]) >= 1){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
☑️ تم تحديث الدولة بنجاح
💠 الدولة: $name
♻️ التطبيق: $APP
🧿 عدد السيرفرات: $i
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
✅ تم إضافة الدولة بنجاح
💠 الدولة: $name
♻️ التطبيق: $APP
🧿 عدد السيرفرات: $i
",
'show_alert'=>true
]);
exit;
}
}
#=========={alnwerah.sms & viotp.com & 2ndline.io and sellotp.com}==========#
if($exdata[0] == "addservice2"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$API = str_replace(["Gi","Ki","Pi","Ui"],["alnwerah.sms","viotp.com","2ndline.io","sellotp.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$name = $_co['country'][$country];
if($api == "Gi"){
$key[inline_keyboard][]=[['text'=>"any",'callback_data'=>"addprice-$app-$add-$api-$country-any"]];
$key[inline_keyboard][]=[['text'=>"mobifone",'callback_data'=>"addprice-$app-$add-$api-$country-mobifone"]];
$key[inline_keyboard][]=[['text'=>"vietnamobile",'callback_data'=>"addprice-$app-$add-$api-$country-vietnamobile"]];
$key[inline_keyboard][]=[['text'=>"viettel",'callback_data'=>"addprice-$app-$add-$api-$country-viettel"]];
$key[inline_keyboard][]=[['text'=>"vinaphone",'callback_data'=>"addprice-$app-$add-$api-$country-vinaphone"]];
}elseif($api == "Ki"){
$key[inline_keyboard][]=[['text'=>"MOBIFONE",'callback_data'=>"addprice-$app-$add-$api-$country-MOBIFONE"]];
$key[inline_keyboard][]=[['text'=>"VINAPHONE",'callback_data'=>"addprice-$app-$add-$api-$country-VINAPHONE"]];
$key[inline_keyboard][]=[['text'=>"VIETTEL",'callback_data'=>"addprice-$app-$add-$api-$country-VIETTEL"]];
$key[inline_keyboard][]=[['text'=>"VIETNAMOBILE",'callback_data'=>"addprice-$app-$add-$api-$country-VIETNAMOBILE"]];
$key[inline_keyboard][]=[['text'=>"ITELECOM",'callback_data'=>"addprice-$app-$add-$api-$country-ITELECOM"]];
}elseif($api == "Pi"){
$key[inline_keyboard][]=[['text'=>"Operator",'callback_data'=>"addprice-$app-$add-$api-$country-0"]];
$key[inline_keyboard][]=[['text'=>"Viettel Group",'callback_data'=>"addprice-$app-$add-$api-$country-1"]];
$key[inline_keyboard][]=[['text'=>"MobiFone",'callback_data'=>"addprice-$app-$add-$api-$country-2"]];
$key[inline_keyboard][]=[['text'=>"VinaPhone",'callback_data'=>"addprice-$app-$add-$api-$country-3"]];
$key[inline_keyboard][]=[['text'=>"Vietnamobile",'callback_data'=>"addprice-$app-$add-$api-$country-4"]];
$key[inline_keyboard][]=[['text'=>"Itelecom",'callback_data'=>"addprice-$app-$add-$api-$country-5"]];
$key[inline_keyboard][]=[['text'=>"Three",'callback_data'=>"addprice-$app-$add-$api-$country-6"]];
$key[inline_keyboard][]=[['text'=>"Indosat Ooredoo",'callback_data'=>"addprice-$app-$add-$api-$country-8"]];
$key[inline_keyboard][]=[['text'=>"Telkomsel",'callback_data'=>"addprice-$app-$add-$api-$country-9"]];
$key[inline_keyboard][]=[['text'=>"Axis",'callback_data'=>"addprice-$app-$add-$api-$country-10"]];
$key[inline_keyboard][]=[['text'=>"Lebara",'callback_data'=>"addprice-$app-$add-$api-$country-11"]];
$key[inline_keyboard][]=[['text'=>"Vodafone Group",'callback_data'=>"addprice-$app-$add-$api-$country-12"]];
$key[inline_keyboard][]=[['text'=>"Smart Telecom",'callback_data'=>"addprice-$app-$add-$api-$country-13"]];
$key[inline_keyboard][]=[['text'=>"DTAC",'callback_data'=>"addprice-$app-$add-$api-$country-14"]];
$key[inline_keyboard][]=[['text'=>"TrueMove H",'callback_data'=>"addprice-$app-$add-$api-$country-15"]];
$key[inline_keyboard][]=[['text'=>"ETL Lao",'callback_data'=>"addprice-$app-$add-$api-$country-16"]];
$key[inline_keyboard][]=[['text'=>"Lao Tel",'callback_data'=>"addprice-$app-$add-$api-$country-17"]];
}elseif($api == "Ui"){
$key[inline_keyboard][]=[['text'=>"MOBIFONE",'callback_data'=>"addprice-$app-$add-$api-$country-MOBIFONE"]];
$key[inline_keyboard][]=[['text'=>"VINAPHONE",'callback_data'=>"addprice-$app-$add-$api-$country-VINAPHONE"]];
$key[inline_keyboard][]=[['text'=>"VIETTEL",'callback_data'=>"addprice-$app-$add-$api-$country-VIETTEL"]];
$key[inline_keyboard][]=[['text'=>"VIETNAMOBILE",'callback_data'=>"addprice-$app-$add-$api-$country-VIETNAMOBILE"]];
$key[inline_keyboard][]=[['text'=>"ITELECOM",'callback_data'=>"addprice-$app-$add-$api-$country-ITELECOM"]];
}
$key[inline_keyboard][]=[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"iE-$app-$add-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
🌐 ⌯ الموقع: $API*
*🅿️ ⌯ قم ب إختيار السيرفر لهذه الدولة ⏬*
",
'parse_mode'=>'Markdown',
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={servicsuper}==========#
if($exdata[0] == "addservice3"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$API = str_replace(["Ji"],["supersmstech.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$name = $_co['country'][$country];
if($app != null){
$serv1 = "Super Channel (Global)";
}
if($app != null){
$serv2 = "Channel 3 (Global)";
}
if($app == "wa" or $app == "tg" or $app == "lf" or $app == "go" or $app == "im" or $app == "vi"){
$serv3 = "Channel 4 (ChatGPT, TG)";
}
if($app == "tg" or $app == "fb" or $app == "ig" or $app == "tw" or $app == "go" or $app == "vi" or $app == "nf"){
$serv4 = "Channel 5 (Global)";
}
if($app != null){
$serv5 = "Channel 7 (Global)";
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
🌐 ⌯ الموقع: $API*
*🅿️ ⌯ قم ب إختيار السيرفر لهذه الدولة ⏬*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$serv1",'callback_data'=>"addprice-$app-$add-$api-$country-1"]],
[['text'=>"$serv2",'callback_data'=>"addprice-$app-$add-$api-$country-3"]],
[['text'=>"$serv3",'callback_data'=>"addprice-$app-$add-$api-$country-4"]],
[['text'=>"$serv4",'callback_data'=>"addprice-$app-$add-$api-$country-5"]],
[['text'=>"$serv5",'callback_data'=>"addprice-$app-$add-$api-$country-7"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"iG-$app-$add-$api"]]
]
])
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={fastpva}==========#
if($exdata[0] == "addservice4"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$API = str_replace(["Ri"],["sms.fastpva.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$name = $_co['country'][$country];
if($app == "wa"){
$key[inline_keyboard][]=[['text'=>"Whatsapp-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3819"]];
$key[inline_keyboard][]=[['text'=>"Whatsapp-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3543"]];
}
if($app == "tg"){
$key[inline_keyboard][]=[['text'=>"Telegram Login-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3528"]];
$key[inline_keyboard][]=[['text'=>"Telegram-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3328"]];
$key[inline_keyboard][]=[['text'=>"Telegram-C2",'callback_data'=>"addprice-$app-$add-$api-$country-2001"]];
}
if($app == "fb"){
$key[inline_keyboard][]=[['text'=>"facebook-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3825"]];
$key[inline_keyboard][]=[['text'=>"Facebook Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3521"]];
$key[inline_keyboard][]=[['text'=>"Facebook-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3326"]];
$key[inline_keyboard][]=[['text'=>"Facebook1-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2940"]];
$key[inline_keyboard][]=[['text'=>"Facebook新-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2919"]];
}
if($app == "gi"){
$key[inline_keyboard][]=[['text'=>"Instagram-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3832"]];
$key[inline_keyboard][]=[['text'=>"Instagram Reg-C4",'callback_data'=>"addprice-$app-$add-$api-$country-3744"]];
$key[inline_keyboard][]=[['text'=>"Instagram Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3375"]];
$key[inline_keyboard][]=[['text'=>"Instagram-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3262"]];
$key[inline_keyboard][]=[['text'=>"Instagram-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3128"]];
}
if($app == "tw"){
$key[inline_keyboard][]=[['text'=>"Twitter-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3826"]];
$key[inline_keyboard][]=[['text'=>"Twitter Reg-C4",'callback_data'=>"addprice-$app-$add-$api-$country-3749"]];
$key[inline_keyboard][]=[['text'=>"Twitter Login-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3484"]];
$key[inline_keyboard][]=[['text'=>"Twitter-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3333"]];
$key[inline_keyboard][]=[['text'=>"Twitter -C3",'callback_data'=>"addprice-$app-$add-$api-$country-3302"]];
$key[inline_keyboard][]=[['text'=>"Twitter-C2",'callback_data'=>"addprice-$app-$add-$api-$country-2003"]];
}
if($app == "lf"){
$key[inline_keyboard][]=[['text'=>"Tiktok-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3475"]];
}
if($app == "go"){
$key[inline_keyboard][]=[['text'=>"Gmail/Google Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3391"]];
$key[inline_keyboard][]=[['text'=>"Google/Gmail-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3335"]];
$key[inline_keyboard][]=[['text'=>"Google Pay-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2012"]];
}
if($app == "im"){
$key[inline_keyboard][]=[['text'=>"Imo-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3862"]];
$key[inline_keyboard][]=[['text'=>"Imo-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3385"]];
$key[inline_keyboard][]=[['text'=>"Imo-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2624"]];
}
if($app == "vi"){
$key[inline_keyboard][]=[['text'=>"Viber-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3820"]];
$key[inline_keyboard][]=[['text'=>"Viber Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3497"]];
$key[inline_keyboard][]=[['text'=>"Viber-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3312"]];
}
if($app == "fu"){
$key[inline_keyboard][]=[['text'=>"Snapchat-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3890"]];
$key[inline_keyboard][]=[['text'=>"Snapchat Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3392"]];
$key[inline_keyboard][]=[['text'=>"Snapchat-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2866"]];
}
if($app == "nf"){
$key[inline_keyboard][]=[['text'=>"Netflix-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3858"]];
$key[inline_keyboard][]=[['text'=>"Netflix Reg-C2",'callback_data'=>"addprice-$app-$add-$api-$country-3515"]];
$key[inline_keyboard][]=[['text'=>"Netflix-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3191"]];
$key[inline_keyboard][]=[['text'=>"Netflix-C3",'callback_data'=>"addprice-$app-$add-$api-$country-3189"]];
}
if($app == "au"){
$key[inline_keyboard][]=[['text'=>"Haraj-C6",'callback_data'=>"addprice-$app-$add-$api-$country-3999"]];
$key[inline_keyboard][]=[['text'=>"Haraj-C3",'callback_data'=>"addprice-$app-$add-$api-$country-2929"]];
}
$key[inline_keyboard][]=[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"iO-$app-$add-$api"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
🌐 ⌯ الموقع: $API*
*🅿️ ⌯ قم ب إختيار السيرفر لهذه الدولة ⏬*
",
'parse_mode'=>'Markdown',
'reply_markup'=>($keyboad),
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={servic5sim}==========#
if($exdata[0] == "addservice"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$API = str_replace(["Ai"],["5sim.biz"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$name = $_co['country'][$country];
$zx = $o_co['country'][$country];
$app_5sim = str_replace(["wa","tg","fb","ig","tw","lf","go","im","vi","fu","nf","au","ot"],["whatsapp","telegram","facebook","instagram","twitter","tiktok","google","imo","viber","snapchat","netflix","haraj","other"],$app);
$all=json_decode(file_get_contents("https://5sim.biz/v1/guest/prices?country=$country&product=$app_5sim"),1);
$adds=$all[$zx][$app_5sim]["019"][count];
$price=$all[$zx][$app_5sim]["019"][cost];
if($price != null){
$bee019 = "019";
$bee0191 = "$price ₽| $adds";
}else{
$bee019 = null;
$bee0191 = null;
}
$adds=$all[$zx][$app_5sim]["activ"][count];
$price=$all[$zx][$app_5sim]["activ"][cost];
if($price != null){
$activ = "activ";
$activ1 = "$price ₽| $adds";
}else{
$activ = null;
$activ1 = null;
}
$adds=$all[$zx][$app_5sim]["altel"][count];
$price=$all[$zx][$app_5sim]["altel"][cost];
if($price != null){
$altel = "altel";
$altel1 = "$price ₽| $adds";
}else{
$altel = null;
$altel1 = null;
}
$adds=$all[$zx][$app_5sim]["beeline"][count];
$price=$all[$zx][$app_5sim]["beeline"][cost];
if($price != null){
$beeline = "beeline";
$beeline1 = "$price ₽| $adds";
}else{
$beeline = null;
$beeline1 = null;
}
$adds=$all[$zx][$app_5sim]["claro"][count];
$price=$all[$zx][$app_5sim]["claro"][cost];
if($price != null){
$claro = "claro";
$claro1 = "$price ₽| $adds";
}else{
$claro = null;
$claro1 = null;
}
$adds=$all[$zx][$app_5sim]["ee"][count];
$price=$all[$zx][$app_5sim]["ee"][cost];
if($price != null){
$ee = "ee";
$ee1 = "$price ₽| $adds";
}else{
$ee = null;
$ee1 = null;
}
$adds=$all[$zx][$app_5sim]["globe"][count];
$price=$all[$zx][$app_5sim]["globe"][cost];
if($price != null){
$globe = "globe";
$globe1 = "$price ₽| $adds";
}else{
$globe = null;
$globe1 = null;
}
$adds=$all[$zx][$app_5sim]["kcell"][count];
$price=$all[$zx][$app_5sim]["kcell"][cost];
if($price != null){
$kcell = "kcell";
$kcell1 = "$price ₽| $adds";
}else{
$kcell = null;
$kcell1 = null;
}
$adds=$all[$zx][$app_5sim]["lycamobile"][count];
$price=$all[$zx][$app_5sim]["lycamobile"][cost];
if($price != null){
$lycamobile = "lycamobile";
$lycamobile1 = "$price ₽| $adds";
}else{
$lycamobile = null;
$lycamobile1 = null;
}
$adds=$all[$zx][$app_5sim]["matrix"][count];
$price=$all[$zx][$app_5sim]["matrix"][cost];
if($price != null){
$matrix = "matrix";
$matrix1 = "$price ₽| $adds";
}else{
$matrix = null;
$matrix1 = null;
}
$adds=$all[$zx][$app_5sim]["megafon"][count];
$price=$all[$zx][$app_5sim]["megafon"][cost];
if($price != null){
$megafon = "megafon";
$megafon1 = "$price ₽| $adds";
}else{
$megafon = null;
$megafon1 = null;
}
$adds=$all[$zx][$app_5sim]["mts"][count];
$price=$all[$zx][$app_5sim]["mts"][cost];
if($price != null){
$mts = "mts";
$mts1 = "$price ₽| $adds";
}else{
$mts = null;
$mts1 = null;
}
$adds=$all[$zx][$app_5sim]["orange"][count];
$price=$all[$zx][$app_5sim]["orange"][cost];
if($price != null){
$orange = "orange";
$orange1 = "$price ₽| $adds";
}else{
$orange = null;
$orange1 = null;
}
$adds=$all[$zx][$app_5sim]["pildyk"][count];
$price=$all[$zx][$app_5sim]["pildyk"][cost];
if($price != null){
$pildyk = "pildyk";
$pildyk1 = "$price ₽| $adds";
}else{
$pildyk = null;
$pildyk1 = null;
}
$adds=$all[$zx][$app_5sim]["play"][count];
$price=$all[$zx][$app_5sim]["play"][cost];
if($price != null){
$play = "play";
$play1 = "$price ₽| $adds";
}else{
$play = null;
$play1 = null;
}
$adds=$all[$zx][$app_5sim]["redbullmobile"][count];
$price=$all[$zx][$app_5sim]["redbullmobile"][cost];
if($price != null){
$redbullmobile = "redbullmobile";
$redbullmobile1 = "$price ₽| $adds";
}else{
$redbullmobile = null;
$redbullmobile1 = null;
}
$adds=$all[$zx][$app_5sim]["rostelecom"][count];
$price=$all[$zx][$app_5sim]["rostelecom"][cost];
if($price != null){
$rostelecom = "rostelecom";
$rostelecom1 = "$price ₽| $adds";
}else{
$rostelecom = null;
$rostelecom1 = null;
}
$adds=$all[$zx][$app_5sim]["smart"][count];
$price=$all[$zx][$app_5sim]["smart"][cost];
if($price != null){
$smart = "smart";
$smart1 = "$price ₽| $adds";
}else{
$smart = null;
$smart1 = null;
}
$adds=$all[$zx][$app_5sim]["sun"][count];
$price=$all[$zx][$app_5sim]["sun"][cost];
if($price != null){
$sun = "sun";
$sun1 = "$price ₽| $adds";
}else{
$sun = null;
$sun1 = null;
}
$adds=$all[$zx][$app_5sim]["tele2"][count];
$price=$all[$zx][$app_5sim]["tele2"][cost];
if($price != null){
$tele2 = "tele2";
$tele21 = "$price ₽| $adds";
}else{
$tele2 = null;
$tele21 = null;
}
$adds=$all[$zx][$app_5sim]["three"][count];
$price=$all[$zx][$app_5sim]["three"][cost];
if($price != null){
$three = "three";
$three1 = "$price ₽| $adds";
}else{
$three = null;
$three1 = null;
}
$adds=$all[$zx][$app_5sim]["tigo"][count];
$price=$all[$zx][$app_5sim]["tigo"][cost];
if($price != null){
$tigo = "tigo";
$tigo1 = "$price ₽| $adds";
}else{
$tigo = null;
$tigo1 = null;
}
$adds=$all[$zx][$app_5sim]["tmobile"][count];
$price=$all[$zx][$app_5sim]["tmobile"][cost];
if($price != null){
$tmobile = "tmobile";
$tmobile1 = "$price ₽| $adds";
}else{
$tmobile = null;
$tmobile1 = null;
}
$adds=$all[$zx][$app_5sim]["tnt"][count];
$price=$all[$zx][$app_5sim]["tnt"][cost];
if($price != null){
$tnt = "tnt";
$tnt1 = "$price ₽| $adds";
}else{
$tnt = null;
$tnt1 = null;
}
$adds=$all[$zx][$app_5sim]["virginmobile"][count];
$price=$all[$zx][$app_5sim]["virginmobile"][cost];
if($price != null){
$virginmobile = "virginmobile";
$virginmobile1 = "$price ₽| $adds";
}else{
$virginmobile = null;
$virginmobile1 = null;
}
$adds=$all[$zx][$app_5sim]["virtual2"][count];
$price=$all[$zx][$app_5sim]["virtual2"][cost];
if($price != null){
$virtual2 = "virtual2";
$virtual21 = "$price ₽| $adds";
}else{
$virtual2 = null;
$virtual21 = null;
}
$adds=$all[$zx][$app_5sim]["virtual4"][count];
$price=$all[$zx][$app_5sim]["virtual4"][cost];
if($price != null){
$virtual4 = "virtual4";
$virtual41 = "$price ₽| $adds";
}else{
$virtual4 = null;
$virtual41 = null;
}
$adds=$all[$zx][$app_5sim]["virtual5"][count];
$price=$all[$zx][$app_5sim]["virtual5"][cost];
if($price != null){
$virtual5 = "virtual5";
$virtual51 = "$price ₽| $adds";
}else{
$virtual5 = null;
$virtual51 = null;
}
$adds=$all[$zx][$app_5sim]["virtual7"][count];
$price=$all[$zx][$app_5sim]["virtual7"][cost];
if($price != null){
$virtual7 = "virtual7";
$virtual71 = "$price ₽| $adds";
}else{
$virtual7 = null;
$virtual71 = null;
}
$adds=$all[$zx][$app_5sim]["virtual8"][count];
$price=$all[$zx][$app_5sim]["virtual8"][cost];
if($price != null){
$virtual8 = "virtual8";
$virtual81 = "$price ₽| $adds";
}else{
$virtual8 = null;
$virtual81 = null;
}
$adds=$all[$zx][$app_5sim]["virtual12"][count];
$price=$all[$zx][$app_5sim]["virtual12"][cost];
if($price != null){
$virtual12 = "virtual12";
$virtual121 = "$price ₽| $adds";
}else{
$virtual12 = null;
$virtual121 = null;
}
$adds=$all[$zx][$app_5sim]["virtual15"][count];
$price=$all[$zx][$app_5sim]["virtual15"][cost];
if($price != null){
$virtual15 = "virtual15";
$virtual151 = "$price ₽| $adds";
}else{
$virtual15 = null;
$virtual151 = null;
}
$adds=$all[$zx][$app_5sim]["virtual16"][count];
$price=$all[$zx][$app_5sim]["virtual16"][cost];
if($price != null){
$virtual16 = "virtual16";
$virtual161 = "$price ₽| $adds";
}else{
$virtual16 = null;
$virtual161 = null;
}
$adds=$all[$zx][$app_5sim]["virtual17"][count];
$price=$all[$zx][$app_5sim]["virtual17"][cost];
if($price != null){
$virtual17 = "virtual17";
$virtual171 = "$price ₽| $adds";
}else{
$virtual17 = null;
$virtual171 = null;
}
$adds=$all[$zx][$app_5sim]["virtual18"][count];
$price=$all[$zx][$app_5sim]["virtual18"][cost];
if($price != null){
$virtual18 = "virtual18";
$virtual181 = "$price ₽| $adds";
}else{
$virtual18 = null;
$virtual181 = null;
}
$adds=$all[$zx][$app_5sim]["virtual19"][count];
$price=$all[$zx][$app_5sim]["virtual19"][cost];
if($price != null){
$virtual19 = "virtual19";
$virtual191 = "$price ₽| $adds";
}else{
$virtual19 = null;
$virtual191 = null;
}
$adds=$all[$zx][$app_5sim]["virtual20"][count];
$price=$all[$zx][$app_5sim]["virtual20"][cost];
if($price != null){
$virtual20 = "virtual20";
$virtual201 = "$price ₽| $adds";
}else{
$virtual20 = null;
$virtual201 = null;
}
$adds=$all[$zx][$app_5sim]["virtual21"][count];
$price=$all[$zx][$app_5sim]["virtual21"][cost];
if($price != null){
$virtual21a = "virtual21";
$virtual211 = "$price ₽| $adds";
}else{
$virtual21a = null;
$virtual211 = null;
}
$adds=$all[$zx][$app_5sim]["virtual22"][count];
$price=$all[$zx][$app_5sim]["virtual22"][cost];
if($price != null){
$virtual22 = "virtual22";
$virtual221 = "$price ₽| $adds";
}else{
$virtual22 = null;
$virtual221 = null;
}
$adds=$all[$zx][$app_5sim]["virtual23"][count];
$price=$all[$zx][$app_5sim]["virtual23"][cost];
if($price != null){
$virtual23 = "virtual23";
$virtual231 = "$price ₽| $adds";
}else{
$virtual23 = null;
$virtual231 = null;
}
$adds=$all[$zx][$app_5sim]["virtual24"][count];
$price=$all[$zx][$app_5sim]["virtual24"][cost];
if($price != null){
$virtual24 = "virtual24";
$virtual241 = "$price ₽| $adds";
}else{
$virtual24 = null;
$virtual241 = null;
}
$adds=$all[$zx][$app_5sim]["virtual25"][count];
$price=$all[$zx][$app_5sim]["virtual25"][cost];
if($price != null){
$virtual25 = "virtual25";
$virtual251 = "$price ₽| $adds";
}else{
$virtual25 = null;
$virtual251 = null;
}
$adds=$all[$zx][$app_5sim]["virtual26"][count];
$price=$all[$zx][$app_5sim]["virtual26"][cost];
if($price != null){
$virtual26 = "virtual26";
$virtual261 = "$price ₽| $adds";
}else{
$virtual26 = null;
$virtual261 = null;
}
$adds=$all[$zx][$app_5sim]["virtual27"][count];
$price=$all[$zx][$app_5sim]["virtual27"][cost];
if($price != null){
$virtual27 = "virtual27";
$virtual271 = "$price ₽| $adds";
}else{
$virtual27 = null;
$virtual271 = null;
}
$adds=$all[$zx][$app_5sim]["virtual28"][count];
$price=$all[$zx][$app_5sim]["virtual28"][cost];
if($price != null){
$virtual28 = "virtual28";
$virtual281 = "$price ₽| $adds";
}else{
$virtual28 = null;
$virtual281 = null;
}
$adds=$all[$zx][$app_5sim]["virtual29"][count];
$price=$all[$zx][$app_5sim]["virtual29"][cost];
if($price != null){
$virtual29 = "virtual29";
$virtual291 = "$price ₽| $adds";
}else{
$virtual29 = null;
$virtual291 = null;
}
$adds=$all[$zx][$app_5sim]["virtual30"][count];
$price=$all[$zx][$app_5sim]["virtual30"][cost];
if($price != null){
$virtual30 = "virtual30";
$virtual301 = "$price ₽| $adds";
}else{
$virtual30 = null;
$virtual301 = null;
}
$adds=$all[$zx][$app_5sim]["virtual31"][count];
$price=$all[$zx][$app_5sim]["virtual31"][cost];
if($price != null){
$virtual31 = "virtual31";
$virtual311 = "$price ₽| $adds";
}else{
$virtual31 = null;
$virtual311 = null;
}
$adds=$all[$zx][$app_5sim]["virtual32"][count];
$price=$all[$zx][$app_5sim]["virtual32"][cost];
if($price != null){
$virtual32 = "virtual32";
$virtual321 = "$price ₽| $adds";
}else{
$virtual32 = null;
$virtual321 = null;
}
$adds=$all[$zx][$app_5sim]["virtual33"][count];
$price=$all[$zx][$app_5sim]["virtual33"][cost];
if($price != null){
$virtual33 = "virtual33";
$virtual331 = "$price ₽| $adds";
}else{
$virtual33 = null;
$virtual331 = null;
}
$adds=$all[$zx][$app_5sim]["virtual34"][count];
$price=$all[$zx][$app_5sim]["virtual34"][cost];
if($price != null){
$virtual34 = "virtual34";
$virtual341 = "$price ₽| $adds";
}else{
$virtual34 = null;
$virtual341 = null;
}
$adds=$all[$zx][$app_5sim]["virtual35"][count];
$price=$all[$zx][$app_5sim]["virtual35"][cost];
if($price != null){
$virtual35 = "virtual35";
$virtual351 = "$price ₽| $adds";
}else{
$virtual35 = null;
$virtual351 = null;
}
$adds=$all[$zx][$app_5sim]["virtual36"][count];
$price=$all[$zx][$app_5sim]["virtual36"][cost];
if($price != null){
$virtual36 = "virtual36";
$virtual361 = "$price ₽| $adds";
}else{
$virtual36 = null;
$virtual361 = null;
}
$adds=$all[$zx][$app_5sim]["virtual37"][count];
$price=$all[$zx][$app_5sim]["virtual37"][cost];
if($price != null){
$virtual37 = "virtual37";
$virtual371 = "$price ₽| $adds";
}else{
$virtual37 = null;
$virtual371 = null;
}
$adds=$all[$zx][$app_5sim]["vodafone"][count];
$price=$all[$zx][$app_5sim]["vodafone"][cost];
if($price != null){
$vodafone = "vodafone";
$vodafone1 = "$price ₽| $adds";
}else{
$vodafone = null;
$vodafone1 = null;
}
$adds=$all[$zx][$app_5sim]["yota"][count];
$price=$all[$zx][$app_5sim]["yota"][cost];
if($price != null){
$yota = "yota";
$yota1 = "$price ₽| $adds";
}else{
$yota = null;
$yota1 = null;
}
$adds=$all[$zx][$app_5sim]["zz"][count];
$price=$all[$zx][$app_5sim]["zz"][cost];
if($price != null){
$zz = "zz";
$zz1 = "$price ₽| $adds";
}else{
$zz = null;
$zz1 = null;
}
if($zz == null and $yota == null and $vodafone == null and $virtual32 == null and $virtual31 == null and $virtual30 == null and $virtual29 == null and $virtual28 == null and $virtual27 == null and $virtual26 == null and $virtual25 == null and $virtual24 == null and $virtual23 == null and $virtual22 == null and $virtual21a == null and $virtual20 == null and $virtual19 == null and $virtual18 == null and $virtual17 == null and $virtual16 == null and $virtual15 == null and $virtual12 == null and $virtual8 == null and $virtual7 == null and $virtual5 == null and $virtual4 == null and $virtual2 == null and $virginmobile == null and $tnt == null and $tmobile == null and $tigo == null and $three == null and $tele2 == null and $sun == null and $smart == null and $rostelecom == null and $redbullmobile == null and $play == null and $pildyk == null and $orange == null and $mts == null and $megafon == null and $matrix == null and $lycamobile == null and $kcell == null and $globe == null and $ee == null and $claro == null and $beeline == null and $altel == null and $activ == null and $bee019 == null){
$adds=json_decode(file_get_contents("https://5sim.biz/v1/guest/products/".$zx."/".any))->{$app_5sim}->Qty;
$price=json_decode(file_get_contents("https://5sim.biz/v1/guest/products/".$zx."/".any))->{$app_5sim}->Price;
if($price != null){
$any = "any";
$any1 = "$price ₽| $adds";
}else{
$any = null;
$any1 = null;
}
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
🌐 ⌯ الموقع: $API*
*🅿️ ⌯ قم ب إختيار السيرفر لهذه الدولة ⏬*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$any1",'callback_data'=>"addprice-$app-$add-$api-$country-$any"],['text'=>"$any",'callback_data'=>"addprice-$app-$add-$api-$country-$any"]],
[['text'=>"$bee0191",'callback_data'=>"addprice-$app-$add-$api-$country-$bee019"],['text'=>"$bee019",'callback_data'=>"addprice-$app-$add-$api-$country-$bee019"]],
[['text'=>"$activ1",'callback_data'=>"addprice-$app-$add-$api-$country-$activ"],['text'=>"$activ",'callback_data'=>"addprice-$app-$add-$api-$country-$activ"]],
[['text'=>"$altel1",'callback_data'=>"addprice-$app-$add-$api-$country-$altel"],['text'=>"$altel",'callback_data'=>"addprice-$app-$add-$api-$country-$altel"]],
[['text'=>"$beeline1",'callback_data'=>"addprice-$app-$add-$api-$country-$beeline"],['text'=>"$beeline",'callback_data'=>"addprice-$app-$add-$api-$country-$beeline"]],
[['text'=>"$claro1",'callback_data'=>"addprice-$app-$add-$api-$country-$claro"],['text'=>"$claro",'callback_data'=>"addprice-$app-$add-$api-$country-$claro"]],
[['text'=>"$ee1",'callback_data'=>"addprice-$app-$add-$api-$country-$ee"],['text'=>"$ee",'callback_data'=>"addprice-$app-$add-$api-$country-$ee"]],
[['text'=>"$globe1",'callback_data'=>"addprice-$app-$add-$api-$country-$globe"],['text'=>"$globe",'callback_data'=>"addprice-$app-$add-$api-$country-$globe"]],
[['text'=>"$kcell1",'callback_data'=>"addprice-$app-$add-$api-$country-$kcell"],['text'=>"$kcell",'callback_data'=>"addprice-$app-$add-$api-$country-$kcell"]],
[['text'=>"$lycamobile1",'callback_data'=>"addprice-$app-$add-$api-$country-$lycamobile"],['text'=>"$lycamobile",'callback_data'=>"addprice-$app-$add-$api-$country-$lycamobile"]],
[['text'=>"$matrix1",'callback_data'=>"addprice-$app-$add-$api-$country-$matrix"],['text'=>"$matrix",'callback_data'=>"addprice-$app-$add-$api-$country-$matrix"]],
[['text'=>"$megafon1",'callback_data'=>"addprice-$app-$add-$api-$country-$megafon"],['text'=>"$megafon",'callback_data'=>"addprice-$app-$add-$api-$country-$megafon"]],
[['text'=>"$mts1",'callback_data'=>"addprice-$app-$add-$api-$country-$mts"],['text'=>"$mts",'callback_data'=>"addprice-$app-$add-$api-$country-$mts"]],
[['text'=>"$orange1",'callback_data'=>"addprice-$app-$add-$api-$country-$orange"],['text'=>"$orange",'callback_data'=>"addprice-$app-$add-$api-$country-$orange"]],
[['text'=>"$pildyk1",'callback_data'=>"addprice-$app-$add-$api-$country-$pildyk"],['text'=>"$pildyk",'callback_data'=>"addprice-$app-$add-$api-$country-$pildyk"]],
[['text'=>"$play1",'callback_data'=>"addprice-$app-$add-$api-$country-$play"],['text'=>"$play",'callback_data'=>"addprice-$app-$add-$api-$country-$play"]],
[['text'=>"$redbullmobile1",'callback_data'=>"addprice-$app-$add-$api-$country-$redbullmobile"],['text'=>"$redbullmobile",'callback_data'=>"addprice-$app-$add-$api-$country-$redbullmobile"]],
[['text'=>"$rostelecom1",'callback_data'=>"addprice-$app-$add-$api-$country-$rostelecom"],['text'=>"$rostelecom",'callback_data'=>"addprice-$app-$add-$api-$country-$rostelecom"]],
[['text'=>"$smart1",'callback_data'=>"addprice-$app-$add-$api-$country-$smart"],['text'=>"$smart",'callback_data'=>"addprice-$app-$add-$api-$country-$smart"]],
[['text'=>"$sun1",'callback_data'=>"addprice-$app-$add-$api-$country-$sun"],['text'=>"$sun",'callback_data'=>"addprice-$app-$add-$api-$country-$sun"]],
[['text'=>"$tele21",'callback_data'=>"addprice-$app-$add-$api-$country-$tele2"],['text'=>"$tele2",'callback_data'=>"addprice-$app-$add-$api-$country-$tele2"]],
[['text'=>"$three1",'callback_data'=>"addprice-$app-$add-$api-$country-$three"],['text'=>"$three",'callback_data'=>"addprice-$app-$add-$api-$country-$three"]],
[['text'=>"$tigo1",'callback_data'=>"addprice-$app-$add-$api-$country-$tigo"],['text'=>"$tigo",'callback_data'=>"addprice-$app-$add-$api-$country-$tigo"]],
[['text'=>"$tmobile1",'callback_data'=>"addprice-$app-$add-$api-$country-$tmobile"],['text'=>"$tmobile",'callback_data'=>"addprice-$app-$add-$api-$country-$tmobile"]],
[['text'=>"$tnt1",'callback_data'=>"addprice-$app-$add-$api-$country-$tnt"],['text'=>"$tnt",'callback_data'=>"addprice-$app-$add-$api-$country-$tnt"]],
[['text'=>"$virginmobile1",'callback_data'=>"addprice-$app-$add-$api-$country-$virginmobile"],['text'=>"$virginmobile",'callback_data'=>"addprice-$app-$add-$api-$country-$virginmobile"]],
[['text'=>"$virtual21",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual2"],['text'=>"$virtual2",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual2"]],
[['text'=>"$virtual41",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual4"],['text'=>"$virtual4",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual4"]],
[['text'=>"$virtual51",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual5"],['text'=>"$virtual5",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual5"]],
[['text'=>"$virtual71",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual7"],['text'=>"$virtual7",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual7"]],
[['text'=>"$virtual81",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual8"],['text'=>"$virtual8",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual8"]],
[['text'=>"$virtual121",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual12"],['text'=>"$virtual12",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual12"]],
[['text'=>"$virtual151",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual15"],['text'=>"$virtual15",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual15"]],
[['text'=>"$virtual161",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual16"],['text'=>"$virtual16",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual16"]],
[['text'=>"$virtual171",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual17"],['text'=>"$virtual17",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual17"]],
[['text'=>"$virtual181",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual18"],['text'=>"$virtual18",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual18"]],
[['text'=>"$virtual191",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual19"],['text'=>"$virtual19",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual19"]],
[['text'=>"$virtual201",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual20"],['text'=>"$virtual20",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual20"]],
[['text'=>"$virtual211",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual21a"],['text'=>"$virtual21a",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual21a"]],
[['text'=>"$virtual221",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual22"],['text'=>"$virtual22",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual22"]],
[['text'=>"$virtual231",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual23"],['text'=>"$virtual23",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual23"]],
[['text'=>"$virtual241",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual24"],['text'=>"$virtual24",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual24"]],
[['text'=>"$virtual251",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual25"],['text'=>"$virtual25",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual25"]],
[['text'=>"$virtual261",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual26"],['text'=>"$virtual26",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual26"]],
[['text'=>"$virtual271",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual27"],['text'=>"$virtual27",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual27"]],
[['text'=>"$virtual281",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual28"],['text'=>"$virtual28",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual28"]],
[['text'=>"$virtual291",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual29"],['text'=>"$virtual29",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual29"]],
[['text'=>"$virtual301",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual30"],['text'=>"$virtual30",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual30"]],
[['text'=>"$virtual311",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual31"],['text'=>"$virtual31",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual31"]],
[['text'=>"$virtual321",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual32"],['text'=>"$virtual32",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual32"]],
[['text'=>"$virtual331",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual33"],['text'=>"$virtual33",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual33"]],
[['text'=>"$virtual341",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual34"],['text'=>"$virtual34",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual34"]],
[['text'=>"$virtual351",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual35"],['text'=>"$virtual35",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual35"]],
[['text'=>"$virtual361",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual36"],['text'=>"$virtual36",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual36"]],
[['text'=>"$virtual371",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual37"],['text'=>"$virtual37",'callback_data'=>"addprice-$app-$add-$api-$country-$virtual37"]],
[['text'=>"$vodafone1",'callback_data'=>"addprice-$app-$add-$api-$country-$vodafone"],['text'=>"$vodafone",'callback_data'=>"addprice-$app-$add-$api-$country-$vodafone"]],
[['text'=>"$yota1",'callback_data'=>"addprice-$app-$add-$api-$country-$yota"],['text'=>"$yota",'callback_data'=>"addprice-$app-$add-$api-$country-$yota"]],
[['text'=>"$zz1",'callback_data'=>"addprice-$app-$add-$api-$country-$zz"],['text'=>"$zz",'callback_data'=>"addprice-$app-$add-$api-$country-$zz"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"iA-$app-$add-$api"]]
]
])
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}
#=========={servicPrice}==========#
if($exdata[0] == "addprice"){
$app=$exdata[1];
$add=$exdata[2];
$api=$exdata[3];
$country=$exdata[4];
$operator=$exdata[5];
$array=["019","three","virtual2"];
if($api == "Ai" and in_array($operator,$array)){
$operator = str_replace(["019","three","virtual2"],["94","114","119"],$operator);
}else{
$operator = str_replace(["any","activ","altel","beeline","claro","ee","globe","kcell","lycamobile","matrix","megafon","mts","orange","pildyk","play","redbullmobile","rostelecom","smart","sun","tele2","tigo","tmobile","tnt","virginmobile","virtual4","virtual5","virtual7","virtual8","virtual12","virtual15","virtual16","virtual17","virtual18","virtual19","virtual20","virtual21","virtual22","virtual23","virtual24","virtual25","virtual26","virtual27","virtual28","virtual29","virtual30","virtual31","virtual32","virtual33","virtual34","virtual35","virtual36","virtual37","vodafone","yota","zz"],["93","95","96","97","98","99","100","101","102","103","104","105","106","107","108","109","110","111","112","113","115","116","117","118","120","121","122","123","124","125","126","127","128","129","130","131","132","133","134","135","136","137","138","139","140","141","142","143","144","145","146","147","148","149","150"],$operator);
}
if($api == "Bi" or $api == "Ci" or $api == "Di" or $api == "Ei" or $api == "Fi" or $api == "Hi" or $api == "Li" or $api == "Mi" or $api == "Ni" or $api == "Oi" or $api == "Qi" or $api == "Si" or $api == "Ti" or $api == "Vi" or $api == "Wi" or $api == "Xi"){
$operator = str_replace(["Bi","Ci","Di","Ei","Fi","Hi","Li","Mi","Ni","Oi","Qi","Si","Ti","Vi"],["1","2","3","4","5","6","7","8","9","10","11","12","13","14"],$api);
}
if($api == "Gi"){
$operator = str_replace(["any","mobifone","vietnamobile","viettel","vinaphone"],["16","17","18","19","20"],$operator);
}
if($api == "Ji"){
$operator = str_replace(["1","3","4","5","7"],["A","B","C","D","E"],$operator);
$operator = str_replace(["A","B","C","D","E"],["48","49","50","51","52"],$operator);
}
if($api == "Ki"){
$operator = str_replace(["MOBIFONE","VINAPHONE","VIETTEL","VIETNAMOBILE","ITELECOM"],["21","22","23","24","25"],$operator);
}
if($api == "Pi"){
$operator = str_replace(["10","11","12","13","14","15","16","17","0","1","2","3","4","5","6","8","9"],["J","K","L","M","N","O","P","Q","A","B","C","D","E","F","G","H","I"],$operator);
$operator = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q"],["26","27","28","29","30","31","32","33","34","35","36","37","38","39","40","41","42"],$operator);
}
if($api == "Ri"){
$operator = str_replace(["3819","3543","3528","3328","2001","3825","3521","3326","2940","2919","3832","3744","3375","3262","3128","3826","3749","3484","3333","3302","2003","3475","3391","3335","2012","3862","3385","2624","3820","3497","3312","3890","3392","2866","3858","3515","3191","3189","3999","2929"],["53","54","55","56","57","58","59","60","61","62","63","64","65","66","67","68","69","70","71","72","73","74","75","76","77","78","79","80","81","82","83","84","85","86","87","88","89","90","91","92"],$operator);
}
if($api == "Ui"){
$operator = str_replace(["MOBIFONE","VINAPHONE","VIETTEL","VIETNAMOBILE","ITELECOM"],["43","44","45","46","47"],$operator);
}
$site = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim","alisms","man","vak","activate","pvapins","botsms","onlinesim","supersmstech","viotp","simsms","grizzly","smscode","tiger","2ndline","store","fastpva","dropsms","24sms7","sellotp","duraincloud"],$api);
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms.com","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$name = $_co['country'][$country];
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
if($status=="200"){
$price=$api_price[price];
}elseif($status=="0"){
$price = "❌";
}
$keybo=str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["iA","iA","iA","iB","iC","iD","iE","iF","iG","iE","iH","iJ","iK","iL","iM","iN","iO","iP","iQ","iE","iR"],$api);
$code = "$country$app$operator$add";
$code = md5($code);
if($price == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
⛔️ - عذرا هذه الدولة ليست متوفر في الموقع ♻️
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
}elseif($buy['number'][$code] != null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
⛔️ - عذرا هذه الدولة قد قمت ب إضافتها من قبل في البوت
♻️ - لاتستطيع إضافة نفس الدولة إلى البوت
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
🌐 ⌯ الموقع: $API*
*⤵️ ⌯ سعر الرقم في الموقع هو $price
♻️ ⌯ قم بإرسال سعر الدولة الان ⏬*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"$keybo-$app-$add-$api"]]
]
])
]);
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
$zzz[mode][$chat_id]="addprice";
$zzz[api]=$api;
$zzz[add]=$add;
$zzz[app]=$app;
$zzz[country]=$country;
$zzz[site]=$site;
$zzz[API]=$API;
$zzz[operator]=$operator;
zzz();
}
}
if($text != '/start' && $text && $zzz[mode][$chat_id]=="addprice"){
if($text>0){
$app=$zzz[app];
$add=$zzz[add];
$api=$zzz[api];
$country=$zzz[country];
$site=$zzz[site];
$API=$zzz[API];
$exn=explode(".", $text);
if($exn[1] > 0){
$price="$text"."0";
}else{
$price="$exn[0]";
}
$operator=$zzz[operator];
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$APP_S = str_replace(["wa","tg","fb","ig","tw","lf","go","im","vi","fu","nf","au","ot"],["WhatsApp","Telegram","Facebook","Instagram","Twitter","Tiktok","Google","Imo","Viber","Snapchat","Netflix","Haraj","Other"],$app);#APP
$name = $_co['country'][$country];
$i=$random[$num]['add'];
if($i==null){
$i=0;
}
$keybo=str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["iA","iA","iA","iB","iC","iD","iE","iF","iG","iE","iH","iJ","iK","iL","iM","iN","iO","iP","iQ","iE","iR"],$api);
$code = "$country$app$operator$add";
$code = md5($code);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"
*🌍 ⪼ تم حفظ الدولة والتطبيق والسعر ✅

☑️ ⌯ الدولة: $name 🌀
✳️ ⌯ التطبيق: $APP 📲
💰 ⌯ السعر: $price
🌐 ⌯ الموقع: $API*
",
'parse_mode'=>'Markdown',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"$keybo-$app-$add-$api"]]
]
])
]);
$buy['number'][$code]['country'] = $country;
$buy['number'][$code]['app'] = $app;
$buy['number'][$code]['operator'] = $operator;
$buy['number'][$code]['api'] = $api;
$buy['number'][$code]['site'] = $site;
$buy['number'][$code]['add'] = $add;
$buy['number'][$code]['name'] = $name;
$buy['number'][$code]['APP'] = $APP_S;
$buy['number'][$code]['Type'] = "not_directly";
$buy['number'][$code]['price'] = $price;
$buy['country_app']["$add-$country"] += 1;
Addserver($buy);
if($add >= 21 and $add <= 30){
$random[$add]['zero'][$i] = $code;
$random[$add]['idd'][$code] = $i;
$random[$add]['add'] += 1;
Save($random);
}
bot_json_delete("zzz.json");
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"
عذرا قم بإرسال رقم أكبر من الصفر ❌
",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'addnumber']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={حذف الدول}==========#
if($data == "delnumber"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
🎬 - قم ب إختار التطبيق الذي تود حذف الدولة من البوت ❌
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• السيرفر العام •','callback_data'=>"delcon-14"]],
[['text'=>'• تيليجرام •','callback_data'=>"delcon-3"],['text'=>'• واتسأب •','callback_data'=>"delcon-2"]],
[['text'=>'• إنستقرام •','callback_data'=>"delcon-5"],['text'=>'• فيسبوك •','callback_data'=>"delcon-4"]],
[['text'=>'• تيك توك •','callback_data'=>"delcon-7"],['text'=>'• تويتر •','callback_data'=>"delcon-6"]],
[['text'=>'• إيمو •','callback_data'=>"delcon-9"],['text'=>'• جوجل •','callback_data'=>"delcon-8"]],
[['text'=>'• سناب •','callback_data'=>"delcon-11"],['text'=>'• فايبر •','callback_data'=>"delcon-10"]],
[['text'=>'• حراج •','callback_data'=>"delcon-13"],['text'=>'• نيتفلكس •','callback_data'=>"delcon-12"]],
[['text'=>'قسم العروض + قسم عروض تيليجرام','callback_data'=>"Cdelw"]],
[['text'=>'• الشـboyـراء التلـقـ🛰ـائي الذكي •','callback_data'=>"delcon-1"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={الدول}==========#
if($exdata[0] == "delcon"){
$add=$exdata[1];
$con=$exdata[2];
if($con==null){
$con=1;
}
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام"],$status);
if($con == 1) { $continent     = $AdminONE;  }
if($con == 2) { $continent     = $AdminTOW;  }
if($con == 3) { $continent     = $AdminTHREE;  }
if($con == 4) { $continent     = $AdminFOUR;  }
if($con == 5) { $continent     = $AdminFIVE;  }
if($con == 6) { $continent     = $AdminSIX;  }
if($con == 7) { $continent     = $AdminSEVEN;  }
if($con == 8) { $continent     = $AdminEIGHT;  }
if($continent == null){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
🎬 - قم ب إختار التطبيق الذي تود حذف الدولة من البوت ❌
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• السيرفر العام •','callback_data'=>"delcon-14"]],
[['text'=>'• تيليجرام •','callback_data'=>"delcon-3"],['text'=>'• واتسأب •','callback_data'=>"delcon-2"]],
[['text'=>'• إنستقرام •','callback_data'=>"delcon-5"],['text'=>'• فيسبوك •','callback_data'=>"delcon-4"]],
[['text'=>'• تيك توك •','callback_data'=>"delcon-7"],['text'=>'• تويتر •','callback_data'=>"delcon-6"]],
[['text'=>'• إيمو •','callback_data'=>"delcon-9"],['text'=>'• جوجل •','callback_data'=>"delcon-8"]],
[['text'=>'• سناب •','callback_data'=>"delcon-11"],['text'=>'• فايبر •','callback_data'=>"delcon-10"]],
[['text'=>'• حراج •','callback_data'=>"delcon-13"],['text'=>'• نيتفلكس •','callback_data'=>"delcon-12"]],
[['text'=>'قسم العروض + قسم عروض تيليجرام','callback_data'=>"Cdelw"]],
[['text'=>'• الشـboyـراء التلـقـ🛰ـائي الذكي •','callback_data'=>"delcon-1"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
if($con >= 8){
$u = $con-1;
}elseif($con >= 1){
$o = $con+1;
$u = $con-1;
}
if($con == 1){
$t = "• التالي ⬅️ •";
$s = null;
$v = "• الأخير ⬅️ •";
}elseif($con == 2 or $con == 3 or $con == 4 or $con == 5 or $con == 6 or $con == 7){
$t = "• التالي ⬅️ •";
$s = "• السابق ⬅️ •";
$v = null;
$n = null;
}elseif($con == 8){
$t = null;
$s = "• السابق ⬅️ •";
$n = "الأول. ⏭";
}
$i = 0;
$e = 0;
$key     = [];
foreach($continent as $country=>$val){
$co = $val;
$name      = $country;
$e++;
$key['inline_keyboard'][$i][]  =   ["text"=>$name,"callback_data"=>"delenu-$add-$co-$con"];
if($e%3 == 0) $i++;
}
$key['inline_keyboard'][0] = [['text'=>'☑️ ⌯ جميع الدول في الأسفل ⬇️','callback_data'=>"no"]];
$key['inline_keyboard'][] = [['text'=>"$t",'callback_data'=>"delcon-$add-$o"],['text'=>"$s",'callback_data'=>"delcon-$add-$u"],['text'=>"$v",'callback_data'=>"delcon-$add-8"],['text'=>"$n",'callback_data'=>"delcon-$add-1"]];
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"delnumber"]];
$keyboad      = json_encode($key);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*✳️ ⌯ إختر الدولة التي تريد حذفها من البوت ❌
☑️ ⌯ في الكيبورد أدناه 👇*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={حذف الدول}==========#
if($exdata[0] == "delenu"){
$add=$exdata[1];
$co=$exdata[2];
$con=$exdata[3];
$APP = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","الاكثر توفرآ واتساب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو"],$add);
$key     = [];
$key['inline_keyboard'][] = [['text'=>'🌐 الموقع','callback_data'=>'no'],['text'=>'💰 السعر','callback_data'=>'no'],['text'=>'⛔️','callback_data'=>'no']];
foreach($buy['number'] as $zero=>$num){
if($num['add'] == $add and $num['country'] == $co){
$price=$num['price'];
$name = $_co['country'][$co];
$app = $num['app'];
$operator = $num['operator'];
$site = $num['site'];
$code="$co$app$operator$add";
$key['inline_keyboard'][] = [['text'=>"$site",'callback_data'=>"look-$code"],['text'=>"$price ₽",'callback_data'=>"priamence-$code"],['text'=>"🗑",'callback_data'=>"GODelsnum-$code"]];
}
}
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"delcon-$add-$con"]];
$keyboad      = json_encode($key);
if($site == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
انت لم تقم ب إضافة سيرفرات لتطبيق $APP في الوقت الحالي 😅
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ الدولة: $name
☑️ ⌯ التطبيق: $APP ♾

🌐 ⌯ إضغط على اسم الموقع لرؤية نوع السيرفر
💸 ⌯ اضغك على السعر لتغيير سعر الدولة
🔆 ⌯ إضغط على [🗑] لحذف السيرف*ر
",
'parse_mode'=>'Markdown',
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={السيرفرات العشوائية}==========#
if($data == "Wdele"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ إختر القسم الذي تود الحذف منه.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'♻️ سيرفر عشوائي [ WhatsApp ] رقم 1','callback_data'=>"Wdelse-21"]],
[['text'=>'♻️ سيرفر عشوائي [ WhatsApp ] رقم 2','callback_data'=>"Wdelse-22"]],
[['text'=>'♻️ سيرفر عشوائي [ Telegram ] رقم 1','callback_data'=>"Wdelse-26"]],
[['text'=>'♻️ سيرفر عشوائي [ Telegram ] رقم 2','callback_data'=>"Wdelse-27"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"delnumber"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={السيرفرات العشوائية}==========#
if($exdata[0] == "Wdelse"){
$add=$exdata[1];
$APP = str_replace(["21","22","23","24","25","26","27","28","29","30"],["واتسأب 1","واتساب 2","واتساب 3","واتساب 4","واتساب 5","تيليجرام 1","تيليجرام 2","تيليجرام 3","تيليجرام 4","تيليجرام 5"],$add);
$key     = [];
$key['inline_keyboard'][] = [['text'=>'🖌','callback_data'=>'no'],['text'=>'🌐 الموقع','callback_data'=>'no'],['text'=>'☑️ الدولة','callback_data'=>'no'],['text'=>'💰 السعر','callback_data'=>'no'],['text'=>'🖌','callback_data'=>'no']];
foreach($buy['number'] as $zero=>$num){
if($num['add'] == $add){
$oop++;
if($oop >= 15){
break;
}
$country = $num['country'];
if(15 < count($buy[country_app]["$add-$country"])){
$to="• التالي ⬅️ •";
}
$i = $random[$add]['idd'][$zero];
$price=$num['price'];
$country = $num['country'];
$name = $_co['country'][$country];
$site = $num['site'];
$code="$co$app$operator$add";
$key['inline_keyboard'][] = [['text'=>"➖ $i",'callback_data'=>"idameni2-$code-del"],['text'=>"$site",'callback_data'=>"look-$code"],['text'=>"$name",'callback_data'=>"GODelsnum-$code-$idd"],['text'=>"$price ₽",'callback_data'=>"priamence-$code"],['text'=>"➕ $i",'callback_data'=>"idameni2-$code-add"]];
}
}
$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Wd2else-$add-2"]];
$key['inline_keyboard'][] = [['text'=>'تحديث ♻️','callback_data'=>"$data"]];
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Wdele"]];
$keyboad      = json_encode($key);
if($site == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
انت لم تقم ب إضافة سيرفرات عشوائي لل$APP في الوقت الحالي 😅
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
🌐 - إختر الدولة الذي تريد حذفها من السيرفر عشوائي ل$APP ♻️
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
if($exdata[0] == "Wd2else"){
$add=$exdata[1];
$num=$exdata[2];
$jk = $num * 15;
 $oj = $num - 1;
 $fj = $oj * 15;
 $kb = $num + 1;
 $jj = $jk - 15;
 $ze='0';
$APP = str_replace(["21","22","23","24","25","26","27","28","29","30"],["واتسأب 1","واتساب 2","واتساب 3","واتساب 4","واتساب 5","تيليجرام 1","تيليجرام 2","تيليجرام 3","تيليجرام 4","تيليجرام 5"],$add);
 if($ze >= $jk){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
- حدث خطأ برمجي قم ب الرجوع.
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Wdele"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
$key     = [];
$key['inline_keyboard'][] = [['text'=>'🖌','callback_data'=>'no'],['text'=>'🌐 الموقع','callback_data'=>'no'],['text'=>'☑️ الدولة','callback_data'=>'no'],['text'=>'💰 السعر','callback_data'=>'no'],['text'=>'🖌','callback_data'=>'no']];
foreach($buy['number'] as $zero=>$num){
$country = $num['country'];
if($jj > count($buy[country_app]["$add-$country"])){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
❌ - لاتوجد صفحة أخرى حالياً 🙄
",
'show_alert'=>true,
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
if($num['add'] == $add){
$oop++;
if($oop >= $jk){
break;
}
if($oop >= $fj){
if($oop > 15){
$ba="➡️ السابق ➡️";
}
if($jk < count($buy[country_app]["$add-$country"])){
$to="• التالي ⬅️ •";
}
$i = $random[$add]['idd'][$zero];
$price=$num['price'];
$country = $num['country'];
$name = $_co['country'][$country];
$site = $num['site'];
$code="$co$app$operator$add";
$key['inline_keyboard'][] = [['text'=>"➖ $i",'callback_data'=>"idameni2-$code-del"],['text'=>"$site",'callback_data'=>"look-$code"],['text'=>"$name",'callback_data'=>"GODelsnum-$code-$idd"],['text'=>"$price ₽",'callback_data'=>"priamence-$code"],['text'=>"➕ $i",'callback_data'=>"idameni2-$code-add"]];
}
}
}
$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Wd2else-$add-$kb"],['text'=>"$ba",'callback_data'=>"Wd2else-$add-$oj"]];
$key['inline_keyboard'][] = [['text'=>'تحديث ♻️','callback_data'=>"$data"]];
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Wdele"]];
$keyboad      = json_encode($key);
if($site == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
انت لم تقم ب إضافة سيرفرات عشوائي لل$APP في الوقت الحالي 😅
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
🌐 - إختر الدولة الذي تريد حذفها من السيرفر عشوائي ل$APP ♻️
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={تعديل السيرفرات}==========#
if($exdata[0] == "idameni2"){
$zero = $exdata[1];
$type = $exdata[2];
$zero = md5($zero);
$add = $buy['number'][$zero]['add'];
$idd=$random[$add]['idd'][$zero];
if($type == "add"){
$idd=$idd+1;
$random[$add]['idd'][$zero] += 1;
$random[$add]['zero'][$idd] = $zero;
Save($random);
}elseif($type == "del"){
$idd=$idd-1;
$random[$add]['idd'][$zero] -= 1;
$random[$add]['zero'][$idd] = $zero;
Save($random);
}
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
☑️ - تم تعديل رقم السيرفر إلى $idd
♻️ - قم بعمل للصفحة تحديث كي يتم تعديل رقم السيرفر
",
'show_alert'=>true
]);
exit;
}
#=========={سيرفرات المميز}==========#
if($data == "Cdelw"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ إختر القسم الذي تود الحذف منه.*
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'‌• 🛍 العروض قسم 🚀 •','callback_data'=>"Cdelsw-31"]],
[['text'=>'• 🔥 عروض تليجرام 🚀 •','callback_data'=>"Cdelsw-36"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"delnumber"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
#=========={سيرفرات المميز}==========#
if($exdata[0] == "Cdelsw"){
$add=$exdata[1];
$APP = str_replace(["31","32","33","34","35","36","37","38","39","40"],["واتسأب 1","واتساب 2","واتساب 3","واتساب 4","واتساب 5","تيليجرام 1","تيليجرام 2","تيليجرام 3","تيليجرام 4","تيليجرام 5"],$add);
$key     = [];
$oop = 0;
$key['inline_keyboard'][] = [['text'=>'🌐 الموقع','callback_data'=>'no'],['text'=>'☑️ الدولة','callback_data'=>'no'],['text'=>'💰 السعر','callback_data'=>'no']];
foreach($buy['number'] as $zero=>$num){
if($num['add'] == $add){
$oop++;

if($oop >= 15){
$to="• التالي ⬅️ •";
break;
}
$country = $num['country'];



$price=$num['price'];
$country = $num['country'];
$app = $num['app'];
$operator = $num['operator'];
$name = $_co['country'][$country];
$site = $num['site'];
$code="$country$app$operator$add";
$key['inline_keyboard'][] = [['text'=>"$site",'callback_data'=>"look-$code"],['text'=>"$name",'callback_data'=>"GODelsnum-$code"],['text'=>"$price ₽",'callback_data'=>"priamence-$code"]];
}
}
$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Cd2elsw-$add-2"]];
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Cdelw"]];
$keyboad      = json_encode($key);
if($site == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
انت لم تقم ب إضافة سيرفرات لتطبيق $APP في الوقت الحالي 😅
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❍ إختر الدولة الذي تريد حذفها من سيرفر $APP ♻️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
if($exdata[0] == "Cd2elsw"){
$add=$exdata[1];
$num=$exdata[2];
$jk = $num * 15; #30
 $oj = $num - 1; #1
 $fj = $oj * 15; #15
 $kb = $num + 1; #3
 $jj = $jk - 15; #15
 $ze='0';
$APP = str_replace(["31","32","33","34","35","36","37","38","39","40"],["واتسأب 1","واتساب 2","واتساب 3","واتساب 4","واتساب 5","تيليجرام 1","تيليجرام 2","تيليجرام 3","تيليجرام 4","تيليجرام 5"],$add);
 if($ze >= $jk){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
- حدث خطأ برمجي قم ب الرجوع.
",
'parse_mode'=>'Markdown',
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Cdelw"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
$key     = [];
$Aaaymn = 0;
$key['inline_keyboard'][] = [['text'=>'🌐 الموقع','callback_data'=>'no'],['text'=>'☑️ الدولة','callback_data'=>'no'],['text'=>'💰 السعر','callback_data'=>'no']];
foreach($buy['number'] as $zero=>$num){

if($num['add'] == $add){
$country = $num['country']; 
$Aaaymn +=1;
$oop++;
if($oop >= $jk){
$to="• التالي ⬅️ •";
break;
}
if($oop >= $fj){
if($oop > 15){
$ba="➡️ السابق ➡️";
}

if($jj > $Aaaymn){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
❌ - لاتوجد صفحة أخرى حالياً 🙄
",
'show_alert'=>true,
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}

$price=$num['price'];
$country = $num['country'];
$name = $_co['country'][$country];
$site = $num['site'];
$operator = $num['operator'];
$app = $num['app'];
$code="$country$app$operator$add";
$key['inline_keyboard'][] = [['text'=>"$site",'callback_data'=>"look-$code"],['text'=>"$name",'callback_data'=>"GODelsnum-$code"],['text'=>"$price ₽",'callback_data'=>"priamence-$code"]];
}
}
}
$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Cd2elsw-$add-$kb"],['text'=>"$ba",'callback_data'=>"Cd2elsw-$add-$oj"]];
$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>"Cdelw"]];
$keyboad      = json_encode($key);
if($site == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
انت لم تقم ب إضافة سيرفرات عشوائي لل$APP في الوقت الحالي 😅
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*❍ إختر الدولة الذي تريد حذفها من سيرفر $APP ♻️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={تغيير السعر}==========#
if($exdata[0] == "priamence"){
$zero = $exdata[1];
$zero = md5($zero);
$price = $buy['number'][$zero]['price'];
$country = $buy['number'][$zero]['country'];
$add = $buy['number'][$zero]['add'];
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$ary1=["1","2","3","4","5","6","7","8","9","10","11","12","13","14"];
$ary2=["21","22","23","24","25","26","27","28","29","30"];
$ary3=["31","32","33","34","35","36","37","38","39","40"];
if(in_array($add,$ary1)){
$back="delenu-$add-$country";
}elseif(in_array($add,$ary2)){
$back="Wdelse-$add";
}elseif(in_array($add,$ary3)){
$back="Cdelsw-$add";
}
$name = $_co['country'][$country];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ الدولة : $name
⚙ ⌯ التطبيق : $APP
💸 ⌯ سعر الدولة الحالي هو : $price
⤵️ ⌯ أرسل السعر الجديد لتغييرة*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"$back"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","priamence|$zero");
}
if($text && $text != '/start' && $exstep[0] == 'priamence'){
$zero = $exstep[1];
$price = $buy['number'][$zero]['price'];
$country = $buy['number'][$zero]['country'];
$app = $buy['number'][$zero]['app'];
$site = $buy['number'][$zero]['site'];
$add = $buy['number'][$zero]['add'];
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$ary1=["1","2","3","4","5","6","7","8","9","10","11","12","13","14"];
$ary2=["21","22","23","24","25","26","27","28","29","30"];
$ary3=["31","32","33","34","35","36","37","38","39","40"];
if(in_array($add,$ary1)){
$back="delenu-$add-$country";
}elseif(in_array($add,$ary2)){
$back="Wdelse-$add";
}elseif(in_array($add,$ary3)){
$back="Cdelsw-$add";
}
$name = $_co['country'][$country];
if($text > 0){
$exn=explode(".", $text);
if($exn[1] > 0){
$text="$text"."0";
}else{
$text="$exn[0]";
}
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑️ ⪼ الدولة : $name
⚙ ⌯ التطبيق : $APP
💸 ⌯ السعر القديم هو : $price
💰 ⌯ السعر الجديد هو : $text*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"$back"]]
]
])
]);
$buy['number'][$zero]['price'] = $text;
Addserver($buy);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
⚠️ ⌯ يرجى إرسال سعر صحيح
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
]);
}
}
#=========={تفاصيل عن الدولة}==========#
if($exdata[0] == "look"){
$zero = $exdata[1];
$zero = md5($zero);
$app = $buy['number'][$zero]['app'];
$country = $buy['number'][$zero]['country'];
$site = $buy['number'][$zero]['site'];
$operator = $buy['number'][$zero]['operator'];
$api_price=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getPrice&site=$site&country=$country&app=$app&operator=$operator"),1);
$status=$api_price[status];
if($status=="200"){
$price=$api_price[price];
}elseif($status=="0"){
$price = "❌";
}
$operator=$api_price[operator];
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
♻️ ⪼ السيرفر: $operator
✅ ⌯ السعر في الموقع: $price
",
'show_alert'=>true
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={حذف الدولة}==========#
if($exdata[0] == "GODelsnum"){
$zero = $exdata[1];
$idd = $exdata[2];
$code=$zero;
$zero = md5($zero);
$price = $buy['number'][$zero]['price'];
$app = $buy['number'][$zero]['app'];
$country = $buy['number'][$zero]['country'];
$add = $buy['number'][$zero]['add'];
$api = $buy['number'][$zero]['api'];
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$ary1=["1","2","3","4","5","6","7","8","9","10","11","12","13","14"];
$ary2=["21","22","23","24","25","26","27","28","29","30"];
$ary3=["31","32","33","34","35","36","37","38","39","40"];
if(in_array($add,$ary1)){
$back="delenu-$add-$country";
}elseif(in_array($add,$ary2)){
$back="Wdelse-$add";
}elseif(in_array($add,$ary3)){
$back="Cdelsw-$add";
}
$name = $_co['country'][$country];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*✅ ⪼ هل انت متأكد بأنك تريد حذف الدولة ❌

☑️ ⌯ الدولة: $name
💰 ⌯ السعر: ₽ $price
🌐 ⌯ الموقع: $API
♏ ⌯ التطبيق: $APP*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ☑️ متأكد •",'callback_data'=>"Mez-$code-$idd"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"$back"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={تأكيد حذف الدولة}==========#
if($exdata[0] == "Mez"){
$zero = $exdata[1];
$idd = $exdata[2];
$zero = md5($zero);
$price = $buy['number'][$zero]['price'];
$app = $buy['number'][$zero]['app'];
$country = $buy['number'][$zero]['country'];
$add = $buy['number'][$zero]['add'];
$api = $buy['number'][$zero]['api'];
$API = str_replace(["Ai","Bi","Ci","Di","Ei","Fi","Gi","Hi","Ji","Ki","Li","Mi","Ni","Oi","Pi","Qi","Ri","Si","Ti","Ui","Vi"],["5sim.biz","alisms.org","sms-man.ru","Vak-sms.com","sms-activate.io","pvapins.com","alnwerah.sms","onlinesim.io","supersmstech.com","viotp.com","simsms.org","grizzlysms.com","sms-code.ru","tiger-sms.com","2ndline.io","receivesms.store","sms.fastpva.com","dropsms.ru","24sms7.com","sellotp.com","mm.durianrcs.com"],$api);
if($add >=1 and $add <=14){
$status = str_replace(["10","11","12","13","14","1","2","3","4","5","6","7","8","9"],["J","K","L","M","N","A","B","C","D","E","F","G","H","I"],$add);
}else{
$status = $add;
}
$APP = str_replace(["A","B","C","D","E","F","G","H","I","J","K","L","M","N","21","22","23","24","25","26","27","28","29","30","31","32","33","34","35","36","37","38","39","40"],["واتسأب","واتسأب","تيليجرام","فيسبوك","إنستقرام","تويتر","تيك توك","قوقل","ايمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي واتسأب","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عشوائي تيليجرام","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض واتسأب","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام","عروض تليجرام"],$status);
$ary1=["1","2","3","4","5","6","7","8","9","10","11","12","13","14"];
$ary2=["21","22","23","24","25","26","27","28","29","30"];
$ary3=["31","32","33","34","35","36","37","38","39","40"];
if(in_array($add,$ary1)){
$back="delenu-$add-$country";
}elseif(in_array($add,$ary2)){
$back="Wdelse-$add";
}elseif(in_array($add,$ary3)){
$back="Cdelsw-$add";
}
$name = $_co['country'][$country];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*تم حذف الدولة هذه بنجاج ✅*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"$back"]]
]
])
]);
unset($buy['number'][$zero]);
Addserver($buy);
$buy['country_app']["$add-$country"] -= 1;
Addserver($buy);
if(in_array($add,$ary2)){
unset($random[$add]['zero'][$idd]);
unset($random[$add]['idd'][$zero]);
$random[$add]['add'] -= 1;
Save($random);
}
bot_store_delete("data/id/$id/step.txt");
}
#=========={API}==========#
if($data == 'counapi'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🔰 تستطيع عبر هذه الأزرار رفع وحذف اي API لاي موقع تريده ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>'• ➕ رفع API لموقع معين •','callback_data'=>'addapi']],
[['text'=>'• ✖️ حذف API لموقع معين •','callback_data'=>'delapi']],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}

if($data == 'api-sites-balances'){
$balanceInfo = admin_api_sites_balance_lines($APPS);
$balanceLines = implode("\n",(array)($balanceInfo['lines'] ?? []));
$countSites = (int)($balanceInfo['count'] ?? 0);

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*💳 ⌯ ارصده المواقع المضافه عبر API*

*📊 ⌯ عدد المواقع المرتبطة : $countSites*

$balanceLines
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• 🔄 تحديث الارصدة •','callback_data'=>'api-sites-balances']],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'counapi']]
]
])
]);

bot_store_delete("data/id/$id/step.txt");
}
#=========={رفع API}==========#
if($addblusdel['5sim']['add'] == "ok"){
$sim5 = "5sim.biz 🌐";
}else{
$sim5 = "";
}
if($addblusdel['alisms']['add'] == "ok"){
$smslive = "alisms.org 🌐";
}else{
$smslive = "";
}
if($addblusdel['man']['add'] == "ok"){
$man = "sms-man.ru 🌐";
}else{
$man = "";
}
if($addblusdel['vak']['add'] == "ok"){
$vak = "Vak-sms.com 🌐";
}else{
$vak = "";
}
if($addblusdel['activate']['add'] == "ok"){
$activate = "sms-activate.io 🌐";
}else{
$activate = "";
}
if($addblusdel['pvapins']['add'] == "ok"){
$pvapins = "pvapins.com 🌐";
}else{
$pvapins = "";
}
if($addblusdel['botsms']['add'] == "ok"){
$botsms = "alnwerah.sms 🌐";
}else{
$botsms = "";
}
if($addblusdel['onlinesim']['add'] == "ok"){
$onlinesim = "onlinesim.io 🌐";
}else{
$onlinesim = "";
}
if($addblusdel['supersmstech']['add'] == "ok"){
$supersmstech = "supersmstech.com 🌐";
}else{
$supersmstech = "";
}
if($addblusdel['viotp']['add'] == "ok"){
$viotp = "viotp.com 🌐";
}else{
$viotp = "";
}
if($addblusdel['simsms']['add'] == "ok"){
$simsms = "simsms.org 🌐";
}else{
$simsms = "";
}
if($addblusdel['grizzly']['add'] == "ok"){
$grizzly = "grizzlysms.com 🌐";
}else{
$grizzly = "";
}
if($addblusdel['smscode']['add'] == "ok"){
$smscode = "sms-code.ru 🌐";
}else{
$smscode = "";
}
if($addblusdel['tiger']['add'] == "ok"){
$tiger = "tiger-sms.com 🌐";
}else{
$tiger = "";
}
if($addblusdel['2ndline']['add'] == "ok"){
$nd2lind = "2ndline.io 🌐";
}else{
$nd2lind = "";
}
if($addblusdel['store']['add'] == "ok"){
$store = "receivesms.store 🌐";
}else{
$store = "";
}
if($addblusdel['fastpva']['add'] == "ok"){
$fastpva = "sms.fastpva.com 🌐";
}else{
$fastpva = "";
}
if($addblusdel['dropsms']['add'] == "ok"){
$dropsms = "dropsms.ru 🌐";
}else{
$dropsms = "";
}
if($addblusdel['24sms7']['add'] == "ok"){
$sms7 = "24sms7.com 🌐";
}else{
$sms7 = "";
}
if($addblusdel['sellotp']['add'] == "ok"){
$sellotp = "sellotp.com 🌐";
}else{
$sellotp = "";
}
if($addblusdel['duraincloud']['add'] == "ok"){
$duraincloud = "mm.durianrcs.com 🌐";
}else{
$duraincloud = "";
}
if($data == 'addapi'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ قم بإختيار الموقع الذي تريد رفع كود ال API إلى البوت*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"$sim5",'callback_data'=>"add5sim"]],
[['text'=>"$smslive",'callback_data'=>"addsmslive"],['text'=>"$man",'callback_data'=>"addman"]],
[['text'=>"$vak",'callback_data'=>"addvaksms"]],
[['text'=>"$activate",'callback_data'=>"addactivate"],['text'=>"$pvapins",'callback_data'=>"addpvapins"]],
[['text'=>"$botsms",'callback_data'=>"addbotsms"]],
[['text'=>"$onlinesim",'callback_data'=>"addonlinesim"],['text'=>"$supersmstech",'callback_data'=>"addsupersmstech"]],
[['text'=>"$viotp",'callback_data'=>"addviotp"]],
[['text'=>"$simsms",'callback_data'=>"addsimsms"],['text'=>"$grizzly",'callback_data'=>"addgrizzly"]],
[['text'=>"$smscode",'callback_data'=>"addsmscode"]],
[['text'=>"$tiger",'callback_data'=>"addtiger"],['text'=>"$nd2lind",'callback_data'=>"add2ndline"]],
[['text'=>"$store",'callback_data'=>"addstore"]],
[['text'=>"$fastpva",'callback_data'=>"addfastpva"],['text'=>"$dropsms",'callback_data'=>"adddropsms"]],
[['text'=>"$sms7",'callback_data'=>"add24sms7"]],
[['text'=>"$sellotp",'callback_data'=>"addsellotp"],['text'=>"$duraincloud",'callback_data'=>"addduraincloud"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'counapi']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={5sim.biz}==========#
if($data == 'add5sim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع 5sim.biz*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","5sim");
}
if($text && $text != '/start' && $step == '5sim'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع 5sim.biz بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS['5sim'][api_key] = $text;
Apps($APPS);
bot_store_delete("data/id/$id/step.txt");
}
#=========={alisms.org}==========#
if($data == 'addsmslive'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع alisms.org*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","alisms");
}
if($text && $text != '/start' && $step == 'alisms'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع alisms.org بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[alisms][api_key] = $text;
Apps($APPS);
}
#=========={sms-man.ru}==========#
if($data == 'addman'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع sms-man.ru*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","man");
}
if($text && $text != '/start' && $step == 'man'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع sms-man.ru بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[man][api_key] = $text;
Apps($APPS);
}
#=========={Vak-sms.com}==========#
if($data == 'addvaksms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع vak-sms.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","vak");
}
if($text && $text != '/start' && $step == 'vak'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع vak-sms.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[vak][api_key] = $text;
Apps($APPS);
}
#=========={sms-activate.io}==========#
if($data == 'addactivate'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع sms-activate.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","activate");
}
if($text && $text != '/start' && $step == 'activate'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع sms-activate.io بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[activate][api_key] = $text;
Apps($APPS);
}
#=========={pvapins.com}==========#
if($data == 'addpvapins'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع pvapins.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","pvapins");
}
if($text && $text != '/start' && $step == 'pvapins'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع pvapins.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[pvapins][api_key] = $text;
Apps($APPS);
}
#=========={alnwerah.sms}==========#
if($data == 'addbotsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع alnwerah.sms*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","botsms");
}
if($text && $text != '/start' && $step == 'botsms'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ - تم رفع كود ال API للموقع alnwerah.sms بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[botsms][api_key] = $text;
Apps($APPS);
}
#=========={onlinesim.io}==========#
if($data == 'addonlinesim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع onlinesim.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","onlinesim");
}
if($text && $text != '/start' && $step == 'onlinesim'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع onlinesim.io بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[onlinesim][api_key] = $text;
Apps($APPS);
}
#=========={supersmstech.com}==========#
if($data == 'addsupersmstech'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع supersmstech.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","supersmstech");
}
if($text && $text != '/start' && $step == 'supersmstech'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع supersmstech.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[supersmstech][api_key] = $text;
Apps($APPS);
}
#=========={viotp.com}==========#
if($data == 'addviotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع viotp.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","viotp");
}
if($text && $text != '/start' && $step == 'viotp'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع viotp.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[viotp][api_key] = $text;
Apps($APPS);
}
#=========={simsms.org}==========#
if($data == 'addsimsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع simsms.org*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","simsms");
}
if($text && $text != '/start' && $step == 'simsms'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع simsms.org بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[simsms][api_key] = $text;
Apps($APPS);
}
#=========={grizzlysms.com}==========#
if($data == 'addgrizzly'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع grizzlysms.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","grizzly");
}
if($text && $text != '/start' && $step == 'grizzly'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع grizzlysms.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[grizzly][api_key] = $text;
Apps($APPS);
}
#=========={sms-code.ru}==========#
if($data == 'addsmscode'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع sms-code.ru*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","smscode");
}
if($text && $text != '/start' && $step == 'smscode'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع sms-code.ru بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[smscode][api_key] = $text;
Apps($APPS);
}
#=========={tiger-sms.com}==========#
if($data == 'addtiger'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع tiger-sms.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","tiger");
}
if($text && $text != '/start' && $step == 'tiger'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع tiger-sms.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[tiger][api_key] = $text;
Apps($APPS);
}
#=========={2ndline.io}==========#
if($data == 'add2ndline'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع 2ndline.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","2ndline");
}
if($text && $text != '/start' && $step == '2ndline'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع 2ndline.io بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS['2ndline'][api_key] = $text;
Apps($APPS);
}
#=========={receivesms.store}==========#
if($data == 'addstore'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع receivesms.store*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","store");
}
if($text && $text != '/start' && $step == 'store'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع receivesms.store بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[store][api_key] = $text;
Apps($APPS);
}
#=========={sms.fastpva.com}==========#
if($data == 'addfastpva'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع sms.fastpva.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","fastpva");
}
if($text && $text != '/start' && $step == 'fastpva'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع sms.fastpva.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[fastpva][api_key] = $text;
Apps($APPS);
}
#=========={dropsms.ru}==========#
if($data == 'adddropsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع dropsms.ru*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","dropsms");
}
if($text && $text != '/start' && $step == 'dropsms'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع dropsms.ru بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[dropsms][api_key] = $text;
Apps($APPS);
}
#=========={24sms7.com}==========#
if($data == 'add24sms7'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع 24sms7.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","24sms7");
}
if($text && $text != '/start' && $step == '24sms7'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع 24sms7.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS['24sms7'][api_key] = $text;
Apps($APPS);
}
#=========={sellotp.com}==========#
if($data == 'addsellotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
☑️ *- ارسل رمز ال API للموقع sellotp.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","sellotp");
}
if($text && $text != '/start' && $step == 'sellotp'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع sellotp.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[sellotp][api_key] = $text;
Apps($APPS);
}
#=========={mm.durianrcs.com}==========#
if($data == 'addduraincloud'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ ارسل معلومات ال API للموقع mm.durianrcs.com

1️⃣ ⌯ اليوزر :
2️⃣ ⌯ الباسورد :
3️⃣ ⌯ ايبي الموقع :*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"addapi"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","duraincloud");
}
if($text && $text != '/start' && $step == 'duraincloud'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☔️ ⌯ تم رفع كود ال API للموقع mm.durianrcs.com بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
$APPS[duraincloud][Username] = $extext[0];
$APPS[duraincloud][Password] = $extext[1];
$APPS[duraincloud][api_key] = $extext[2];
Apps($APPS);
}
#=========={حذف API}==========#
if($data == 'delapi'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ قم بإختيار الموقع الذي تريد حذف كود ال API من البوت*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"$sim5",'callback_data'=>"del5sim"]],
[['text'=>"$smslive",'callback_data'=>"delsmslive"],['text'=>"$man",'callback_data'=>"delman"]],
[['text'=>"$vak",'callback_data'=>"delvaksms"]],
[['text'=>"$activate",'callback_data'=>"delactivate"],['text'=>"$pvapins",'callback_data'=>"delpvapins"]],
[['text'=>"$botsms",'callback_data'=>"delbotsms"]],
[['text'=>"$onlinesim",'callback_data'=>"delonlinesim"],['text'=>"$supersmstech",'callback_data'=>"delsupersmstech"]],
[['text'=>"$viotp",'callback_data'=>"delviotp"]],
[['text'=>"$simsms",'callback_data'=>"delsimsms"],['text'=>"$grizzly",'callback_data'=>"delgrizzly"]],
[['text'=>"$smscode",'callback_data'=>"delsmscode"]],
[['text'=>"$tiger",'callback_data'=>"deltiger"],['text'=>"$nd2line",'callback_data'=>"del2ndline"]],
[['text'=>"$store",'callback_data'=>"delstore"]],
[['text'=>"$fastpva",'callback_data'=>"delfastpva"],['text'=>"$dropsms",'callback_data'=>"deldropsms"]],
[['text'=>"$sms7",'callback_data'=>"del24sms7"]],
[['text'=>"$sellotp",'callback_data'=>"delsellotp"],['text'=>"$duraincloud",'callback_data'=>"delduraincloud"]],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'counapi']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={5sim.biz}==========#
if($data == 'del5sim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع 5sim.biz*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• • نعم متأكد ☑️ • •",'callback_data'=>"yesdel5sim"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdel5sim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع 5sim.biz من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS['5sim']);
Apps($APPS);
}
#=========={alisms.pro}==========#
if($data == 'delsmslive'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع alisms.org*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelsmslive"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelsmslive'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع alisms.org من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[alisms]);
Apps($APPS);
}
#=========={sms-man.ru}==========#
if($data == 'delman'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع sms-man.ru*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelman"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelman'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع للموقع sms-man.ru من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[man]);
Apps($APPS);
}
#=========={Vak-sms.com}==========#
if($data == 'delvaksms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع vak-sms.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelvak"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelvak'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع vak-sms.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[vaksms]);
Apps($APPS);
}
#=========={sms-activate.io}==========#
if($data == 'delactivate'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع sms-activate.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelactivate"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelactivate'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع sms-activate.io من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[activate]);
Apps($APPS);
}
#=========={pvapins.com}==========#
if($data == 'delpvapins'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع pvapins.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelpvapins"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelpvapins'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع pvapins.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[pvapins]);
Apps($APPS);
}
#=========={alnwerah.sms}==========#
if($data == 'delbotsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع alnwerah.sms*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelbotsms"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelbotsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع alnwerah.sms من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[botsms]);
Apps($APPS);
}
#=========={onlinesim.io}==========#
if($data == 'delonlinesim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع onlinesim.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelonlinesim"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelonlinesim'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع onlinesim.io من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[onlinesim]);
Apps($APPS);
}
#=========={supersmstech.com}==========#
if($data == 'delsupersmstech'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع supersmstech.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelsupersmstech"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelsupersmstech'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع supersmstech.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[supersmstech]);
Apps($APPS);
}
#=========={viotp.com}==========#
if($data == 'delviotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع viotp.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelviotp"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelviotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع viotp.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[viotp]);
Apps($APPS);
}
#=========={simsms.org}==========#
if($data == 'delsimsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع simsms.org*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelsimsms"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelsimsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع simsms.org من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[simsms]);
Apps($APPS);
}
#=========={grizzlysms.com}==========#
if($data == 'delgrizzly'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع grizzlysms.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelgrizzly"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelgrizzly'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع grizzlysms.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[grizzly]);
Apps($APPS);
}
#=========={sms-code.ru}==========#
if($data == 'delsmscode'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع sms-code.ru*
",
'parse_mode'=>"MarkDown",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelsmscode"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelsmscode'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع sms-code.ru من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[smscode]);
Apps($APPS);
}
#=========={tiger-sms.com}==========#
if($data == 'deltiger'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع tiger-sms.com*
",
'parse_mode'=>"MarkDown",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdeltiger"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdeltiger'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع tiger-sms.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[tiger]);
Apps($APPS);
}
#=========={2ndline.io}==========#
if($data == 'del2ndline'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع 2ndline.io*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdel2ndline"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdel2ndline'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع 2ndline.io من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS['2ndline']);
Apps($APPS);
}
#=========={receivesms.store}==========#
if($data == 'delstore'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع receivesms.store*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelstore"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelstore'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع receivesms.store من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[store]);
Apps($APPS);
}
#=========={sms.fastpva.com}==========#
if($data == 'delfastpva'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع sms.fastpva.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelfastpva"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelfastpva'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع sms.fastpva.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[fastpva]);
Apps($APPS);
}
#=========={dropsms.ru}==========#
if($data == 'deldropsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع dropsms.ru*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdeldropsms"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdeldropsms'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع dropsms.ru من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[dropsms]);
Apps($APPS);
}
#=========={24sms7.com}==========#
if($data == 'del24sms7'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع 24sms7.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdel24sms7"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdel24sms7'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع 24sms7.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS['24sms7']);
Apps($APPS);
}
#=========={sellotp.com}==========#
if($data == 'delsellotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع sellotp.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelsellotp"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelsellotp'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع sellotp.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[sellotp]);
Apps($APPS);
}
#=========={mm.durianrcs.com}==========#
if($data == 'delduraincloud'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⌯ هل انت متأكد بأنك تريد حذف كود ال API للموقع mm.durianrcs.com*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelduraincloud"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"delapi"]]
]
])
]);
}
if($data == 'yesdelduraincloud'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☔️ ⌯ تم حذف كود ال API للموقع mm.durianrcs.com من التخزين بنجاح ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"counapi"]]
]
])
]);
unset($APPS[duraincloud]);
Apps($APPS);
}
#=========={رفع رقم جاهز في البوت}==========#
if($data == 'readynumber'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
*❍ اضف الرقم بالشكل التالي

❍ الاسم :
❍ السعر :
❍ الحالة :
❍ ملاحظة :
❍ الرقم :
❍ الكود :

❍ بعد إرسال الرقم سيتم اضافتة مباشرة ونشر إشعار تلقائي بقناة البوت*
','parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","ready");
}
if($text && $text != '/start' && $step == 'ready'){
$country         = $extext[0];
$price             = $extext[1];
$now             = $extext[2];
$what             = $extext[3];
$number        = $extext[4];
$code             = $extext[5];
$code             = str_replace('-','',$code);
$pk                  = rand(1,100980077);
$num = substr($number, 0,-4)."••••";
if($country == null or $price == null or $now == null or $what == null or $number == null or $code == null or $extext[6] != null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'
لم تقم ب إرسالها بشكل صحيح أعد المحاولة ♻️
',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'
✅ تم إضافة رقم جاهز بنجاح
',
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$sim,
'text'=>"
*☑ ⌯ تم إضافة رقم جديد الى الأرقام الجاهزة!*

☎️ ⪼ الدولة: $country
💸 ⪼ السعر: ₽ ".$price."
☎️ ⪼ الرقم: $num
✳️ ⪼ الحالة: *$now*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'⏮ ⌯ اضغط هنا لشراء الرقم. ☑️','url'=>"t.me/$me?start=ONE"]]
]
])
]);
$storenumber['ready'][$pk]['country'] = $country;
$storenumber['ready'][$pk]['price'] = $price;
$storenumber['ready'][$pk]['now'] = $now;
$storenumber['ready'][$pk]['what'] = $what;
$storenumber['ready'][$pk]['number'] = $number;
$storenumber['ready'][$pk]['code'] = $code;
Ready($storenumber);
}
}
#=========={حذف رقم جاهز}==========#
if($data == 'delreadynumber'){
if(count($storenumber['ready']) >= 1){
$keyboad['inline_keyboard'][] = [['text'=>'الدولة ☑️','callback_data'=>'no'],['text'=>'الرقم ☎️','callback_data'=>'no']];
foreach($storenumber['ready'] as $zero => $name){
$country = $name['country'];
$number = $name['number'];
$keyboad['inline_keyboard'][] = [['text'=>"$country",'callback_data'=>"ahg#$zero"],['text'=>"$number",'callback_data'=>"ahg#$zero"]];
}
$keyboad['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']];
$keyboad      = json_encode($keyboad);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
قم بالضغط على الرقم لحذفه 💯
',
'reply_markup'=>($keyboad),
]);
bot_store_delete("data/id/$id/step.txt");
} else {
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
☑️ عذرا مطوري لم تقم ب إضافة أي دولة في القسم ⚜
",
'show_alert'=>false,
]);
bot_store_delete("data/id/$id/step.txt");
}
}
if($ex_data[0] == "ahg"){
$x = $ex_data[1];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
✅ تم حذف الرقم بنجاح!
',
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
unset($storenumber['ready'][$x]);
Ready($storenumber);
}
#=========={شحن رصيد}==========#
if($data == 'addcoin'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🔰 ⌯ حسناً أرسل الحساب الذي تريد إضافة الرصيد إليه ♻️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","addcoin");
}
if($text && $text != '/start' && $step == 'addcoin'){
if($EMILS['emils'][$text]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*💰 ⌯ أرسل عدد الروبل التي تريد إضافتة للحساب [$text] ♻️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","addcoin2|$text");
}
}
if($text && $text != '/start' && $exstep[0] == 'addcoin2'){
$emile = $exstep[1];
$reo = $EMILS['emils'][$emile]['id'];
$prices = bot_store_get("EMILS/$emile/points.txt");
$pricet = $prices + $text;
$idSend = rand(1234567,9999999);
bot('sendMessage',[
'chat_id'=>$reo,
'text'=>"
*❍ تم شحن حسابك في البوت بنجاح

❍ عدد الروبل المشحون $text ₽
❍ بتاريخ : $DAY3

❍ تم الشحن من قبل : $first* 
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*💁🏻 ⪼ تم إضافة $taxt روبل بنجاح ✅

🌐 ⌯ الحساب: $emile
♻️ ⌯ رصيدة الآن: $pricet 💰
💁🏻 ⌯ تم الشحن بتاريخ ⇩
📆 ⌯ $DAY2*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$reo"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
$points = bot_store_get("EMILS/$emile/points.txt");
$as = $points + $text;
bot_store_set("EMILS/$emile/points.txt",$as);
$rubles = bot_store_get("EMILS/$emile/rubles.txt");
$ds = $rubles + $text;
bot_store_set("EMILS/$emile/rubles.txt",$ds);
$rubleall = bot_store_get("data/txt/rubleall.txt");
$dlls = $rubleall + $text;
bot_store_set("data/txt/rubleall.txt",$dlls);
$BUYSPRIC = bot_json_get("EMILS/$emile/price.json",[]);
$idd = count($BUYSPRIC);
$BUYSPRIC[$idd][id] = $idSend;
$BUYSPRIC[$idd][price] = $text;
$BUYSPRIC[$idd][status] = 2;
$BUYSPRIC[$idd][via] = 1;
$BUYSPRIC[$idd]["chat-id"] = $reo;
$BUYSPRIC[$idd]["user_chat-id"] = $id;
$BUYSPRIC[$idd][emil] = $emile;
$BUYSPRIC[$idd][user_emil] = $EM;
$BUYSPRIC[$idd][user_name] = $first;
$BUYSPRIC[$idd][DAY] = $DAY;
PricBuys($BUYSPRIC,$emile);
$ORDERALL[add] +=1;
OrdAll($ORDERALL);
bot_store_delete("data/id/$id/step.txt");
}
if($extext[0] == 'اضف'){
$emile = $extext[1];
$taxt = $extext[2];
$reo = $EMILS['emils'][$emile]['id'];
$prices = bot_store_get("EMILS/$emile/points.txt");
$pricet = $prices + $taxt;
$idSend = rand(1234567,9999999);
if($taxt == null){
exit;
}
if($EMILS['emils'][$emile]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
exit;
}
bot('sendMessage',[
'chat_id'=>$reo,
'text'=>"
*❍ تم شحن حسابك في البوت بنجاح

❍ عدد الروبل المشحون $text ₽
❍ بتاريخ : $DAY3

❍ تم الشحن من قبل : $first* 
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*💁🏻 ⪼ تم إضافة $taxt روبل بنجاح ✅

🌐 ⌯ الحساب: $emile
♻️ ⌯ رصيدة الآن: $pricet 💰
💁🏻 ⌯ تم الشحن بتاريخ ⇩
📆 ⌯ $DAY2*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$reo"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
$points = bot_store_get("EMILS/$emile/points.txt");
$as = $points + $taxt;
bot_store_set("EMILS/$emile/points.txt",$as);
$rubles = bot_store_get("EMILS/$emile/rubles.txt");
$ds = $rubles + $taxt;
bot_store_set("EMILS/$emile/rubles.txt",$ds);
$rubleall = bot_store_get("data/txt/rubleall.txt");
$dlls = $rubleall + $taxt;
bot_store_set("data/txt/rubleall.txt",$dlls);
$BUYSPRIC = bot_json_get("EMILS/$emile/price.json",[]);
$idd = count($BUYSPRIC);
$BUYSPRIC[$idd][id] = $idSend;
$BUYSPRIC[$idd][price] = $taxt;
$BUYSPRIC[$idd][status] = 2;
$BUYSPRIC[$idd][via] = 1;
$BUYSPRIC[$idd]["chat-id"] = $reo;
$BUYSPRIC[$idd]["user_chat-id"] = $id;
$BUYSPRIC[$idd][emil] = $emile;
$BUYSPRIC[$idd][user_emil] = $EM;
$BUYSPRIC[$idd][user_name] = $first;
$BUYSPRIC[$idd][DAY] = $DAY;
PricBuys($BUYSPRIC,$emile);
$ORDERALL[add] +=1;
OrdAll($ORDERALL);
bot_store_delete("data/id/$id/step.txt");
}
#=========={خصم رصيد}==========#
if($data == 'delcoin'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🔰 ⌯ أرسل الحساب الذي تريد خصم الرصيد منه ♻️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","delcoin");
}
if($text && $text != '/start' && $step == 'delcoin'){
if($EMILS['emils'][$text]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*💰 ⌯ أرسل عدد الروبل التي تريد خصمة من الحساب [$text] ♻️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","delcoin2|$text");
}
}
if($text && $text != '/start' && $exstep[0] == 'delcoin2'){
$emile = $exstep[1];
$reo = $EMILS['emils'][$emile]['id'];
$prices = bot_store_get("EMILS/$emile/points.txt");
$pricet = $prices - $text;
if($text > $prices){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ لايمكنك خصم رصيد اكثر من رصيد المستخدم*
",
'parse_mode'=>"MarkDown",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑ ⪼ تم خصم $text روبل بنجاح

🌐 ⌯ الحساب: $emile
♻️ ⌯ رصيدة الآن: $pricet* 💰
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$reo"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
$points = bot_store_get("EMILS/$emile/points.txt");
$as = $points - $text;
bot_store_set("EMILS/$emile/points.txt",$as);
$rubles = bot_store_get("EMILS/$emile/rubles.txt");
$ds = $rubles - $text;
bot_store_set("EMILS/$emile/rubles.txt",$ds);
$rubleall = bot_store_get("data/txt/rubleall.txt");
$dlls = $rubleall - $text;
bot_store_set("data/txt/rubleall.txt",$dlls);
}
}
if($extext[0] == 'خصم'){
$emile = $extext[1];
$taxt = $extext[2];
$reo = $EMILS['emils'][$emile]['id'];
$prices = bot_store_get("EMILS/$emile/points.txt");
$pricet = $prices - $taxt;
if($EMILS['emils'][$emile]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}elseif($taxt > $prices){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ لايمكنك خصم رصيد اكثر من رصيد المستخدم*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑ ⪼ تم خصم $text روبل بنجاح

🌐 ⌯ الحساب: $emile
♻️ ⌯ رصيدة الآن: $pricet* 💰
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$reo"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
$points = bot_store_get("EMILS/$emile/points.txt");
$as = $points - $taxt;
bot_store_set("EMILS/$emile/points.txt",$as);
$rubles = bot_store_get("EMILS/$emile/rubles.txt");
$ds = $rubles - $taxt;
bot_store_set("EMILS/$emile/rubles.txt",$ds);
$rubleall = bot_store_get("data/txt/rubleall.txt");
$dlls = $rubleall - $taxt;
bot_store_set("data/txt/rubleall.txt",$dlls);
}
}
#=========={الكشف}==========#
if($data == 'cop'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*👮🏻 ⪼ اهلا وسهلا مطوري $first  🖤

الان قم بإختيار موضوع كشف رصيد عضو او رصيد موقع الناقل ❄️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>'• رصيد موقع الارقام 🗃 •','callback_data'=>'simcop']],
[['text'=>'• رصيد حساباتي 💸 •','callback_data'=>'Balancesms']],
[['text'=>'• ♡ رجوع ♡ •','callback_data'=>'c']]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
#=========={كشف ارصده الزبون}==========#
if($data == 'copchat'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*♻️ ⪼ أرسل الأيدي أو الحساب الذي تريد الكشف عن معلوماتة*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","copchat");
}
if($text && $text != '/start' && $step == 'copchat'){
$emils = $EMIL[$text]['emil'];
if($emils == null){
$emils = $EMILS['emils'][$text]['emil'];
}
$ero = $EMILS['emils'][$emils]['id'];
$pricet = bot_store_get("EMILS/$emils/points.txt");
if($pricet == null){
$pricet = 0;
}
if($EMILS['emils'][$emils]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⪼ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🔰 ⪼ قم بالتسجيل للحساب للكشف عنة أكثر ✅

🌐 ⌯ الحساب: $emils
♻️ ⌯ رصيدة: $pricet 💰*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$emils",'callback_data'=>"emils-$emils"]],
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$ero"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
}
if($extext[0] == 'كشف'){
$emils = $EMIL[$extext[1]]['emil'];
if($emils == null){
$emils = $EMILS['emils'][$extext[1]]['emil'];
}
$ero = $EMILS['emils'][$emils]['id'];
$pricet = bot_store_get("EMILS/$emils/points.txt");
if($pricet == null){
$pricet = 0;
}
if($extext[1] == null){
exit;
}
if($EMILS['emils'][$emils]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🔰 ⪼ قم بالتسجيل للحساب للكشف عنة أكثر ✅

🌐 ⌯ الحساب: $emils
♻️ ⌯ رصيدة: $pricet 💰*
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$emils",'callback_data'=>"emils-$emils"]],
[['text'=>"رابط العضو",'url'=>"tg://openmessage?user_id=$ero"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={كشف رصيد للمواقع}==========#
if($data == 'simcop'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🔰 ⪼ أرسل api الموقع الذي تريد معرفة رصيدة!*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","simcop");
}
if($text && $text != '/start' && $step == 'simcop'){
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://5sim.biz/v1/user/profile');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');


$headers = array();
$headers[] = 'Authorization: Bearer ' . $text;
$headers[] = 'Accept: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);
$api=json_decode($result,1);
$price = $api[balance];
$email = $api[email];
if($email != null){
$tax="💬 - الإيميل : $email";
}
if($price == null){
$api = file_get_contents("https://activation.pw/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = file_get_contents("http://api.sms-man.ru/stubs/handler_api.php?action=getBalance&api_key=$text");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = file_get_contents("https://vak-sms.com/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = file_get_contents("https://sms-activate.io/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = json_decode(file_get_contents("https://numbros.shop/jj/tele/GetBalance.php?key=$text&from_id=1134472453"),1);
$price = $api['coin'];
}
if($price == null){
$api = json_decode(file_get_contents("https://onlinesim.io/api/getBalance.php?apikey=$text"),1);
$price = $api[balance];
}
if($price == null){
$api = json_decode(file_get_contents("https://api.viotp.com/users/balance?token=$text"),1);
$price = $api[data][balance];
}
if($price == null){
$api = json_decode(file_get_contents("http://simsms.org/priemnik.php?metod=get_balance&apikey=$text"),1);
$price = $api[balance];
}
if($price == null){
$api = file_get_contents("https://api.grizzlysms.com/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = json_decode(file_get_contents("https://sms-code.ru/api.php?api_key=$text&method=get_balance"),1);
$price = $api[data][balance];
}
if($price == null){
$api = file_get_contents("https://tiger-sms.com/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = json_decode(file_get_contents("https://2ndline.io/apiv1/getbalance?apikey=$text"),1);
$price = $api[balance];
}
if($price == null){
$api = file_get_contents("https://receivesms.store/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = json_decode(file_get_contents("http://api.fastpva.com/pvapublic/user/info?apikey=$text"),1);
$price = $api[data][balance];
$email = $api[data][email];
if($email != null){
$tax="💬 - الإيميل : $email";
}
}
if($price == null){
$api = file_get_contents("https://api.dropsms.cc/stubs/handler_api.php?action=getBalance&api_key=$text");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = file_get_contents("https://24sms7.com/stubs/handler_api.php?api_key=$text&action=getBalance");
$api = explode(":", $api);
$price = $api[1];
}
if($price == null){
$api = json_decode(file_get_contents("https://api.sellotp.com/users/balance?token=$text&lang=en"),1);
$price = $api[data][balance];
}
if($price == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🔰 - ال API الذي أرسلتة غير صحيح ⛔️*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*🔰 - تم الكشف عن الرصيد الذي في الموقع ☑️
💰 - الرصيد : ₽ $price
$tax*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={عدد المشتركين}==========#
if($data == 'members'){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
💯 عدد المشتركين هو $count مشترك
",
'show_alert'=>true,
]);
}
#=========={ارصدة المستخدمين}==========#
if($data == 'baluser'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*🙋🏻 ⪼ إحصائية روبل حسابات الجميع : $poi_money ❗️

✅ ⌯ إحصائيات جميع الروبل : $money2
💸 ⌯ إحصائيات الرصيد المستهلك : $money 
♻️ ⌯ إحصائيات الأرقام المباعه : $numbot 
📮 ⌯ إحصائيات الارقام المستخرجه : $numbots

📆 هذه الأحصائيات من يوم 2025/10/18 م ☑️*
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}
#=========={فك تقييد رقم}==========#
if($data == 'unnum'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
*🆔 ⌯ ارسل ايدي العضو الذي تريد فك تقييده عن الشراء ♻️*
',
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","unnum");
}
if($text && $text != '/start' && $step == 'unnum'){
$text = trim((string)$text);
if(!ctype_digit($text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ ارسل ايدي رقمي صحيح فقط*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
return;
}
$Detector = bot_store_get("data/id/$text/restriction.txt");
if($Detector == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*✅ ⌯ هذا العضو ليس مقيد من سابق*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*✅ ⌯ تم فك تقييد العضو* [$text](tg://user?id=$text) *والسماح لة ب الشراء بنجاح.*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$text,
'text'=>"
*🔓 ⪼ تم فك تقييدك الآن تستطيع ان تقوم ب الشراء بنجاح* ♻️
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
bot_store_delete("data/id/$text/restriction.txt");
bot_store_delete("data/id/$id/step.txt");
}
}
if($extext[0] == 'فك'){
$Detector = bot_store_get("data/id/$extext[1]/restriction.txt");
if($Detector == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*✅ ⪼ هذا العضو ليس مقيد من سابق*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*✅ ⌯ تم فك تقييد العضو* [$extext[1]](tg://user?id=$extext[1]) *والسماح لة ب الشراء بنجاح.*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$extext[1],
'text'=>"
*🔓 ⪼ تم فك تقييدك الآن تستطيع ان تقوم ب الشراء بنجاح* ♻️
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
bot_store_delete("data/id/$extext[1]/restriction.txt");
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={التقييد}==========#
if($data == 'res'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
*☑️ ⌯ ارسل الحساب الذي تريد تقييده في البوت ♻️*
',
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","rees");
}
if($text && $text != '/start' && $step == 'rees'){
$t=$admins[$text]["end"];
$c=$admins[$text]["time"];
$v=$t-(time() - $c);
if($admins[$text]["check"] != null and time() - $c < $t){
$Before = "*☑️ ⪼ مالك هذا الحساب تم تقييدة من سابق لمده $t ثانية ✅
⏰ ⌯ متبقى له: $v ثانية*
";
}
$second=3600;
if($EMILS['emils'][$text]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑️ ⌯ أكتب الوقت الذي تريد تقييد مالك الحساب [$text]* ⏰
$Before
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"☑️ ⌯ تقييد لمده ($second ثانية)",'callback_data'=>"Sd-$text-$second"]],
[['text'=>"➖ سنة ➖",'callback_data'=>"rees-$text-0"],['text'=>"➕ سنة ➕",'callback_data'=>"rees-$text-31107600"]],
[['text'=>"➖ شهر ➖",'callback_data'=>"rees-$text-0"],['text'=>"➕ شهر ➕",'callback_data'=>"rees-$text-2595600"]],
[['text'=>"➖ يوم ➖",'callback_data'=>"rees-$text-0"],['text'=>"➕ يوم ➕",'callback_data'=>"rees-$text-90000"]],
[['text'=>"➖ ساعة ➖",'callback_data'=>"rees-$text-0"],['text'=>"➕ ساعة ➕",'callback_data'=>"rees-$text-7200"]],
[['text'=>"➖ دقيقة ➖",'callback_data'=>"rees-$text-3540"],['text'=>"➕ دقيقة ➕",'callback_data'=>"rees-$text-3660"]],
[['text'=>"➖ ثانية ➖",'callback_data'=>"rees-$text-3599"],['text'=>"➕ ثانية ➕",'callback_data'=>"rees-$text-3601"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
}
if($exdata[0] == "rees"){
$idEM=$exdata[1];
$reo = $EMILS['emils'][$idEM]['id'];
$second=$exdata[2];
if($second <= 0 or $second == null or $second == 0 or $second === 0){
bot_store_delete("data/id/$id/step.txt");
exit;
}
$t=$admins[$idEM]["end"];
$c=$admins[$idEM]["time"];
$v=$t-(time() - $c);
if($admins[$idEM]["check"] != null and time() - $c < $t){
$Before = "*☑️ ⪼ مالك هذا الحساب تم تقييدة من سابق لمده $t ثانية ✅
⏰ ⌯ متبقى له: $v ثانية*
";
}
$y=$second+1;
$i=$second-1;
$h=$second+60;
$k=$second-60;
$u=$second+3600;
$s=$second-3600;
$a=$second+86400;
$b=$second-86400;
$r=$second+2592000;
$w=$second-2592000;
$q=$second+31104000;
$p=$second-31104000;
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ أكتب الوقت الذي تريد تقييد مالك الحساب [$idEM]* ⏰
$Before
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"☑️ ⌯ تقييد لمده ($second ثانية)",'callback_data'=>"Sd-$idEM-$second"]],
[['text'=>"➖ سنة ➖",'callback_data'=>"rees-$idEM-$p"],['text'=>"➕ سنة ➕",'callback_data'=>"rees-$idEM-$q"]],
[['text'=>"➖ شهر ➖",'callback_data'=>"rees-$idEM-$w"],['text'=>"➕ شهر ➕",'callback_data'=>"rees-$idEM-$r"]],
[['text'=>"➖ يوم ➖",'callback_data'=>"rees-$idEM-$b"],['text'=>"➕ يوم ➕",'callback_data'=>"rees-$idEM-$a"]],
[['text'=>"➖ ساعة ➖",'callback_data'=>"rees-$idEM-$s"],['text'=>"➕ ساعة ➕",'callback_data'=>"rees-$idEM-$u"]],
[['text'=>"➖ دقيقة ➖",'callback_data'=>"rees-$idEM-$k"],['text'=>"➕ دقيقة ➕",'callback_data'=>"rees-$idEM-$h"]],
[['text'=>"➖ ثانية ➖",'callback_data'=>"rees-$idEM-$i"],['text'=>"➕ ثانية ➕",'callback_data'=>"rees-$idEM-$y"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}
if($exdata[0] == "Sd"){
$idEM=$exdata[1];
$reo = $EMILS['emils'][$idEM]['id'];
$second=$exdata[2];
if($idEM == null or $second <= 0){
bot_store_delete("data/id/$id/step.txt");
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*☑️ ⪼ تم تقييد الحساب [$idEM] في البوت بنجاح 🌟
⏰ ⌯ المده: $second ثانية*
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$reo,
'text'=>"
*☑️ ⪼ قام المالك بتقييد حسابك في البوت لمده $second ثانية ⏰
♻️ ⌯ اذا تظن أنه تم تقييدك ب الخطأ تواصل مع الدعم اون لاين* ⬇️
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>'• 🚀 تواصل مع الدعم 🚀 •','callback_data'=>"super"]],
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
$admins[$idEM]["check"] = 'ok';
$admins[$idEM]["end"] = $second;
$admins[$idEM]["time"] = time();
Admins($admins);
bot_store_delete("data/id/$id/step.txt");
}
#=========={فك التقييد}==========#
if($data == 'unres'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>'
*☑️ ⌯ ارسل الحساب الذي تريد فك تقييده في البوت ♻️*
',
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","unres");
}
if($text && $text != '/start' && $step == 'unres'){
$reo = $EMILS['emils'][$text]['id'];
if($EMILS['emils'][$text]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"cop"]]
]
])
]);
}elseif($admins[$text]["check"] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑️ ⪼ مالك هذا الحساب ليس مقيد من قبل* 🤷‍♂
",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑️ ⌯ تم فك تقييد الحساب [$text] في البوت بنجاح 🌟*
  ",
'reply_to_message_id'=>$message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot('sendMessage',[
'chat_id'=>$reo,
'text'=>"
*☑️ ⪼ قام المالك بفك تقييدك عن البوت* 🌟
",
'parse_mode'=>"MarkDown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"back"]]
]
])
]);
unset($admins[$text]);
Admins($admins);
bot_store_delete("data/id/$id/step.txt");
}
}
#=========={zero}==========#
if($text == 'تصفير'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'
*🔰 ⌯ أرسل حساب الزبون الذي تريد تصفير حسابه*
',
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_set("data/id/$id/step.txt","zero");
}
if($text && $text != '/start' && $step == 'zero'){
$reo = $EMILS['emils'][$text]['id'];
$pri=bot_store_get("EMILS/$text/points.txt");
if($EMILS['emils'][$text]['emil'] == null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*⛔️ ⌯ عذرا مطوري هذا الإيميل او الأيدي محذوف أو ليس صحيح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*☑️ ⪼ تم تصفير حساب العضو بنحاح
🌐 ⌯ الحساب : $text
🆔 ⌯ ايدية :* [$reo](tg://user?id=$reo)
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• ♡ رجوع ♡ •",'callback_data'=>"c"]]
]
])
]);
bot_store_delete("data/id/$id/step.txt");
bot_store_delete("EMILS/$text/points.txt");
$rubles = bot_store_get("EMILS/$text/rubles.txt");
$ds = $rubles - $pri;
bot_store_set("EMILS/$text/rubles.txt",$ds);
$rubleall = bot_store_get("data/txt/rubleall.txt");
$dlls = $rubleall - $pri;
bot_store_set("data/txt/rubleall.txt",$dlls);
}
}
