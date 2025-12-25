<?php
$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit;
}

$user_id = $_GET['id'];

try {
    $wp_host = "sql103.infinityfree.com";
    $wp_db_name = "if0_39913189_wp887";
    $wp_username = "if0_39913189";
    $wp_password = "lyE2sjuBnU";
    
    $wp_db = new PDO("mysql:host=$wp_host;dbname=$wp_db_name", $wp_username, $wp_password);
    $wp_db->exec("set names utf8");
    $wp_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $wp_db = null;
    error_log("WordPress database connection failed: " . $e->getMessage());
}

$user = null;

if ($wp_db) {
    try {
        $user_query = "SELECT * FROM wppw_fc_subscribers WHERE id = :user_id";
        $user_stmt = $wp_db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();
        
        if ($user_stmt->rowCount() > 0) {
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['error'] = "User not found.";
            header("Location: users.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in user_edit.php: " . $e->getMessage());
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: users.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? 'subscribed';
    $phone = $_POST['phone'] ?? '';

    if (empty($email)) {
        $error = "Please fill in email field.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        if ($wp_db) {
            try {
                $update_query = "UPDATE wppw_fc_subscribers 
                                SET first_name = :first_name, 
                                    last_name = :last_name, 
                                    email = :email, 
                                    status = :status,
                                    phone = :phone,
                                    updated_at = NOW()
                                WHERE id = :user_id";
                
                $update_stmt = $wp_db->prepare($update_query);
                $update_stmt->bindParam(':first_name', $first_name);
                $update_stmt->bindParam(':last_name', $last_name);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':status', $status);
                $update_stmt->bindParam(':phone', $phone);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    $success = "User updated successfully!";
                    $user_stmt->execute();
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update user. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Database error updating user: " . $e->getMessage());
            }
        } else {
            $error = "Database connection not available.";
        }
    }
}

$display_name = trim($user['first_name'] . ' ' . $user['last_name']);
if (empty($display_name)) {
    $display_name = 'Unknown';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit User</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="users.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editUserForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="subscribed" <?php echo ($user['status'] == 'subscribed') ? 'selected' : ''; ?>>Subscribed</option>
                                    <option value="pending" <?php echo ($user['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="unsubscribed" <?php echo ($user['status'] == 'unsubscribed') ? 'selected' : ''; ?>>Unsubscribed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Password (Phone Field)</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                <small class="text-muted">This field is used as password for login</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">* Required fields</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">User Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>User ID:</strong> <?php echo $user['id']; ?>
                </div>
                <div class="mb-3">
                    <strong>Display Name:</strong> <?php echo htmlspecialchars($display_name); ?>
                </div>
                <div class="mb-3">
                    <strong>Current Status:</strong> 
                    <span class="badge bg-<?php 
                        echo $user['status'] == 'subscribed' ? 'success' : 
                             ($user['status'] == 'pending' ? 'warning' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Contact Type:</strong> <?php echo ucfirst($user['contact_type'] ?? 'lead'); ?>
                </div>
                <div class="mb-3">
                    <strong>Registered:</strong> <?php echo $user['created_at'] ? date('M j, Y', strtotime($user['created_at'])) : 'N/A'; ?>
                </div>
                <div class="mb-3">
                    <strong>Last Activity:</strong> <?php echo $user['last_activity'] ? date('M j, Y', strtotime($user['last_activity'])) : 'Never'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editUserForm');
    const emailInput = document.getElementById('email');
    
    form.addEventListener('submit', function(e) {
        let valid = true;

        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailInput.value && !emailRegex.test(emailInput.value)) {
            valid = false;
            emailInput.classList.add('is-invalid');
        } else {
            emailInput.classList.remove('is-invalid');
        }
        
        if (!valid) {
            e.preventDefault();
            alert('Please fix the errors in the form before submitting.');
        }
    });

    emailInput.addEventListener('input', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (this.value && !emailRegex.test(this.value)) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>