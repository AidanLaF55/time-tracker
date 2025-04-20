<?php
session_start();

if (isset($_SESSION['employee_id'])) {
    header("Location: employee_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Clock-In/Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Employee Clock-In/Out</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <form action="process_login.php" method="POST" class="mt-4">
            <div class="mb-3">
                <label for="employee_id" class="form-label">Employee ID</label>
            <input type="text" class="form-control" id="employee_id" name="employee_id" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="mt-3"><a href="admin_login.php" class="btn btn-secondary">Go to Admin Login</a></p>
    </div>
</body>
</html>
