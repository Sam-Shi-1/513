<?php
$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
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
} catch (PDOException $e) {
    $wp_db = null;
    error_log("WordPress database connection failed: " . $e->getMessage());
}

$database = new Database();
$db = $database->getConnection();

$user = null;
$order_count = 0;
$success_message = '';
$error_message = '';

$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                $password_query = "SELECT phone FROM wppw_fc_subscribers WHERE id = :user_id";
                $password_stmt = $wp_db->prepare($password_query);
                $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $password_stmt->execute();
                $user_data = $password_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data && $_POST['current_password'] === $user_data['phone']) {
                    $email_check_query = "SELECT id FROM wppw_fc_subscribers WHERE email = :email AND id != :user_id";
                    $email_check_stmt = $wp_db->prepare($email_check_query);
                    $email_check_stmt->bindParam(':email', $email);
                    $email_check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $email_check_stmt->execute();
                    
                    if ($email_check_stmt->rowCount() > 0) {
                        $error_message = "Email address is already in use by another account.";
                    } else {
                        $update_query = "UPDATE wppw_fc_subscribers 
                                        SET first_name = :first_name, 
                                            last_name = :last_name, 
                                            email = :email,
                                            updated_at = NOW()
                                        WHERE id = :user_id";
                        $update_stmt = $wp_db->prepare($update_query);
                        $update_stmt->bindParam(':first_name', $first_name);
                        $update_stmt->bindParam(':last_name', $last_name);
                        $update_stmt->bindParam(':email', $email);
                        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Profile updated successfully!";
                            $_SESSION['email'] = $email;
                            $_SESSION['username'] = trim($first_name . ' ' . $last_name);
                            if (empty($_SESSION['username'])) {
                                $_SESSION['username'] = explode('@', $email)[0];
                            }
                            $edit_mode = false;
                        } else {
                            $error_message = "Failed to update profile. Please try again.";
                        }
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                error_log("Database error in profile update: " . $e->getMessage());
                $error_message = "Database error. Please try again.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } else {
            try {
                $password_query = "SELECT phone FROM wppw_fc_subscribers WHERE id = :user_id";
                $password_stmt = $wp_db->prepare($password_query);
                $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $password_stmt->execute();
                $user_data = $password_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data && $current_password === $user_data['phone']) {
                    $update_query = "UPDATE wppw_fc_subscribers SET phone = :password, updated_at = NOW() WHERE id = :user_id";
                    $update_stmt = $wp_db->prepare($update_query);
                    $update_stmt->bindParam(':password', $new_password);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Failed to change password. Please try again.";
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                error_log("Database error in password change: " . $e->getMessage());
                $error_message = "Database error. Please try again.";
            }
        }
    }
}

if ($wp_db) {
    try {
        $user_query = "SELECT * FROM wppw_fc_subscribers WHERE id = :user_id";
        $user_stmt = $wp_db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in profile.php: " . $e->getMessage());
    }
}

if ($db) {
    try {
        $order_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = :user_id";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $order_stmt->execute();
        $order_count_result = $order_stmt->fetch(PDO::FETCH_ASSOC);
        $order_count = $order_count_result['order_count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Database error fetching order count: " . $e->getMessage());
    }
}

if (!$user && isset($_SESSION['user_id'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'first_name' => explode(' ', $_SESSION['username'])[0] ?? '',
        'last_name' => explode(' ', $_SESSION['username'])[1] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

if (!$user) {
    header("Location: ../auth/login.php");
    exit;
}

$display_name = trim($user['first_name'] . ' ' . $user['last_name']);
if (empty($display_name)) {
    $display_name = explode('@', $user['email'])[0];
}
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h4><?php echo htmlspecialchars($display_name); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="badge bg-<?php echo $_SESSION['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                    <?php echo ucfirst($_SESSION['role']); ?>
                </div>
                
                <?php if (!$edit_mode): ?>
                <div class="mt-3">
                    <a href="?edit=true" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Profile
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Account Stats</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Orders:</span>
                    <strong><?php echo $order_count; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Member Since:</span>
                    <strong><?php echo $user['created_at'] ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Account Status:</span>
                    <span class="badge bg-<?php 
                        echo $user['status'] == 'subscribed' ? 'success' : 
                             ($user['status'] == 'pending' ? 'warning' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst($user['status'] ?? 'active'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($edit_mode): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Profile Information</h5>
                <a href="profile.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm">
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
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="form-text">We'll never share your email with anyone else.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button type="button" class="btn btn-outline-secondary password-toggle" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Enter your current password to save changes.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="created_at" class="form-label">Member Since</label>
                        <input type="text" class="form-control" id="created_at" 
                               value="<?php echo $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?>" readonly>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <div class="mb-3">
                        <label for="change_current_password" class="form-label">Current Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="change_current_password" name="current_password" required>
                            <button type="button" class="btn btn-outline-secondary password-toggle" data-target="change_current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary password-toggle" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 characters.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    <button type="button" class="btn btn-outline-secondary password-toggle" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Re-enter your new password.</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Profile Information</h5>
                <a href="?edit=true" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">First Name</label>
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($user['first_name'] ?? 'Not set'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Last Name</label>
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($user['last_name'] ?? 'Not set'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Member Since</label>
                    <p class="form-control-plaintext"><?php echo $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Account Status</label>
                    <p class="form-control-plaintext">
                        <span class="badge bg-<?php 
                            echo $user['status'] == 'subscribed' ? 'success' : 
                                 ($user['status'] == 'pending' ? 'warning' : 'secondary'); 
                        ?>">
                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Recent Orders</h5>
            </div>
            <div class="card-body">
                <?php
                $recent_orders = [];
                if ($db) {
                    try {
                        $recent_orders_query = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
                        $recent_orders_stmt = $db->prepare($recent_orders_query);
                        $recent_orders_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        $recent_orders_stmt->execute();
                        $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Database error fetching recent orders: " . $e->getMessage());
                    }
                }
                
                if (count($recent_orders) > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($order['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'paid': echo 'info'; break;
                                            case 'delivered': echo 'success'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                <?php else: ?>
                    <p class="text-muted">No orders found.</p>
                    <a href="../products/index.php" class="btn btn-primary">Start Shopping</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
        }
        
        if (newPassword) newPassword.addEventListener('input', validatePasswords);
        if (confirmPassword) confirmPassword.addEventListener('input', validatePasswords);

        passwordForm.addEventListener('submit', function(e) {
            validatePasswords();
            if (!this.checkValidity()) {
                e.preventDefault();
                this.reportValidity();
            }
        });
    }

    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        const emailInput = document.getElementById('email');
        
        emailInput.addEventListener('input', function() {
            if (!this.validity.valid) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>