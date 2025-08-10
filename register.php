<?php
session_start();

// DB接続情報


// エラーメッセージ格納
$error_message = "";
$success_message = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("DB接続エラー: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $password_confirm = $_POST["password_confirm"];

    // 入力チェック
    if ($username === "" || $email === "" || $password === "" || $password_confirm === "") {
        $error_message = "すべての項目を入力してください。";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "メールアドレスの形式が正しくありません。";
    } elseif ($password !== $password_confirm) {
        $error_message = "パスワードが一致しません。";
    } else {
        // メール重複チェック
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_users WHERE email = :email");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error_message = "このメールアドレスはすでに登録されています。";
        } else {
            // パスワードをハッシュ化
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // ユーザー登録
            $stmt = $pdo->prepare("INSERT INTO sns_users (username, email, password_hash) VALUES (:username, :email, :password)");
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':password', $hash, PDO::PARAM_STR);
            $stmt->execute();

            $success_message = "ココベースへようこそ！ログインしてください。";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規登録 | ココベース</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* デフォルトテーマ */
            --bg-primary: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --bg-card: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --accent-primary: linear-gradient(45deg, #06b6d4, #f472b6);
            --accent-secondary: linear-gradient(45deg, #10b981, #06b6d4);
            --border-color: #e5e7eb;
            --shadow: 0 10px 25px rgba(0,0,0,0.1);
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
            --shadow: 0 10px 25px rgba(0,0,0,0.3);
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
            --shadow: 0 10px 25px rgba(244, 114, 182, 0.2);
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
            --shadow: 0 10px 25px rgba(6, 182, 212, 0.2);
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
            --shadow: 0 10px 25px rgba(245, 158, 11, 0.2);
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
            --shadow: 0 10px 25px rgba(14, 165, 233, 0.2);
        }

        body { 
            font-family: 'Noto Sans JP', sans-serif; 
            background: var(--bg-primary);
            color: var(--text-primary);
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

        .theme-selector {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .theme-button {
            width: 40px;
            height: 40px;
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

        /* ココベースロゴのアニメーション */
        .logo-icon {
            animation: heartbeat 2s ease-in-out infinite;
        }

        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .welcome-message {
            opacity: 0;
            animation: fadeInUp 1s ease-out 0.5s forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
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

    <div class="card-shadow p-8 rounded-3xl w-full max-w-md">
        <!-- ココベースロゴ部分 -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl mb-4 logo-icon relative" style="background: var(--accent-primary);">
                <!-- 家のベース -->
                <svg class="w-10 h-10 text-white absolute" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                <!-- ハート -->
                <svg class="w-6 h-6 text-white absolute top-1 right-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold logo-text mb-2">ココベース</h1>
            <p class="text-sm welcome-message" style="color: var(--text-secondary);">心の拠点 - みんなを迎え入れる場所</p>
        </div>

        <h2 class="text-2xl font-bold mb-6 text-center">新しい仲間になりませんか？</h2>

        <?php if ($error_message): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg mb-6">
                <p class="text-red-700 text-sm">
                    <?= htmlspecialchars($error_message) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg mb-6">
                <p class="text-green-700 text-sm">
                    <?= htmlspecialchars($success_message) ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="username" class="block font-medium mb-2" style="color: var(--text-primary);">ニックネーム</label>
                <input type="text" name="username" id="username" 
                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                       placeholder="ココベースでの呼び名を入力" required>
            </div>
            <div>
                <label for="email" class="block font-medium mb-2" style="color: var(--text-primary);">メールアドレス</label>
                <input type="email" name="email" id="email" 
                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                       placeholder="your@email.com" required>
            </div>
            <div>
                <label for="password" class="block font-medium mb-2" style="color: var(--text-primary);">パスワード</label>
                <input type="password" name="password" id="password" 
                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                       placeholder="安全なパスワードを入力" required>
            </div>
            <div>
                <label for="password_confirm" class="block font-medium mb-2" style="color: var(--text-primary);">パスワード（確認）</label>
                <input type="password" name="password_confirm" id="password_confirm" 
                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors" 
                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                       placeholder="もう一度入力してください" required>
            </div>
            <button type="submit" 
                    class="w-full text-white p-3 rounded-xl font-medium transition-all transform hover:scale-105"
                    style="background: var(--accent-primary);">
                ココベースの仲間になる
            </button>
        </form>
        
        <div class="text-center mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
            <p class="text-sm mb-2" style="color: var(--text-secondary);">すでにココベースのメンバーですか？</p>
            <a href="login.php" class="font-medium" style="color: var(--text-primary);">ログインはこちら</a>
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
