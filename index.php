<?php
// index.php
session_start();

// Jika sudah ada session login, lempar ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
} 
// Jika belum, lempar ke login
else {
    header("Location: login.php");
    exit();
}
?>