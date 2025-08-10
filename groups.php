<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB接続


$message = "";
$message_type = ""; // 'success' or 'error'

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$search_query = trim($_GET['search'] ?? '');

// グループ参加処理
if (isset($_GET['join_group_id'])) {
    $join_group_id = intval($_GET['join_group_id']);
    try {
        // 既にメンバーかチェック
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_group_members WHERE group_id = :group_id AND user_id = :user_id");
        $stmt->bindValue(':group_id', $join_group_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            // グループが公開か、非公開でも招待されているか（今回はシンプルに公開グループのみ参加可能とする）
            $stmt = $pdo->prepare("SELECT is_private FROM sns_groups WHERE id = :group_id");
            $stmt->bindValue(':group_id', $join_group_id, PDO::PARAM_INT);
            $stmt->execute();
            $group_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($group_info && $group_info['is_private'] == 0) { // 公開グループのみ即時参加
                $stmt = $pdo->prepare("INSERT INTO sns_group_members (group_id, user_id) VALUES (:group_id, :user_id)");
                $stmt->bindValue(':group_id', $join_group_id, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $message = "グループに参加しました！";
                $message_type = "success";
            } else {
                $message = "このグループは非公開のため、直接参加できません。";
                $message_type = "error";
            }
        } else {
            $message = "すでにこのグループのメンバーです。";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "グループ参加中にエラーが発生しました: " . $e->getMessage();
        $message_type = "error";
    }
    header("Location: groups.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// グループ脱退処理
if (isset($_GET['leave_group_id'])) {
    $leave_group_id = intval($_GET['leave_group_id']);
    try {
        // グループ作成者ではないかチェック（作成者は脱退できないようにする）
        $stmt = $pdo->prepare("SELECT creator_id FROM sns_groups WHERE id = :group_id");
        $stmt->bindValue(':group_id', $leave_group_id, PDO::PARAM_INT);
        $stmt->execute();
        $group_creator = $stmt->fetchColumn();

        if ($group_creator == $user_id) {
            $message = "あなたが作成したグループからは脱退できません。";
            $message_type = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM sns_group_members WHERE group_id = :group_id AND user_id = :user_id");
            $stmt->bindValue(':group_id', $leave_group_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $message = "グループから脱退しました。";
                $message_type = "success";
            } else {
                $message = "グループからの脱退に失敗しました。";
                $message_type = "error";
            }
        }
    } catch (PDOException $e) {
        $message = "グループ脱退中にエラーが発生しました: " . $e->getMessage();
        $message_type = "error";
    }
    header("Location: groups.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// URLパラメータからのメッセージ取得
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

// グループ一覧取得
// 公開グループ、または自分がメンバーの非公開グループ
$sql = "SELECT sg.*, su.username as creator_username, 
               CASE WHEN sgm.user_id IS NOT NULL THEN 1 ELSE 0 END as is_member
        FROM sns_groups sg
        JOIN sns_users su ON sg.creator_id = su.id
        LEFT JOIN sns_group_members sgm ON sg.id = sgm.group_id AND sgm.user_id = :user_id
        WHERE (sg.is_private = 0 OR sgm.user_id = :user_id)";

if (!empty($search_query)) {
    $sql .= " AND sg.name LIKE :search_query";
}
$sql .= " ORDER BY sg.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
if (!empty($search_query)) {
    $stmt->bindValue(':search_query', "%$search_query%", PDO::PARAM_STR);
}
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ユーザーが作成したグループのIDリスト
$my_created_groups = [];
$stmt = $pdo->prepare("SELECT id FROM sns_groups WHERE creator_id = :user_id");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $my_created_groups[] = $row['id'];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>グループ一覧 | ココベース</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* デフォルトテーマ */
            --bg-primary: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --accent-primary: linear-gradient(45deg, #ff6b6b, #ffa726);
            --accent-secondary: linear-gradient(45deg, #f472b6, #fb923c);
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
        
        .group-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .group-card:hover {
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
    </style>
</head>
<body>
    <div class="min-h-screen">
        <div class="max-w-4xl mx-auto">
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
                    <a href="profile.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">マイページ</a>
                    <a href="groups.php" class="px-3 py-2 rounded-lg font-medium transition-colors" style="color: var(--accent-primary);">グループ</a>
                    <a href="trash.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">ゴミ箱</a>
                    <a href="logout.php" class="px-3 py-2 rounded-lg font-medium text-red-500 hover:bg-red-50 transition-colors">ログアウト</a>
                </div>
            </nav>
            <div class="mt-20"></div> <!-- ヘッダー分の余白を確保 -->

            <!-- テーマセレクター -->
            <div class="theme-selector">
                <div class="flex flex-col space-y-2">
                    <button class="theme-button" data-theme="default" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);" title="デフォルト"></button>
                    <button class="theme-button" data-theme="dark" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%);" title="ダークモード"></button>
                    <button class="theme-button" data-theme="spring" style="background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);" title="春テーマ"></button>
                    <button class="theme-button" data-theme="summer" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);" title="夏テーマ"></button>
                    <button class="theme-button" data-theme="autumn" style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);" title="秋テーマ"></button>
                    <button class="theme-button" data-theme="winter" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);" title="冬テーマ"></button>
                </div>
            </div>

            <div class="p-4 md:p-6">
                <!-- ヘッダー -->
                <div class="card-shadow rounded-2xl p-6 mb-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: var(--accent-primary); opacity: 0.8;">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.31L18.6 7 12 9.69 5.4 7 12 4.31zM4 9.5l8 4 8-4V17l-8 4-8-4V9.5z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">グループ</h1>
                    <p class="text-sm" style="color: var(--text-secondary);">同じ思いを持つ仲間と繋がろう</p>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 rounded-lg mb-6 
                        <?= $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-400 text-green-700' : 'bg-red-50 border-l-4 border-red-400 text-red-700' ?>">
                        <p class="text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <!-- グループ検索と作成ボタン -->
                <div class="card-shadow rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                        </svg>
                        グループを探す
                    </h2>
                    <form action="" method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
                        <input type="text" name="search" placeholder="グループ名で検索" 
                               value="<?= htmlspecialchars($search_query) ?>" 
                               class="flex-1 p-3 border-2 rounded-xl focus:outline-none transition-colors"
                               style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                        <button type="submit" class="text-white px-6 py-3 rounded-xl font-medium transition-all transform hover:scale-105" style="background: var(--accent-secondary);">
                            検索
                        </button>
                        <?php if (!empty($search_query)): ?>
                            <a href="groups.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-3 rounded-xl font-medium transition-colors">
                                クリア
                            </a>
                        <?php endif; ?>
                    </form>
                    <div class="text-center">
                        <a href="create_group.php" class="text-white px-6 py-3 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-primary);">
                            <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                            </svg>
                            新しいグループを作成する
                        </a>
                    </div>
                </div>

                <!-- グループ一覧 -->
                <div class="card-shadow rounded-2xl p-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 002-2V4H4zm10 10v2a2 2 0 002 2h4a2 2 0 002-2v-2H14zm-8 0v2a2 2 0 002 2h4a2 2 0 002-2v-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        グループ一覧 (<?= count($groups); ?>件)
                    </h2>
                    
                    <?php if (!empty($groups)): ?>
                        <div class="space-y-4">
                            <?php foreach ($groups as $group): ?>
                                <div class="group-card p-4 rounded-xl border-l-4" style="background: var(--bg-card); border-left-color: var(--accent-secondary);">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="text-lg font-bold" style="color: var(--text-primary);"><?= htmlspecialchars($group['name']); ?></h3>
                                            <p class="text-sm" style="color: var(--text-secondary);">作成者: <?= htmlspecialchars($group['creator_username']); ?></p>
                                            <p class="text-xs" style="color: var(--text-secondary);">作成日: <?= date('Y年m月d日', strtotime($group['created_at'])); ?></p>
                                        </div>
                                        <?php if ($group['is_private']): ?>
                                            <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">非公開</span>
                                        <?php else: ?>
                                            <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">公開</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="leading-relaxed mb-3 text-sm" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($group['description'])); ?></p>
                                    
                                    <div class="flex gap-2 justify-end">
                                        <a href="group_detail.php?group_id=<?= $group['id']; ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-full text-sm transition-colors">
                                            詳細を見る
                                        </a>
                                        <?php if ($group['is_member']): ?>
                                            <?php if (in_array($group['id'], $my_created_groups)): ?>
                                                <span class="bg-gray-300 text-gray-700 px-3 py-1 rounded-full text-sm">作成者</span>
                                            <?php else: ?>
                                                <a href="groups.php?leave_group_id=<?= $group['id']; ?>" 
                                                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-full text-sm transition-colors"
                                                   onclick="return confirm('このグループから脱退しますか？');">
                                                    脱退する
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($group['is_private'] == 0): // 公開グループのみ参加ボタンを表示 ?>
                                                <a href="groups.php?join_group_id=<?= $group['id']; ?>" 
                                                   class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-full text-sm transition-colors"
                                                   onclick="return confirm('このグループに参加しますか？');">
                                                    参加する
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: var(--accent-primary); opacity: 0.3;">
                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.31L18.6 7 12 9.69 5.4 7 12 4.31zM4 9.5l8 4 8-4V17l-8 4-8-4V9.5z"/>
                                </svg>
                            </div>
                            <p style="color: var(--text-secondary);">
                                <?php if (!empty($search_query)): ?>
                                    「<?= htmlspecialchars($search_query) ?>」に一致するグループは見つかりませんでした。
                                <?php else: ?>
                                    まだグループがありません。
                                <?php endif; ?>
                            </p>
                            <p class="text-sm" style="color: var(--text-secondary);">新しいグループを作成してみませんか？</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
