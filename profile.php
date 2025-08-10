<?php
// エラー表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB接続


    // 色の感情スコアマップ (1:非常に沈鬱 〜 5:非常に幸福)
    $color_mood_map = [
        '#fef7cd' => 4, // Light Yellow (穏やか、明るい)
        '#ff6b6b' => 3, // Reddish (情熱、活発)
        '#ffa726' => 4, // Orange (元気、明るい)
        '#f472b6' => 5, // Pink (優しい、幸福)
        '#fb923c' => 4, // Dark Orange (活発、少し落ち着き)
        '#8b5cf6' => 3, // Purple (落ち着き、神秘)
        '#06b6d4' => 4, // Cyan (冷静、爽やか)
        '#ec4899' => 5, // Dark Pink (非常に幸福、愛情)
        '#a78bfa' => 4, // Light Purple (穏やか、癒し)
        '#10b981' => 4, // Green (安定、成長)
        '#0ea5e9' => 3, // Blue (冷静、少し悲しみ)
        '#d97706' => 2, // Dark Yellow/Brown (沈んだ、不調)
        '#dc2626' => 1, // Dark Red (怒り、非常に不調)
        '#e5e7eb' => 2, // Light Grey (無感情、疲労)
        '#374151' => 1, // Dark Grey (沈鬱、重い)
        '#ffffff' => 3, // White (ニュートラル)
        '#000000' => 1, // Black (非常に沈鬱)
    ];

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

/* ------------------------
   削除（ゴミ箱へ移動）
------------------------ */
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $pdo->prepare("UPDATE sns_posts SET is_deleted = 1 WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $delete_id, ':user_id' => $_SESSION['user_id']]);
}

