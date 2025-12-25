<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

try {
    $wp_host = "sql103.infinityfree.com";
    $wp_db_name = "if0_39913189_wp887";
    $wp_username = "if0_39913189";
    $wp_password = "lyE2sjuBnU";
    
    $wp_db = new PDO("mysql:host=$wp_host;dbname=$wp_db_name", $wp_username, $wp_password);
    $wp_db->exec("set names utf8");
    $wp_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test connection and check if table exists
    $test_query = "SHOW TABLES LIKE 'wppw_fc_subscribers'";
    $test_stmt = $wp_db->query($test_query);
    $table_exists = $test_stmt->rowCount() > 0;
    
    if (!$table_exists) {
        throw new Exception("Table wppw_fc_subscribers does not exist");
    }
    
} catch (Exception $e) {
    $wp_db = null;
    error_log("WordPress database connection failed: " . $e->getMessage());
    $_SESSION['error'] = "Database connection failed: " . $e->getMessage();
}

$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $records_per_page;
$total_records = 0;
$total_pages = 0;

// Only proceed with queries if database connection is successful
if ($wp_db) {
    try {
        $count_query = "SELECT COUNT(*) as total FROM wppw_fc_subscribers";
        $count_stmt = $wp_db->prepare($count_query);
        $count_stmt->execute();
        $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = ceil($total_records / $records_per_page);

        if ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
            $offset = ($current_page - 1) * $records_per_page;
        }
    } catch (PDOException $e) {
        error_log("Error counting records: " . $e->getMessage());
        $_SESSION['error'] = "Error counting records: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">User Management</h1>
        <p class="text-muted">Managing users from WordPress database</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (!$wp_db): ?>
<div class="alert alert-warning">
    <h4><i class="fas fa-exclamation-triangle"></i> Database Connection Issue</h4>
    <p>Unable to connect to WordPress user database. Possible reasons:</p>
    <ul>
        <li>Incorrect database connection information</li>
        <li>Database server unavailable</li>
        <li>Table wppw_fc_subscribers does not exist</li>
    </ul>
    <p class="mb-0">Please check database configuration or contact system administrator.</p>
</div>
<?php elseif ($total_records == 0): ?>
<div class="alert alert-info">
    <h4><i class="fas fa-info-circle"></i> No User Data</h4>
    <p class="mb-0">No user records found in the user table.</p>
</div>
<?php else: ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body py-3">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded p-3 me-3">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $total_records; ?></h5>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-success text-white rounded p-3 me-3">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $records_per_page; ?></h5>
                                <small class="text-muted">Per Page</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-info text-white rounded p-3 me-3">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $current_page; ?> of <?php echo $total_pages; ?></h5>
                                <small class="text-muted">Current Page</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning text-white rounded p-3 me-3">
                                <i class="fas fa-sort fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="mb-0">ID ↑</h5>
                                <small class="text-muted">Sorted by</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Contact Type</th>
                <th>Registration Date</th>
                <th>Last Activity</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $query = "SELECT * FROM wppw_fc_subscribers ORDER BY id ASC LIMIT :limit OFFSET :offset";
                $stmt = $wp_db->prepare($query);
                $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $users_on_page = 0;
                
                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $users_on_page++;
                    $display_name = trim($user['first_name'] . ' ' . $user['last_name']);
                    if (empty($display_name)) {
                        $display_name = 'Unknown';
                    }
            ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($display_name); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <span class="badge bg-<?php 
                        echo $user['status'] == 'subscribed' ? 'success' : 
                             ($user['status'] == 'pending' ? 'warning' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </td>
                <td><?php echo ucfirst($user['contact_type'] ?? 'lead'); ?></td>
                <td><?php echo $user['created_at'] ? date('M j, Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                <td><?php echo $user['last_activity'] ? date('M j, Y', strtotime($user['last_activity'])) : 'Never'; ?></td>
                <td>
                    <a href="user_edit.php?id=<?php echo $user['id']; ?>&page=<?php echo $current_page; ?>" class="btn btn-sm btn-outline-primary">
                        Edit
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($display_name); ?>', <?php echo $current_page; ?>)">
                        Delete
                    </button>
                </td>
            </tr>
            <?php
                }

                if ($users_on_page === 0) {
                    echo '<tr><td colspan="8" class="text-center text-muted py-4">No users found on this page</td></tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="8" class="text-center text-danger">Error loading users: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="User pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>

        <?php
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        if ($end_page - $start_page < 4) {
            if ($start_page == 1) {
                $end_page = min($total_pages, $start_page + 4);
            } else {
                $start_page = max(1, $end_page - 4);
            }
        }

        if ($start_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
            if ($start_page > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = $i == $current_page ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
        }
        ?>

        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>

<div class="text-center text-muted mt-2">
    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function confirmDeleteUser(userId, username, currentPage) {
    if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
        window.location.href = 'user_delete.php?id=' + userId + '&page=' + currentPage;
    }
}
</script>

<?php include '../includes/footer.php'; ?>