<?php
// One-time maintenance script:
// - Normalize balances to RUB-only semantics
// - Merge duplicate users by case-insensitive account_email
// - Re-link dependent rows (movements, payments, orders, transfers)

declare(strict_types=1);

include_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

function m_table_exists(SQLite3 $db, string $table): bool {
    $stmt = $db->prepare('SELECT 1 FROM sqlite_master WHERE type = "table" AND name = :name LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bindValue(':name', $table, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_NUM) : false;
    if ($res) {
        $res->finalize();
    }
    return !empty($row);
}

function m_scalar(SQLite3 $db, string $sql): float {
    $v = $db->querySingle($sql);
    return is_numeric($v) ? (float)$v : 0.0;
}

function m_merge_duplicates(SQLite3 $db): int {
    if (!m_table_exists($db, 'users')) {
        return 0;
    }

    $mergedUsers = 0;
    $dup = $db->query('SELECT LOWER(TRIM(account_email)) AS em, COUNT(*) AS c FROM users WHERE TRIM(COALESCE(account_email, "")) <> "" GROUP BY LOWER(TRIM(account_email)) HAVING COUNT(*) > 1');
    while ($dup && ($d = $dup->fetchArray(SQLITE3_ASSOC))) {
        $em = (string)($d['em'] ?? '');
        if ($em === '') {
            continue;
        }

        $rows = [];
        $stmt = $db->prepare('SELECT id, username, telegram_username, account_email, account_password, COALESCE(login_count,0) AS login_count, COALESCE(last_login_at,0) AS last_login_at, COALESCE(balance_rub,0) AS balance_rub FROM users WHERE LOWER(TRIM(account_email)) = :em ORDER BY login_count DESC, last_login_at DESC, id ASC');
        if (!$stmt) {
            continue;
        }
        $stmt->bindValue(':em', $em, SQLITE3_TEXT);
        $rs = $stmt->execute();
        while ($rs && ($r = $rs->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $r;
        }
        if ($rs) {
            $rs->finalize();
        }
        if (count($rows) < 2) {
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

        foreach (array_slice($rows, 1) as $drop) {
            $dropId = (int)($drop['id'] ?? 0);
            if ($dropId <= 0 || $dropId === $keepId) {
                continue;
            }

            if ($bestUsername === '' && trim((string)($drop['username'] ?? '')) !== '') $bestUsername = trim((string)$drop['username']);
            if ($bestTg === '' && trim((string)($drop['telegram_username'] ?? '')) !== '') $bestTg = trim((string)$drop['telegram_username']);
            if ($bestPass === '' && trim((string)($drop['account_password'] ?? '')) !== '') $bestPass = trim((string)$drop['account_password']);
            if ($bestEmail === '' && trim((string)($drop['account_email'] ?? '')) !== '') $bestEmail = trim((string)$drop['account_email']);
            if ((int)($drop['login_count'] ?? 0) > $bestLogin) $bestLogin = (int)$drop['login_count'];
            if ((int)($drop['last_login_at'] ?? 0) > $bestLast) $bestLast = (int)$drop['last_login_at'];
            if ((float)($drop['balance_rub'] ?? 0) > $bestRub) $bestRub = (float)$drop['balance_rub'];

            if (m_table_exists($db, 'user_movements')) $db->exec('UPDATE user_movements SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
            if (m_table_exists($db, 'payment_transactions')) $db->exec('UPDATE payment_transactions SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
            if (m_table_exists($db, 'active_orders')) $db->exec('UPDATE active_orders SET user_id = ' . $keepId . ' WHERE user_id = ' . $dropId);
            if (m_table_exists($db, 'balance_transfers')) {
                $db->exec('UPDATE balance_transfers SET from_user_id = ' . $keepId . ' WHERE from_user_id = ' . $dropId);
                $db->exec('UPDATE balance_transfers SET to_user_id = ' . $keepId . ' WHERE to_user_id = ' . $dropId);
            }
            $db->exec('DELETE FROM users WHERE id = ' . $dropId);
            $mergedUsers++;
        }

        $up = $db->prepare('UPDATE users SET username = :username, telegram_username = :tg, account_email = :email, account_password = :pass, login_count = :login_count, last_login_at = :last_login_at, balance_rub = :balance_rub, balance_usd = 0 WHERE id = :id');
        if ($up) {
            $up->bindValue(':username', $bestUsername, SQLITE3_TEXT);
            $up->bindValue(':tg', $bestTg, SQLITE3_TEXT);
            $up->bindValue(':email', $bestEmail !== '' ? $bestEmail : $em, SQLITE3_TEXT);
            $up->bindValue(':pass', $bestPass, SQLITE3_TEXT);
            $up->bindValue(':login_count', $bestLogin, SQLITE3_INTEGER);
            $up->bindValue(':last_login_at', $bestLast, SQLITE3_INTEGER);
            $up->bindValue(':balance_rub', $bestRub, SQLITE3_FLOAT);
            $up->bindValue(':id', $keepId, SQLITE3_INTEGER);
            $up->execute();
        }
    }
    if ($dup) {
        $dup->finalize();
    }

    return $mergedUsers;
}

$db = bot_db_manager()->connection();

$beforeUsers = (int)m_scalar($db, 'SELECT COUNT(*) FROM users');
$beforeRub = m_scalar($db, 'SELECT COALESCE(SUM(balance_rub),0) FROM users');
$beforeUsd = m_scalar($db, 'SELECT COALESCE(SUM(balance_usd),0) FROM users');
$beforeDup = (int)m_scalar($db, 'SELECT COUNT(*) FROM (SELECT LOWER(TRIM(account_email)) em, COUNT(*) c FROM users WHERE TRIM(COALESCE(account_email, "")) <> "" GROUP BY LOWER(TRIM(account_email)) HAVING COUNT(*) > 1)');

$db->exec('BEGIN IMMEDIATE');

if (m_table_exists($db, 'users')) {
    $db->exec('UPDATE users SET balance_rub = CASE WHEN COALESCE(balance_rub,0) = 0 AND COALESCE(balance_usd,0) > 0 THEN balance_usd ELSE balance_rub END, balance_usd = 0');
}
if (m_table_exists($db, 'user_movements')) {
    $db->exec('UPDATE user_movements SET amount_rub = CASE WHEN COALESCE(amount_rub,0) = 0 AND COALESCE(amount_usd,0) > 0 THEN amount_usd ELSE amount_rub END, balance_rub = CASE WHEN COALESCE(balance_rub,0) = 0 AND COALESCE(balance_usd,0) > 0 THEN balance_usd ELSE balance_rub END, amount_usd = 0, balance_usd = 0 WHERE source_path = "documents"');
}

$merged = m_merge_duplicates($db);

$db->exec('COMMIT');

$afterUsers = (int)m_scalar($db, 'SELECT COUNT(*) FROM users');
$afterRub = m_scalar($db, 'SELECT COALESCE(SUM(balance_rub),0) FROM users');
$afterUsd = m_scalar($db, 'SELECT COALESCE(SUM(balance_usd),0) FROM users');
$afterDup = (int)m_scalar($db, 'SELECT COUNT(*) FROM (SELECT LOWER(TRIM(account_email)) em, COUNT(*) c FROM users WHERE TRIM(COALESCE(account_email, "")) <> "" GROUP BY LOWER(TRIM(account_email)) HAVING COUNT(*) > 1)');

echo "RUB cleanup completed\n";
echo "before: users={$beforeUsers}, rub={$beforeRub}, usd={$beforeUsd}, dup_groups={$beforeDup}\n";
echo "after : users={$afterUsers}, rub={$afterRub}, usd={$afterUsd}, dup_groups={$afterDup}\n";
echo "merged_user_rows={$merged}\n";
