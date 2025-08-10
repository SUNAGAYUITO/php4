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


try {
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
  die("DB接続エラー: " . $e->getMessage());
}

// タグ検索処理
$search_tag = $_GET['tag'] ?? '';

// 投稿処理
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['content'])) {
  $content = $_POST['content'] ?? '';
  $color = $_POST['color'] ?? '#fef7cd';
  $tags = $_POST['tags'] ?? '';
  $is_private = isset($_POST['is_private']) ? 1 : 0;

  // 画像アップロード処理
  $imagePath = null;
  if (!empty($_FILES["image"]["name"])) {
      $targetDir = "uploads/";
      if (!file_exists($targetDir)) {
          mkdir($targetDir, 0777, true);
      }
      $fileName = time() . "_" . basename($_FILES["image"]["name"]);
      $targetFile = $targetDir . $fileName;

      if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
          $imagePath = $targetFile;
      }
  }

  try {
      $stmt = $pdo->prepare("INSERT INTO sns_posts (user_id, group_id, content, color, image_path, tags, is_private) 
                         VALUES (:user_id, :group_id, :content, :color, :image, :tags, :is_private)");
      $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
      $stmt->bindValue(':group_id', null, PDO::PARAM_NULL); // ここをNULLに設定
      $stmt->bindValue(':content', $content, PDO::PARAM_STR);
      $stmt->bindValue(':color', $color, PDO::PARAM_STR);
      $stmt->bindValue(':image', $imagePath, PDO::PARAM_STR);
      $stmt->bindValue(':tags', $tags, PDO::PARAM_STR);
      $stmt->bindValue(':is_private', $is_private, PDO::PARAM_INT);
      $stmt->execute();
  } catch (PDOException $e) {
      echo "投稿エラー: " . $e->getMessage();
  }
}

// 過去の自分との対話用データ取得
$today = date('m-d');
$one_year_ago = date('Y-m-d', strtotime('-1 year'));
$one_month_ago = date('Y-m-d', strtotime('-1 month'));

// 1年前の今日の投稿
$stmt = $pdo->prepare("SELECT * FROM sns_posts 
                     WHERE user_id = :user_id 
                     AND DATE_FORMAT(created_at, '%m-%d') = :today 
                     AND DATE(created_at) < :one_year_ago 
                     AND is_deleted = 0 
                     AND group_id IS NULL
                     ORDER BY created_at DESC LIMIT 1");
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':today', $today, PDO::PARAM_STR);
$stmt->bindValue(':one_year_ago', $one_year_ago, PDO::PARAM_STR);
$stmt->execute();
$past_year_post = $stmt->fetch(PDO::FETCH_ASSOC);

// 1ヶ月前の今日の投稿
$stmt = $pdo->prepare("SELECT * FROM sns_posts 
                     WHERE user_id = :user_id 
                     AND DATE_FORMAT(created_at, '%m-%d') = :today 
                     AND DATE(created_at) < :one_month_ago 
                     AND DATE(created_at) >= :one_year_ago
                     AND is_deleted = 0 
                     AND group_id IS NULL
                     ORDER BY created_at DESC LIMIT 1");
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':today', $today, PDO::PARAM_STR);
$stmt->bindValue(':one_month_ago', $one_month_ago, PDO::PARAM_STR);
$stmt->bindValue(':one_year_ago', $one_year_ago, PDO::PARAM_STR);
$stmt->execute();
$past_month_post = $stmt->fetch(PDO::FETCH_ASSOC);

// 投稿一覧取得（コメント数も含む）
$sql = "SELECT p.*, u.username, 
      (SELECT COUNT(*) FROM sns_comments c WHERE c.post_id = p.id AND c.is_deleted = 0) as comment_count
      FROM sns_posts p 
      JOIN sns_users u ON p.user_id = u.id 
      WHERE (p.is_private = 0 OR p.user_id = :uid) AND p.is_deleted = 0 AND p.group_id IS NULL"; // ここに group_id IS NULL を追加
if (!empty($search_tag)) {
  $sql .= " AND p.tags LIKE :tag";
}
$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
if (!empty($search_tag)) {
  $stmt->bindValue(':tag', "%$search_tag%", PDO::PARAM_STR);
}
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

