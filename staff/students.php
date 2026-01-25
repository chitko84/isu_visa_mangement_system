<?php
// staff/students.php

$page_title = "Students - Staff Portal";
require_once __DIR__ . "/header.php"; // must provide $conn

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function photoUrl(?string $path): string {
    // staff is /staff, student uploads are in /student
    $default = "../student/uploads/default_image.png";
    if (!$path) return $default;
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    return "../student/" . ltrim($path, '/'); // stored like uploads/profile/xx.png
}

function buildQueryString(array $params): string {
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
    }
    return http_build_query($params);
}

function redirectTo(string $qs = ""): void {
    $url = "students.php" . ($qs ? ("?" . $qs) : "");

    // If headers are still possible, do normal redirect
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }

    // Otherwise fallback to JS redirect
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit();
}


// ------------------------------------------------------------
// State
// ------------------------------------------------------------
$success = "";
$error   = "";

// query params
$q       = trim($_GET['q'] ?? "");
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$viewId  = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
$editId  = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

// sorting params
$sort = trim($_GET['sort'] ?? "id");
$dir  = strtolower(trim($_GET['dir'] ?? "desc"));
$dir  = ($dir === "asc") ? "ASC" : "DESC";

// only allow safe sorts
$sortMap = [
    "id"          => "s.student_id",
    "name"        => "s.first_name", // secondary last_name
    "email"       => "s.email",
    "status"      => "s.status",
    "program"     => "p.program_name",
    "school"      => "sc.school_name",
    "nationality" => "c.country_name"
];
$sortCol = $sortMap[$sort] ?? "s.student_id";

// success flags
if (($_GET['msg'] ?? '') === 'updated') $success = "Student updated successfully.";


