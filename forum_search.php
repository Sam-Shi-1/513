<?php
// forum_search.php
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

$search_query = '';
$search_results = [];
$total_results = 0;
$has_search = false;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 10;

if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $search_query = trim($_GET['q']);
    $has_search = true;

    $offset = ($current_page - 1) * $results_per_page;
    
    if ($db) {
        try { 
            $count_query = "SELECT COUNT(*) as total 
                           FROM forum_topics ft 
                           LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                           WHERE ft.title LIKE :search_query 
                           OR ft.content LIKE :search_query 
                           OR fc.category_name LIKE :search_query";
            
            $count_stmt = $db->prepare($count_query);
            $search_param = '%' . $search_query . '%';
            $count_stmt->bindParam(':search_query', $search_param);
            $count_stmt->execute();
            $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $total_results = $count_result['total'] ?? 0;

            $total_pages = ceil($total_results / $results_per_page);

            if ($current_page < 1) $current_page = 1;
            if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

            $search_sql = "SELECT 
                                ft.*, 
                                COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'Anonymous') as username, 
                                fc.category_name,
                                ft.replies as reply_count,
                                CASE 
                                    WHEN ft.title LIKE :search_query1 THEN 3
                                    WHEN ft.content LIKE :search_query2 THEN 2
                                    WHEN fc.category_name LIKE :search_query3 THEN 1
                                    ELSE 0
                                END as relevance_score
                        FROM forum_topics ft 
                        LEFT JOIN wppw_fc_subscribers s ON ft.user_id = s.id
                        LEFT JOIN forum_categories fc ON ft.category_id = fc.category_id
                        WHERE ft.title LIKE :search_query4 
                           OR ft.content LIKE :search_query5 
                           OR fc.category_name LIKE :search_query6
                        ORDER BY relevance_score DESC, ft.created_at DESC 
                        LIMIT :limit OFFSET :offset";
            
            $search_stmt = $db->prepare($search_sql);
            $search_param = '%' . $search_query . '%';

            $search_stmt->bindParam(':search_query1', $search_param);
            $search_stmt->bindParam(':search_query2', $search_param);
            $search_stmt->bindParam(':search_query3', $search_param);
            $search_stmt->bindParam(':search_query4', $search_param);
            $search_stmt->bindParam(':search_query5', $search_param);
            $search_stmt->bindParam(':search_query6', $search_param);
            $search_stmt->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
            $search_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $search_stmt->execute();
            
            $search_results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Search database error: " . $e->getMessage());
            $error = "Failed to perform search. Please try again later.";
        }
    }
}
?>

