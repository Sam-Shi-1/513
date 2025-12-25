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

if (!isset($_GET['id'])) {
    header("Location: forum_index.php");
    exit;
}

$topic_id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();

$topic = null;
$replies = [];
$error = '';
$success = '';
$is_owner = false;
$is_admin = false;

if ($db) {
    try {
        $topic_query = "UPDATE forum_topics SET views = views + 1 WHERE topic_id = :topic_id";
        $update_stmt = $db->prepare($topic_query);
        $update_stmt->bindParam(':topic_id', $topic_id);
        $update_stmt->execute();

        $topic_query = "SELECT ft.*, fc.category_name, 
                            CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as username,
                            (SELECT COUNT(*) FROM forum_replies WHERE topic_id = ft.topic_id) as reply_count
                        FROM forum_topics ft
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                        LEFT JOIN wppw_fc_subscribers u ON ft.user_id = u.user_id
                        WHERE ft.topic_id = :topic_id";
        $topic_stmt = $db->prepare($topic_query);
        $topic_stmt->bindParam(':topic_id', $topic_id);
        $topic_stmt->execute();
        
        if ($topic_stmt->rowCount() > 0) {
            $topic = $topic_stmt->fetch(PDO::FETCH_ASSOC);

            $is_owner = ($_SESSION['user_id'] == $topic['user_id']);

            $is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

            $reply_query = "SELECT fr.*, CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as username 
                        FROM forum_replies fr
                        LEFT JOIN wppw_fc_subscribers u ON fr.user_id = u.user_id
                        WHERE fr.topic_id = :topic_id
                        ORDER BY fr.created_at ASC";
            $reply_stmt = $db->prepare($reply_query);
            $reply_stmt->bindParam(':topic_id', $topic_id);
            $reply_stmt->execute();
            $replies = $reply_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            header("Location: forum_index.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_topic'])) {
    if ($is_owner || $is_admin) {
        try {
            $db->beginTransaction();

            $delete_replies_query = "DELETE FROM forum_replies WHERE topic_id = :topic_id";
            $delete_replies_stmt = $db->prepare($delete_replies_query);
            $delete_replies_stmt->bindParam(':topic_id', $topic_id);
            $delete_replies_stmt->execute();

            $delete_topic_query = "DELETE FROM forum_topics WHERE topic_id = :topic_id";
            $delete_topic_stmt = $db->prepare($delete_topic_query);
            $delete_topic_stmt->bindParam(':topic_id', $topic_id);
            $delete_topic_stmt->execute();

            $update_category_query = "UPDATE forum_categories 
                                    SET topic_count = GREATEST(0, COALESCE(topic_count, 0) - 1)
                                    WHERE category_id = :category_id";
            $update_stmt = $db->prepare($update_category_query);
            $update_stmt->bindParam(':category_id', $topic['category_id']);
            $update_stmt->execute();
            
            $db->commit();
            
            $_SESSION['success_message'] = "Topic deleted successfully!";
            header("Location: forum_index.php");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Failed to delete topic: " . $e->getMessage();
        }
    } else {
        $error = "You don't have permission to delete this topic.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_content'])) {
    $reply_content = trim($_POST['reply_content']);
    
    if (empty($reply_content)) {
        $error = "Reply content cannot be empty.";
    } elseif ($topic['is_locked']) {
        $error = "This topic is locked and cannot be replied to.";
    } else {
        try {
            $db->beginTransaction();

            $current_china_time = getChinaTime();

            $insert_reply = "INSERT INTO forum_replies (topic_id, user_id, content, created_at) 
                            VALUES (:topic_id, :user_id, :content, :created_at)";
            $reply_stmt = $db->prepare($insert_reply);
            $reply_stmt->bindParam(':topic_id', $topic_id);
            $reply_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $reply_stmt->bindParam(':content', $reply_content);
            $reply_stmt->bindParam(':created_at', $current_china_time);
            $reply_stmt->execute();

            $update_topic = "UPDATE forum_topics 
                            SET replies = replies + 1, 
                                updated_at = :updated_at 
                            WHERE topic_id = :topic_id";
            $update_stmt = $db->prepare($update_topic);
            $update_stmt->bindParam(':updated_at', $current_china_time);
            $update_stmt->bindParam(':topic_id', $topic_id);
            $update_stmt->execute();

            $update_category = "UPDATE forum_categories 
                            SET reply_count = COALESCE(reply_count, 0) + 1,
                                last_activity = :last_activity
                            WHERE category_id = (SELECT category_id FROM forum_topics WHERE topic_id = :topic_id)";
            $update_cat_stmt = $db->prepare($update_category);
            $update_cat_stmt->bindParam(':last_activity', $current_china_time);
            $update_cat_stmt->bindParam(':topic_id', $topic_id);
            $update_cat_stmt->execute();
            
            $db->commit();
            
            $success = "Reply posted successfully!";
            header("Location: forum_topic.php?id=" . $topic_id);
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Failed to post reply: " . $e->getMessage();
        }
    }
}
?>

<div class="modal fade" id="deleteTopicModal" tabindex="-1" aria-labelledby="deleteTopicModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTopicModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this topic? This action cannot be undone.
                <br><br>
                <strong>Warning:</strong> All replies to this topic will also be deleted.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_topic" value="1">
                    <button type="submit" class="btn btn-danger">Delete Topic</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo htmlspecialchars($topic['title'] ?? 'Topic'); ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="forum_index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Forum
        </a>
        <?php if ($is_owner || $is_admin): ?>
        <button type="button" class="btn btn-sm btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteTopicModal">
            <i class="fas fa-trash"></i> Delete Topic
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-info"><?php echo htmlspecialchars($topic['category_name'] ?? ''); ?></span>
                <?php if ($topic['is_sticky'] ?? false): ?>
                    <span class="badge bg-warning">Sticky</span>
                <?php endif; ?>
                <?php if ($topic['is_locked'] ?? false): ?>
                    <span class="badge bg-danger">Locked</span>
                <?php endif; ?>
            </div>
            <small class="text-muted">
                Posted by <?php echo htmlspecialchars($topic['username'] ?? 'User'); ?> 
                on <?php echo formatForumTime($topic['created_at']); ?>
            </small>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <?php echo nl2br(htmlspecialchars($topic['content'] ?? '')); ?>
        </div>
        <div class="small text-muted">
            <i class="fas fa-eye"></i> <?php echo $topic['views'] ?? 0; ?> views • 
            <i class="fas fa-comment"></i> <?php echo $topic['reply_count'] ?? 0; ?> replies
        </div>
    </div>
</div>

<?php if (!empty($replies)): ?>
<div class="mb-4">
    <h4>Replies (<?php echo count($replies); ?>)</h4>
    <?php foreach ($replies as $reply): ?>
    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <strong><?php echo htmlspecialchars($reply['username'] ?? 'User'); ?></strong>
                <small class="text-muted">
                    <?php echo formatForumTime($reply['created_at']); ?>
                </small>
            </div>
        </div>
        <div class="card-body">
            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!($topic['is_locked'] ?? false)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Post a Reply</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="replyForm">
            <div class="mb-3">
                <label for="reply_content" class="form-label">Your Reply *</label>
                <textarea class="form-control" id="reply_content" name="reply_content" 
                          rows="5" required></textarea>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning">
    <i class="fas fa-lock"></i> This topic is locked and no longer accepts new replies.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>