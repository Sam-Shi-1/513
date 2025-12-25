<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

date_default_timezone_set('Asia/Shanghai');

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

// Include forum time functions
require_once '../includes/forum_time_functions.php';

$database = new Database();
$db = $database->getConnection();

$categories = [];
$error = '';
$success = '';

if ($db) {
    try {
        $category_query = "SELECT * FROM forum_categories WHERE is_active = 1 ORDER BY category_order";
        $category_stmt = $db->prepare($category_query);
        $category_stmt->execute();
        $categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id = $_POST['category_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($category_id) || empty($title) || empty($content)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($title) > 200) {
        $error = "Title must be less than 200 characters.";
    } else {
        try {
            $db->beginTransaction();

            $current_china_time = getChinaTime();

            $topic_query = "INSERT INTO forum_topics (category_id, user_id, title, content, created_at) 
                           VALUES (:category_id, :user_id, :title, :content, :created_at)";
            $topic_stmt = $db->prepare($topic_query);
            $topic_stmt->bindParam(':category_id', $category_id);
            $topic_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $topic_stmt->bindParam(':title', $title);
            $topic_stmt->bindParam(':content', $content);
            $topic_stmt->bindParam(':created_at', $current_china_time);
            $topic_stmt->execute();
            
            $topic_id = $db->lastInsertId();

            $update_category_query = "UPDATE forum_categories 
                                    SET topic_count = COALESCE(topic_count, 0) + 1,
                                        last_activity = :last_activity
                                    WHERE category_id = :category_id";
            $update_stmt = $db->prepare($update_category_query);
            $update_stmt->bindParam(':category_id', $category_id);
            $update_stmt->bindParam(':last_activity', $current_china_time);
            $update_stmt->execute();
            
            $db->commit();
            
            $success = "Topic created successfully!";
            header("Location: forum_topic.php?id=" . $topic_id);
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Failed to create topic: " . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Create New Topic</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="forum_index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Forum
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
                <h5 class="mb-0">Topic Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="newTopicForm">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category *</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select a category...</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Topic Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               maxlength="200" required>
                        <div class="form-text">Maximum 200 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" name="content" 
                                  rows="10" required></textarea>
                        <div class="form-text">
                            You can use basic HTML tags: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">Create Topic</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Posting Guidelines</h5>
            </div>
            <div class="card-body">
                <ul class="small">
                    <li>Stay on topic and respect the category</li>
                    <li>No spam or advertising</li>
                    <li>Be respectful to other members</li>
                    <li>Use appropriate language</li>
                    <li>No personal attacks</li>
                    <li>Do not post copyrighted material</li>
                </ul>
                <hr>
                <p class="small text-muted">
                    By posting, you agree to our <a href="#">Community Guidelines</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('newTopicForm');
    
    form.addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        const category = document.getElementById('category_id').value;
        
        if (!category) {
            e.preventDefault();
            alert('Please select a category.');
            return;
        }
        
        if (title.length < 5) {
            e.preventDefault();
            alert('Title must be at least 5 characters long.');
            return;
        }
        
        if (content.length < 10) {
            e.preventDefault();
            alert('Content must be at least 10 characters long.');
            return;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>