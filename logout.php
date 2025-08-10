<?php
// セッション開始（すでに開始されている場合も考慮）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// セッションを全て削除
$_SESSION = array();

// セッションを破棄
session_destroy();

// ログインページへリダイレクト
header("Location: login.php");
exit();
?>