// メッセージの取得と削除
$success_message = $_SESSION['comment_success'] ?? '';
$error_message = $_SESSION['comment_error'] ?? '';
unset($_SESSION['comment_success'], $_SESSION['comment_error']);

// 今日の名言データ
$daily_quotes = [
  "今日という日は、残りの人生の最初の日である。",
  "小さな一歩でも、歩き続ければ必ず目的地に着く。",
  "あなたの笑顔が、誰かの一日を明るくしている。",
  "完璧でなくても、今日のあなたで十分素晴らしい。",
  "困難は、あなたが成長するための贈り物。",
  "今日の小さな優しさが、明日の大きな幸せになる。",
  "失敗は成功への階段。一段ずつ登っていこう。",
  "あなたの存在そのものが、この世界への贈り物。",
  "今日できることに集中しよう。明日は明日の風が吹く。",
  "心の中の小さな光を大切に。それがあなたの希望。",
  "変化を恐れずに。新しい自分に出会えるチャンス。",
  "今日の感謝が、明日の幸せを呼び寄せる。",
  "一人じゃない。あなたを支えてくれる人がいる。",
  "今日の涙も、明日の笑顔のための準備。",
  "あなたのペースで大丈夫。比べる必要はない。",
  "小さな幸せに気づく心が、人生を豊かにする。",
  "今日という日は二度と来ない。大切に過ごそう。",
  "あなたの優しさが、世界をより良い場所にしている。",
  "困った時こそ、本当の強さが見えてくる。",
  "今日の努力は、未来のあなたへのプレゼント。",
  "心配事の9割は起こらない。今を大切に。",
  "あなたの夢は、きっと叶う。信じ続けよう。",
  "今日の小さな成長が、明日の大きな変化になる。",
  "辛い時こそ、自分を大切にしてあげよう。",
  "あなたの人生は、あなたが主人公の物語。",
  "今日の一歩が、明日への道を作っている。",
  "心の声に耳を傾けよう。答えはあなたの中にある。",
  "今日という日に感謝。新しい可能性に満ちている。",
  "あなたの笑顔は、世界で一番美しい花。",
  "今日の挑戦が、明日の自信になる。",
  "休むことも大切。自分を労わってあげよう。"
];

