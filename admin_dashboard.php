<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

// Get week start (Monday)
$week_start = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));

// Fetch employees for cascade modal
try {
    $stmt = $pdo->prepare("SELECT employee_id, name FROM employees");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($employees)) {
        $_SESSION['error'] = "No employees found. Please enroll employees first.";
    }

    // Sort employees by employee_id numerically
    usort($employees, function($a, $b) {
        return (int)$a['employee_id'] - (int)$b['employee_id'];
    });

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}

// Fetch admins for delete modal (exclude current admin)
try {
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE admin_id != ? ORDER BY admin_id");
    $stmt->execute([$_SESSION['admin_id']]);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching admins: " . $e->getMessage();
    $admins = [];
}

// Fetch time transactions and calculate totals
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.employee_id, e.name, t.start_time, t.end_time
        FROM time_transactions t
        JOIN employees e ON t.employee_id = e.employee_id
        WHERE DATE(t.start_time) BETWEEN ? AND ?
    ");
    $stmt->execute([$week_start, $week_end]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Sort transactions by employee_id (numeric) and start_time
    usort($transactions, function($a, $b) {
        $numA = (int)$a['employee_id'];
        $numB = (int)$b['employee_id'];
        if ($numA === $numB) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        }
        return $numA - $numB;
    });
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching transactions: " . $e->getMessage();
    $transactions = [];
}

// Calculate total hours per employee
$employee_totals = [];
foreach ($transactions as $row) {
    if ($row['end_time']) {
        $hours = round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600, 2);
        $employee_id = $row['employee_id'];
        if (!isset($employee_totals[$employee_id])) {
            $employee_totals[$employee_id] = ['name' => $row['name'], 'hours' => 0];
        }
        $employee_totals[$employee_id]['hours'] += $hours;
    }
}

// Sort employee totals by employee_id
uksort($employee_totals, function($a, $b) {
    $numA = (int)$a;
    $numB = (int)$b;
    return $numA - $numB;
});

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'] ?: null;

     try {
        $stmt = $pdo->prepare("UPDATE time_transactions SET start_time = ?, end_time = ? WHERE id = ?");
        $stmt->execute([$start_time, $end_time, $id]);
        $_SESSION['success'] = "Transaction updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating transaction: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?week_start=$week_start");
    exit;
}

// Handle delete transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_transaction'])) {
    $id = $_POST['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM time_transactions WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = "Transaction deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete transaction";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting transaction: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?week_start=$week_start");
    exit;
}

// Handle delete employee
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_employee'])) {
    $employee_id = $_POST['employee_id'];

    try {
        // Delete time transactions
        $stmt = $pdo->prepare("DELETE FROM time_transactions WHERE employee_id = ?");
        $stmt->execute([$employee_id]);

        // Delete employee
        $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
        if ($stmt->execute([$employee_id])) {
            $_SESSION['success'] = "Employee deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete employee";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting employee: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php?week_start=$week_start");
    exit;
}

// Handle delete admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_admin'])) {
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
    header("Location: admin_dashboard.php?week_start=$week_start");
    exit;
}

