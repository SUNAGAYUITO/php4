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
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if ($group_id === 0) {
    header("Location: groups.php?message=" . urlencode("グループが指定されていません。") . "&type=error");
    exit();
}

// グループ情報を取得
$stmt = $pdo->prepare("SELECT sg.*, su.username as creator_username
                       FROM sns_groups sg
                       JOIN sns_users su ON sg.creator_id = su.id
                       WHERE sg.id = :group_id");
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->execute();
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header("Location: groups.php?message=" . urlencode("指定されたグループが見つかりません。") . "&type=error");
    exit();
}

// ユーザーがグループのメンバーであるかチェック
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sns_group_members WHERE group_id = :group_id AND user_id = :user_id");
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$is_member = $stmt->fetchColumn() > 0;

// 非公開グループの場合、メンバーでなければアクセス拒否
if ($group['is_private'] && !$is_member) {
    header("Location: groups.php?message=" . urlencode("このグループは非公開です。メンバーのみアクセスできます。") . "&type=error");
    exit();
}

// グループメンバーを取得
$stmt = $pdo->prepare("SELECT sgm.*, su.username
                       FROM sns_group_members sgm
                       JOIN sns_users su ON sgm.user_id = su.id
                       WHERE sgm.group_id = :group_id
                       ORDER BY sgm.joined_at ASC");
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// グループ内の投稿を取得（コメント数も含む）
$sql = "SELECT p.*, u.username, 
        (SELECT COUNT(*) FROM sns_comments c WHERE c.post_id = p.id AND c.is_deleted = 0) as comment_count
        FROM sns_posts p 
        JOIN sns_users u ON p.user_id = u.id 
        WHERE p.group_id = :group_id AND p.is_deleted = 0
        ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 各投稿のコメントを取得
$posts_with_comments = [];
foreach ($posts as $post) {
    $stmt = $pdo->prepare("SELECT c.*, u.username 
                           FROM sns_comments c 
                           JOIN sns_users u ON c.user_id = u.id 
                           WHERE c.post_id = :post_id AND c.is_deleted = 0 
                           ORDER BY c.created_at ASC");
    $stmt->bindValue(':post_id', $post['id'], PDO::PARAM_INT);
    $stmt->execute();
    $post['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $posts_with_comments[] = $post;
}
$posts = $posts_with_comments;

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
    <title><?= htmlspecialchars($group['name']); ?> | ココベース</title>
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

        /* コメント関連のスタイル */
        .comment-section {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            margin-top: 12px;
            padding: 12px;
        }

        .comment-item {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 8px;
            border-left: 3px solid var(--accent-secondary);
        }

        .comment-form {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }

        .comment-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .comment-toggle:hover {
            transform: scale(1.05);
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
                <!-- グループヘッダー -->
                <div class="card-shadow rounded-2xl p-6 mb-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background: var(--accent-secondary); opacity: 0.8;">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.31L18.6 7 12 9.69 5.4 7 12 4.31zM4 9.5l8 4 8-4V17l-8 4-8-4V9.5z"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold logo-text mb-2"><?= htmlspecialchars($group['name']); ?></h1>
                    <p class="text-sm" style="color: var(--text-secondary);"><?= nl2br(htmlspecialchars($group['description'])); ?></p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">作成者: <?= htmlspecialchars($group['creator_username']); ?> | 作成日: <?= date('Y年m月d日', strtotime($group['created_at'])); ?></p>
                    <?php if ($group['is_private']): ?>
                        <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full mt-2 inline-block">非公開グループ</span>
                    <?php else: ?>
                        <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full mt-2 inline-block">公開グループ</span>
                    <?php endif; ?>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 rounded-lg mb-6 
                        <?= $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-400 text-green-700' : 'bg-red-50 border-l-4 border-red-400 text-red-700' ?>">
                        <p class="text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <!-- グループメンバー一覧 -->
                <div class="card-shadow rounded-2xl p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        メンバー (<?= count($members); ?>人)
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($members as $member): ?>
                            <span class="bg-gray-100 text-gray-800 text-sm px-3 py-1 rounded-full flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                                <?= htmlspecialchars($member['username']); ?>
                                <?php if ($member['role'] === 'admin'): ?>
                                    <span class="ml-1 text-xs bg-blue-200 text-blue-800 px-1.5 py-0.5 rounded-full">管理者</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-end mt-4">
                        <?php if ($is_member): ?>
                            <?php if ($group['creator_id'] !== $user_id): // 作成者は脱退できない ?>
                                <a href="groups.php?leave_group_id=<?= $group['id']; ?>" 
                                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-full text-sm transition-colors"
                                   onclick="return confirm('このグループから脱退しますか？');">
                                    グループを脱退する
                                </a>
                            <?php else: ?>
                                <span class="bg-gray-300 text-gray-700 px-4 py-2 rounded-full text-sm">あなたが作成したグループです</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($group['is_private'] == 0): // 公開グループのみ参加ボタンを表示 ?>
                                <a href="groups.php?join_group_id=<?= $group['id']; ?>" 
                                   class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full text-sm transition-colors"
                                   onclick="return confirm('このグループに参加しますか？');">
                                    グループに参加する
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- グループ内投稿フォーム -->
                <?php if ($is_member): // メンバーのみ投稿可能 ?>
                    <div class="card-shadow rounded-2xl p-6 mb-6">
                        <h2 class="text-lg font-semibold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                            </svg>
                            グループに投稿する
                        </h2>
                        <form action="group_post_insert.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="group_id" value="<?= $group['id']; ?>">
                            <textarea name="content" placeholder="このグループでシェアしたいことを投稿しましょう..." 
                                      class="w-full p-4 border-2 rounded-xl focus:outline-none transition-colors resize-none" 
                                      style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                                      rows="4" required></textarea>
                            
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm font-medium" style="color: var(--text-primary);">気分の色:</label>
                                    <input type="color" name="color" value="#fef7cd" class="w-12 h-8 border-2 rounded cursor-pointer" style="border-color: var(--border-color);">
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm font-medium" style="color: var(--text-primary);">画像:</label>
                                    <input type="file" name="image" accept="image/*" class="text-sm" style="color: var(--text-secondary);">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">タグ（カンマ区切り）:</label>
                                <input type="text" name="tags" placeholder="例: 趣味, イベント, 雑談" 
                                       class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors"
                                       style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                            </div>
                            
                            <div class="flex items-center justify-end">
                                <button type="submit" class="text-white px-6 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-primary);">
                                    投稿する
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card-shadow rounded-2xl p-6 mb-6 text-center">
                        <p class="text-sm" style="color: var(--text-secondary);">このグループに投稿するには、まずグループに参加してください。</p>
                    </div>
                <?php endif; ?>

                <!-- グループ内投稿一覧 -->
                <div class="card-shadow rounded-2xl p-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        グループの投稿
                    </h2>
                    
                    <?php if (!empty($posts)): ?>
                        <div class="space-y-6">
                            <?php foreach ($posts as $post): ?>
                                <div class="post-card p-4 rounded-xl border-l-4 border-pink-300" style="background-color: <?= htmlspecialchars($post['color']); ?>;">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--accent-secondary);">
                                            <span class="text-white text-sm font-bold"><?= mb_substr(htmlspecialchars($post['username']), 0, 1); ?></span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-medium" style="color: var(--text-primary);"><?= htmlspecialchars($post['username']); ?></p>
                                            <p class="text-xs" style="color: var(--text-secondary);"><?= date('Y年m月d日 H:i', strtotime($post['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <p class="leading-relaxed mb-3" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <?php if ($post['image_path']): ?>
                                        <img src="<?= htmlspecialchars($post['image_path']); ?>" class="rounded-lg max-h-60 object-cover mb-3">
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
                                    <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                                        <div class="flex gap-2 justify-end mt-3">
                                            <a href="group_post_edit.php?post_id=<?= $post['id']; ?>&group_id=<?= $group['id']; ?>" 
                                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-full text-sm transition-colors">
                                                編集
                                            </a>
                                            <a href="group_post_delete.php?post_id=<?= $post['id']; ?>&group_id=<?= $group['id']; ?>" 
                                               class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-full text-sm transition-colors"
                                               onclick="return confirm('この投稿をゴミ箱に移動しますか？');">
                                                削除
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <!-- コメント表示・投稿エリア -->
                                    <div class="comment-section">
                                        <!-- コメント数表示とトグルボタン -->
                                        <div class="flex items-center justify-between mb-3">
                                            <button class="comment-toggle flex items-center space-x-2 text-sm font-medium" 
                                                    style="color: var(--text-primary);" 
                                                    onclick="toggleComments(<?= $post['id']; ?>)">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span><?= $post['comment_count']; ?>件のコメント</span>
                                                <svg class="w-4 h-4 transform transition-transform" id="arrow-<?= $post['id']; ?>" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- コメント一覧（初期は非表示） -->
                                        <div id="comments-<?= $post['id']; ?>" class="hidden">
                                            <?php if (!empty($post['comments'])): ?>
                                                <div class="space-y-2 mb-3">
                                                    <?php foreach ($post['comments'] as $comment): ?>
                                                        <div class="comment-item">
                                                            <div class="flex items-center justify-between mb-1">
                                                                <div class="flex items-center space-x-2">
                                                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background: var(--accent-primary);">
                                                                        <?= mb_substr(htmlspecialchars($comment['username']), 0, 1); ?>
                                                                    </div>
                                                                    <span class="text-sm font-medium" style="color: var(--text-primary);"><?= htmlspecialchars($comment['username']); ?></span>
                                                                </div>
                                                                <div class="flex items-center space-x-2">
                                                                    <span class="text-xs" style="color: var(--text-secondary);"><?= date('m/d H:i', strtotime($comment['created_at'])); ?></span>
                                                                    <?php if ($comment['user_id'] == $_SESSION['user_id']): ?>
                                                                        <a href="comment_delete.php?comment_id=<?= $comment['id']; ?>&redirect_url=<?= urlencode($_SERVER['REQUEST_URI']); ?>" 
                                                                           class="text-red-500 hover:text-red-700 text-xs"
                                                                           onclick="return confirm('このコメントを削除しますか？');">削除</a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <p class="text-sm leading-relaxed" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- コメント投稿フォーム -->
                                            <div class="comment-form">
                                                <form action="comment_insert.php" method="POST" class="flex gap-2">
                                                    <input type="hidden" name="post_id" value="<?= $post['id']; ?>">
                                                    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                                    <textarea name="content" placeholder="温かいコメントを残しませんか..." 
                                                              class="flex-1 p-2 border rounded-lg resize-none text-sm" 
                                                              style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);"
                                                              rows="2" required></textarea>
                                                    <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-medium transition-all transform hover:scale-105" style="background: var(--accent-secondary);">
                                                        送信
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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
                            <p style="color: var(--text-secondary);">
                                まだこのグループに投稿がありません。
                            </p>
                            <?php if ($is_member): ?>
                                <p class="text-sm" style="color: var(--text-secondary);">最初の投稿をしてみませんか？</p>
                            <?php endif; ?>
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

        // コメント表示・非表示の切り替え
        function toggleComments(postId) {
            const commentsDiv = document.getElementById('comments-' + postId);
            const arrow = document.getElementById('arrow-' + postId);
            
            if (commentsDiv.classList.contains('hidden')) {
                commentsDiv.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                commentsDiv.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>
