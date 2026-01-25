<?php
// student/dashboard.php

$page_title = "Dashboard - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtDate($d): string {
    if (!$d) return '-';
    $t = strtotime($d);
    return $t ? date('d M Y', $t) : (string)$d;
}

function daysLeft($d): ?int {
    if (!$d) return null;
    $t = strtotime($d);
    if (!$t) return null;
    return (int)floor(($t - time()) / 86400);
}

function badgeForDays(?int $days): array {
    if ($days === null) return ['secondary', 'Unknown'];
    if ($days < 0)      return ['danger', 'Expired'];
    if ($days <= 30)    return ['warning', $days . " days left"];
    return ['success', $days . " days left"];
}

/**
 * IMPORTANT (matches your profile.php):
 * - DB stores profile_photo like "uploads/profile/xxx.png" (relative to /student/)
 * - dashboard.php is also inside /student/
 */
function photoUrlFromDb(?string $path): string {
    $default = "uploads/default_image.png";
    if (!$path) return $default;

    if (preg_match('/^https?:\/\//i', $path)) return $path;

    return ltrim($path, '/');
}

// ------------------------------------------------------------
// Data holders
// ------------------------------------------------------------
$success = "";
$error   = "";

$studentRow    = null;
$visaRow       = null;
$renewalRow    = null;
$insuranceRow  = null;
$exitRow       = null;
$nationalities = [];
$recentUsers   = [];

// ------------------------------------------------------------
// 1) Student + Program + School
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT
            s.student_id, s.program_id, s.first_name, s.last_name, s.email, s.phone, s.status, s.student_type, s.profile_photo,
            p.program_name, p.level, p.faculty, p.duration_years,
            sc.school_name
        FROM student s
        LEFT JOIN program p ON p.program_id = s.program_id
        LEFT JOIN school sc ON sc.school_id = p.school_id
        WHERE s.student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {
    $error = "Student load error: " . $e->getMessage();
}

// ------------------------------------------------------------
// 2) Latest Visa (student_visa)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT visa_id, student_id, visa_type, issue_date, expiry_date, status, passport_no
        FROM student_visa
        WHERE student_id = ?
        ORDER BY expiry_date DESC, visa_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $visaRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// 3) Latest Renewal (visa_renewal_application)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT application_id, student_id, submission_date, requested_months, status
        FROM visa_renewal_application
        WHERE student_id = ?
        ORDER BY submission_date DESC, application_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $renewalRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// 4) Latest Insurance (insurance_policy + insurance_provider)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT
            ip.policy_id, ip.student_id, ip.provider_id, ip.policy_number, ip.start_date, ip.end_date, ip.coverage_type, ip.status,
            prov.provider_name, prov.contact_info
        FROM insurance_policy ip
        LEFT JOIN insurance_provider prov ON prov.provider_id = ip.provider_id
        WHERE ip.student_id = ?
        ORDER BY ip.end_date DESC, ip.policy_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $insuranceRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// 5) Latest Exit Case (exit_case)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT exit_id, student_id, exit_type, request_date, exit_status
        FROM exit_case
        WHERE student_id = ?
        ORDER BY request_date DESC, exit_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $exitRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// 6) Nationality (nationality + country)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT n.country_id, n.acquired_date, n.is_primary, c.country_name, c.region
        FROM nationality n
        JOIN country c ON c.country_id = n.country_id
        WHERE n.student_id = ?
        ORDER BY n.is_primary DESC, c.country_name ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $nationalities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// 7) Recently Registered Students (LATEST 5)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT student_id, first_name, last_name, email, phone, status, student_type, profile_photo, created_at
        FROM student
        ORDER BY created_at DESC, student_id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    // don't block dashboard
}

// ------------------------------------------------------------
// Derived values
// ------------------------------------------------------------
$fullName = trim(($studentRow['first_name'] ?? '') . ' ' . ($studentRow['last_name'] ?? ''));
$email    = $studentRow['email'] ?? '-';
$phone    = $studentRow['phone'] ?? '-';
$status   = $studentRow['status'] ?? '-';
$type     = $studentRow['student_type'] ?? '-';

$programName = $studentRow['program_name'] ?? '-';
$schoolName  = $studentRow['school_name'] ?? '-';
$level       = $studentRow['level'] ?? '-';
$faculty     = $studentRow['faculty'] ?? '-';

$visaExpiry = $visaRow['expiry_date'] ?? null;
$insExpiry  = $insuranceRow['end_date'] ?? null;

$visaDays = daysLeft($visaExpiry);
$insDays  = daysLeft($insExpiry);

[$visaBadge, $visaBadgeText] = badgeForDays($visaDays);
[$insBadge,  $insBadgeText]  = badgeForDays($insDays);

// ------------------------------------------------------------
// 8) Chart Data
// ------------------------------------------------------------

// A) Days left: Visa vs Insurance
$visaDaysLeft = $visaDays ?? 0;
$insDaysLeft  = $insDays ?? 0;

