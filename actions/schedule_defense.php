<?php
require_once('../includes/auth_check.php');
checkRole(['coordinator', 'hod']);
require_once('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);

    $stmt = $conn->prepare("UPDATE project SET m7_status = 'Scheduled' WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);

    if ($stmt->execute()) {
        header("Location: ../dashboards/evaluate_defense.php?pid=" . $project_id);
        exit();
    } else {
        die("<div style='color:red; padding:20px; font-weight:bold;'>Error scheduling defense: " . htmlspecialchars($conn->error) . "</div>");
    }
} else {
    header("Location: ../dashboards/coordinator.php");
    exit();
}
?>