<?php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];

// Fetch employee's name
$stmt = $pdo->prepare("SELECT name FROM employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
$name = $employee['name'];

// Check if clocked in
$stmt = $pdo->prepare("SELECT id FROM time_transactions WHERE employee_id = ? AND end_time IS NULL");
$stmt->execute([$employee_id]);
$transaction = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($transaction) {
        $stmt = $pdo->prepare("UPDATE time_transactions SET end_time = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00') WHERE id = ?");
        if ($stmt->execute([$transaction['id']])) {
            $_SESSION['success'] = "Clocked out successfully";
        } else {
            $_SESSION['error'] = "Failed to clock out";
        }
    } else {
        $_SESSION['error'] = "You are not clocked in";
    }
    header("Location: employee_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clock Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Clock Out - <?php echo htmlspecialchars($name); ?></h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (!$transaction): ?>
            <div class="alert alert-warning">You are not clocked in, <?php echo htmlspecialchars($name); ?>.</div>
            <p><a href="employee_dashboard.php" class="btn btn-primary">Back to Dashboard</a></p>
        <?php else: ?>
            <form method="POST">
                <p>Ready to end your shift, <?php echo htmlspecialchars($name); ?>?</p>
                <button type="submit" class="btn btn-danger">Clock Out Now</button>
                <a href="employee_dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
