<?php
session_start();
require 'db.php';

if (!isset($_SESSION['email'])) {
    echo "Not authenticated";
    exit();
}

// Get the record ID from the request
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recordId <= 0) {
    echo "Invalid record ID";
    exit();
}

// Get user info for permission checks
$userEmail = $_SESSION['email'] ?? '';
$userQuery = "SELECT id, role, userwithempid FROM users WHERE email = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$userRole = $userData['role'];

// Fetch the record details
$sql = "SELECT * FROM paperworkdetails WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p class='error'>Record not found</p>";
    exit();
}

$row = $result->fetch_assoc();
?>

<!-- Modal sections for different categories of details -->
<div class="modal-section active" data-tab="consultant">
    <h3 class="section-title">Consultant Details</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cfirstname'] . ' ' . $row['clastname']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cemail'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Mobile Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cmobilenumber'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Home Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['chomeaddress'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Date of Birth</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cdob'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Location</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['clocation'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">SSN</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cssn'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Work Authorization Status</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cwork_authorization_status'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">CV Validate Status</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cv_validate_status'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Has Certifications</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['has_certifications'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Certifications</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ccertifications'] ?? 'None'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Overall Experience</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['coverall_experience'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Recent Job Title</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['crecent_job_title'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Candidate Source</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ccandidate_source'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">LinkedIn URL</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['clinkedinurl'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Eipal ID</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ceipalid'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Document Status</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Resume Attached</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cresume_attached'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Photo ID Attached</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cphoto_id_attached'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Work Authorization Attached</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cwa_attached'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Any Other Documents</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['cany_other_specify'] ?? 'Not specified'); ?></span>
        </div>
    </div>
</div>

<div class="modal-section" data-tab="employer">
    <h3 class="section-title">Primary Employer Details</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Own Corporation</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['employer_own_corporation'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Corporation Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['employer_corporation_name'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Federal ID Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['fed_id_number'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Contact Person</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_name'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Designation</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_designation'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_address'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Phone Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_phone_number'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Extension</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_extension_number'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_email_id'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Website</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['website_link'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">LinkedIn URL</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['employer_linkedin_url'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Employer Type</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['employer_type'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Secondary Employer Details</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Corporation Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['employer_corporation_name1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Federal ID Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['fed_id_number1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Contact Person</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_name1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Designation</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_designation1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_address1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Phone Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_phone_number1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Extension</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_extension_number1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['contact_person_email_id1'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Bench Sale Information</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Bench</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['bench'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Recruiter Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['benchsale_recruiter_name'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Phone Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['benchsale_recruiter_phone_number'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Extension</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['benchsale_recruiter_extension_number'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['benchsale_recruiter_email_id'] ?? 'Not specified'); ?></span>
        </div>
    </div>
</div>

<div class="modal-section" data-tab="project">
    <h3 class="section-title">Project Details</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Job Title</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['job_title'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Job Code</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['job_code'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Primary Skill</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['primary_skill'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Secondary Skill</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['secondary_skill'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Expertise Role Experience</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['experience_in_expertise_role'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Term</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['term'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Duration</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['duration'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Project Location</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['project_location'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Start Date</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">End Date</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Client Information</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Client</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['client'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">End Client</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['end_client'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Client Manager</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['client_manager'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Client Manager Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['client_manager_email_id'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Industry</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['industry'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Business Track</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['business_track'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Geo Entity</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['geoentity'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Financial Details</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Pay Rate</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['payrate'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Client Rate</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['clientrate'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Margin</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['margin'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Vendor Fee</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['vendor_fee'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Margin Deviation Approval</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['margin_deviation_approval'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Margin Deviation Reason</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['margin_deviation_reason'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Ratecard Adherence</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ratecard_adherence'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Ratecard Deviation Reason</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ratecard_deviation_reason'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Ratecard Deviation Approved</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['ratecard_deviation_approved'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment Term</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['payment_term'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment Term Approval</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['payment_term_approval'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment Term Deviation Reason</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['payment_term_deviation_reason'] ?? 'Not specified'); ?></span>
        </div>
    </div>
</div>

<div class="modal-section" data-tab="collaboration">
    <h3 class="section-title">Collaboration Details</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Collaboration Status</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['collaboration_collaborate'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Delivery Manager</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['delivery_manager'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Delivery Account Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['delivery_account_lead'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Team Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['team_lead'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Associate Team Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['associate_team_lead'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Business Unit</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['business_unit'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Client Account Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['client_account_lead'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Client Partner</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['client_partner'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Associate Director Delivery</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['associate_director_delivery'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Secondary Collaboration Team</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Delivery Manager</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['delivery_manager1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Delivery Account Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['delivery_account_lead1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Team Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['team_lead1'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Associate Team Lead</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['associate_team_lead1'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <h4 class="subsection-title">Recruitment & PT Details</h4>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Recruiter Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['recruiter_name'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Recruiter Employee ID</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['recruiter_employee_id'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">PT Support</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['pt_support'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">PT Ownership</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['pt_ownership'] ?? 'Not specified'); ?></span>
        </div>
    </div>
    
    <?php if (isset($row['plc_code']) && !empty($row['plc_code'])): ?>
    <div class="plc-section">
        <h3 class="section-title">PLC Code</h3>
        <div class="plc-code"><?php echo htmlspecialchars($row['plc_code']); ?></div>
        <?php if (isset($row['plc_updated_at']) && isset($row['plc_updated_by'])): ?>
        <div class="plc-meta">
            Last updated: <?php echo date('M d, Y H:i', strtotime($row['plc_updated_at'])); ?> by <?php echo htmlspecialchars($row['plc_updated_by']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal-section" data-tab="status">
    <h3 class="section-title">Status Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Type</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['type'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['status'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Reason</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['reason'] ?? 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Created At</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['created_at'] ? date('M d, Y H:i', strtotime($row['created_at'])) : 'Not specified'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Submitted By</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['submittedby'] ?? 'Not specified'); ?></span>
        </div>
        <?php if (isset($row['idplc_code']) && !empty($row['idplc_code'])): ?>
        <div class="detail-item">
            <span class="detail-label">PLC Code ID</span>
            <span class="detail-value"><?php echo htmlspecialchars($row['idplc_code'] ?? 'Not specified'); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>