<div class="forum-container">
    <div class="forum-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Search Forum</h1>
                <p class="subtitle">Find discussions by title, content, or category</p>
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
            <a href="forum_recent.php" class="forum-nav-link">
                <i class="fas fa-clock me-1"></i> Recent Discussions
            </a>
            <a href="forum_search.php" class="forum-nav-link active">
                <i class="fas fa-search me-1"></i> Search
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <form action="forum_search.php" method="GET" class="search-form">
                        <div class="input-group">
                            <input type="text" 
                                   name="q" 
                                   class="form-control form-control-lg" 
                                   placeholder="Search discussions, topics, or categories..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   required>
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Search in titles, content, and category names
                            </small>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($has_search): ?>
                <div class="card">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                                <?php if ($total_results > 0): ?>
                                    <span class="badge bg-primary ms-2"><?php echo $total_results; ?> results</span>
                                <?php endif; ?>
                            </h4>
                            <?php if ($total_results > 0 && isset($total_pages) && $total_pages > 1): ?>
                                <span class="text-muted small">
                                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($total_results == 0): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5>No results found</h5>
                                <p class="text-muted">Try different keywords or browse categories</p>
                                <div class="mt-4">
                                    <a href="forum_categories.php" class="btn btn-outline-primary me-2">
                                        <i class="fas fa-th-large me-2"></i>Browse Categories
                                    </a>
                                    <a href="forum_recent.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-clock me-2"></i>View Recent Discussions
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($search_results as $topic): ?>
                                <div class="thread-card <?php echo ($topic['is_sticky'] ? 'sticky' : ''); ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="thread-title">
                                                <a href="forum_topic.php?id=<?php echo $topic['topic_id']; ?>">
                                                    <?php if ($topic['is_sticky']): ?>
                                                        <i class="fas fa-thumbtack text-warning me-2" title="Sticky"></i>
                                                    <?php endif; ?>
                                                    <?php 
                                                        $highlighted_title = preg_replace(
                                                            '/(' . preg_quote($search_query, '/') . ')/i',
                                                            '<span class="bg-warning text-dark px-1 rounded">$1</span>',
                                                            htmlspecialchars($topic['title'])
                                                        );
                                                        echo $highlighted_title;
                                                    ?>
                                                </a>
                                            </h5>
                                            <p class="thread-excerpt mb-2">
                                                <?php 
                                                    $content = strip_tags($topic['content']);
                                                    $content = preg_replace(
                                                        '/(' . preg_quote($search_query, '/') . ')/i',
                                                        '<span class="bg-warning text-dark px-1 rounded">$1</span>',
                                                        htmlspecialchars(substr($content, 0, 200))
                                                    );
                                                    echo $content . '...';
                                                ?>
                                            </p>
                                            <div class="d-flex align-items-center">
                                                <a href="forum_category.php?id=<?php echo $topic['category_id']; ?>" 
                                                   class="category-tag me-3">
                                                    <i class="fas fa-folder"></i> 
                                                    <?php 
                                                        $highlighted_category = preg_replace(
                                                            '/(' . preg_quote($search_query, '/') . ')/i',
                                                            '<span class="bg-warning text-dark px-1 rounded">$1</span>',
                                                            htmlspecialchars($topic['category_name'])
                                                        );
                                                        echo $highlighted_category;
                                                    ?>
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
                                                    <span><?php echo timeAgo($topic['created_at']); ?></span>
                                                </div>
                                                <?php if ($topic['relevance_score'] > 0): ?>
                                                    <div class="thread-meta-item">
                                                        <i class="fas fa-signal"></i>
                                                        <span>
                                                            <?php 
                                                                if ($topic['relevance_score'] == 3) echo 'Title match';
                                                                elseif ($topic['relevance_score'] == 2) echo 'Content match';
                                                                else echo 'Category match';
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Search results pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" 
                                               href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page - 1; ?>" 
                                               aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>

                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        if ($start_page > 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif;
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" 
                                                   href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor;
                                        
                                        if ($end_page < $total_pages): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>

                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" 
                                               href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page + 1; ?>" 
                                               aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>

                                    <div class="text-center mt-2">
                                        <small class="text-muted">
                                            Showing <?php echo min($offset + 1, $total_results); ?>-<?php echo min($offset + $results_per_page, $total_results); ?> 
                                            of <?php echo $total_results; ?> results
                                        </small>
                                    </div>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-0">Search Tips</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <h6><i class="fas fa-lightbulb text-warning me-2"></i>How to Search</h6>
                                <ul class="text-muted small">
                                    <li>Type keywords related to your topic</li>
                                    <li>Search in titles, content, and categories</li>
                                    <li>Results are sorted by relevance</li>
                                    <li>Use specific terms for better results</li>
                                </ul>
                            </div>
                            <div class="col-md-6 mb-4">
                                <h6><i class="fas fa-fire text-danger me-2"></i>Popular Searches</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="?q=GameVault" class="badge bg-light text-dark p-2 text-decoration-none">GameVault</a>
                                    <a href="?q=CD+Key" class="badge bg-light text-dark p-2 text-decoration-none">CD Key</a>
                                    <a href="?q=gaming" class="badge bg-light text-dark p-2 text-decoration-none">gaming</a>
                                    <a href="?q=support" class="badge bg-light text-dark p-2 text-decoration-none">support</a>
                                    <a href="?q=community" class="badge bg-light text-dark p-2 text-decoration-none">community</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>Ready to Search?</h5>
                            <p class="text-muted">Enter your search terms in the box above to find discussions</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Search Stats</h5>
                </div>
                <div class="card-body">
                    <?php if ($has_search): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Search Query:</span>
                                <strong><?php echo htmlspecialchars($search_query); ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Results Found:</span>
                                <strong><?php echo $total_results; ?></strong>
                            </div>
                        </div>
                        <?php if ($total_results > 0): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Current Page:</span>
                                    <strong><?php echo $current_page; ?> of <?php echo $total_pages ?? 1; ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Enter a search query to see statistics</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="forum_new_topic.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i>Start New Discussion
                        </a>
                        <a href="forum_recent.php" class="btn btn-outline-secondary">
                            <i class="fas fa-clock me-2"></i>Recent Discussions
                        </a>
                        <a href="forum_my_topics.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user me-2"></i>My Topics
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Search Tips</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><i class="fas fa-star text-warning me-2"></i>Best Practices:</strong>
                        <ul class="small text-muted mt-2 mb-0 ps-3">
                            <li>Use specific keywords</li>
                            <li>Try different variations</li>
                            <li>Check spelling</li>
                            <li>Use quotes for exact phrases</li>
                        </ul>
                    </div>
                    <div>
                        <strong><i class="fas fa-history text-info me-2"></i>Recent Searches:</strong>
                        <div class="mt-2">
                            <small class="text-muted">No recent searches yet</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Need Help?</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Can't find what you're looking for? Try browsing categories or start a new discussion.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="forum_new_topic.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>Ask a Question
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.focus();

        if (searchInput.value) {
            searchInput.select();
        }
    }

    document.querySelectorAll('.badge.bg-light').forEach(function(badge) {
        badge.addEventListener('click', function(e) {
            e.preventDefault();
            const searchTerm = this.textContent.trim();
            const searchForm = document.querySelector('.search-form');
            const searchInput = searchForm.querySelector('input[name="q"]');
            searchInput.value = searchTerm;
            searchForm.submit();
        });
    });
});
</script>

<?php 
include '../includes/footer.php'; 
?>