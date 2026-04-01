<?php
declare(strict_types=1);

/**
 * 活動テストデータ投入スクリプト
 *
 * 使い方: php tools/seed_activity_test_data.php
 * または ブラウザで http://localhost/insurance-agency/tools/seed_activity_test_data.php
 *
 * ※ 開発環境専用。本番では削除すること。
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$envPath = dirname(__DIR__) . '/.env';
if (!file_exists($envPath)) {
    echo "ERROR: .env not found at {$envPath}\n";
    exit(1);
}

// .env 読み込み
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);
    if (strlen($v) >= 2 && $v[0] === '"' && $v[-1] === '"') {
        $v = substr($v, 1, -1);
    }
    $env[$k] = $v;
}

function pdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

$commonHost = (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1');
$commonPort = (int)   ($env['COMMON_DB_PORT'] ?? 3306);
$commonDb   = (string) ($env['COMMON_DB_NAME'] ?? '');
$commonUser = (string) ($env['COMMON_DB_USER'] ?? '');
$commonPass = (string) ($env['COMMON_DB_PASSWORD'] ?? '');

$tenantHost = (string) ($env['TENANT_DB_HOST'] ?? $commonHost);
$tenantPort = (int)   ($env['TENANT_DB_PORT'] ?? $commonPort);
$tenantUser = (string) ($env['TENANT_DB_USER'] ?? $commonUser);
$tenantPass = (string) ($env['TENANT_DB_PASSWORD'] ?? $commonPass);

$common = pdo($commonHost, $commonPort, $commonDb, $commonUser, $commonPass);

// 最初のアクティブな管理者ユーザーとそのテナントを取得
$admin = $common->query(
    "SELECT u.id AS user_id, u.name AS user_name, ut.tenant_code, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
     ORDER BY (ut.role = 'admin') DESC, u.id ASC
     LIMIT 1"
)->fetch();

if (!is_array($admin)) {
    echo "ERROR: アクティブユーザーが見つかりません。\n";
    exit(1);
}

$userId  = (int)    $admin['user_id'];
$dbName  = (string) $admin['db_name'];
echo "ユーザー: {$admin['user_name']} (id={$userId}), DB: {$dbName}\n";

$tenant = pdo($tenantHost, $tenantPort, $dbName, $tenantUser, $tenantPass);

// 顧客を最大5件取得
$customers = $tenant->query(
    "SELECT id, customer_name FROM m_customer WHERE is_deleted = 0 ORDER BY id ASC LIMIT 5"
)->fetchAll();

if (empty($customers)) {
    echo "ERROR: 顧客が1件もありません。先に顧客を登録してください。\n";
    exit(1);
}

echo "使用する顧客:\n";
foreach ($customers as $c) {
    echo "  - {$c['customer_name']} (id={$c['id']})\n";
}
echo "\n";

// テストデータ定義
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$twoDays   = date('Y-m-d', strtotime('-2 days'));
$threeDays = date('Y-m-d', strtotime('-3 days'));
$nextWeek  = date('Y-m-d', strtotime('+7 days'));
$nextMonth = date('Y-m-d', strtotime('+30 days'));

$cust0 = (int) $customers[0]['id'];
$cust1 = (int) ($customers[1]['id'] ?? $cust0);
$cust2 = (int) ($customers[2]['id'] ?? $cust0);

$seeds = [
    [
        'customer_id'      => $cust0,
        'activity_date'    => $today,
        'start_time'       => '10:00:00',
        'end_time'         => '11:00:00',
        'activity_type'    => 'visit',
        'subject'          => '定期訪問・契約更新の確認',
        'content_summary'  => '満期が近づいているため訪問。更新の意向を確認した。次回見積書を持参予定。',
        'detail_text'      => "お客様は更新に前向き。家族構成に変化があるため補償内容の見直しが必要とのこと。\n次回は奥様も同席予定。",
        'next_action_date' => $nextWeek,
        'next_action_note' => '見積書持参・補償内容の見直し提案',
        'result_type'      => 'follow_required',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust1,
        'activity_date'    => $today,
        'start_time'       => '14:30:00',
        'end_time'         => '14:45:00',
        'activity_type'    => 'call',
        'subject'          => '事故報告の受付確認',
        'content_summary'  => '先日の事故について保険金請求の流れを説明。書類の送付先を案内した。',
        'detail_text'      => null,
        'next_action_date' => null,
        'next_action_note' => null,
        'result_type'      => 'completed',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust2,
        'activity_date'    => $yesterday,
        'start_time'       => '09:00:00',
        'end_time'         => '09:30:00',
        'activity_type'    => 'online',
        'subject'          => 'オンライン面談・新規契約提案',
        'content_summary'  => 'Web会議にて生命保険の新規提案を実施。パンフレットをメール送付済み。',
        'detail_text'      => "関心度は高い。来週末までに返事をもらえる予定。",
        'next_action_date' => $nextWeek,
        'next_action_note' => '返答確認の連絡',
        'result_type'      => 'follow_required',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust0,
        'activity_date'    => $yesterday,
        'start_time'       => null,
        'end_time'         => null,
        'activity_type'    => 'email',
        'subject'          => '満期案内メール送付',
        'content_summary'  => '満期60日前のご案内メールを送付。更新手続きのご案内と連絡を依頼。',
        'detail_text'      => null,
        'next_action_date' => null,
        'next_action_note' => null,
        'result_type'      => 'completed',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust1,
        'activity_date'    => $twoDays,
        'start_time'       => '11:00:00',
        'end_time'         => '12:00:00',
        'activity_type'    => 'visit',
        'subject'          => '法人契約の見直し相談',
        'content_summary'  => '事務所を訪問。現在の火災保険の保障内容を確認し、見直し案を提示した。',
        'detail_text'      => "設備更新により保険価額の見直しが必要。追加見積を依頼された。",
        'next_action_date' => $nextMonth,
        'next_action_note' => '追加見積書の提出',
        'result_type'      => 'follow_required',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust2,
        'activity_date'    => $threeDays,
        'start_time'       => '16:00:00',
        'end_time'         => '16:20:00',
        'activity_type'    => 'call',
        'subject'          => '書類不備の確認電話',
        'content_summary'  => '保険金請求書類に不備があったため電話確認。再提出をお願いした。',
        'detail_text'      => null,
        'next_action_date' => null,
        'next_action_note' => null,
        'result_type'      => 'completed',
        'staff_user_id'    => $userId,
    ],
    [
        'customer_id'      => $cust0,
        'activity_date'    => $threeDays,
        'start_time'       => null,
        'end_time'         => null,
        'activity_type'    => 'other',
        'subject'          => '年賀状・お礼状の送付',
        'content_summary'  => 'お礼状および年度末ご挨拶の郵便物を送付。',
        'detail_text'      => null,
        'next_action_date' => null,
        'next_action_note' => null,
        'result_type'      => 'completed',
        'staff_user_id'    => $userId,
    ],
];

$stmt = $tenant->prepare(
    'INSERT INTO t_activity
        (customer_id, activity_date, start_time, end_time, activity_type,
         subject, content_summary, detail_text, next_action_date, next_action_note,
         result_type, staff_user_id, sales_case_id)
     VALUES
        (:customer_id, :activity_date, :start_time, :end_time, :activity_type,
         :subject, :content_summary, :detail_text, :next_action_date, :next_action_note,
         :result_type, :staff_user_id, NULL)'
);

$inserted = 0;
foreach ($seeds as $seed) {
    $stmt->execute([
        'customer_id'      => $seed['customer_id'],
        'activity_date'    => $seed['activity_date'],
        'start_time'       => $seed['start_time'],
        'end_time'         => $seed['end_time'],
        'activity_type'    => $seed['activity_type'],
        'subject'          => $seed['subject'],
        'content_summary'  => $seed['content_summary'],
        'detail_text'      => $seed['detail_text'],
        'next_action_date' => $seed['next_action_date'],
        'next_action_note' => $seed['next_action_note'],
        'result_type'      => $seed['result_type'],
        'staff_user_id'    => $seed['staff_user_id'],
    ]);
    $id = (int) $tenant->lastInsertId();
    echo "  INSERT t_activity id={$id}: [{$seed['activity_date']}] {$seed['activity_type']} - {$seed['subject']}\n";
    $inserted++;
}

echo "\n合計 {$inserted} 件のテストデータを挿入しました。\n";
echo "活動一覧: http://localhost/insurance-agency/?route=activity/list\n";