// Handle cascade hours
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cascade'])) {
    $employee_id = $_POST['employee_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $days = isset($_POST['days']) ? $_POST['days'] : [];

    if (empty($employee_id) || empty($start_time) || empty($end_time) || empty($days)) {
        $_SESSION['error'] = "All fields are required";
    } else {
        try {
            $start_date = new DateTime($week_start);
            $errors = [];
            foreach ($days as $day) {
                $day_index = array_search($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                $transaction_date = (clone $start_date)->modify("+$day_index days")->format('Y-m-d');
                
                // Check if transaction exists
                $stmt = $pdo->prepare("SELECT id FROM time_transactions WHERE employee_id = ? AND DATE(start_time) = ?");
                $stmt->execute([$employee_id, $transaction_date]);
                if ($stmt->fetch()) {
                    $errors[] = "Transaction already exists for $day";
                    continue;
                }

                // Insert transaction
                $start_datetime = "$transaction_date " . date('H:i:00', strtotime($start_time));
                $end_datetime = "$transaction_date " . date('H:i:00', strtotime($end_time));
                $stmt = $pdo->prepare("INSERT INTO time_transactions (employee_id, start_time, end_time) VALUES (?, ?, ?)");
                if (!$stmt->execute([$employee_id, $start_datetime, $end_datetime])) {
                    $errors[] = "Failed to add transaction for $day";
                }
            }
            if (empty($errors)) {
                $_SESSION['success'] = "Hours applied successfully";
            } else {
                $_SESSION['error'] = implode(", ", $errors);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error applying hours: " . $e->getMessage();
        }
    }
    header("Location: admin_dashboard.php?week_start=$week_start");
    exit;
}

// Handle HTML export
if (isset($_GET['export_html'])) {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="time_transactions_' . $week_start . '.html"');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Time Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Time Transactions ($week_start to $week_end)</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Hours Worked</th>
                </tr>
            </thead>
            <tbody>
HTML;

    foreach ($transactions as $row) {
        $hours = $row['end_time'] ? round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600, 2) : 0;
        $employee_id = htmlspecialchars($row['employee_id']);
        $name = htmlspecialchars($row['name']);
        $date = date('m-d-Y', strtotime($row['start_time']));
        $start_time = date('m-d-Y h:i A', strtotime($row['start_time']));
        $end_time = $row['end_time'] ? date('m-d-Y h:i A', strtotime($row['end_time'])) : '';
        echo <<<HTML
                <tr>
                    <td>$employee_id</td>
                    <td>$name</td>
                    <td>$date</td>
                    <td>$start_time</td>
                    <td>$end_time</td>
                    <td>$hours</td>
                </tr>
HTML;
    }
    echo <<<HTML
            </tbody>
            <tfoot>
HTML;
    foreach ($employee_totals as $total) {
        $name = htmlspecialchars($total['name']);
        $hours = $total['hours'];
        echo <<<HTML
                <tr>
                    <td colspan="5">Total for $name</td>
                    <td>$hours</td>
                </tr>
HTML;
    }
    echo <<<HTML
            </tfoot>
        </table>
    </div>
</body>
</html>
HTML;
    exit;
}
// Handle CSV export
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="time_transactions_' . $week_start . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee ID', 'Name', 'Date', 'Start Time', 'End Time', 'Hours Worked']);

    foreach ($transactions as $row) {
        $hours = $row['end_time'] ? round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600, 2) : 0;
        fputcsv($output, [
            $row['employee_id'],
            $row['name'],
            date('m-d-Y', strtotime($row['start_time'])),
            date('m-d-Y h:i A', strtotime($row['start_time'])),
            $row['end_time'] ? date('m-d-Y h:i A', strtotime($row['end_time'])) : '',
            $hours
        ]);
    }
    fputcsv($output, ['', '', '', '', '']);
    foreach ($employee_totals as $total) {
        fputcsv($output, ['', "Total for {$total['name']}", '', '', '', $total['hours']]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-center">Admin Dashboard</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <p>
            <a href="admin_enroll.php" class="btn btn-info">Create New Admin</a>
            <a href="employee_enroll.php" class="btn btn-info">Create New Employee</a>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#cascadeModal">Add Weekly Hours</button>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal">Delete Employee</button>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#deleteAdminModal">Delete Admin</button>
        </p>
        <form class="mb-4">
            <label for="week_start" class="form-label">Select Week</label>
            <input type="date" class="form-control w-25 d-inline" id="week_start" name="week_start" value="<?php echo $week_start; ?>">
            <button type="submit" class="btn btn-primary">View</button>
            <a href="?week_start=<?php echo $week_start; ?>&export_html=1" class="btn btn-success">Export HTML</a>
            <a href="?week_start=<?php echo $week_start; ?>&export_csv=1" class="btn btn-success">Export CSV</a>
        </form>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Hours</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $row): ?>
                    <?php $hours = $row['end_time'] ? round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600, 2) : 0; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo date('m-d-Y', strtotime($row['start_time'])); ?></td>
                        <td><?php echo date('m-d-Y h:i A', strtotime($row['start_time'])); ?></td>
                        <td><?php echo $row['end_time'] ? date('m-d-Y h:i A', strtotime($row['end_time'])) : 'Not clocked out'; ?></td>
                        <td><?php echo $hours; ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">Edit</button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTransactionModal<?php echo $row['id']; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php foreach ($employee_totals as $total): ?>
                    <tr>
                        <td colspan="4">Total for <?php echo htmlspecialchars($total['name']); ?></td>
                        <td><?php echo $total['hours']; ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </tfoot>
        </table>
        <p><a href="?logout=1" class="btn btn-danger">Logout</a></p>
    </div>

    <!-- Cascade Hours Modal -->
    <div class="modal fade" id="cascadeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Weekly Hours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Days of the Week</label>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days[]" value="<?php echo $day; ?>" id="day_<?php echo $day; ?>">
                                    <label class="form-check-label" for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="cascade" class="btn btn-primary">Apply Hours</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Employee Modal -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-danger">This will also delete all time transactions for the selected employee.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="delete_employee" class="btn btn-danger">Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Admin Modal -->
    <div class="modal fade" id="deleteAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="admin_id" class="form-label">Admin</label>
                            <select class="form-control" id="admin_id" name="admin_id" required>
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo htmlspecialchars($admin['admin_id']); ?>">
                                        <?php echo htmlspecialchars($admin['admin_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-danger">This will permanently delete the selected admin account.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="delete_admin" class="btn btn-danger">Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($transactions as $row): ?>
        <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="admin_dashboard.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="datetime-local" class="form-control" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($row['start_time'])); ?>" required step="60">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="datetime-local" class="form-control" name="end_time" value="<?php echo $row['end_time'] ? date('Y-m-d\TH:i', strtotime($row['end_time'])) : ''; ?>" step="60">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="edit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Transaction Modal -->
        <div class="modal fade" id="deleteTransactionModal<?php echo $row['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <p>Are you sure you want to delete the transaction for <?php echo htmlspecialchars($row['name']); ?> on <?php echo date('m-d-Y', strtotime($row['start_time'])); ?> at <?php echo date('h:i A', strtotime($row['start_time'])); ?>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="delete_transaction" class="btn btn-danger">Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
