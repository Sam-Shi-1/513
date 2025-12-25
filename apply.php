<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isset($_GET['position_id']) || !is_numeric($_GET['position_id'])) {
    header('Location: careers.php');
    exit();
}

$position_id = intval($_GET['position_id']);

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, title, department FROM career_positions WHERE id = ? AND is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute([$position_id]);
$position = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$position) {
    echo '<div class="container py-5">
            <div class="alert alert-danger">
                Position not found or no longer available for applications.
            </div>
            <a href="careers.php" class="btn btn-primary">Back to Careers</a>
          </div>';
    require_once 'footer.php';
    exit();
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $required_fields = ['first_name', 'last_name', 'email', 'phone'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please upload your resume.");
        }
        
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['resume']['type'], $allowed_types)) {
            throw new Exception("Please upload a PDF or Word document (max 5MB).");
        }
        
        if ($_FILES['resume']['size'] > $max_size) {
            throw new Exception("File size must be less than 5MB.");
        }

        $upload_dir = 'uploads/resumes/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . preg_replace('/[^A-Za-z0-9.]/', '_', $_FILES['resume']['name']);
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['resume']['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload resume. Please try again.");
        }

        $insertQuery = "INSERT INTO career_applications (
            position_id, first_name, last_name, email, phone, address, city, country,
            resume_path, cover_letter, linkedin_url, portfolio_url, current_company,
            current_position, years_experience, education, skills, referral_source,
            consent_privacy, consent_communications
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($insertQuery);
        $stmt->execute([
            $position_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['country'] ?? null,
            $file_path,
            $_POST['cover_letter'] ?? null,
            $_POST['linkedin_url'] ?? null,
            $_POST['portfolio_url'] ?? null,
            $_POST['current_company'] ?? null,
            $_POST['current_position'] ?? null,
            $_POST['years_experience'] ?? null,
            $_POST['education'] ?? null,
            $_POST['skills'] ?? null,
            $_POST['referral_source'] ?? null,
            isset($_POST['consent_privacy']) ? 1 : 0,
            isset($_POST['consent_communications']) ? 1 : 0
        ]);
        
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            
            <!-- Success Message -->
            <?php if($success): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Application Submitted!</h4>
                    <p>Thank you for applying for the <strong><?php echo htmlspecialchars($position['title']); ?></strong> position.</p>
                    <p>We have received your application and will review it carefully. If your qualifications match our requirements, we will contact you for the next steps.</p>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="careers.php" class="btn btn-outline-primary">View Other Positions</a>
                        <a href="../index.php" class="btn btn-primary">Return to Home</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Error Message -->
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Application Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h3 mb-4">Apply for: <?php echo htmlspecialchars($position['title']); ?></h2>
                        <p class="text-muted mb-4">Please fill out the form below to submit your application.</p>
                        
                        <form method="POST" enctype="multipart/form-data" id="applicationForm">
                            <!-- Personal Information -->
                            <div class="mb-4">
                                <h4 class="h5 mb-3"><i class="fas fa-user me-2 text-primary"></i>Personal Information</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" name="country" value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Professional Information -->
                            <div class="mb-4">
                                <h4 class="h5 mb-3"><i class="fas fa-briefcase me-2 text-primary"></i>Professional Information</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="current_company" class="form-label">Current Company</label>
                                        <input type="text" class="form-control" id="current_company" name="current_company" value="<?php echo isset($_POST['current_company']) ? htmlspecialchars($_POST['current_company']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="current_position" class="form-label">Current Position</label>
                                        <input type="text" class="form-control" id="current_position" name="current_position" value="<?php echo isset($_POST['current_position']) ? htmlspecialchars($_POST['current_position']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="years_experience" class="form-label">Years of Experience</label>
                                        <select class="form-select" id="years_experience" name="years_experience">
                                            <option value="">Select...</option>
                                            <option value="0" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == '0') ? 'selected' : ''; ?>>0-1 years</option>
                                            <option value="1" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == '1') ? 'selected' : ''; ?>>1-3 years</option>
                                            <option value="3" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == '3') ? 'selected' : ''; ?>>3-5 years</option>
                                            <option value="5" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == '5') ? 'selected' : ''; ?>>5-10 years</option>
                                            <option value="10" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == '10') ? 'selected' : ''; ?>>10+ years</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="education" class="form-label">Highest Education</label>
                                        <input type="text" class="form-control" id="education" name="education" placeholder="e.g., Bachelor's in Computer Science" value="<?php echo isset($_POST['education']) ? htmlspecialchars($_POST['education']) : ''; ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="skills" class="form-label">Skills (comma separated)</label>
                                        <textarea class="form-control" id="skills" name="skills" rows="2" placeholder="e.g., JavaScript, React, Node.js, Project Management"><?php echo isset($_POST['skills']) ? htmlspecialchars($_POST['skills']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Documents -->
                            <div class="mb-4">
                                <h4 class="h5 mb-3"><i class="fas fa-file me-2 text-primary"></i>Documents</h4>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="resume" class="form-label">Resume/CV *</label>
                                        <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx" required>
                                        <div class="form-text">Upload your resume (PDF or Word, max 5MB)</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="cover_letter" class="form-label">Cover Letter</label>
                                        <textarea class="form-control" id="cover_letter" name="cover_letter" rows="4" placeholder="Tell us why you're interested in this position..."><?php echo isset($_POST['cover_letter']) ? htmlspecialchars($_POST['cover_letter']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Links -->
                            <div class="mb-4">
                                <h4 class="h5 mb-3"><i class="fas fa-link me-2 text-primary"></i>Links</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="linkedin_url" class="form-label">LinkedIn Profile</label>
                                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" placeholder="https://linkedin.com/in/yourprofile" value="<?php echo isset($_POST['linkedin_url']) ? htmlspecialchars($_POST['linkedin_url']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="portfolio_url" class="form-label">Portfolio/Website</label>
                                        <input type="url" class="form-control" id="portfolio_url" name="portfolio_url" placeholder="https://yourportfolio.com" value="<?php echo isset($_POST['portfolio_url']) ? htmlspecialchars($_POST['portfolio_url']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Information -->
                            <div class="mb-4">
                                <h4 class="h5 mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Additional Information</h4>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="referral_source" class="form-label">How did you hear about us?</label>
                                        <select class="form-select" id="referral_source" name="referral_source">
                                            <option value="">Select...</option>
                                            <option value="LinkedIn" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'LinkedIn') ? 'selected' : ''; ?>>LinkedIn</option>
                                            <option value="Indeed" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Indeed') ? 'selected' : ''; ?>>Indeed</option>
                                            <option value="Company Website" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Company Website') ? 'selected' : ''; ?>>Company Website</option>
                                            <option value="Employee Referral" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Employee Referral') ? 'selected' : ''; ?>>Employee Referral</option>
                                            <option value="Job Board" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Job Board') ? 'selected' : ''; ?>>Job Board</option>
                                            <option value="Social Media" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Social Media') ? 'selected' : ''; ?>>Social Media</option>
                                            <option value="Other" <?php echo (isset($_POST['referral_source']) && $_POST['referral_source'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Consents -->
                            <div class="mb-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="consent_privacy" name="consent_privacy" required>
                                    <label class="form-check-label" for="consent_privacy">
                                        I agree to the collection and processing of my personal data for recruitment purposes *
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="consent_communications" name="consent_communications">
                                    <label class="form-check-label" for="consent_communications">
                                        I agree to receive future communications about job opportunities at GameVault
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="career-details.php?id=<?php echo $position['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Job
                                </a>
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('applicationForm');
    const resumeInput = document.getElementById('resume');

    resumeInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                this.value = '';
            }

            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload a PDF or Word document only');
                this.value = '';
            }
        }
    });

    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
                
                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required';
                    field.parentNode.appendChild(errorDiv);
                }
            } else {
                field.classList.remove('is-invalid');
                
                const errorDiv = field.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                    errorDiv.remove();
                }
            }
        });

        const emailField = form.querySelector('#email');
        if (emailField.value && !isValidEmail(emailField.value)) {
            valid = false;
            emailField.classList.add('is-invalid');
            if (!emailField.nextElementSibling || !emailField.nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Please enter a valid email address';
                emailField.parentNode.appendChild(errorDiv);
            }
        }
        
        if (!valid) {
            e.preventDefault();

            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        }
    });
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>