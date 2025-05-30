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

// Check if already clocked in
$stmt = $pdo->prepare("SELECT id FROM time_transactions WHERE employee_id = ? AND end_time IS NULL");
$stmt->execute([$employee_id]);
$is_clocked_in = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($is_clocked_in) {
        $_SESSION['error'] = "You are already clocked in";
    } else {
        $stmt = $pdo->prepare("INSERT INTO time_transactions (employee_id, start_time) VALUES (?, DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00'))");
        if ($stmt->execute([$employee_id])) {
            $_SESSION['success'] = "Clocked in successfully";
        } else {
            $_SESSION['error'] = "Failed to clock in";
        }
    }
    header("Location: employee_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clock In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Clock In - <?php echo htmlspecialchars($name); ?></h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if ($is_clocked_in): ?>
            <div class="alert alert-warning">You are already clocked in, <?php echo htmlspecialchars($name); ?>.</div>
            <p><a href="employee_dashboard.php" class="btn btn-primary">Back to Dashboard</a></p>
        <?php else: ?>
            <form method="POST">
                <p>Ready to start your shift, <?php echo htmlspecialchars($name); ?>?</p>
                <button type="submit" class="btn btn-success">Clock In Now</button>
                <a href="employee_dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
