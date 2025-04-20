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

// Fetch time transactions
$stmt = $pdo->prepare("
    SELECT t.id, t.employee_id, e.name, t.start_time, t.end_time
    FROM time_transactions t
    JOIN employees e ON t.employee_id = e.employee_id
    WHERE DATE(t.start_time) BETWEEN ? AND ?
    ORDER BY e.name, t.start_time
");
$stmt->execute([$week_start, $week_end]);
$transactions = $stmt->fetchAll();

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'] ?: null;

    $stmt = $pdo->prepare("UPDATE time_transactions SET start_time = ?, end_time = ? WHERE id = ?");
    $stmt->execute([$start_time, $end_time, $id]);
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
        <p>
            <a href="admin_enroll.php" class="btn btn-info">Create New Admin</a>
            <a href="employee_enroll.php" class="btn btn-info">Create New Employee</a>
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
                        </td>
                    </tr>
                    <!-- Edit Modal -->
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
                                            <input type="datetime-local" class="form-control" name="start_time" value="<?php echo date('Y-m-d\TH:i:s', strtotime($row['start_time'])); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">End Time</label>
                                            <input type="datetime-local" class="form-control" name="end_time" value="<?php echo $row['end_time'] ? date('Y-m-d\TH:i:s', strtotime($row['end_time'])) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="edit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="?logout=1" class="btn btn-danger">Logout</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
