<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === 'admin' && $password === 'password') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
        $_SESSION['email'] = 'admin@gamevault.com';
        header("Location: ../index.php");
        exit;
    } elseif ($username === 'user' && $password === 'password') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'user';
        $_SESSION['role'] = 'customer';
        $_SESSION['email'] = 'user@gamevault.com';
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
        
        if ($wp_db) {
            $query = "SELECT * FROM wppw_fc_subscribers WHERE email = :username OR first_name = :username";
            $stmt = $wp_db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user['phone'] && $user['phone'] === $password) {
                    $_SESSION['user_id'] = $user['id'];
                    $display_name = trim($user['first_name'] . ' ' . $user['last_name']);
                    if (empty($display_name)) {
                        $display_name = explode('@', $user['email'])[0]; 
                    }
                    $_SESSION['username'] = $display_name;
                    $_SESSION['role'] = 'customer'; 
                    $_SESSION['email'] = $user['email'];
                    
                    header("Location: ../index.php");
                    exit;
                } else {
                    $error = "Invalid username or password!";
                }
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "Database connection failed.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">User Login</h4>
                </div>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Email or Userame</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="https://sam4567.lovestoblog.com/WordPress/register/?i=1" target="_blank">Don't have an account? Register now</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>