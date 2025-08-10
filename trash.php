<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB接続


try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

// 復元処理
if (isset($_GET['restore_id'])) {
    $restore_id = intval($_GET['restore_id']);
    $stmt = $pdo->prepare("UPDATE sns_posts SET is_deleted = 0 WHERE id = :id AND user_id = :uid");
    $stmt->bindValue(':id', $restore_id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    header("Location: trash.php");
    exit();
}

// 完全削除処理
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM sns_posts WHERE id = :id AND user_id = :uid");
    $stmt->bindValue(':id', $delete_id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    header("Location: trash.php");
    exit();
}

// ゴミ箱にある投稿を取得
$stmt = $pdo->prepare("SELECT * FROM sns_posts WHERE user_id = :uid AND is_deleted = 1 ORDER BY created_at DESC");
$stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$trash_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ゴミ箱 | ココベース</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* デフォルトテーマ */
            --bg-primary: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --accent-primary: linear-gradient(45deg, #6b7280, #9ca3af);
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
        
        .post-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            opacity: 0.7;
        }
        
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            opacity: 0.9;
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
            <a href="groups.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">グループ</a>
            <a href="diary.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">感謝日記</a>
            <a href="trash.php" class="px-3 py-2 rounded-lg font-medium transition-colors" style="color: var(--accent-primary);">ゴミ箱</a>
            <a href="logout.php" class="px-3 py-2 rounded-lg font-medium text-red-500 hover:bg-red-50 transition-colors">ログアウト</a>
        </div>
    </nav>
    <div class="mt-20"></div> <!-- ヘッダー分の余白を確保 -->

    <!-- テーマセレクター -->
    <div class="theme-selector">
        <div class="flex flex-col space-y-2">
            <button class="theme-button" data-theme="default" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);" title="デフォルト"></button>
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
                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">ゴミ箱</h1>
            <p class="text-sm" style="color: var(--text-secondary);">削除した投稿はここで管理できます。復元または完全削除を選択してください。</p>
        </div>

        <!-- ゴミ箱の投稿一覧 -->
        <div class="card-shadow rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                削除された投稿 (<?= count($trash_posts); ?>件)
            </h2>

            <?php if (!empty($trash_posts)): ?>
                <div class="space-y-4">
                    <?php foreach ($trash_posts as $post): ?>
                        <div class="post-card p-4 rounded-xl border-l-4" style="background: var(--bg-card); border-left-color: var(--border-color);">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm font-medium" style="color: var(--text-secondary);">削除済み</span>
                                </div>
                                <p class="text-xs" style="color: var(--text-secondary);">削除日: <?= date('Y年m月d日 H:i', strtotime($post['created_at'])); ?></p>
                            </div>
                            
                            <p class="leading-relaxed mb-3" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($post['content'])); ?></p>
                            
                            <?php if (!empty($post['image_path'])): ?>
                                <img src="<?= htmlspecialchars($post['image_path']); ?>" class="rounded-lg max-h-40 object-cover mb-3 opacity-75">
                            <?php endif; ?>

                            <!-- タグ表示 -->
                            <?php if (!empty($post['tags'])): ?>
                                <div class="mb-3">
                                    <?php 
                                    $tagList = explode(',', $post['tags']);
                                    foreach ($tagList as $tag): 
                                        $trimmed = trim($tag);
                                        if ($trimmed !== ''): ?>
                                            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full mr-1 mb-1">
                                               #<?= htmlspecialchars($trimmed); ?>
                                            </span>
                                        <?php endif; 
                                    endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex gap-2 justify-end">
                                <a href="trash.php?restore_id=<?= $post['id']; ?>" 
                                   class="bg-green-400 hover:bg-green-500 text-white px-3 py-1 rounded-full text-sm transition-colors"
                                   onclick="return confirm('この投稿を復元しますか？');">
                                    復元
                                </a>
                                <a href="trash.php?delete_id=<?= $post['id']; ?>" 
                                   class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-full text-sm transition-colors"
                                   onclick="return confirm('この投稿を完全に削除しますか？元に戻すことはできません。');">
                                    完全削除
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl mb-4" style="background: var(--accent-primary); opacity: 0.3;">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">ゴミ箱は空です</h3>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">削除した投稿はここに表示されます。</p>
                    <a href="home.php" class="text-white px-4 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-secondary);">
                        ホームに戻る
                    </a>
                </div>
            <?php endif; ?>
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