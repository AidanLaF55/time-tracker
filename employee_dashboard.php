<?php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];

// Fetch employee's name
$stmt = $pdo->prepare("SELECT name FROM employees WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
$name = $employee['name'];

// Check if employee is clocked in
$stmt = $pdo->prepare("SELECT id FROM time_transactions WHERE employee_id = ? AND end_time IS NULL");
$stmt->execute([$employee_id]);
$is_clocked_in = $stmt->fetch();

// Fetch time transactions for this employee
$stmt = $pdo->prepare("
    SELECT start_time, end_time
    FROM time_transactions
    WHERE employee_id = ?
    ORDER BY start_time DESC
    LIMIT 10
");
$stmt->execute([$employee_id]);
$transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Welcome, <?php echo htmlspecialchars($name); ?></h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <div class="mt-4">
            <?php if ($is_clocked_in): ?>
                <a href="clock_out.php" class="btn btn-danger">Clock Out</a>
            <?php else: ?>
                <a href="clock_in.php" class="btn btn-success">Clock In</a>
            <?php endif; ?>
        </div>
        <h4 class="mt-4">Recent Time Records</h4>
        <?php if (empty($transactions)): ?>
            <p>No time records yet.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $row): ?>
                        <?php $hours = $row['end_time'] ? round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600, 2) : 0; ?>
                        <tr>
                            <td><?php echo date('m-d-Y h:i A', strtotime($row['start_time'])); ?></td>
                            <td><?php echo $row['end_time'] ? date('m-d-Y h:i A', strtotime($row['end_time'])) : 'Not clocked out'; ?></td>
                            <td><?php echo $hours; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="mt-3">
            <a href="?logout=1" class="btn btn-danger">Logout</a>
        </p>
    </div>
</body>
</html>
