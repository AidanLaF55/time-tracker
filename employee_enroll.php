<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle employee enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll'])) {
    $employee_id = trim($_POST['employee_id']);
    $name = trim($_POST['name']);

    if (empty($employee_id) || empty($name)) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        try {
            // Check if employee_id already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Employee ID already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO employees (employee_id, name) VALUES (?, ?)");
                $stmt->execute([$employee_id, $name]);
                $_SESSION['success'] = "Employee enrolled successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error enrolling employee: " . $e->getMessage();
        }
    }
    header("Location: employee_enroll.php");
    exit;
}

// Handle employee deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $employee_id = $_POST['employee_id'];

    try {
        // Delete associated time transactions
        $stmt = $pdo->prepare("DELETE FROM time_transactions WHERE employee_id = ?");
        $stmt->execute([$employee_id]);

        // Delete employee
        $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $_SESSION['success'] = "Employee deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting employee: " . $e->getMessage();
    }
    header("Location: employee_enroll.php");
    exit;
}

// Fetch all employees
try {
    $stmt = $pdo->prepare("SELECT employee_id, name FROM employees");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Sort employees by employee_id numerically
    usort($employees, function($a, $b) {
        return (int)$a['employee_id'] - (int)$b['employee_id'];
    });
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll Employee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Enroll Employee</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="employee_id" class="form-label">Employee ID</label>
                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <button type="submit" name="enroll" class="btn btn-primary">Enroll Employee</button>
        </form>

        <h4>Existing Employees</h4>
        <?php if (empty($employees)): ?>
            <p>No employees found.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>
    </div>

    <!-- Delete Confirmation Modals -->
    <?php foreach ($employees as $employee): ?>
        <div class="modal fade" id="deleteModal<?php echo htmlspecialchars($employee['employee_id']); ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <?php echo htmlspecialchars($employee['name']); ?> (ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)? This will also delete all associated time transactions.</p>
                    </div>
                    <div class="modal-footer">
                        <form method="POST">
                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                            <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
