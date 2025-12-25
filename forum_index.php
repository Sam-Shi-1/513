<?php
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

$total_topics = 0;
$total_posts = 0;
$total_members = 0;
$online_users = 0;

$categories = [];
$recent_topics = [];
$popular_topics = [];

if ($db) {
    try {
        $stats_query = "
            SELECT 
                (SELECT COUNT(*) FROM forum_topics) as total_topics,
                (SELECT COUNT(*) FROM forum_replies) as total_replies,
                (SELECT COUNT(DISTINCT user_id) FROM forum_topics) as total_members,
                (SELECT COUNT(DISTINCT user_id) FROM forum_user_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as online_users
        ";
        $stats_stmt = $db->query($stats_query);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_topics = $stats['total_topics'] ?? 0;
        $total_posts = $total_topics + ($stats['total_replies'] ?? 0);
        $total_members = $stats['total_members'] ?? 0;
        $online_users = $stats['online_users'] ?? 0;

        $category_query = "SELECT fc.*,
                        (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.category_id) as topic_count,
                        (SELECT COUNT(*) FROM forum_replies fr 
                        INNER JOIN forum_topics ft ON fr.topic_id = ft.topic_id 
                        WHERE ft.category_id = fc.category_id) as reply_count
                        FROM forum_categories fc
                        WHERE fc.is_active = 1
                        ORDER BY fc.category_order ASC";

        $category_stmt = $db->prepare($category_query);
        $category_stmt->execute();
        $categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

        $recent_query = "SELECT ft.*, 
                                COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'Anonymous') as username, 
                                fc.category_name,
                                ft.replies as reply_count
                        FROM forum_topics ft 
                        LEFT JOIN wppw_fc_subscribers s ON ft.user_id = s.id
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                        ORDER BY ft.created_at DESC 
                        LIMIT 10";
        $recent_stmt = $db->prepare($recent_query);
        $recent_stmt->execute();
        $recent_topics = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

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
        
    } catch (PDOException $e) {
        error_log("Forum database error: " . $e->getMessage());
    }
}
?>

<div class="forum-container">
    <div class="forum-header">
        <h1>Community Forum</h1>
        <p class="subtitle">Join discussions, share knowledge, and connect with other gamers</p>
        <div class="mt-4">
            <a href="forum_new_topic.php" class="btn btn-light btn-lg">
                <i class="fas fa-plus me-2"></i>Start New Discussion
            </a>
        </div>
    </div>

    <div class="forum-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_topics; ?></div>
            <div class="stat-label">Total Topics</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_posts; ?></div>
            <div class="stat-label">Total Posts</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_members; ?></div>
            <div class="stat-label">Community Members</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $online_users; ?></div>
            <div class="stat-label">Online Now</div>
        </div>
    </div>

    <div class="forum-nav">
        <div class="forum-nav-links">
            <a href="forum_index.php" class="forum-nav-link active">
                <i class="fas fa-home me-1"></i> Home
            </a>
            <a href="forum_new_topic.php" class="forum-nav-link">
                <i class="fas fa-plus me-1"></i> New Topic
            </a>
            <a href="forum_my_topics.php" class="forum-nav-link">
                <i class="fas fa-user me-1"></i> My Topics
            </a>
            <a href="forum_recent.php" class="forum-nav-link">
                <i class="fas fa-clock me-1"></i> Recent Discussions
            </a>
            <a href="forum_search.php" class="forum-nav-link">
                <i class="fas fa-search me-1"></i> Search
            </a>
        </div>
        <div class="forum-actions">
            <button class="btn btn-outline-primary btn-sm" onclick="markAllRead()">
                <i class="fas fa-check-double me-1"></i> Mark All Read
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="quick-actions mb-4">
                <a href="forum_new_topic.php" class="quick-action-btn">
                    <i class="fas fa-comment-medical"></i>
                    <div class="btn-label">Start Discussion</div>
                    <div class="btn-desc">Ask questions or share thoughts</div>
                </a>
                <a href="forum_search.php" class="quick-action-btn">
                    <i class="fas fa-search"></i>
                    <div class="btn-label">Search Forum</div>
                    <div class="btn-desc">Find specific discussions</div>
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0">Forum Categories</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5>No categories available</h5>
                            <p class="text-muted">Forum categories will be set up by administrators.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="category-icon me-3">
                                                <i class="fas fa-comments"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($category['category_name']); ?></h5>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($category['category_description']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex flex-column align-items-md-end">
                                            <div class="mb-2">
                                                <span class="badge bg-light text-dark me-2">
                                                    <i class="fas fa-comment me-1"></i> <?php echo $category['topic_count']; ?> topics
                                                </span>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-reply me-1"></i> <?php echo $category['reply_count']; ?> replies
                                                </span>
                                            </div>
                                            <a href="forum_category.php?id=<?php echo $category['category_id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                View Category <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="hot-topics mb-4">
                <h4>Hot Topics</h4>
                <?php if (empty($popular_topics)): ?>
                    <p class="text-muted text-center py-3">No popular topics yet</p>
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

            <div class="online-users mb-4">
                <h5>Online Now (<?php echo $online_users; ?>)</h5>
                <ul class="online-user-list">
                    <?php
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
                        } catch (Exception $e) {
                        }
                    }
                    ?>
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

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Forum Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Welcome to our Community!</strong>
                        <p class="small text-muted mt-1">
                            This is a place for gamers to discuss games, share tips, and connect with fellow players.
                        </p>
                    </div>
                    <div class="mb-3">
                        <strong>Forum Rules:</strong>
                        <ul class="small text-muted mt-1 mb-0 ps-3">
                            <li>Be respectful to others</li>
                            <li>No spamming or advertising</li>
                            <li>Keep discussions on-topic</li>
                            <li>No inappropriate content</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function markAllRead() {
    showToast('All discussions marked as read', 'success');
}

function showToast(message, type = 'info') {
    const existingToast = document.getElementById('forum-toast');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.id = 'forum-toast';
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 250px;
    `;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php 
include '../includes/footer.php'; 
?>