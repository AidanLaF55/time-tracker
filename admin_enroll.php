<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle admin enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll'])) {
    $admin_id = trim($_POST['admin_id']);
    $password = $_POST['password'];

    if (empty($admin_id) || empty($password)) {
        $_SESSION['error'] = "All fields are required";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Admin ID already exists";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (admin_id, password) VALUES (?, ?)");
                if ($stmt->execute([$admin_id, $password_hash])) {
                    $_SESSION['success'] = "Admin enrolled successfully";
                } else {
                    $_SESSION['error'] = "Failed to enroll admin";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: admin_enroll.php");
    exit;
}

// Handle admin deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $admin_id = $_POST['admin_id'];

    if ($admin_id === $_SESSION['admin_id']) {
        $_SESSION['error'] = "You cannot delete your own account";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE admin_id = ?");
            if ($stmt->execute([$admin_id])) {
                $_SESSION['success'] = "Admin deleted successfully";
            } else {
                $_SESSION['error'] = "Failed to delete admin";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting admin: " . $e->getMessage();
        }
    }
    header("Location: admin_enroll.php");
    exit;
}

// Fetch all admins
try {
    $stmt = $pdo->prepare("SELECT admin_id FROM admins ORDER BY admin_id");
    $stmt->execute();
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching admins: " . $e->getMessage();
    $admins = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Enroll Admin</h2>
        <p><a href="admin_dashboard.php" class="btn btn-primary">Back to Dashboard</a></p>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <!-- Admin List -->
        <h4 class="mt-4">Current Admins</h4>
        <?php if (empty($admins)): ?>
            <p>No admins enrolled.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Admin ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['admin_id']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo htmlspecialchars($admin['admin_id']); ?>">Delete</button>
                            </td>
                        </tr>
                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?php echo htmlspecialchars($admin['admin_id']); ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Admin</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete Admin ID: <?php echo htmlspecialchars($admin['admin_id']); ?>?</p>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin['admin_id']); ?>">
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Enrollment Form -->
        <h4 class="mt-4">Add New Admin</h4>
        <form method="POST">
            <div class="mb-3">
                <label for="admin_id" class="form-label">Admin ID</label>
                <input type="text" class="form-control" id="admin_id" name="admin_id" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" name="enroll" class="btn btn-primary">Enroll Admin</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
