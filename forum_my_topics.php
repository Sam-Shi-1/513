<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

// Include forum time functions
require_once '../includes/forum_time_functions.php';

$database = new Database();
$db = $database->getConnection();

$user_topics = [];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';

if ($db) {
    try {
        $query = "SELECT ft.*, fc.category_name,
                         (SELECT COUNT(*) FROM forum_replies WHERE topic_id = ft.topic_id) as reply_count
                  FROM forum_topics ft
                  LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                  WHERE ft.user_id = :user_id
                  ORDER BY ft.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $user_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Forum my topics error: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_topic_id'])) {
    $delete_topic_id = intval($_POST['delete_topic_id']);

    $can_delete = false;

    foreach ($user_topics as $topic) {
        if ($topic['topic_id'] == $delete_topic_id) {
            $can_delete = true;
            break;
        }
    }

    if (!$can_delete && $is_admin) {
        $check_query = "SELECT * FROM forum_topics WHERE topic_id = :topic_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':topic_id', $delete_topic_id);
        $check_stmt->execute();
        if ($check_stmt->rowCount() > 0) {
            $can_delete = true;
        }
    }
    
    if ($can_delete) {
        try {
            $db->beginTransaction();

            $get_category_query = "SELECT category_id FROM forum_topics WHERE topic_id = :topic_id";
            $get_category_stmt = $db->prepare($get_category_query);
            $get_category_stmt->bindParam(':topic_id', $delete_topic_id);
            $get_category_stmt->execute();
            $category_info = $get_category_stmt->fetch(PDO::FETCH_ASSOC);

            $delete_replies_query = "DELETE FROM forum_replies WHERE topic_id = :topic_id";
            $delete_replies_stmt = $db->prepare($delete_replies_query);
            $delete_replies_stmt->bindParam(':topic_id', $delete_topic_id);
            $delete_replies_stmt->execute();

            $delete_topic_query = "DELETE FROM forum_topics WHERE topic_id = :topic_id";
            $delete_topic_stmt = $db->prepare($delete_topic_query);
            $delete_topic_stmt->bindParam(':topic_id', $delete_topic_id);
            $delete_topic_stmt->execute();

            if ($category_info) {
                $update_category_query = "UPDATE forum_categories 
                                        SET topic_count = GREATEST(0, COALESCE(topic_count, 0) - 1)
                                        WHERE category_id = :category_id";
                $update_stmt = $db->prepare($update_category_query);
                $update_stmt->bindParam(':category_id', $category_info['category_id']);
                $update_stmt->execute();
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = "Topic deleted successfully!";
            header("Location: forum_my_topics.php");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error_message'] = "Failed to delete topic: " . $e->getMessage();
            header("Location: forum_my_topics.php");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete this topic.";
        header("Location: forum_my_topics.php");
        exit;
    }
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
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
                <form method="POST" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="delete_topic_id" id="delete_topic_id_input" value="">
                    <button type="submit" class="btn btn-danger">Delete Topic</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Topics</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="forum_index.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="fas fa-arrow-left"></i> Back to Forum
        </a>
        <a href="forum_new_topic.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus"></i> New Topic
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Topics I've Started</h5>
    </div>
    
    <?php if (empty($user_topics)): ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
        <h4>No topics yet</h4>
        <p class="text-muted">You haven't started any discussions yet.</p>
        <a href="forum_new_topic.php" class="btn btn-primary">Start Your First Topic</a>
    </div>
    <?php else: ?>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 40%;">Topic</th>
                    <th>Category</th>
                    <th class="text-center">Replies</th>
                    <th class="text-center">Views</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_topics as $topic): ?>
                <tr id="topic-<?php echo $topic['topic_id']; ?>">
                    <td>
                        <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>" class="text-decoration-none">
                            <strong><?php echo htmlspecialchars($topic['title']); ?></strong>
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-info"><?php echo htmlspecialchars($topic['category_name']); ?></span>
                    </td>
                    <td class="text-center"><?php echo $topic['reply_count']; ?></td>
                    <td class="text-center"><?php echo $topic['views']; ?></td>
                    <td class="small"><?php echo formatForumTime($topic['created_at'], 'M j, Y'); ?></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>" 
                               class="btn btn-outline-primary" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="forum_new_topic.php?edit=<?php echo $topic['topic_id']; ?>" 
                               class="btn btn-outline-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger delete-topic-btn" 
                                    data-topic-id="<?php echo $topic['topic_id']; ?>" 
                                    data-topic-title="<?php echo htmlspecialchars($topic['title']); ?>"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-topic-btn');
    const deleteTopicIdInput = document.getElementById('delete_topic_id_input');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteTopicModal'));
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const topicId = this.getAttribute('data-topic-id');
            const topicTitle = this.getAttribute('data-topic-title');

            const modalBody = document.querySelector('#deleteTopicModal .modal-body');
            modalBody.innerHTML = `
                Are you sure you want to delete the topic "<strong>${topicTitle}</strong>"? 
                This action cannot be undone.
                <br><br>
                <strong>Warning:</strong> All replies to this topic will also be deleted.
            `;

            deleteTopicIdInput.value = topicId;

            deleteModal.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>