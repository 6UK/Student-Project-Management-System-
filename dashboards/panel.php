<?php
// Safe Redirect Stub if legacy bookmarks target panel.php
if (isset($_GET['pid'])) {
    header("Location: evaluate_defense.php?pid=" . (int)$_GET['pid']);
    exit();
} else {
    header("Location: supervisor.php");
    exit();
}