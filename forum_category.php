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

$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    header("Location: forum_index.php");
    exit;
}

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;

$database = new Database();
$db = $database->getConnection();

$topics_per_page = 5;

$category = [];
$topics = [];
$total_topics = 0;
$total_pages = 1;

if ($db) {
    try {
        $category_query = "SELECT * FROM forum_categories WHERE category_id = ? AND is_active = 1";
        $category_stmt = $db->prepare($category_query);
        $category_stmt->execute([$category_id]);
        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            echo "<div class='alert alert-danger'>Category not found or inactive.</div>";
            include '../includes/footer.php';
            exit;
        }

        $count_query = "SELECT COUNT(*) as total 
                       FROM forum_topics ft 
                       WHERE ft.category_id = ?";
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute([$category_id]);
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_topics = $count_result['total'] ?? 0;

        $total_pages = ceil($total_topics / $topics_per_page);

        if ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $topics_per_page;

        $topics_query = "SELECT ft.*, 
                                CONCAT(s.first_name, ' ', COALESCE(s.last_name, '')) as username,
                                fc.category_name,
                                COUNT(fr.reply_id) as reply_count,
                                (SELECT created_at FROM forum_replies 
                                 WHERE topic_id = ft.topic_id 
                                 ORDER BY created_at DESC LIMIT 1) as last_reply_time
                         FROM forum_topics ft 
                         LEFT JOIN wppw_fc_subscribers s ON ft.user_id = s.id
                         LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                         LEFT JOIN forum_replies fr ON ft.topic_id = fr.topic_id
                         WHERE ft.category_id = ?
                         GROUP BY ft.topic_id
                         ORDER BY ft.is_sticky DESC, ft.created_at DESC
                         LIMIT ? OFFSET ?";
        
        $topics_stmt = $db->prepare($topics_query);
        $topics_stmt->bindValue(1, $category_id, PDO::PARAM_INT);
        $topics_stmt->bindValue(2, $topics_per_page, PDO::PARAM_INT);
        $topics_stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $topics_stmt->execute();
        $topics = $topics_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Category page database error: " . $e->getMessage());
    }
}
?>

