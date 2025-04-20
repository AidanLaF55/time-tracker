<?php
session_start();
require 'config.php';

$employee_id = $_POST['employee_id'];

// Validate employee exists
$stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Invalid Employee ID";
    header("Location: index.php");
    exit;
}

// Check if already clocked in
$stmt = $pdo->prepare("SELECT * FROM time_transactions WHERE employee_id = ? AND end_time IS NULL");
$stmt->execute([$employee_id]);
$open_transaction = $stmt->fetch();

$_SESSION['employee_id'] = $employee_id;

if ($open_transaction) {
    header("Location: clock_out.php");
} else {
    header("Location: clock_in.php");
}
exit;
?>