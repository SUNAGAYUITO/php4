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

// 感謝日記の保存処理
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_gratitude') {
    $entry_date = trim($_POST['entry_date'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($entry_date) || empty($content)) {
        $message = "日付と感謝の内容を入力してください。";
        $message_type = "error";
    } else {
        try {
            // 既存のエントリーがあるかチェック（同じ日付で重複投稿を防ぐ）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_gratitude_entries WHERE user_id = :user_id AND entry_date = :entry_date");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':entry_date', $entry_date, PDO::PARAM_STR);
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // 既存のエントリーを更新
                $stmt = $pdo->prepare("UPDATE sns_gratitude_entries SET content = :content, updated_at = NOW() WHERE user_id = :user_id AND entry_date = :entry_date");
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':entry_date', $entry_date, PDO::PARAM_STR);
                $stmt->execute();
                $message = "感謝日記を更新しました！";
                $message_type = "success";
            } else {
                // 新しいエントリーを挿入
                $stmt = $pdo->prepare("INSERT INTO sns_gratitude_entries (user_id, entry_date, content) VALUES (:user_id, :entry_date, :content)");
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':entry_date', $entry_date, PDO::PARAM_STR);
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->execute();
                $message = "感謝日記が保存されました！";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "感謝日記の保存中にエラーが発生しました: " . $e->getMessage();
            $message_type = "error";
        }
    }
    // リダイレクトしてPOSTリクエストをクリア
    header("Location: diary.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// 感謝日記の削除処理
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM sns_gratitude_entries WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $delete_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $message = "感謝日記を削除しました。";
            $message_type = "success";
        } else {
            $message = "感謝日記の削除に失敗しました。";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "感謝日記の削除中にエラーが発生しました: " . $e->getMessage();
        $message_type = "error";
    }
    header("Location: diary.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// 感謝日記の一覧取得
$stmt = $pdo->prepare("SELECT * FROM sns_gratitude_entries WHERE user_id = :user_id ORDER BY entry_date DESC");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$gratitude_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// URLパラメータからのメッセージ取得
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>感謝日記 | ココベース</title>
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
        
        .diary-entry-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .diary-entry-card:hover {
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
                    <a href="groups.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">グループ</a>
                    <a href="trash.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">ゴミ箱</a>
                    <a href="diary.php" class="px-3 py-2 rounded-lg font-medium transition-colors" style="color: var(--accent-primary);">感謝日記</a>
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
                            <path d="M19 4H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zm-7 2h6v2h-6V6zm0 4h6v2h-6v-2zM5 6h2v2H5V6zm0 4h2v2H5v-2zm0 4h2v2H5v-2zm4 0h6v2H9v-2z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold logo-text mb-2">��謝日記</h1>
                    <p class="text-sm" style="color: var(--text-secondary);">今日あった感謝したいことを記録しましょう。</p>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 rounded-lg mb-6 
                        <?= $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-400 text-green-700' : 'bg-red-50 border-l-4 border-red-400 text-red-700' ?>">
                        <p class="text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <!-- 感謝日記入力フォーム -->
                <div class="card-shadow rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                        新しい感謝を記録する
                    </h2>
                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="save_gratitude">
                        <div>
                            <label for="entry_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">日付</label>
                            <input type="date" name="entry_date" id="entry_date" 
                                   value="<?= date('Y-m-d'); ?>" 
                                   class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                                   style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                                   required>
                        </div>
                        <div>
                            <label for="content" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">感謝の内容</label>
                            <textarea name="content" id="content" placeholder="今日感謝したことをここに書いてください..." 
                                      class="w-full p-4 border-2 rounded-xl focus:outline-none transition-colors resize-none" 
                                      style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                                      rows="5" required></textarea>
                        </div>
                        <button type="submit" class="w-full text-white p-3 rounded-xl font-medium transition-all transform hover:scale-105" style="background: var(--accent-primary);">
                            保存する
                        </button>
                    </form>
                </div>

                <!-- 感謝日記一覧 -->
                <div class="card-shadow rounded-2xl p-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        あなたの感謝の記録
                    </h2>
                    
                    <?php if (!empty($gratitude_entries)): ?>
                        <div class="space-y-4">
                            <?php foreach ($gratitude_entries as $entry): ?>
                                <div class="diary-entry-card p-4 rounded-xl border-l-4 border-green-400" style="background: var(--bg-card);">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            <?= date('Y年m月d日', strtotime($entry['entry_date'])); ?>
                                        </p>
                                        <div class="flex gap-2">
                                            <!-- 編集機能は今回は省略。必要であれば追加可能 -->
                                            <a href="diary.php?delete_id=<?= $entry['id']; ?>" 
                                               class="text-red-500 hover:text-red-700 text-sm"
                                               onclick="return confirm('この感謝日記を削除しますか？');">
                                                削除
                                            </a>
                                        </div>
                                    </div>
                                    <p class="leading-relaxed" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($entry['content'])); ?></p>
                                    <p class="text-xs text-right mt-2" style="color: var(--text-secondary);">
                                        記録日: <?= date('Y年m月d日 H:i', strtotime($entry['created_at'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: var(--accent-primary); opacity: 0.3;">
                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 4H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zm-7 2h6v2h-6V6zm0 4h6v2h-6v-2zM5 6h2v2H5V6zm0 4h2v2H5v-2zm0 4h2v2H5v-2zm4 0h6v2H9v-2z"/>
                                </svg>
                            </div>
                            <p style="color: var(--text-secondary);">まだ感謝の記録がありません。</p>
                            <p class="text-sm" style="color: var(--text-secondary);">今日の感謝を記録してみませんか？</p>
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