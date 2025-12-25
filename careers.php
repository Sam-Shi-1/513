<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = "SELECT p.*, d.icon_class FROM career_positions p 
          LEFT JOIN career_departments d ON p.department = d.name 
          WHERE p.is_active = 1
          ORDER BY p.posted_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();

$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deptQuery = "SELECT DISTINCT department FROM career_positions WHERE is_active = 1";
$deptStmt = $db->prepare($deptQuery);
$deptStmt->execute();
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

$locationQuery = "SELECT DISTINCT location FROM career_positions WHERE is_active = 1";
$locationStmt = $db->prepare($locationQuery);
$locationStmt->execute();
$locations = $locationStmt->fetchAll(PDO::FETCH_COLUMN);

$typeQuery = "SELECT DISTINCT type FROM career_positions WHERE is_active = 1";
$typeStmt = $db->prepare($typeQuery);
$typeStmt->execute();
$types = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-5">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center">
            <h1 class="display-4 fw-bold mb-3">Join Our Team</h1>
            <p class="lead mb-4">Help us shape the future of gaming. We're looking for passionate individuals to join our growing team.</p>
            <div class="row g-3 justify-content-center">
                <div class="col-auto">
                    <span class="badge bg-primary fs-6 p-2"><i class="fas fa-users me-2"></i><?php echo count($positions); ?> Open Positions</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-success fs-6 p-2"><i class="fas fa-globe me-2"></i>Remote & On-site</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-info fs-6 p-2"><i class="fas fa-graduation-cap me-2"></i>Growth Opportunities</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>Filter Positions</h5>
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="departmentFilter" class="form-label">Department</label>
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="locationFilter" class="form-label">Location</label>
                            <select class="form-select" id="locationFilter">
                                <option value="">All Locations</option>
                                <?php foreach($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>"><?php echo htmlspecialchars($location); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="typeFilter" class="form-label">Job Type</label>
                            <select class="form-select" id="typeFilter">
                                <option value="">All Types</option>
                                <?php foreach($types as $type): ?>
                                    <?php $typeName = ucwords(str_replace('-', ' ', $type)); ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($typeName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="experienceFilter" class="form-label">Experience Level</label>
                            <select class="form-select" id="experienceFilter">
                                <option value="">All Levels</option>
                                <option value="entry">Entry Level</option>
                                <option value="mid">Mid Level</option>
                                <option value="senior">Senior Level</option>
                                <option value="executive">Executive</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Join Us Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Why Join GameVault?</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-gamepad fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title">Gaming Culture</h5>
                            <p class="card-text">Work in an environment where gaming isn't just a product - it's a passion.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-home fa-3x text-success"></i>
                            </div>
                            <h5 class="card-title">Flexible Work</h5>
                            <p class="card-text">Remote and hybrid options to fit your lifestyle and work preferences.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-chart-line fa-3x text-warning"></i>
                            </div>
                            <h5 class="card-title">Career Growth</h5>
                            <p class="card-text">Opportunities for advancement and professional development support.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-gift fa-3x text-danger"></i>
                            </div>
                            <h5 class="card-title">Great Benefits</h5>
                            <p class="card-text">Competitive compensation, health benefits, and gaming allowances.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Listings -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Open Positions</h2>
                <div id="positionCount" class="badge bg-primary fs-6"><?php echo count($positions); ?> positions</div>
            </div>
            
            <div id="positionsContainer">
                <?php if(count($positions) > 0): ?>
                    <?php foreach($positions as $position): ?>
                        <div class="card mb-3 position-card border-0 shadow-sm" 
                             data-department="<?php echo htmlspecialchars($position['department']); ?>"
                             data-location="<?php echo htmlspecialchars($position['location']); ?>"
                             data-type="<?php echo htmlspecialchars($position['type']); ?>"
                             data-experience="<?php echo htmlspecialchars($position['experience_level']); ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <i class="<?php echo htmlspecialchars($position['icon_class']); ?> fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h4 class="card-title mb-1">
                                                    <a href="career-details.php?id=<?php echo $position['id']; ?>" class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($position['title']); ?>
                                                    </a>
                                                </h4>
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($position['department']); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($position['location']); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="fas fa-briefcase me-1"></i><?php echo ucwords(str_replace('-', ' ', $position['type'])); ?>
                                                    </span>
                                                    <?php if($position['experience_level']): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="fas fa-signal me-1"></i><?php echo ucfirst($position['experience_level']); ?> Level
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if($position['salary_range']): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="fas fa-money-bill-wave me-1"></i><?php echo htmlspecialchars($position['salary_range']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="card-text text-muted mb-0">
                                                    <?php 
                                                    $description = strip_tags($position['description']);
                                                    echo strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex flex-column gap-2">
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i>Posted: <?php echo date('M d, Y', strtotime($position['posted_date'])); ?>
                                            </div>
                                            <?php if($position['open_positions'] > 1): ?>
                                                <div class="text-success small">
                                                    <i class="fas fa-user-friends me-1"></i><?php echo $position['open_positions']; ?> positions available
                                                </div>
                                            <?php endif; ?>
                                            <?php if($position['closing_date']): ?>
                                                <div class="text-danger small">
                                                    <i class="fas fa-calendar-times me-1"></i>Closes: <?php echo date('M d, Y', strtotime($position['closing_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <a href="career-details.php?id=<?php echo $position['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                <a href="apply.php?position_id=<?php echo $position['id']; ?>" class="btn btn-success">
                                                    <i class="fas fa-paper-plane me-1"></i>Apply Now
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">No positions currently available</h3>
                            <p class="text-muted">Check back later for new opportunities!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- How to Apply Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body p-4">
                    <h3 class="mb-4">How to Apply</h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Find Your Role</h5>
                                    <p class="mb-0 text-muted">Browse our open positions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Submit Application</h5>
                                    <p class="mb-0 text-muted">Complete the online form</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Interview Process</h5>
                                    <p class="mb-0 text-muted">Meet with our team</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const positionCards = document.querySelectorAll('.position-card');
    const positionCount = document.getElementById('positionCount');
    
    function filterPositions() {
        const department = document.getElementById('departmentFilter').value;
        const location = document.getElementById('locationFilter').value;
        const type = document.getElementById('typeFilter').value;
        const experience = document.getElementById('experienceFilter').value;
        
        let visibleCount = 0;
        
        positionCards.forEach(card => {
            const cardDept = card.getAttribute('data-department');
            const cardLocation = card.getAttribute('data-location');
            const cardType = card.getAttribute('data-type');
            const cardExperience = card.getAttribute('data-experience');
            
            const showCard = 
                (!department || cardDept === department) &&
                (!location || cardLocation === location) &&
                (!type || cardType === type) &&
                (!experience || cardExperience === experience);
            
            if (showCard) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        positionCount.textContent = visibleCount + ' position' + (visibleCount !== 1 ? 's' : '');
    }

    filterForm.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', filterPositions);
    });

    filterPositions();
});
</script>

<?php require_once '../includes/footer.php'; ?>