// clamp negative (expired) to 0 for nicer chart
$visaDaysLeftChart = max(0, (int)$visaDaysLeft);
$insDaysLeftChart  = max(0, (int)$insDaysLeft);

?>

<style>
.cover-image {
    object-fit: cover;
    width: 46px;
    height: 46px;
    border-radius: 999px;
    border: 2px solid #e5e7eb;
    background: #fff;
}

/* ---------- Students list (like screenshot) ---------- */
.students-card-title{
    font-size: 1.25rem;
    font-weight: 800;
}

.student-list{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.student-item{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
}

.student-left{
    display:flex;
    align-items:flex-start;
    gap:12px;
    min-width:0;
}

.student-avatar{
    width:56px;
    height:56px;
    border-radius:999px;
    object-fit:cover;
    border:3px solid #e6eefc;
    background:#fff;
    flex:0 0 auto;
}

.student-meta{
    min-width:0;
}

.student-badge{
    display:inline-block;
    font-size:12px;
    font-weight:700;
    padding:4px 10px;
    border-radius:999px;
    background:#eaf2ff;
    color:#1f5eff;
    line-height:1;
    margin-bottom:6px;
}

.student-name{
    font-weight:800;
    margin:0;
    line-height:1.2;
}

.student-email{
    margin-top:4px;
    color:#8a8f99;
    font-size:.95rem;
    word-break:break-word;
}

.student-right{
    text-align:right;
    white-space:nowrap;
}

.student-phone{
    font-weight:800;
    color:#1f5eff;
}

.student-phone-sub{
    display:block;
    margin-top:3px;
    font-weight:800;
    color:#1f5eff;
}

.student-extra{
    margin-top:6px;
    font-size:.9rem;
    color:#6b7280;
}

.student-divider{
    height:1px;
    background:#f0f2f6;
    margin-top:16px;
}
</style>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Dashboard</h2>
            <div class="text-muted">Welcome back, <?php echo h($fullName ?: 'Student'); ?>.</div>
        </div>

        <div class="d-flex gap-2">
            <a href="profile.php" class="btn btn-outline-primary">
                <i class="bi bi-person-circle me-1"></i> My Profile
            </a>
            <a href="visa_renewal.php" class="btn btn-primary">
                <i class="bi bi-arrow-clockwise me-1"></i> Visa Renewal
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3">

        <!-- Visa -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Visa Status</div>
                        <i class="bi bi-passport fs-4 text-primary"></i>
                    </div>

                    <div class="mt-2">
                        <span class="badge bg-<?php echo h($visaBadge); ?>">
                            <?php echo h($visaBadgeText); ?>
                        </span>
                    </div>

                    <div class="small text-muted mt-2">Expiry Date</div>
                    <div class="fw-semibold"><?php echo h(fmtDate($visaExpiry)); ?></div>

                    <div class="small text-muted mt-2">Visa Type</div>
                    <div class="fw-semibold"><?php echo h($visaRow['visa_type'] ?? '-'); ?></div>

                    <div class="mt-3">
                        <a href="visa_renewal.php" class="btn btn-sm btn-outline-primary w-100">
                            View Visa & Renewals
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insurance -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Insurance</div>
                        <i class="bi bi-shield-check fs-4 text-success"></i>
                    </div>

                    <div class="mt-2">
                        <span class="badge bg-<?php echo h($insBadge); ?>">
                            <?php echo h($insBadgeText); ?>
                        </span>
                    </div>

                    <div class="small text-muted mt-2">End Date</div>
                    <div class="fw-semibold"><?php echo h(fmtDate($insExpiry)); ?></div>

                    <div class="small text-muted mt-2">Provider</div>
                    <div class="fw-semibold"><?php echo h($insuranceRow['provider_name'] ?? '-'); ?></div>

                    <div class="mt-3">
                        <a href="insurance.php" class="btn btn-sm btn-outline-success w-100">
                            View Insurance
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Renewal -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Latest Renewal</div>
                        <i class="bi bi-file-earmark-text fs-4 text-warning"></i>
                    </div>

                    <div class="small text-muted mt-2">Status</div>
                    <div class="fw-semibold"><?php echo h($renewalRow['status'] ?? 'No application'); ?></div>

                    <div class="small text-muted mt-2">Submission Date</div>
                    <div class="fw-semibold"><?php echo h(fmtDate($renewalRow['submission_date'] ?? null)); ?></div>

                    <div class="small text-muted mt-2">Requested Months</div>
                    <div class="fw-semibold"><?php echo h($renewalRow['requested_months'] ?? '-'); ?></div>

                    <div class="mt-3">
                        <a href="visa_renewal.php" class="btn btn-sm btn-outline-warning w-100">
                            Track Renewal
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exit -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Exit Request</div>
                        <i class="bi bi-door-open fs-4 text-danger"></i>
                    </div>

                    <div class="small text-muted mt-2">Status</div>
                    <div class="fw-semibold"><?php echo h($exitRow['exit_status'] ?? 'No request'); ?></div>

                    <div class="small text-muted mt-2">Type / Date</div>
                    <div class="fw-semibold">
                        <?php echo h(($exitRow['exit_type'] ?? '-') . " • " . fmtDate($exitRow['request_date'] ?? null)); ?>
                    </div>

                    <div class="mt-3">
                        <a href="exit.php" class="btn btn-sm btn-outline-danger w-100">
                            View Exit Module
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Row -->
    <div class="row g-3 mt-1">

        <!-- Days left chart -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>My Expiry Overview</span>
                    <small class="text-muted">Days left (0 if expired)</small>
                </div>
                <div class="card-body">
                    <div style="height:260px;">
                        <canvas id="daysLeftChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Details Row -->
    <div class="row g-3 mt-1">

        <!-- Student Summary -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">Student Summary</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Student ID</div>
                            <div class="fw-semibold"><?php echo (int)$student_id; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Status</div>
                            <span class="badge bg-dark"><?php echo h($status); ?></span>
                        </div>

                        <div class="col-md-6">
                            <div class="small text-muted">Name</div>
                            <div class="fw-semibold"><?php echo h($fullName ?: '-'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Student Type</div>
                            <div class="fw-semibold"><?php echo h($type); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div class="small text-muted">Email</div>
                            <div class="fw-semibold"><?php echo h($email); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Phone</div>
                            <div class="fw-semibold"><?php echo h($phone); ?></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Program</div>
                            <div class="fw-semibold"><?php echo h($programName); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">School</div>
                            <div class="fw-semibold"><?php echo h($schoolName); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Level</div>
                            <div class="fw-semibold"><?php echo h($level); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Faculty</div>
                            <div class="fw-semibold"><?php echo h($faculty); ?></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Nationality -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">Nationality</div>
                <div class="card-body">
                    <?php if (!$nationalities): ?>
                        <div class="text-muted">No nationality records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Primary</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($nationalities as $n): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo h($n['country_name']); ?></div>
                                            <div class="small text-muted"><?php echo h($n['region'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <?php if ((int)$n['is_primary'] === 1): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-sm btn-outline-primary w-100">
                            View Full Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recently Registered Students (NEW DESIGN + ALL INFO) -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="students-card-title">Students</div>
                        <span class="text-muted small">Latest students who registered</span>
                    </div>

                    <?php if (!$recentUsers): ?>
                        <div class="text-muted">No student records found.</div>
                    <?php else: ?>
                        <div class="student-list">
                            <?php foreach ($recentUsers as $index => $u): ?>
                                <?php
                                    $name  = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                    $name  = $name ?: ('Student #' . (int)$u['student_id']);
                                    $email2 = $u['email'] ?? '-';
                                    $phone2 = $u['phone'] ?? '-';

                                    $badge = trim((string)($u['student_type'] ?? ''));
                                    if ($badge === '') $badge = 'STUDENT';

                                    $phoneTop = $phone2;
                                    $phoneBottom = '';
                                    $digitsOnly = preg_replace('/\s+/', '', (string)$phone2);
                                    if ($phone2 !== '-' && strlen($digitsOnly) >= 10) {
                                        $phoneTop = substr($phone2, 0, max(0, strlen($phone2) - 4));
                                        $phoneBottom = substr($phone2, -4);
                                    }
                                ?>

                                <div class="student-item">
                                    <div class="student-left">
                                        <img
                                            src="<?php echo h(photoUrlFromDb($u['profile_photo'] ?? null)); ?>"
                                            class="student-avatar"
                                            alt="Profile">

                                        <div class="student-meta">
                                            <span class="student-badge"><?php echo h($badge); ?></span>
                                            <p class="student-name"><?php echo h($name); ?></p>
                                            <div class="student-email"><?php echo h($email2); ?></div>

                                            <div class="student-extra">
                                                <div><strong>ID:</strong> <?php echo (int)$u['student_id']; ?></div>
                                                <div><strong>Status:</strong> <?php echo h($u['status'] ?? '-'); ?></div>
                                                <div><strong>Type:</strong> <?php echo h($u['student_type'] ?? '-'); ?></div>
                                                <div><strong>Registered:</strong> <?php echo h(fmtDate($u['created_at'] ?? null)); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="student-right">
                                        <?php if ($phone2 !== '-'): ?>
                                            <span class="student-phone"><?php echo h(trim($phoneTop)); ?></span>
                                            <?php if ($phoneBottom): ?>
                                                <span class="student-phone-sub"><?php echo h($phoneBottom); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($index < count($recentUsers) - 1): ?>
                                    <div class="student-divider"></div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(() => {
    // 1) Bar chart: Visa vs Insurance days left
    const visaDays = <?php echo (int)$visaDaysLeftChart; ?>;
    const insDays  = <?php echo (int)$insDaysLeftChart; ?>;

    const ctx1 = document.getElementById('daysLeftChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Visa', 'Insurance'],
                datasets: [{
                    label: 'Days Left',
                    data: [visaDays, insDays],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
