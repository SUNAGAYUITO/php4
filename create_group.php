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

// グループ作成処理
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $group_name = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $creator_id = $_SESSION['user_id'];

    if (empty($group_name)) {
        $message = "グループ名を入力してください。";
        $message_type = "error";
    } else {
        try {
            // グループ名の重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_groups WHERE name = :name");
            $stmt->bindValue(':name', $group_name, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $message = "このグループ名はすでに使用されています。別の名前をお試しください。";
                $message_type = "error";
            } else {
                // グループを挿入
                $stmt = $pdo->prepare("INSERT INTO sns_groups (name, description, creator_id, is_private) VALUES (:name, :description, :creator_id, :is_private)");
                $stmt->bindValue(':name', $group_name, PDO::PARAM_STR);
                $stmt->bindValue(':description', $description, PDO::PARAM_STR);
                $stmt->bindValue(':creator_id', $creator_id, PDO::PARAM_INT);
                $stmt->bindValue(':is_private', $is_private, PDO::PARAM_INT);
                $stmt->execute();

                $group_id = $pdo->lastInsertId();

                // 作成者をグループメンバーとして追加
                $stmt = $pdo->prepare("INSERT INTO sns_group_members (group_id, user_id, role) VALUES (:group_id, :user_id, 'admin')");
                $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $creator_id, PDO::PARAM_INT);
                $stmt->execute();

                $message = "グループ「" . htmlspecialchars($group_name) . "」が作成されました！";
                $message_type = "success";
                // 成功したらリダイレクト
                header("Location: group_detail.php?group_id=" . $group_id);
                exit();
            }
        } catch (PDOException $e) {
            $message = "グループ作成中にエラーが発生しました: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>グループ作成 | ココベース</title>
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
<body class="min-h-screen flex flex-col items-center justify-center p-4">
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

    <div class="card-shadow p-8 rounded-3xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl mb-4 logo-icon relative" style="background: var(--accent-primary);">
                <svg class="w-10 h-10 text-white absolute" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.31L18.6 7 12 9.69 5.4 7 12 4.31zM4 9.5l8 4 8-4V17l-8 4-8-4V9.5z"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold logo-text mb-2">新しいグループを作成</h1>
            <p class="text-sm" style="color: var(--text-secondary);">同じ思いを持つ仲間と繋がる場所を作りましょう</p>
        </div>

        <?php if ($message): ?>
            <div class="p-4 rounded-lg mb-6 
                <?= $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-400 text-green-700' : 'bg-red-50 border-l-4 border-red-400 text-red-700' ?>">
                <p class="text-sm"><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="group_name" class="block font-medium mb-2" style="color: var(--text-primary);">グループ名 <span class="text-red-500">*</span></label>
                <input type="text" name="group_name" id="group_name" 
                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                       placeholder="例: 猫好きの集い、読書クラブ" required>
            </div>
            <div>
                <label for="description" class="block font-medium mb-2" style="color: var(--text-primary);">グループの説明</label>
                <textarea name="description" id="description" 
                          class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors resize-none" 
                          style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                          rows="4" placeholder="このグループで何をしたいですか？どんな人が集まる場所ですか？"></textarea>
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" name="is_private" id="is_private" value="1" class="rounded border-gray-300">
                <label for="is_private" class="text-sm" style="color: var(--text-primary);">非公開グループにする（招待されたメンバーのみ参加可能）</label>
            </div>
            <button type="submit" 
                    class="w-full text-white p-3 rounded-xl font-medium transition-all transform hover:scale-105"
                    style="background: var(--accent-primary);">
                グループを作成する
            </button>
        </form>
        
        <div class="text-center mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
            <a href="home.php" class="font-medium" style="color: var(--text-primary);">ホームに戻る</a>
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
