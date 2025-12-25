<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } elseif (strlen($username) > 50) {
        $error = "Username must be less than 50 characters!";
    } elseif (strlen($email) > 100) {
        $error = "Email must be less than 100 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                $check_query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':email', $email);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $error = "Username or email already exists!";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $insert_query = "INSERT INTO users (username, email, password, role) 
                                    VALUES (:username, :email, :password, 'customer')";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':username', $username);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':password', $hashed_password);
                    
                    if ($insert_stmt->execute()) {
                        $user_id = $db->lastInsertId();
                        $success = "Registration successful! You can now login.";
                    } else {
                        $error = "Registration failed. Please try again.";
                        $errorInfo = $insert_stmt->errorInfo();
                        $error .= " Error: " . $errorInfo[2];
                    }
                }
            } else {
                $error = "Database connection failed.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">User Registration</h4>
            </div>
            <div class="card-body">
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php else: ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="login.php">Already have an account? Login here</a>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>