// ------------------------------------------------------------
// UPDATE student (EDIT) - direct update (no proc)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_student') {
    $sid = (int)($_POST['student_id'] ?? 0);

    if ($sid <= 0) {
        $error = "Invalid student ID.";
    } else {
        $first = trim($_POST['first_name'] ?? "");
        $last  = trim($_POST['last_name'] ?? "");
        $email = trim($_POST['email'] ?? "");
        $phone = trim($_POST['phone'] ?? "");
        $status = trim($_POST['status'] ?? "");
        $stype  = trim($_POST['student_type'] ?? "");
        $programId = (int)($_POST['program_id'] ?? 0);

        if ($first === "" || $last === "" || $email === "" || $status === "" || $stype === "") {
            $error = "Please fill required fields (First/Last/Email/Status/Student Type).";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE student
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, status = ?, student_type = ?, program_id = ?
                    WHERE student_id = ?
                ");
                $stmt->bind_param("ssssssii", $first, $last, $email, $phone, $status, $stype, $programId, $sid);
                $stmt->execute();
                $stmt->close();

                redirectTo(buildQueryString([
                    "q" => $q,
                    "page" => $page,
                    "sort" => $sort,
                    "dir" => strtolower($dir),
                    "msg" => "updated"
                ]));
            } catch (Throwable $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------
// Programs list for edit dropdown
// ------------------------------------------------------------
$programs = [];
try {
    $rs = $conn->query("SELECT program_id, program_name FROM program ORDER BY program_name ASC");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $programs[] = $row;
        $rs->free();
    }
} catch (Throwable $e) {}

// ------------------------------------------------------------
// View / Edit student data (top panel)
// ------------------------------------------------------------
$selectedStudent = null;
if ($viewId > 0 || $editId > 0) {
    $targetId = $editId > 0 ? $editId : $viewId;

    try {
        $stmt = $conn->prepare("
            SELECT
                s.student_id, s.first_name, s.last_name, s.email, s.phone, s.status, s.student_type, s.profile_photo, s.program_id,
                p.program_name, p.level, p.faculty,
                sc.school_name,
                c.country_name AS primary_country, c.region AS primary_region
            FROM student s
            LEFT JOIN program p ON p.program_id = s.program_id
            LEFT JOIN school sc ON sc.school_id = p.school_id
            LEFT JOIN nationality n ON n.student_id = s.student_id AND n.is_primary = 1
            LEFT JOIN country c ON c.country_id = n.country_id
            WHERE s.student_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $selectedStudent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        $error = "Failed to load student: " . $e->getMessage();
    }
}

// ------------------------------------------------------------
// LIST + SEARCH + PAGINATION + SORT
// ------------------------------------------------------------
$where  = "1=1";
$params = [];
$types  = "";

if ($q !== "") {
    $where .= " AND (
        s.student_id = ?
        OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
        OR s.first_name LIKE ?
        OR s.last_name LIKE ?
        OR s.email LIKE ?
        OR s.phone LIKE ?
    )";

    $idExact = ctype_digit($q) ? (int)$q : 0;
    $like = "%" . $q . "%";

    $params = [$idExact, $like, $like, $like, $like, $like];
    $types  = "isssss";
}

// count
$totalRows = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM student s WHERE $where");
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRows = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
} catch (Throwable $e) {
    $error = $error ?: ("Count error: " . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// list
$students = [];
try {
    $orderBy = $sortCol . " " . $dir;

    // special case for name: also order by last_name for nicer sorting
    if ($sort === "name") {
        $orderBy = "s.first_name $dir, s.last_name $dir";
    }

    $sql = "
        SELECT
            s.student_id, s.first_name, s.last_name, s.email, s.phone, s.status, s.student_type, s.profile_photo,
            p.program_name,
            sc.school_name,
            c.country_name AS primary_country
        FROM student s
        LEFT JOIN program p ON p.program_id = s.program_id
        LEFT JOIN school sc ON sc.school_id = p.school_id
        LEFT JOIN nationality n ON n.student_id = s.student_id AND n.is_primary = 1
        LEFT JOIN country c ON c.country_id = n.country_id
        WHERE $where
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    $error = $error ?: ("List load error: " . $e->getMessage());
}

// build sort links
function sortLink(string $label, string $key, string $currentSort, string $currentDir, string $q, int $page): string {
    $nextDir = "asc";
    if ($currentSort === $key && strtolower($currentDir) === "asc") $nextDir = "desc";

    $qs = buildQueryString([
        "q" => $q,
        "page" => $page,
        "sort" => $key,
        "dir" => $nextDir
    ]);

    $arrow = "";
    if ($currentSort === $key) {
        $arrow = (strtolower($currentDir) === "asc") ? " ▲" : " ▼";
    }

    return '<a href="students.php?' . h($qs) . '" class="text-decoration-none">' . h($label) . $arrow . '</a>';
}
?>
<style>
    .avatar {
        width: 46px; height: 46px; border-radius: 999px;
        object-fit: cover; border: 2px solid #e5e7eb; background:#fff;
    }
    .table td, .table th { vertical-align: middle; }
</style>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">Students</h2>
            <div class="text-muted">Search, view, edit, delete, and sort student records.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- View/Edit Panel -->
    <?php if ($selectedStudent): ?>
        <div class="card mb-3">
            <div class="card-header fw-semibold">
                <?php echo ($editId > 0) ? ("Edit Student #" . (int)$selectedStudent['student_id']) : ("Student Details #" . (int)$selectedStudent['student_id']); ?>
            </div>
            <div class="card-body">

                <?php if ($editId > 0): ?>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_student">
                        <input type="hidden" name="student_id" value="<?php echo (int)$selectedStudent['student_id']; ?>">

                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input class="form-control" name="first_name" value="<?php echo h($selectedStudent['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input class="form-control" name="last_name" value="<?php echo h($selectedStudent['last_name']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input class="form-control" type="email" name="email" value="<?php echo h($selectedStudent['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="phone" value="<?php echo h($selectedStudent['phone']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status *</label>
                            <input class="form-control" name="status" value="<?php echo h($selectedStudent['status']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Student Type *</label>
                            <input class="form-control" name="student_type" value="<?php echo h($selectedStudent['student_type']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Program</label>
                            <select class="form-select" name="program_id">
                                <option value="0">-- None --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?php echo (int)$p['program_id']; ?>"
                                        <?php echo ((int)$selectedStudent['program_id'] === (int)$p['program_id']) ? 'selected' : ''; ?>>
                                        <?php echo h($p['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary">Save Changes</button>
                            <a class="btn btn-outline-secondary"
                               href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$page,"sort"=>$sort,"dir"=>strtolower($dir)])); ?>">
                                Cancel
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img class="avatar" src="<?php echo h(photoUrl($selectedStudent['profile_photo'] ?? null)); ?>" alt="Photo">
                        <div>
                            <div class="fw-semibold" style="font-size:1.1rem;">
                                <?php echo h(trim(($selectedStudent['first_name'] ?? '') . " " . ($selectedStudent['last_name'] ?? ''))); ?>
                            </div>
                            <div class="text-muted"><?php echo h($selectedStudent['email'] ?? '-'); ?></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Status</div>
                            <div class="fw-semibold"><?php echo h($selectedStudent['status'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Program</div>
                            <div class="fw-semibold"><?php echo h($selectedStudent['program_name'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Primary Nationality</div>
                            <div class="fw-semibold"><?php echo h($selectedStudent['primary_country'] ?? '-'); ?></div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <a class="btn btn-outline-primary"
                           href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$page,"sort"=>$sort,"dir"=>strtolower($dir),"edit_id"=>$selectedStudent['student_id']])); ?>">
                            Edit
                        </a>
                        <a class="btn btn-outline-secondary"
                           href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$page,"sort"=>$sort,"dir"=>strtolower($dir)])); ?>">
                            Close
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-center" method="get">
                <div class="col-md-6">
                    <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Search by ID, name, email, phone...">
                </div>
                <input type="hidden" name="sort" value="<?php echo h($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo h(strtolower($dir)); ?>">
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
                <div class="col-md-2">
                    <a class="btn btn-outline-secondary w-100" href="students.php">Reset</a>
                </div>
                <div class="col-md-2 text-end text-muted">
                    <?php echo (int)$totalRows; ?> record(s)
                </div>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>Photo</th>
                    <th><?php echo sortLink("ID", "id", $sort, $dir, $q, $page); ?></th>
                    <th><?php echo sortLink("Name", "name", $sort, $dir, $q, $page); ?></th>
                    <th><?php echo sortLink("Email", "email", $sort, $dir, $q, $page); ?></th>
                    <th>Phone</th>
                    <th><?php echo sortLink("Program", "program", $sort, $dir, $q, $page); ?></th>
                    <th><?php echo sortLink("School", "school", $sort, $dir, $q, $page); ?></th>
                    <th><?php echo sortLink("Nationality", "nationality", $sort, $dir, $q, $page); ?></th>
                    <th><?php echo sortLink("Status", "status", $sort, $dir, $q, $page); ?></th>
                    <th style="width:160px;">Actions</th>

                </tr>
                </thead>

                <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="10" class="text-muted">No students found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                        <?php
                        $sid = (int)$s['student_id'];
                        $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                        ?>
                        <tr>
                            <td><img class="avatar" src="<?php echo h(photoUrl($s['profile_photo'] ?? null)); ?>" alt="Photo"></td>
                            <td class="fw-semibold"><?php echo $sid; ?></td>
                            <td><?php echo h($name ?: '-'); ?></td>
                            <td><?php echo h($s['email'] ?? '-'); ?></td>
                            <td><?php echo h($s['phone'] ?? '-'); ?></td>
                            <td><?php echo h($s['program_name'] ?? '-'); ?></td>
                            <td><?php echo h($s['school_name'] ?? '-'); ?></td>
                            <td><?php echo h($s['primary_country'] ?? '-'); ?></td>
                            <td><span class="badge bg-dark"><?php echo h($s['status'] ?? '-'); ?></span></td>

                            <td class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$page,"sort"=>$sort,"dir"=>strtolower($dir),"view_id"=>$sid])); ?>">
                                    View
                                </a>

                                <a class="btn btn-sm btn-outline-success"
                                   href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$page,"sort"=>$sort,"dir"=>strtolower($dir),"edit_id"=>$sid])); ?>">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></div>

                <div class="d-flex gap-2">
                    <?php $prev = max(1, $page - 1); $next = min($totalPages, $page + 1); ?>
                    <a class="btn btn-sm btn-outline-secondary <?php echo ($page <= 1) ? 'disabled' : ''; ?>"
                       href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$prev,"sort"=>$sort,"dir"=>strtolower($dir)])); ?>">
                        Prev
                    </a>
                    <a class="btn btn-sm btn-outline-secondary <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>"
                       href="students.php?<?php echo h(buildQueryString(["q"=>$q,"page"=>$next,"sort"=>$sort,"dir"=>strtolower($dir)])); ?>">
                        Next
                    </a>
                </div>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