$today_quote = $daily_quotes[date('z') % count($daily_quotes)];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ホーム | ココベース</title>
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

      /* テーマ別の投稿カード色調整 */
      [data-theme="dark"] .post-card {
          border-left-color: #8b5cf6 !important;
      }

      [data-theme="spring"] .post-card {
          border-left-color: #f472b6 !important;
      }

      [data-theme="summer"] .post-card {
          border-left-color: #06b6d4 !important;
      }

      [data-theme="autumn"] .post-card {
          border-left-color: #f59e0b !important;
      }

      [data-theme="winter"] .post-card {
          border-left-color: #0ea5e9 !important;
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

      /* 過去の投稿カード */
      .past-post-card {
          background: rgba(255, 255, 255, 0.8);
          border: 2px dashed var(--accent-primary);
          transition: all 0.3s ease;
      }

      .past-post-card:hover {
          background: rgba(255, 255, 255, 0.95);
          transform: translateY(-1px);
      }

      .time-travel-icon {
          animation: float 3s ease-in-out infinite;
      }

      @keyframes float {
          0%, 100% { transform: translateY(0px); }
          50% { transform: translateY(-5px); }
          100% { transform: translateY(0px); }
      }

      /* レスポンシブデザインの改善 */
      .container-responsive {
          max-width: 1024px;
          margin: 0 auto;
          padding: 0 1rem;
      }

      @media (max-width: 768px) {
          .container-responsive {
              padding: 0 0.5rem;
          }
      }

      /* 名言セクションのアニメーション */
      .quote-animation {
          animation: fadeInUp 1s ease-out;
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
                  <a href="home.php" class="px-3 py-2 rounded-lg font-medium transition-colors" style="color: var(--accent-primary);">ホーム</a>
                  <a href="profile.php" class="px-3 py-2 rounded-lg font-medium transition-colors hover:bg-gray-100" style="color: var(--text-primary);">マイページ</a>
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
                  <button class="theme-button" data-theme="default" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);" title="デフォルト"></button>
                  <button class="theme-button" data-theme="dark" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%);" title="ダークモード"></button>
                  <button class="theme-button" data-theme="spring" style="background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);" title="春テーマ"></button>
                  <button class="theme-button" data-theme="summer" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);" title="夏テーマ"></button>
                  <button class="theme-button" data-theme="autumn" style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);" title="秋テーマ"></button>
                  <button class="theme-button" data-theme="winter" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);" title="冬テーマ"></button>
              </div>
          </div>

          <div class="p-4 md:p-6">
              <!-- ウェルカムメッセージ -->
              <div class="card-shadow rounded-2xl p-6 mb-6 text-center">
                  <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">おかえりなさい、<?= htmlspecialchars($_SESSION['username']); ?>さん</h1>
                  <p class="text-sm" style="color: var(--text-secondary);">ココベースはあなたの心の拠点です。今日の気持ちを自由に表現してください。</p>
              </div>

              <!-- 今日の名言 -->
              <div class="card-shadow rounded-2xl p-6 mb-6 text-center relative overflow-hidden">
                  <div class="absolute top-0 left-0 w-full h-1" style="background: var(--accent-primary);"></div>
                  <div class="flex items-center justify-center mb-3">
                      <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" style="background: var(--accent-secondary);">
                          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                          </svg>
                      </div>
                      <h2 class="text-lg font-semibold" style="color: var(--text-primary);">今日のひとこと</h2>
                  </div>
                  <blockquote class="text-lg font-medium italic mb-2" style="color: var(--text-primary);">
                      "<?= htmlspecialchars($today_quote) ?>"
                  </blockquote>
                  <p class="text-sm" style="color: var(--text-secondary);">今日も素敵な一日になりますように ✨</p>
              </div>

              <!-- 過去の自分との対話セクション -->
              <?php if ($past_year_post || $past_month_post): ?>
                  <div class="card-shadow rounded-2xl p-6 mb-6">
                      <h2 class="text-lg font-semibold mb-4 flex items-center">
                          <div class="time-travel-icon mr-2">
                              <svg class="w-5 h-5" style="color: var(--accent-primary);" fill="currentColor" viewBox="0 0 20 20">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                              </svg>
                          </div>
                          過去の自分からのメッセージ
                      </h2>
                      
                      <div class="space-y-4">
                          <?php if ($past_year_post): ?>
                              <div class="past-post-card p-4 rounded-xl">
                                  <div class="flex items-center mb-3">
                                      <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--accent-primary);">
                                          <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                          </svg>
                                      </div>
                                      <div>
                                          <p class="font-medium" style="color: var(--text-primary);">1年前の今日のあなた</p>
                                          <p class="text-xs" style="color: var(--text-secondary);"><?= date('Y年m月d日', strtotime($past_year_post['created_at'])); ?></p>
                                      </div>
                                  </div>
                                  <div class="p-3 rounded-lg" style="background-color: <?= htmlspecialchars($past_year_post['color']); ?>;">
                                      <p class="leading-relaxed" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($past_year_post['content'])); ?></p>
                                  </div>
                                  <p class="text-xs mt-2 italic" style="color: var(--text-secondary);">「1年前のあなたは、こんな気持ちでした」</p>
                              </div>
                          <?php endif; ?>

                          <?php if ($past_month_post): ?>
                              <div class="past-post-card p-4 rounded-xl">
                                  <div class="flex items-center mb-3">
                                      <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: var(--accent-secondary);">
                                          <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                          </svg>
                                      </div>
                                      <div>
                                          <p class="font-medium" style="color: var(--text-primary);">1ヶ月前の今日のあなた</p>
                                          <p class="text-xs" style="color: var(--text-secondary);"><?= date('Y年m月d日', strtotime($past_month_post['created_at'])); ?></p>
                                      </div>
                                  </div>
                                  <div class="p-3 rounded-lg" style="background-color: <?= htmlspecialchars($past_month_post['color']); ?>;">
                                      <p class="leading-relaxed" style="color: var(--text-primary);"><?= nl2br(htmlspecialchars($past_month_post['content'])); ?></p>
                                  </div>
                                  <p class="text-xs mt-2 italic" style="color: var(--text-secondary);">「1ヶ月前のあなたは、こんな気持ちでした」</p>
                              </div>
                          <?php endif; ?>
                      </div>
                      
                      <div class="mt-4 p-3 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg">
                          <p class="text-sm text-center" style="color: var(--text-primary);">
                              <span class="font-medium">💭 今日の投稿は、未来のあなたへのメッセージになります</span>
                          </p>
                      </div>
                  </div>
              <?php endif; ?>

              <!-- メッセージ表示 -->
              <?php if ($success_message): ?>
                  <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg mb-6">
                      <p class="text-green-700 text-sm"><?= htmlspecialchars($success_message) ?></p>
                  </div>
              <?php endif; ?>

              <?php if ($error_message): ?>
                  <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg mb-6">
                      <p class="text-red-700 text-sm"><?= htmlspecialchars($error_message) ?></p>
                  </div>
              <?php endif; ?>

              <!-- タグ検索フォーム -->
              <div class="card-shadow rounded-2xl p-6 mb-6">
                  <h2 class="text-lg font-semibold mb-4 flex items-center">
                      <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                  タグで検索
              </h2>
              <form action="" method="GET" class="flex gap-2">
                  <input type="text" name="tag" placeholder="タグで検索 (例: 音楽, 感情)" 
                         value="<?= htmlspecialchars($search_tag) ?>" 
                         class="flex-1 p-3 border-2 rounded-xl focus:outline-none transition-colors"
                         style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                  <button type="submit" class="text-white px-6 py-3 rounded-xl font-medium transition-all transform hover:scale-105" style="background: var(--accent-secondary);">
                      検索
                  </button>
                  <?php if (!empty($search_tag)): ?>
                      <a href="home.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-3 rounded-xl font-medium transition-colors">
                          クリア
                      </a>
                  <?php endif; ?>
              </form>
              <?php if (!empty($search_tag)): ?>
                  <p class="mt-2 text-sm" style="color: var(--text-secondary);">「<?= htmlspecialchars($search_tag) ?>」の検索結果</p>
              <?php endif; ?>
          </div>

          <!-- 投稿フォーム -->
          <div class="card-shadow rounded-2xl p-6 mb-6">
              <h2 class="text-lg font-semibold mb-4 flex items-center">
                  <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                  </svg>
                  今の気持ちをシェアしませんか？
              </h2>
              <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                  <textarea name="content" placeholder="今日はどんな一日でしたか？ココベースのみんなと気持ちをシェアしてください..." 
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
                      <input type="text" name="tags" placeholder="例: 音楽, 感情, 日常" 
                             class="w-full p-3 border-2 rounded-xl focus:outline-none transition-colors"
                             style="border-color: var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                  </div>
                  
                  <div class="flex items-center justify-between">
                      <label class="flex items-center space-x-2 text-sm" style="color: var(--text-primary);">
                          <input type="checkbox" name="is_private" value="1" class="rounded border-gray-300">
                          <span>プライベート（自分だけが見れる）</span>
                      </label>
                      <button type="submit" class="text-white px-6 py-2 rounded-full font-medium transition-all transform hover:scale-105" style="background: var(--accent-primary);">
                          シェアする
                      </button>
                  </div>
              </form>
          </div>

          <!-- 投稿一覧 -->
          <div class="card-shadow rounded-2xl p-6">
              <h2 class="text-lg font-semibold mb-4 flex items-center">
                  <svg class="w-5 h-5 mr-2" style="color: var(--text-secondary);" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  ココベースのみんなの想い
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
                                  <?php if ($post['is_private'] && $post['user_id'] == $_SESSION['user_id']): ?>
                                      <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">プライベート</span>
                                  <?php endif; ?>
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
                                              <a href="home.php?tag=<?= urlencode($trimmed); ?>" 
                                                 class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full mr-1 mb-1 hover:bg-blue-200 transition-colors">
                                                 #<?= htmlspecialchars($trimmed); ?>
                                              </a>
                                          <?php endif; 
                                      endforeach; ?>
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
                          <?php if (!empty($search_tag)): ?>
                              「<?= htmlspecialchars($search_tag) ?>」に関する投稿が見つかりませんでした。
                          <?php else: ?>
                              まだ投稿がありません。
                          <?php endif; ?>
                      </p>
                      <p class="text-sm" style="color: var(--text-secondary);">ココベースの最初の投稿をしてみませんか？</p>
                  </div>
              <?php endif; ?>
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