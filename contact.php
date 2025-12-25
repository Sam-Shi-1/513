<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';

// Add database connection
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        // Generate ticket ID
        $ticket_id = "GV-" . time() . "-" . rand(1000, 9999);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Save to database
        $db = new Database();
        $conn = $db->getConnection();
        
        if ($conn) {
            try {
                $sql = "INSERT INTO contact_submissions (name, email, subject, message, ticket_id, ip_address, user_agent) 
                        VALUES (:name, :email, :subject, :message, :ticket_id, :ip_address, :user_agent)";
                
                $stmt = $conn->prepare($sql);
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':subject', $subject);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':ticket_id', $ticket_id);
                $stmt->bindParam(':ip_address', $ip_address);
                $stmt->bindParam(':user_agent', $user_agent);
                
                $stmt->execute();
                
                $contact_id = $conn->lastInsertId();
                
                // Record auto-reply
                $response_sql = "INSERT INTO feedback_responses (contact_id, response_text, response_type) 
                                VALUES (:contact_id, :response_text, 'auto_reply')";
                
                $response_stmt = $conn->prepare($response_sql);
                $auto_response = "Thank you for your message. We'll get back to you within 24 hours.";
                $response_stmt->bindParam(':contact_id', $contact_id);
                $response_stmt->bindParam(':response_text', $auto_response);
                $response_stmt->execute();
                
            } catch(PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                // Database error, but continue to send email
            }
        }
        
        // Send email to user
        $user_subject = "Thank You for Contacting GameVault";
        
        $user_message = "
        <html>
        <head>
            <title>Thank You for Contacting GameVault</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 30px; background-color: #f8f9fa; border-radius: 0 0 5px 5px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
                .highlight { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>GameVault Support</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($name) . ",</h2>
                    <p>Thank you for contacting GameVault! We have received your message and our support team will get back to you within 24 hours.</p>
                    
                    <div class='highlight'>
                        <h3>Your Message Details:</h3>
                        <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    
                    <p><strong>Your Reference Information:</strong></p>
                    <ul>
                        <li><strong>Submission Date:</strong> " . date('F j, Y, g:i a') . "</li>
                        <li><strong>Ticket ID:</strong> " . $ticket_id . "</li>
                    </ul>
                    
                    <p>You can also reach us through:</p>
                    <ul>
                        <li>Email: <a href='mailto:support@gamevault.com'>support@gamevault.com</a></li>
                        <li>Phone: +1 (555) 123-4567</li>
                    </ul>
                    
                    <p>Best regards,<br>
                    GameVault Support Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>© " . date('Y') . " GameVault Inc. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: GameVault Support <support@gamevault.com>" . "\r\n";
        $headers .= "Reply-To: support@gamevault.com" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $mail_sent = mail($email, $user_subject, $user_message, $headers);
        
        // Optional: also send notification to admin email
        $admin_subject = "New Contact Form Submission: " . $subject;
        $admin_message = "
        New contact form submission received:
        
        Name: $name
        Email: $email
        Subject: $subject
        Message: $message
        Ticket ID: $ticket_id
        
        Time: " . date('Y-m-d H:i:s') . "
        IP Address: " . $_SERVER['REMOTE_ADDR'] . "
        
        View in database: Contact ID: " . ($contact_id ?? 'N/A') . "
        ";
        
        $admin_headers = "From: GameVault Website <noreply@gamevault.com>" . "\r\n";
        
        // Send email to admin (optional)
        mail('support@gamevault.com', $admin_subject, $admin_message, $admin_headers);
        
        // Set success message
        if ($mail_sent) {
            $success = "Thank you for your message! We've sent a confirmation email to $email and will get back to you within 24 hours. Your Ticket ID: $ticket_id";
        } else {
            $success = "Thank you for your message! We'll get back to you within 24 hours.Your Ticket ID: $ticket_id";
        }
        
        // Clear form data
        $_POST = [];
    }
}
?>

<?php 
$current_dir = '';
include 'includes/header.php'; 
?>

<!-- The rest of the HTML remains unchanged -->
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Contact Us</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Send us a Message</h4>
            </div>
            <div class="card-body">
                <?php if(isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Please fix the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="contact.php">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <select class="form-control" id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                            <option value="Product Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Product Support') ? 'selected' : ''; ?>>Product Support</option>
                            <option value="Order Issue" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Order Issue') ? 'selected' : ''; ?>>Order Issue</option>
                            <option value="Payment Problem" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Payment Problem') ? 'selected' : ''; ?>>Payment Problem</option>
                            <option value="Business Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Business Partnership') ? 'selected' : ''; ?>>Business Partnership</option>
                            <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="6" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Contact Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6><i class="fas fa-envelope text-primary me-2"></i>Email</h6>
                    <p class="mb-1">Customer Support: <a href="mailto:support@gamevault.com">support@gamevault.com</a></p>
                    <p class="mb-0">Business Inquiries: <a href="mailto:business@gamevault.com">business@gamevault.com</a></p>
                </div>
                
                <div class="mb-4">
                    <h6><i class="fas fa-phone text-primary me-2"></i>Phone</h6>
                    <p class="mb-1">Customer Support: <a href="tel:+15551234567">+1 (555) 123-4567</a></p>
                    <p class="mb-0">Business: <a href="tel:+15551234568">+1 (555) 123-4568</a></p>
                </div>
                
                <div class="mb-4">
                    <h6><i class="fas fa-clock text-primary me-2"></i>Business Hours</h6>
                    <p class="mb-1"><strong>Customer Support:</strong> 24/7</p>
                    <p class="mb-0"><strong>Business Office:</strong> Mon-Fri, 9AM-5PM EST</p>
                </div>
                
                <div>
                    <h6><i class="fas fa-map-marker-alt text-primary me-2"></i>Address</h6>
                    <p class="mb-0">
                        GameVault Inc.<br>
                        123 Gaming Street<br>
                        San Francisco, CA 94105<br>
                        United States
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Frequently Asked Questions</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                How fast do I receive my CD keys?
                            </button>
                        </h2>
                        <div id="faqCollapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                CD keys are delivered instantly after payment confirmation. You'll receive them immediately in your account and via email.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                Are your products genuine?
                            </button>
                        </h2>
                        <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! All our products are 100% genuine and sourced directly from official publishers and distributors.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                What payment methods do you accept?
                            </button>
                        </h2>
                        <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We accept all major credit cards, PayPal, and various regional payment methods depending on your location.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h4>Still Have Questions?</h4>
                <p class="mb-3">Check out our comprehensive FAQ section or browse our help center for detailed guides and troubleshooting.</p>
                <a href="#" class="btn btn-outline-primary me-2">Visit FAQ</a>
                <a href="#" class="btn btn-outline-secondary">Help Center</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>