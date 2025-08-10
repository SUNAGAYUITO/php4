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
$message_type = "";
$post = null;
$group_id = 0;

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB接続エラー: " . $e->getMessage());
}

// 投稿IDとグループIDを取得
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if ($post_id === 0 || $group_id === 0) {
    $message = "編集する投稿またはグループが指定されていません。";
    $message_type = "error";
    // エラーの場合はグループ詳細ページへリダイレクト
    header("Location: group_detail.php?group_id=" . $group_id . "&message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// 投稿データを取得し、編集権限があるか確認
$stmt = $pdo->prepare("SELECT * FROM sns_posts WHERE id = :post_id AND user_id = :user_id AND group_id = :group_id AND is_deleted = 0");
$stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->execute();
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    $message = "投稿が見つからないか、編集する権限がありません。";
    $message_type = "error";
    header("Location: group_detail.php?group_id=" . $group_id . "&message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// 投稿更新処理
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $content = $_POST['content'] ?? '';
    $color = $_POST['color'] ?? '#fef7cd';
    $tags = $_POST['tags'] ?? '';

    // 画像アップロード処理
    $imagePath = $post['image_path']; // 既存の画像を保持
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $imagePath = $targetFile;
        } else {
            $message = "画像のアップロードに失敗しました。";
            $message_type = "error";
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        // 画像削除チェックボックスがオンの場合
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath); // 物理削除
        }
        $imagePath = null;
    }

    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("UPDATE sns_posts SET content = :content, color = :color, image_path = :image_path, tags = :tags, updated_at = NOW() WHERE id = :post_id AND user_id = :user_id AND group_id = :group_id");
            $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            $stmt->bindValue(':color', $color, PDO::PARAM_STR);
            $stmt->bindValue(':image_path', $imagePath, PDO::PARAM_STR);
            $stmt->bindValue(':tags', $tags, PDO::PARAM_STR);
            $stmt->bindValue(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->execute();

            $message = "投稿を更新しました！";
            $message_type = "success";
            // 更新後、グループ詳細ページへリダイレクト
            header("Location: group_detail.php?group_id=" . $group_id . "&message=" . urlencode($message) . "&type=" . $message_type);
            exit();
        } catch (PDOException $e) {
            $message = "投稿の更新中にエラーが発生しました: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "投稿内容を入力してください。";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>投稿編集 | ココベース</title>
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
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">グループ投稿を編集</h1>
                    <p class="text-sm" style="color: var(--text-secondary);">あなたの投稿を修正しましょう</p>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 rounded-lg mb-6 
                        <?= $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-400 text-green-700' : 'bg-red-50 border-l-4 border-red-400 text-red-700' ?>">
                        <p class="text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <!-- 投稿編集フォーム -->
                <div class="card-shadow rounded-2xl p-6 mb-6">
                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="post_id" value="<?= htmlspecialchars($post['id']); ?>">
                        <input type="hidden" name="group_id" value="<?= htmlspecialchars($group_id); ?>">
                        
                        <div>
                            <label for="content" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">投稿内容 <span class="text-red-500">*</span></label>
                            <textarea name="content" id="content" placeholder="今日の気持ちを自由に表現してください..." 
                                      class="w-full p-4 border-2 rounded-xl focus:outline-none transition-colors resize-none" 
                                      style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                                      rows="6" required><?= htmlspecialchars($post['content']); ?></textarea>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center space-x-2">
                                <label for="color" class="text-sm font-medium" style="color: var(--text-primary);">気分の色:</label>
                                <input type="color" name="color" id="color" value="<?= htmlspecialchars($post['color']); ?>" class="w-12 h-8 border-2 rounded cursor-pointer" style="border-color: var(--border-color);">
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <label for="image" class="text-sm font-medium" style="color: var(--text-primary);">画像:</label>
                                <input type="file" name="image" id="image" accept="image/*" class="text-sm" style="color: var(--text-secondary);">
                            </div>
                        </div>

                        <?php if ($post['image_path']): ?>
                            <div class="mt-4">
                                <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">現在の画像:</p>
                                <img src="<?= htmlspecialchars($post['image_path']); ?>" class="rounded-lg max-h-40 object-cover mb-2">
                                <label class="flex items-center space-x-2 text-sm" style="color: var(--text-primary);">
                                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                                    <span>画像を削除する</span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="tags" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">タグ（カンマ区切り）:</label>
                            <input type="text" name="tags" id="tags" placeholder="例: 趣味, イベント, 雑談" 
                                   value="<?= htmlspecialchars($post['tags']); ?>"
                                   class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors"
                                   style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                        </div>
                        
                        <div class="flex items-center justify-end gap-4">
                            <a href="group_detail.php?group_id=<?= htmlspecialchars($group_id); ?>" class="px-6 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--border-color); color: var(--text-secondary);">
                                キャンセル
                            </a>
                            <button type="submit" class="text-white px-6 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-primary);">
                                更新する
                            </button>
                        </div>
                    </form>
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