/* ------------------------
   復元
------------------------ */
if (isset($_POST['restore_id'])) {
    $restore_id = $_POST['restore_id'];
    $stmt = $pdo->prepare("UPDATE sns_posts SET is_deleted = 0 WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $restore_id, ':user_id' => $_SESSION['user_id']]);
}

/* ------------------------
   完全削除
------------------------ */
if (isset($_POST['permanent_delete_id'])) {
    $permanent_delete_id = $_POST['permanent_delete_id'];
    $stmt = $pdo->prepare("DELETE FROM sns_posts WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $permanent_delete_id, ':user_id' => $_SESSION['user_id']]);
}

/* ------------------------
   カレンダー用データ取得
------------------------ */
$current_year = $_GET['year'] ?? date('Y');
$current_month = $_GET['month'] ?? date('n');

// 指定月の投稿データを取得（日付と色）
$stmt = $pdo->prepare("SELECT DATE(created_at) as post_date, color, content, id
                       FROM sns_posts 
                       WHERE user_id = :user_id 
                       AND YEAR(created_at) = :year 
                       AND MONTH(created_at) = :month 
                       AND is_deleted = 0 
                       ORDER BY created_at DESC");
$stmt->execute([
    ':user_id' => $_SESSION['user_id'],
    ':year' => $current_year,
    ':month' => $current_month
]);
$calendar_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 日付をキーとした配列に変換
$posts_by_date = [];
foreach ($calendar_posts as $post) {
    $date = $post['post_date'];
    if (!isset($posts_by_date[$date])) {
        $posts_by_date[$date] = [];
    }
    $posts_by_date[$date][] = $post;
}

/* ------------------------
   投稿一覧取得（コメント数も含む）
------------------------ */
$stmt = $pdo->prepare("SELECT p.*, 
                       (SELECT COUNT(*) FROM sns_comments c WHERE c.post_id = p.id AND c.is_deleted = 0) as comment_count
                       FROM sns_posts p 
                       WHERE p.user_id = :user_id AND p.is_deleted = 0 
                       ORDER BY p.created_at DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------
   ゴミ箱一覧取得
------------------------ */
$stmt = $pdo->prepare("SELECT * FROM sns_posts WHERE user_id = :user_id AND is_deleted = 1 ORDER BY created_at DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$trash_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------
   色分析データ取得
------------------------ */
$stmt = $pdo->prepare("SELECT color, COUNT(*) as count FROM sns_posts WHERE user_id = :user_id AND is_deleted = 0 GROUP BY color ORDER BY count DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$color_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------
   統計データ取得
------------------------ */
$stmt = $pdo->prepare("SELECT COUNT(*) as total_posts FROM sns_posts WHERE user_id = :user_id AND is_deleted = 0");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$total_posts = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as private_posts FROM sns_posts WHERE user_id = :user_id AND is_deleted = 0 AND is_private = 1");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$private_posts = $stmt->fetchColumn();

// コメント統計
$stmt = $pdo->prepare("SELECT COUNT(*) as total_comments FROM sns_comments WHERE user_id = :user_id AND is_deleted = 0");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$total_comments = $stmt->fetchColumn();

// 自分の投稿に対するコメント数
$stmt = $pdo->prepare("SELECT COUNT(*) as received_comments 
                       FROM sns_comments c 
                       JOIN sns_posts p ON c.post_id = p.id 
                       WHERE p.user_id = :user_id AND c.is_deleted = 0 AND p.is_deleted = 0");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$received_comments = $stmt->fetchColumn();

    // 感情の推移グラフ用データ取得 (過去30日間の日別平均感情スコア)
    $mood_trend_data = [];
    $mood_scores_last_30_days = []; // 感情診断用: 全スコアを収集

    $stmt = $pdo->prepare("SELECT DATE(created_at) as post_date, color 
                           FROM sns_posts 
                           WHERE user_id = :user_id 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                           AND is_deleted = 0 
                           ORDER BY created_at ASC");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $daily_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 過去30日間の日付を初期化
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $mood_trend_data[$date] = ['total_score' => 0, 'count' => 0];
    }

    foreach ($daily_posts as $post) {
        $date = $post['post_date'];
        $color = $post['color'];
        $score = $color_mood_map[$color] ?? 3; // マップにない色はニュートラル(3)と仮定
        
        if (isset($mood_trend_data[$date])) {
            $mood_trend_data[$date]['total_score'] += $score;
            $mood_trend_data[$date]['count']++;
        }
        $mood_scores_last_30_days[] = $score; // 感情診断用に全スコアを収集
    }

    $mood_trend_labels = [];
    $mood_trend_values = [];
    foreach ($mood_trend_data as $date => $data) {
        $mood_trend_labels[] = date('m/d', strtotime($date)); // チャート表示用に月/日形式にフォーマット
        $mood_trend_values[] = $data['count'] > 0 ? round($data['total_score'] / $data['count'], 2) : 0; // 平均スコア、投稿がなければ0
    }

    // 感情診断用: 過去30日間の平均感情スコアを計算
    $overall_avg_mood = 0;
    if (!empty($mood_scores_last_30_days)) {
        $overall_avg_mood = array_sum($mood_scores_last_30_days) / count($mood_scores_last_30_days);
    }

    // 感情診断メッセージの条件判定
    $show_mood_support_message = false;
    $mood_support_message = "";
    if ($overall_avg_mood > 0) { // 投稿がある場合のみメッセージを表示
        $show_mood_support_message = true;
        if ($overall_avg_mood < 2.5) {
            $mood_support_message = "最近、少し心が疲れているかもしれませんね。無理せず、ゆっくり休む時間も大切にしてください。";
        } elseif ($overall_avg_mood >= 2.5 && $overall_avg_mood < 3.5) {
            $mood_support_message = "最近の感情は穏やかですが、時々変化があるようです。自分の気持ちに寄り添ってあげましょう。";
        } else { // $overall_avg_mood >= 3.5
            $mood_support_message = "最近のあなたは、とてもポジティブで充実しているようですね！素晴らしいです。";
        }
    }

// 期間別感情レポート用データ取得
$report_data = [
    'weekly' => [],
    'monthly' => [],
    'yearly' => []
];

// 過去7日間のデータ
$stmt = $pdo->prepare("SELECT color, COUNT(*) as count FROM sns_posts
                       WHERE user_id = :user_id AND is_deleted = 0
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       GROUP BY color ORDER BY count DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$report_data['weekly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 過去30日間のデータ
$stmt = $pdo->prepare("SELECT color, COUNT(*) as count FROM sns_posts
                       WHERE user_id = :user_id AND is_deleted = 0
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                       GROUP BY color ORDER BY count DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$report_data['monthly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 過去1年間のデータ
$stmt = $pdo->prepare("SELECT color, COUNT(*) as count FROM sns_posts
                       WHERE user_id = :user_id AND is_deleted = 0
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                       GROUP BY color ORDER BY count DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$report_data['yearly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マイページ | ココベース</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* デフォルトテーマ */
            --bg-primary: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --accent-primary: linear-gradient(45deg, #ff6b6b, #ffa726);
            --accent-primary-rgb: 255, 107, 107; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #10b981, #06b6d4);
            --border-color: #e5e7eb;
            --shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* ダークモード */
        [data-theme="dark"] {
            --bg-primary: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            --bg-card: #374151;
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --accent-primary: linear-gradient(45deg, #8b5cf6, #06b6d4);
            --accent-primary-rgb: 139, 92, 246; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #ec4899, #8b5cf6);
            --border-color: #4b5563;
            --shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        /* 春テーマ */
        [data-theme="spring"] {
            --bg-primary: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --accent-primary: linear-gradient(45deg, #f472b6, #ec4899);
            --accent-primary-rgb: 244, 114, 182; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #a78bfa, #f472b6);
            --border-color: #f3e8ff;
            --shadow: 0 4px 15px rgba(244, 114, 182, 0.2);
        }

        /* 夏テーマ */
        [data-theme="summer"] {
            --bg-primary: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            --bg-card: #ffffff;
            --text-primary: #1e40af;
            --text-secondary: #3730a3;
            --accent-primary: linear-gradient(45deg, #06b6d4, #0ea5e9);
            --accent-primary-rgb: 6, 182, 212; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #10b981, #06b6d4);
            --border-color: #dbeafe;
            --shadow: 0 4px 15px rgba(6, 182, 212, 0.2);
        }

        /* 秋テーマ */
        [data-theme="autumn"] {
            --bg-primary: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
            --bg-card: #ffffff;
            --text-primary: #92400e;
            --text-secondary: #b45309;
            --accent-primary: linear-gradient(45deg, #f59e0b, #d97706);
            --accent-primary-rgb: 245, 158, 11; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #dc2626, #f59e0b);
            --border-color: #fed7aa;
            --shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
        }

        /* 冬テーマ */
        [data-theme="winter"] {
            --bg-primary: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            --bg-card: #ffffff;
            --text-primary: #0c4a6e;
            --text-secondary: #0369a1;
            --accent-primary: linear-gradient(45deg, #0ea5e9, #0284c7);
            --accent-primary-rgb: 14, 165, 233; /* 追加 */
            --accent-secondary: linear-gradient(45deg, #8b5cf6, #0ea5e9);
            --border-color: #e0f2fe;
            --shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
        }

        body { 
            font-family: 'Noto Sans JP', sans-serif; 
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .card-shadow {
            box-shadow: var(--shadow);
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .logo-text {
            background: var(--accent-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .post-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .theme-selector {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }

        .theme-button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-button:hover {
            transform: scale(1.1);
        }

        .theme-button.active {
            border: 3px solid var(--text-primary);
        }

        /* ナビゲーションバー */
        .navbar {
            background: var(--bg-card);
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border-color);
        }

        .logo-icon {
            animation: heartbeat 2s ease-in-out infinite;
        }

        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* カレンダーのスタイル */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: var(--border-color);
            border-radius: 12px;
            padding: 2px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .calendar-day:hover {
            transform: scale(1.05);
        }

        .calendar-day.has-post {
            color: white;
            font-weight: bold;
        }

        .calendar-day.other-month {
            opacity: 0.3;
            color: var(--text-secondary);
        }

        .calendar-day.today {
            border: 2px solid var(--accent-primary);
        }

        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }

        .calendar-header-day {
            text-align: center;
            font-weight: 600;
            font-size: 12px;
            color: var(--text-secondary);
            padding: 8px 0;
        }

        /* モーダルスタイル */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: var(--bg-card);
            margin: 15% auto;
            padding: 20px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
        }

        .close {
            color: var(--text-secondary);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-primary);
        }
        
        .report-tab.active {
            background: var(--accent-primary);
            color: white;
        }
    </style>
</head>
<body>
    <!-- ナビゲーションバー -->
    <nav class="navbar fixed top-0 left-0 right-0 z-50 flex justify-between items-center px-4 py-3">
        <div class="flex items-center space-x-3">
            <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl logo-icon relative" style="background: var(--accent-primary);">
                <!-- 家のベース -->
                <svg class="w-5 h-5 text-white absolute" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                <!-- ハート -->
                <svg class="w-3 h-3 text-white absolute top-0.5 right-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="text-xl font-bold logo-text">ココベース</div>
        </div>
        <div class="flex space-x-4">
            <a href="home.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">ホーム</a>
            <a href="profile.php" class="px-3 py-2 rounded-lg font-medium transition-colors" style="color: var(--accent-primary);">マイページ</a>
            <a href="groups.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">グループ</a>
            <a href="diary.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">感謝日記</a>
            <a href="trash.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">ゴミ箱</a>
            <a href="logout.php" class="px-3 py-2 rounded-lg font-medium text-red-500 hover:bg-red-50 transition-colors">ログアウト</a>
        </div>
    </nav>
    <div class="mt-20"></div> <!-- ヘッダー分の余白を確保 -->

    <!-- テーマセレクター -->
    <div class="theme-selector">
        <div class="flex flex-col space-y-2">
            <button class="theme-button" data-theme="default" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);" title="デフォルト"></button>
            <button class="theme-button" data-theme="dark" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%);" title="ダークモード"></button>
            <button class="theme-button" data-theme="spring" style="background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);" title="春テーマ"></button>
            <button class="theme-button" data-theme="summer" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);" title="夏テーマ"></button>
            <button class="theme-button" data-theme="autumn" style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);" title="秋テーマ"></button>
            <button class="theme-button" data-theme="winter" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);" title="冬テーマ"></button>
        </div>
    </div>

    <!-- モーダル -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalContent"></div>
        </div>
    </div>

    <div class="min-h-screen">
        <div class="max-w-4xl mx-auto p-4 md:p-6">
            <!-- プロフィールヘッダー -->
            <div class="card-shadow rounded-2xl p-6 mb-6 text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background: var(--accent-secondary);">
                    <span class="text-white text-2xl font-bold"><?= mb_substr(htmlspecialchars($_SESSION['username']), 0, 1); ?></span>
                </div>
                <h1 class="text-2xl font-bold logo-text mb-2"><?= htmlspecialchars($_SESSION['username']); ?>さんのマイページ</h1>
                <p class="text-sm" style="color: var(--text-secondary);">ココベースでのあなたの記録と想いの軌跡</p>
            </div>

            <!-- 感情の推移グラフ -->
            <div class="card-shadow rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                    </svg>
                    感情の推移 (過去30日間)
                </h2>
                <div class="h-64">
                    <canvas id="moodTrendChart"></canvas>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        日々の投稿から算出された平均感情スコアの推移です。
                    </p>
                </div>
            </div>

            <?php if ($show_mood_support_message): ?>
                <div class="card-shadow rounded-2xl p-6 mb-6 text-center border-l-4" style="border-color: var(--accent-primary);">
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">ココベースからのメッセージ</h3>
                    <p class="text-base" style="color: var(--text-secondary);">
                        <?= htmlspecialchars($mood_support_message) ?>
                    </p>
                    <?php if ($overall_avg_mood < 3.5): // 平均スコアが3.5未満の場合にサポートリンクを表示 ?>
                        <div class="mt-4">
                            <a href="#" class="text-white px-4 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-secondary);">
                                心のケアに関する情報を見る
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 統計情報 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card-shadow rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background: var(--accent-primary);">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold"><?= $total_posts; ?></h3>
                    <p class="text-sm" style="color: var(--text-secondary);">総投稿数</p>
                </div>
                <div class="card-shadow rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background: var(--accent-secondary);">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold"><?= $private_posts; ?></h3>
                    <p class="text-sm" style="color: var(--text-secondary);">プライベート投稿</p>
                </div>
                <div class="card-shadow rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold"><?= $total_comments; ?></h3>
                    <p class="text-sm" style="color: var(--text-secondary);">投稿したコメント</p>
                </div>
                <div class="card-shadow rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-blue-400 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold"><?= $received_comments; ?></h3>
                    <p class="text-sm" style="color: var(--text-secondary);">受け取ったコメント</p>
                </div>
            </div>

            <!-- 期間別感情レポート -->
            <div class="card-shadow rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                    </svg>
                    感情の変化レポート
                </h2>
                
                <div class="flex justify-center mb-4">
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <button class="report-tab px-4 py-2 rounded-md text-sm font-medium transition-colors active" data-period="weekly">週間</button>
                        <button class="report-tab px-4 py-2 rounded-md text-sm font-medium transition-colors" data-period="monthly">月間</button>
                        <button class="report-tab px-4 py-2 rounded-md text-sm font-medium transition-colors" data-period="yearly">年間</button>
                    </div>
                </div>
                
                <div class="h-64">
                    <canvas id="reportChart"></canvas>
                </div>
                
                <div class="mt-4 text-center">
                    <p class="text-sm" style="color: var(--text-secondary);" id="reportDescription">
                        過去7日間の感情の色分布です
                    </p>
                </div>
            </div>

            <!-- 気分のカレンダー -->
            <div class="card-shadow rounded-2xl p-6 mb-6">
                <div class="max-w-md mx-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold flex items-center">
                            <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                            気分のカレンダー
                        </h2>
                        <div class="flex items-center space-x-2">
                            <a href="?year=<?= $current_year ?>&month=<?= $current_month - 1 < 1 ? 12 : $current_month - 1 ?><?= $current_month - 1 < 1 ? '&year=' . ($current_year - 1) : '' ?>" 
                               class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </a>
                            <h3 class="text-lg font-medium" style="color: var(--text-primary);">
                                <?= $current_year ?>年<?= $current_month ?>月
                            </h3>
                            <a href="?year=<?= $current_year ?>&month=<?= $current_month + 1 > 12 ? 1 : $current_month + 1 ?><?= $current_month + 1 > 12 ? '&year=' . ($current_year + 1) : '' ?>" 
                               class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- カレンダーヘッダー -->
                    <div class="calendar-header">
                        <div class="calendar-header-day">日</div>
                        <div class="calendar-header-day">月</div>
                        <div class="calendar-header-day">火</div>
                        <div class="calendar-header-day">水</div>
                        <div class="calendar-header-day">木</div>
                        <div class="calendar-header-day">金</div>
                        <div class="calendar-header-day">土</div>
                    </div>

                    <!-- カレンダーグリッド -->
                    <div class="calendar-grid" id="calendar">
                        <!-- JavaScriptで生成 -->
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            色のついた日をクリックすると、その日の投稿を見ることができます
                        </p>
                    </div>
                </div>
            </div>

            <!-- 色分析結果 -->
            <div class="card-shadow rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 102 0V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 2V6a1 1 0 112 0v1a1 1 0 11-2 0zm2 3a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                    あなたの気持ちの色分析
                </h2>
                <?php if (!empty($color_analysis)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($color_analysis as $row): ?>
                            <div class="flex items-center p-3 rounded-xl border" style="background-color: <?= htmlspecialchars($row['color']); ?>; border-color: var(--border-color);">
                                <div class="w-8 h-8 rounded-full mr-3 border-2 border-white" style="background-color: <?= htmlspecialchars($row['color']); ?>;"></div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);"><?= $row['count']; ?>回使用</p>
                                    <p class="text-sm" style="color: var(--text-secondary);"><?= htmlspecialchars($row['color']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto mb-4" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                        </svg>
                        <p style="color: var(--text-secondary);">まだ投稿データがありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 通常投稿一覧 -->
            <div class="card-shadow rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                    </svg>
                    あなたの投稿
                </h2>
                
                <?php if (!empty($posts)): ?>
                    <div class="space-y-4">
                        <?php foreach ($posts as $post): ?>
                            <div class="post-card p-4 rounded-xl border-l-4" style="background-color: <?= htmlspecialchars($post['color']); ?>; border-left-color: var(--accent-primary);">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center">
                                        <?php if ($post['is_private']): ?>
                                            <svg class="w-4 h-4 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-sm font-medium" style="color: var(--text-secondary);">プライベート</span>
                                        <?php else: ?>
                                            <svg class="w-4 h-4 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-sm font-medium" style="color: var(--text-secondary);">シェア済み</span>
                                        <?php endif; ?>
                                        <span class="ml-2 text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700"><?= $post['comment_count']; ?>件のコメント</span>
                                    </div>
                                    <p class="text-xs" style="color: var(--text-secondary);"><?= date('Y年m月d日 H:i', strtotime($post['created_at'])); ?></p>
                                </div>
                                <p class="leading-relaxed mb-3" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($post['content'])); ?></p>
                                <?php if ($post['image_path']): ?>
                                    <img src="<?= htmlspecialchars($post['image_path']); ?>" class="rounded-lg max-h-40 object-cover mb-3">
                                <?php endif; ?>
                                
                                <!-- タグ表示 -->
                                <?php if (!empty($post['tags'])): ?>
                                    <div class="mb-3">
                                        <?php 
                                        $tagList = explode(',', $post['tags']);
                                        foreach ($tagList as $tag): 
                                            $trimmed = trim($tag);
                                            if ($trimmed !== ''): ?>
                                                <span class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full mr-1 mb-1">
                                                   #<?= htmlspecialchars($trimmed); ?>
                                                </span>
                                            <?php endif; 
                                        endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form action="" method="POST" onsubmit="return confirm('この投稿をゴミ箱に移動しますか？');" class="text-right">
                                    <input type="hidden" name="delete_id" value="<?= $post['id']; ?>">
                                    <button type="submit" class="bg-red-400 hover:bg-red-500 text-white px-3 py-1 rounded-full text-sm transition-colors">
                                        ゴミ箱へ移動
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: var(--accent-primary); opacity: 0.3;">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                            </svg>
                        </div>
                        <p style="color: var(--text-secondary);">まだ投稿がありません。</p>
                        <p class="text-sm" style="color: var(--text-secondary);">ホームから最初の投稿をしてみませんか？</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // レポートデータ
        const reportData = <?= json_encode($report_data) ?>;

        // reportChart変数をグローバルスコープで定義し、Chartインスタンスを保持するように変更
        let reportChartInstance; 

        function initChart() {
            const ctx = document.getElementById('reportChart').getContext('2d');
            if (!ctx) {
                console.error("Canvas element with ID 'reportChart' not found.");
                return;
            }

            // 既存のChartインスタンスがあれば破棄し、重複描画を防ぐ
            if (reportChartInstance) { // window.reportChartInstance から reportChartInstance に変更
                reportChartInstance.destroy();
            }

            reportChartInstance = new Chart(ctx, { // window.reportChartInstance から reportChartInstance に変更
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: '投稿数',
                        data: [],
                        backgroundColor: [],
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            updateChart('weekly');
        }

        function updateChart(period) {
            const data = reportData[period] || [];
            const labels = data.map(item => item.color);
            const values = data.map(item => item.count);
            const colors = data.map(item => item.color);
            
            // reportChart.data.labels を reportChartInstance.data.labels に変更
            reportChartInstance.data.labels = labels.length > 0 ? labels : ['データなし'];
            reportChartInstance.data.datasets[0].data = values.length > 0 ? values : [0];
            reportChartInstance.data.datasets[0].backgroundColor = colors.length > 0 ? colors : ['#e5e7eb'];
            
            reportChartInstance.update(); // reportChart.update() を reportChartInstance.update() に変更
            
            // 説明文を更新
            const descriptions = {
                weekly: '過去7日間の感情の色分布です',
                monthly: '過去30日間の感情の色分布です',
                yearly: '過去1年間の感情の色分布です'
            };
            document.getElementById('reportDescription').textContent = descriptions[period];
        }

        // タブ切り替え
        document.querySelectorAll('.report-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                updateChart(tab.dataset.period);
            });
        });

        // チャート初期化
        document.addEventListener('DOMContentLoaded', initChart);

    document.addEventListener('DOMContentLoaded', () => {
        initChart(); // 既存の棒グラフ
        initMoodTrendChart(); // 新しい折れ線グラフ
    });

    // 感情の推移グラフ
    const moodTrendLabels = <?= json_encode($mood_trend_labels) ?>;
    const moodTrendValues = <?= json_encode($mood_trend_values) ?>;

    function initMoodTrendChart() {
        const canvas = document.getElementById('moodTrendChart');
        if (!canvas) {
            console.error("Canvas element with ID 'moodTrendChart' not found.");
            // グラフが表示されない場合の代替メッセージ
            const chartContainer = document.querySelector('.card-shadow .h-64');
            if (chartContainer) {
                chartContainer.innerHTML = '<p class="text-center text-sm" style="color: var(--text-secondary);">感情の推移グラフを表示できませんでした。投稿データがないか、エラーが発生しています。</p>';
            }
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error("Failed to get 2D context for canvas 'moodTrendChart'.");
            const chartContainer = document.querySelector('.card-shadow .h-64');
            if (chartContainer) {
                chartContainer.innerHTML = '<p class="text-center text-sm" style="color: var(--text-secondary);">感情の推移グラフの描画に失敗しました。</p>';
            }
            return;
        }

        // 既存のChartインスタンスがあれば破棄し、重複描画を防ぐ
        if (window.moodTrendChartInstance) {
            window.moodTrendChartInstance.destroy();
        }

        window.moodTrendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: moodTrendLabels,
                datasets: [{
                    label: '平均感情スコア',
                    data: moodTrendValues,
                    borderColor: 'var(--accent-primary)',
                    backgroundColor: 'rgba(var(--accent-primary-rgb), 0.2)',
                    fill: true,
                    tension: 0.3, // 滑らかな線にする
                    pointRadius: 3,
                    pointBackgroundColor: 'var(--accent-primary)',
                    pointBorderColor: 'white',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'var(--accent-primary)',
                    pointHoverBorderColor: 'white',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // 凡例は非表示
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '平均スコア: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false // X軸のグリッド線は非表示
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 5, // 最大感情スコア
                        min: 0, // 最小感情スコア
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                if (value === 1) return '非常に沈鬱';
                                if (value === 2) return '沈んだ';
                                if (value === 3) return 'ニュートラル';
                                if (value === 4) return '明るい';
                                if (value === 5) return '非常に幸福';
                                return ''; // その他の値は表示しない
                            }
                        },
                        grid: {
                            color: 'var(--border-color)' // Y軸のグリッド線色
                        }
                    }
                }
            }
        });
    }

        // テーマ管理
        const themeButtons = document.querySelectorAll('.theme-button');
        const body = document.body;

        // 保存されたテーマを読み込み
        const savedTheme = localStorage.getItem('theme') || 'default';
        setTheme(savedTheme);

        // テーマボタンのイベントリスナー
        themeButtons.forEach(button => {
            button.addEventListener('click', () => {
                const theme = button.getAttribute('data-theme');
                setTheme(theme);
                localStorage.setItem('theme', theme);
            });
        });

        function setTheme(theme) {
            if (theme === 'default') {
                body.removeAttribute('data-theme');
            } else {
                body.setAttribute('data-theme', theme);
            }
            
            // アクティブボタンの更新
            themeButtons.forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-theme="${theme}"]`).classList.add('active');
        }

        // 季節に応じた自動テーマ設定（初回のみ）
        if (!localStorage.getItem('theme')) {
            const month = new Date().getMonth() + 1;
            let autoTheme = 'default';
            
            if (month >= 3 && month <= 5) autoTheme = 'spring';
            else if (month >= 6 && month <= 8) autoTheme = 'summer';
            else if (month >= 9 && month <= 11) autoTheme = 'autumn';
            else if (month === 12 || month <= 2) autoTheme = 'winter';
            
            setTheme(autoTheme);
            localStorage.setItem('theme', autoTheme);
        }

        // カレンダー生成
        const currentYear = <?= $current_year ?>;
        const currentMonth = <?= $current_month ?>;
        const postsData = <?= json_encode($posts_by_date) ?>;
        
        function generateCalendar() {
            const calendar = document.getElementById('calendar');
            const firstDay = new Date(currentYear, currentMonth - 1, 1);
            const lastDay = new Date(currentYear, currentMonth, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay();
            
            // 前月の日付を追加
            const prevMonth = new Date(currentYear, currentMonth - 2, 0);
            const daysInPrevMonth = prevMonth.getDate();
            
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day other-month';
                dayElement.textContent = day;
                calendar.appendChild(dayElement);
            }
            
            // 今月の日付を追加
            const today = new Date();
            const isCurrentMonth = today.getFullYear() === currentYear && today.getMonth() + 1 === currentMonth;
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                dayElement.className = 'calendar-day';
                dayElement.textContent = day;
                
                // 今日の日付をハイライト
                if (isCurrentMonth && today.getDate() === day) {
                    dayElement.classList.add('today');
                }
                
                // 投稿がある日は色を付ける
                if (postsData[dateStr]) {
                    dayElement.classList.add('has-post');
                    // 最初の投稿の色を使用
                    dayElement.style.backgroundColor = postsData[dateStr][0].color;
                    dayElement.onclick = () => showPostsForDate(dateStr, postsData[dateStr]);
                }
                
                calendar.appendChild(dayElement);
            }
            
            // 次月の日付を追加（42マス埋めるため）
            const totalCells = calendar.children.length;
            const remainingCells = 42 - totalCells;
            
            for (let day = 1; day <= remainingCells; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day other-month';
                dayElement.textContent = day;
                calendar.appendChild(dayElement);
            }
        }
        
        function showPostsForDate(date, posts) {
            const modal = document.getElementById('postModal');
            const modalContent = document.getElementById('modalContent');
            
            const formattedDate = new Date(date).toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            let content = `<h3 class="text-lg font-bold mb-4">${formattedDate}の投稿</h3>`;
            
            posts.forEach(post => {
                content += `
                    <div class="mb-4 p-3 rounded-lg" style="background-color: ${post.color};">
                        <p class="leading-relaxed">${post.content.replace(/\n/g, '<br>')}</p>
                    </div>
                `;
            });
            
            modalContent.innerHTML = content;
            modal.style.display = 'block';
        }
        
        // モーダル閉じる
        document.querySelector('.close').onclick = function() {
            document.getElementById('postModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('postModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // カレンダー生成実行
        generateCalendar();
    </script>
</body>
</html>
