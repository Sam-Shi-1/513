<?php
// forum_recent.php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

date_default_timezone_set('Asia/Shanghai');

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../includes/forum_time_functions.php';

$database = new Database();
$db = $database->getConnection();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5; 
$offset = ($page - 1) * $per_page;

$total_topics = 0;
$recent_topics = [];

if ($db) {
    try {
        $count_query = "SELECT COUNT(*) as total FROM forum_topics";
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute();
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_topics = $count_result['total'] ?? 0;

        $total_pages = ceil($total_topics / $per_page);

        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

        $recent_query = "SELECT 
                                ft.*, 
                                COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'Anonymous') as username, 
                                fc.category_name,
                                ft.replies as reply_count
                        FROM forum_topics ft 
                        LEFT JOIN wppw_fc_subscribers s ON ft.user_id = s.id
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                        ORDER BY ft.updated_at DESC 
                        LIMIT :limit OFFSET :offset";
        
        $recent_stmt = $db->prepare($recent_query);
        $recent_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $recent_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $recent_stmt->execute();
        $recent_topics = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Recent discussions database error: " . $e->getMessage());
        $error = "Failed to load recent discussions. Please try again later.";
    }
}
?>

<div class="forum-container">
    <div class="forum-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Recent Discussions</h1>
                <p class="subtitle">All topics sorted by latest activity</p>
            </div>
            <a href="forum_new_topic.php" class="btn btn-light">
                <i class="fas fa-plus me-2"></i>New Topic
            </a>
        </div>
    </div>

    <div class="forum-nav">
        <div class="forum-nav-links">
            <a href="forum_index.php" class="forum-nav-link">
                <i class="fas fa-home me-1"></i> Home
            </a>
            <a href="forum_new_topic.php" class="forum-nav-link">
                <i class="fas fa-plus me-1"></i> New Topic
            </a>
            <a href="forum_my_topics.php" class="forum-nav-link">
                <i class="fas fa-user me-1"></i> My Topics
            </a>
            <a href="forum_recent.php" class="forum-nav-link active">
                <i class="fas fa-clock me-1"></i> Recent Discussions
            </a>
            <a href="forum_search.php" class="forum-nav-link">
                <i class="fas fa-search me-1"></i> Search
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Recent Discussions</h4>
                        <?php if ($total_pages > 1): ?>
                            <span class="text-muted small">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_topics)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No discussions yet. Start the first one!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_topics as $topic): ?>
                            <div class="thread-card <?php echo ($topic['is_sticky'] ? 'sticky' : ''); ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="thread-title">
                                            <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>">
                                                <?php if ($topic['is_sticky']): ?>
                                                    <i class="fas fa-thumbtack text-warning me-2" title="Sticky"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($topic['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="thread-excerpt mb-2">
                                            <?php echo substr(strip_tags($topic['content']), 0, 150); ?>...
                                        </p>
                                        <div class="d-flex align-items-center">
                                            <a href="forum_category.php?id=<?php echo $topic['category_id']; ?>" class="category-tag me-3">
                                                <i class="fas fa-folder"></i> <?php echo htmlspecialchars($topic['category_name']); ?>
                                            </a>
                                            <div class="user-info">
                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($topic['username']); ?>&background=007bff&color=fff&size=32" 
                                                     class="user-avatar-small" alt="<?php echo htmlspecialchars($topic['username']); ?>">
                                                <div>
                                                    <div class="username"><?php echo htmlspecialchars($topic['username']); ?></div>
                                                    <div class="user-title">Member</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="thread-meta justify-content-md-end">
                                            <div class="thread-meta-item">
                                                <i class="fas fa-comment"></i>
                                                <span><?php echo $topic['reply_count']; ?> replies</span>
                                            </div>
                                            <div class="thread-meta-item">
                                                <i class="fas fa-eye"></i>
                                                <span><?php echo $topic['views']; ?> views</span>
                                            </div>
                                            <div class="thread-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo timeAgo($topic['updated_at']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif;
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor;
                                    
                                    if ($end_page < $total_pages): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>

                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>

                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_topics); ?> of <?php echo $total_topics; ?> discussions
                                    </small>
                                </div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Hot Topics This Week</h5>
                </div>
                <div class="card-body">
                    <?php
                    $popular_topics = [];
                    if ($db) {
                        try {
                            $popular_query = "SELECT ft.*, 
                                                    COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'Anonymous') as username, 
                                                    fc.category_name,
                                                    ft.replies as reply_count
                                            FROM forum_topics ft 
                                            LEFT JOIN wppw_fc_subscribers s ON ft.user_id = s.id
                                            LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                                            WHERE ft.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                            ORDER BY ft.replies DESC, ft.views DESC 
                                            LIMIT 5";
                            $popular_stmt = $db->prepare($popular_query);
                            $popular_stmt->execute();
                            $popular_topics = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                        }
                    }
                    ?>
                    
                    <?php if (empty($popular_topics)): ?>
                        <p class="text-muted text-center py-3">No popular topics this week</p>
                    <?php else: ?>
                        <ul class="hot-topic-list">
                            <?php foreach ($popular_topics as $index => $topic): ?>
                                <li>
                                    <div class="hot-topic-rank"><?php echo $index + 1; ?></div>
                                    <div>
                                        <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>" 
                                           class="text-decoration-none">
                                            <strong><?php echo htmlspecialchars($topic['title']); ?></strong>
                                        </a>
                                        <div class="small text-muted">
                                            <i class="fas fa-comment me-1"></i> <?php echo $topic['reply_count']; ?> replies
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Online Now</h5>
                </div>
                <div class="card-body">
                    <?php
                    $online_users = 0;
                    $online_users_list = [];
                    if ($db) {
                        try {
                            $online_query = "SELECT DISTINCT 
                                                    CONCAT(s.first_name, ' ', COALESCE(s.last_name, '')) as username 
                                            FROM forum_user_sessions us
                                            LEFT JOIN wppw_fc_subscribers s ON us.user_id = s.id
                                            WHERE us.last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                                            LIMIT 10";
                            $online_stmt = $db->prepare($online_query);
                            $online_stmt->execute();
                            $online_users_list = $online_stmt->fetchAll(PDO::FETCH_ASSOC);

                            $online_count_query = "SELECT COUNT(DISTINCT user_id) as online_count 
                                                  FROM forum_user_sessions 
                                                  WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
                            $online_count_stmt = $db->prepare($online_count_query);
                            $online_count_stmt->execute();
                            $online_count_result = $online_count_stmt->fetch(PDO::FETCH_ASSOC);
                            $online_users = $online_count_result['online_count'] ?? 0;
                        } catch (Exception $e) {
                        }
                    }
                    ?>
                    
                    <h6 class="text-muted">(<?php echo $online_users; ?> users online)</h6>
                    <ul class="online-user-list mt-3">
                        <?php if (empty($online_users_list)): ?>
                            <li class="text-muted">No users online</li>
                        <?php else: ?>
                            <?php foreach ($online_users_list as $user): ?>
                                <li>
                                    <span class="online-indicator"></span>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Forum Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Recent Discussions Page</strong>
                        <p class="small text-muted mt-1">
                            This page shows all forum topics sorted by their last update time. Topics with recent replies or edits will appear at the top.
                        </p>
                    </div>
                    <div class="mb-3">
                        <strong>Total Topics:</strong>
                        <p class="small text-muted mt-1 mb-0"><?php echo $total_topics; ?> discussions</p>
                    </div>
                    <div>
                        <strong>Quick Navigation:</strong>
                        <div class="d-grid gap-2 mt-2">
                            <a href="forum_new_topic.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>Start New Discussion
                            </a>
                            <a href="forum_index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-home me-2"></i>Forum Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php'; 
?>