<div class="forum-container">
    <div class="category-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo htmlspecialchars($category['category_name']); ?></h1>
                <div class="category-description">
                    <?php echo htmlspecialchars($category['category_description']); ?>
                </div>
            </div>
            <div>
                <a href="forum_new_topic.php?category_id=<?php echo $category_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Topic
                </a>
            </div>
        </div>

        <div class="category-stats-container mt-3">
            <div class="category-stat-card">
                <span class="stat-number"><?php echo $total_topics; ?></span>
                <span class="stat-label">Total Topics</span>
            </div>
            <div class="category-stat-card">
                <span class="stat-number"><?php echo $category['member_count'] ?? 0; ?></span>
                <span class="stat-label">Members</span>
            </div>
            <div class="category-stat-card">
                <span class="stat-number"><?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                <span class="stat-label">Current Page</span>
            </div>
            <div class="category-stat-card">
                <span class="stat-number"><?php echo count($topics); ?></span>
                <span class="stat-label">This Page</span>
            </div>
        </div>
    </div>

    <div class="forum-nav mt-3">
        <div class="forum-nav-links">
            <a href="forum_index.php" class="forum-nav-link">
                <i class="fas fa-home me-1"></i> Home
            </a>
            <a href="forum_new_topic.php?category_id=<?php echo $category_id; ?>" class="forum-nav-link">
                <i class="fas fa-plus me-1"></i> New Topic
            </a>
            <a href="forum_my_topics.php" class="forum-nav-link">
                <i class="fas fa-user me-1"></i> My Topics
            </a>
            <a href="forum_recent.php" class="forum-nav-link">
                <i class="fas fa-clock me-1"></i> Recent
            </a>
            <a href="forum_search.php" class="forum-nav-link">
                <i class="fas fa-search me-1"></i> Search
            </a>
        </div>
        <div class="forum-actions sort-dropdown">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-sort"></i> Sort by
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?id=<?php echo $category_id; ?>&sort=latest">Latest</a></li>
                    <li><a class="dropdown-item" href="?id=<?php echo $category_id; ?>&sort=replies">Most Replies</a></li>
                    <li><a class="dropdown-item" href="?id=<?php echo $category_id; ?>&sort=views">Most Views</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="topic-list-container">
        <div class="topic-list-header">
            <div class="row">
                <div class="col-md-6">Topic</div>
                <div class="col-md-2 text-center">Replies</div>
                <div class="col-md-2 text-center">Views</div>
                <div class="col-md-2">Last Post</div>
            </div>
        </div>

        <?php if (empty($topics)): ?>
            <div class="topic-empty-state">
                <i class="fas fa-comments"></i>
                <h4>No topics yet in this category</h4>
                <p>Be the first to start a discussion in <?php echo htmlspecialchars($category['category_name']); ?>!</p>
                <a href="forum_new_topic.php?category_id=<?php echo $category_id; ?>" class="btn-new-topic">
                    <i class="fas fa-plus me-2"></i>Create First Topic
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($topics as $topic): ?>
                <div class="topic-list-item <?php echo $topic['is_sticky'] ? 'sticky' : ''; ?> <?php echo $topic['is_locked'] ? 'locked' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="topic-status me-3">
                                    <?php if ($topic['is_sticky']): ?>
                                        <i class="fas fa-thumbtack" title="Sticky Topic"></i>
                                    <?php elseif ($topic['is_locked']): ?>
                                        <i class="fas fa-lock" title="Locked Topic"></i>
                                    <?php else: ?>
                                        <i class="fas fa-comment"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="topic-title mb-1">
                                        <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($topic['title']); ?>
                                        </a>
                                    </h5>
                                    <div class="topic-meta">
                                        <span class="me-3">
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($topic['username']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo timeAgo($topic['created_at']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="topic-replies">
                                <span class="count"><?php echo $topic['reply_count']; ?></span>
                                <small class="text-muted d-block">replies</small>
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="topic-views">
                                <span class="count"><?php echo $topic['views']; ?></span>
                                <small class="text-muted d-block">views</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="topic-last-post">
                                <?php if ($topic['last_reply_time']): ?>
                                    <div class="last-time"><?php echo timeAgo($topic['last_reply_time']); ?></div>
                                    <small class="text-muted">by <?php echo htmlspecialchars($topic['username']); ?></small>
                                <?php else: ?>
                                    <div class="last-time"><?php echo timeAgo($topic['created_at']); ?></div>
                                    <small class="text-muted">No replies yet</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="forum-pagination">
            <nav aria-label="Topic pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?id=<?php echo $category_id; ?>&page=<?php echo $current_page - 1; ?>" 
                           aria-label="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $category_id; ?>&page=1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif;
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor;
                    
                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?id=<?php echo $category_id; ?>&page=<?php echo $current_page + 1; ?>" 
                           aria-label="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="text-center mt-3">
                <small class="text-muted">
                    Showing <?php echo min(($current_page - 1) * $topics_per_page + 1, $total_topics); ?> 
                    to <?php echo min($current_page * $topics_per_page, $total_topics); ?> 
                    of <?php echo $total_topics; ?> topics
                    (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
                </small>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.topic-list-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.parentElement.tagName === 'A') {
                return;
            }
            const topicLink = this.querySelector('.topic-title a');
            if (topicLink) {
                window.location.href = topicLink.href;
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight' && document.querySelector('.page-item:not(.disabled) .fa-chevron-right')) {
            const nextLink = document.querySelector('.page-item:not(.disabled) .fa-chevron-right').parentElement;
            if (nextLink) window.location.href = nextLink.href;
        } else if (e.key === 'ArrowLeft' && document.querySelector('.page-item:not(.disabled) .fa-chevron-left')) {
            const prevLink = document.querySelector('.page-item:not(.disabled) .fa-chevron-left').parentElement;
            if (prevLink) window.location.href = prevLink.href;
        }
    });
});
</script>

<?php 
include '../includes/footer.php'; 
?>