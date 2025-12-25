<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: careers.php');
    exit();
}

$position_id = intval($_GET['id']);

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = "SELECT p.*, d.icon_class FROM career_positions p 
          LEFT JOIN career_departments d ON p.department = d.name 
          WHERE p.id = ? AND p.is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute([$position_id]);
$position = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$position) {
    echo '<div class="container py-5">
            <div class="alert alert-danger">
                Position not found or no longer available.
            </div>
            <a href="careers.php" class="btn btn-primary">Back to Careers</a>
          </div>';
    require_once 'footer.php';
    exit();
}

$updateQuery = "UPDATE career_positions SET application_count = application_count + 1 WHERE id = ?";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->execute([$position_id]);
?>

<div class="container py-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="careers.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>Return to the recruitment page
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($position['title']); ?></li>
                </ol>
            </nav>
            
            <!-- Job Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="<?php echo htmlspecialchars($position['icon_class']); ?> fa-3x text-primary"></i>
                                </div>
                                <div>
                                    <h1 class="h2 mb-2"><?php echo htmlspecialchars($position['title']); ?></h1>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($position['department']); ?>
                                        </span>
                                        <span class="badge bg-success">
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($position['location']); ?>
                                        </span>
                                        <span class="badge bg-info">
                                            <i class="fas fa-briefcase me-1"></i><?php echo ucwords(str_replace('-', ' ', $position['type'])); ?>
                                        </span>
                                        <?php if($position['experience_level']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-signal me-1"></i><?php echo ucfirst($position['experience_level']); ?> Level
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex flex-column gap-2">
                                <?php if($position['salary_range']): ?>
                                    <div class="h4 text-success mb-2"><?php echo htmlspecialchars($position['salary_range']); ?></div>
                                <?php endif; ?>
                                <div>
                                    <a href="apply.php?position_id=<?php echo $position['id']; ?>" class="btn btn-success btn-lg px-4">
                                        <i class="fas fa-paper-plane me-2"></i>Apply Now
                                    </a>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-eye me-1"></i><?php echo $position['application_count']; ?> applications received
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Job Details -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <h3 class="h4 mb-3"><i class="fas fa-align-left me-2 text-primary"></i>Job Description</h3>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($position['description'])); ?>
                        </div>
                    </div>
                    
                    <?php if($position['responsibilities']): ?>
                    <div class="mb-4">
                        <h3 class="h4 mb-3"><i class="fas fa-tasks me-2 text-primary"></i>Key Responsibilities</h3>
                        <ul class="list-group list-group-flush">
                            <?php 
                            $responsibilities = explode("\n", $position['responsibilities']);
                            foreach($responsibilities as $resp):
                                if(trim($resp)): ?>
                                    <li class="list-group-item border-0 px-0">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php echo htmlspecialchars(trim($resp)); ?>
                                    </li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($position['requirements']): ?>
                    <div class="mb-4">
                        <h3 class="h4 mb-3"><i class="fas fa-graduation-cap me-2 text-primary"></i>Requirements</h3>
                        <ul class="list-group list-group-flush">
                            <?php 
                            $requirements = explode("\n", $position['requirements']);
                            foreach($requirements as $req):
                                if(trim($req)): ?>
                                    <li class="list-group-item border-0 px-0">
                                        <i class="fas fa-asterisk text-info me-2"></i>
                                        <?php echo htmlspecialchars(trim($req)); ?>
                                    </li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($position['benefits']): ?>
                    <div class="mb-4">
                        <h3 class="h4 mb-3"><i class="fas fa-gift me-2 text-primary"></i>Benefits & Perks</h3>
                        <div class="row">
                            <?php 
                            $benefits = explode("\n", $position['benefits']);
                            foreach($benefits as $benefit):
                                if(trim($benefit)): ?>
                                    <div class="col-md-6 mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo htmlspecialchars(trim($benefit)); ?>
                                    </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 20px;">
                <!-- Apply Box -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h4 class="h5 mb-3">Ready to Apply?</h4>
                        <p class="text-muted mb-4">Submit your application and join our team!</p>
                        <a href="apply.php?position_id=<?php echo $position['id']; ?>" class="btn btn-success w-100 mb-3">
                            <i class="fas fa-paper-plane me-2"></i>Apply for this Position
                        </a>
                        <div class="text-center">
                            <small class="text-muted">Applications close: 
                                <?php if($position['closing_date']): ?>
                                    <?php echo date('M d, Y', strtotime($position['closing_date'])); ?>
                                <?php else: ?>
                                    Rolling basis
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Job Summary -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h4 class="h5 mb-3">Job Details</h4>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                <strong>Posted:</strong> <?php echo date('M d, Y', strtotime($position['posted_date'])); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-users text-primary me-2"></i>
                                <strong>Positions:</strong> <?php echo $position['open_positions']; ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-briefcase text-primary me-2"></i>
                                <strong>Type:</strong> <?php echo ucwords(str_replace('-', ' ', $position['type'])); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                <strong>Location:</strong> <?php echo htmlspecialchars($position['location']); ?>
                            </li>
                            <?php if($position['education_requirement']): ?>
                            <li class="mb-2">
                                <i class="fas fa-graduation-cap text-primary me-2"></i>
                                <strong>Education:</strong> <?php echo htmlspecialchars($position['education_requirement']); ?>
                            </li>
                            <?php endif; ?>
                            <?php if($position['experience_level']): ?>
                            <li class="mb-2">
                                <i class="fas fa-signal text-primary me-2"></i>
                                <strong>Experience:</strong> <?php echo ucfirst($position['experience_level']); ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Share Job -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="h5 mb-3">Share This Job</h4>
                        <div class="d-flex gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Check out this job: ' . $position['title']); ?>" 
                               target="_blank" class="btn btn-outline-info btn-sm flex-fill">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&title=<?php echo urlencode($position['title']); ?>" 
                               target="_blank" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode('Job Opportunity: ' . $position['title']); ?>&body=<?php echo urlencode('Check out this job opportunity: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               class="btn btn-outline-danger btn-sm flex-fill">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>