<?php
// student/student.php
$page_title = "Student Profile - ISU";
require_once __DIR__ . "/header.php"; // provides $conn, $student_id, etc.

// -----------------------------
// Helpers
// -----------------------------
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = "";
$error = "";

// Load countries for dropdown (for nationality add/edit)
$countries = [];
$country_rs = $conn->query("SELECT country_id, country_name, region FROM country ORDER BY country_name ASC");
if ($country_rs) {
    while ($row = $country_rs->fetch_assoc()) $countries[] = $row;
}

// -----------------------------
// Handle POST actions
// -----------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // 1) Update student master record (editable fields)
    if ($action === "update_student") {
        $first_name = trim($_POST["first_name"] ?? "");
        $last_name  = trim($_POST["last_name"] ?? "");
        $phone      = trim($_POST["phone"] ?? "");
        $email      = trim($_POST["email"] ?? "");
        $status     = trim($_POST["status"] ?? "Active");
        $program_id = (int)($_POST["program_id"] ?? 0);

        if ($first_name === "" || $last_name === "" || $email === "" || $program_id <= 0) {
            $error = "Please fill in required fields (First Name, Last Name, Email, Program).";
        } else {
            // Students normally should NOT update program/status, but for assignment demo you can allow.
            // If you want to restrict: remove program/status from the form and update only phone/email.
            $stmt = $conn->prepare("
                UPDATE student
                SET program_id=?, first_name=?, last_name=?, phone=?, email=?, status=?
                WHERE student_id=?
            ");
            $stmt->bind_param("isssssi", $program_id, $first_name, $last_name, $phone, $email, $status, $student_id);

            if ($stmt->execute()) {
                $success = "Student profile updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // 2) Update subtype info (PC/UG/PG)
    if ($action === "update_subtype") {
        $student_type = $_POST["student_type"] ?? "";

        if ($student_type === "PC") {
            $guardian_name = trim($_POST["guardian_name"] ?? "");
            $guardian_contact = trim($_POST["guardian_contact"] ?? "");
            $placement_test_score = ($_POST["placement_test_score"] === "" ? null : (int)$_POST["placement_test_score"]);

            if ($guardian_name === "" || $guardian_contact === "") {
                $error = "Guardian name and guardian contact are required for Pre-College.";
            } else {
                // Upsert pre_college
                $stmt = $conn->prepare("
                    INSERT INTO pre_college(student_id, guardian_name, guardian_contact, placement_test_score)
                    VALUES(?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        guardian_name=VALUES(guardian_name),
                        guardian_contact=VALUES(guardian_contact),
                        placement_test_score=VALUES(placement_test_score)
                ");
                // placement_test_score can be null => use i with null? easiest: bind as string
                $pts = $placement_test_score;
                $stmt->bind_param("issi", $student_id, $guardian_name, $guardian_contact, $pts);

                if ($stmt->execute()) {
                    $success = "Pre-College details updated.";
                } else {
                    $error = "Update failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }

        if ($student_type === "UG") {
            $high_school_name = trim($_POST["high_school_name"] ?? "");
            $admission_score  = ($_POST["admission_score"] === "" ? null : (float)$_POST["admission_score"]);
            $scholarship_flag = isset($_POST["scholarship_flag"]) ? 1 : 0;

            // Upsert undergraduate
            $stmt = $conn->prepare("
                INSERT INTO undergraduate(student_id, high_school_name, admission_score, scholarship_flag)
                VALUES(?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    high_school_name=VALUES(high_school_name),
                    admission_score=VALUES(admission_score),
                    scholarship_flag=VALUES(scholarship_flag)
            ");
            $stmt->bind_param("isdi", $student_id, $high_school_name, $admission_score, $scholarship_flag);

            if ($stmt->execute()) {
                $success = "Undergraduate details updated.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        }

        if ($student_type === "PG") {
            $previous_degree = trim($_POST["previous_degree"] ?? "");
            $supervisor_name = trim($_POST["supervisor_name"] ?? "");
            $thesis_required = isset($_POST["thesis_required"]) ? 1 : 0;

            if ($previous_degree === "") {
                $error = "Previous degree is required for Postgraduate.";
            } else {
                // Upsert post_graduate
                $stmt = $conn->prepare("
                    INSERT INTO post_graduate(student_id, previous_degree, supervisor_name, thesis_required)
                    VALUES(?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        previous_degree=VALUES(previous_degree),
                        supervisor_name=VALUES(supervisor_name),
                        thesis_required=VALUES(thesis_required)
                ");
                $stmt->bind_param("issi", $student_id, $previous_degree, $supervisor_name, $thesis_required);

                if ($stmt->execute()) {
                    $success = "Postgraduate details updated.";
                } else {
                    $error = "Update failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // 3) Add nationality (repeating items)
    if ($action === "add_nationality") {
        $country_id = (int)($_POST["country_id"] ?? 0);
        $acquired_date = $_POST["acquired_date"] ?? null;
        $is_primary = isset($_POST["is_primary"]) ? 1 : 0;

        if ($country_id <= 0) {
            $error = "Please select a country.";
        } else {
            // If set primary, unset others
            if ($is_primary === 1) {
                $stmt = $conn->prepare("UPDATE nationality SET is_primary=0 WHERE student_id=?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare("
                INSERT INTO nationality(student_id, country_id, acquired_date, is_primary)
                VALUES(?,?,?,?)
            ");
            $stmt->bind_param("iisi", $student_id, $country_id, $acquired_date, $is_primary);

            if ($stmt->execute()) {
                $success = "Nationality added.";
            } else {
                $error = "Add failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // 4) Update nationality row
    if ($action === "update_nationality") {
        $country_id_old = (int)($_POST["country_id_old"] ?? 0);
        $country_id_new = (int)($_POST["country_id_new"] ?? 0);
        $acquired_date  = $_POST["acquired_date"] ?? null;
        $is_primary     = isset($_POST["is_primary"]) ? 1 : 0;

        if ($country_id_old <= 0 || $country_id_new <= 0) {
            $error = "Invalid nationality update request.";
        } else {
            if ($is_primary === 1) {
                $stmt = $conn->prepare("UPDATE nationality SET is_primary=0 WHERE student_id=?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $stmt->close();
            }

            // If country changes, easiest: delete old + insert new
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM nationality WHERE student_id=? AND country_id=?");
                $stmt->bind_param("ii", $student_id, $country_id_old);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    INSERT INTO nationality(student_id, country_id, acquired_date, is_primary)
                    VALUES(?,?,?,?)
                ");
                $stmt->bind_param("iisi", $student_id, $country_id_new, $acquired_date, $is_primary);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $success = "Nationality updated.";
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }

    // 5) Delete nationality row
    if ($action === "delete_nationality") {
        $country_id = (int)($_POST["country_id"] ?? 0);

        if ($country_id <= 0) {
            $error = "Invalid delete request.";
        } else {
            $stmt = $conn->prepare("DELETE FROM nationality WHERE student_id=? AND country_id=?");
            $stmt->bind_param("ii", $student_id, $country_id);

            if ($stmt->execute()) {
                $success = "Nationality deleted.";
            } else {
                $error = "Delete failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// -----------------------------
// Fetch page data using Procedure (3 result sets)
// -----------------------------
$core = null;
$subtype = null;
$nationalities = [];

$proc_sql = "CALL sp_get_student_profile($student_id)";
if ($conn->multi_query($proc_sql)) {
    // Result set #1: core
    if ($result = $conn->store_result()) {
        $core = $result->fetch_assoc();
        $result->free();
    }
    // Next result
    if ($conn->more_results()) { $conn->next_result(); }

    // Result set #2: subtype union
    if ($result = $conn->store_result()) {
        // Could return 0-3 rows; we'll pick the row matching student_type best later
        $rows = [];
        while ($r = $result->fetch_assoc()) $rows[] = $r;
        $result->free();

        // pick best subtype row (based on student_type)
        $student_type = $core["student_type"] ?? "";
        $want = ($student_type === "PC") ? "pre_college" : (($student_type === "UG") ? "undergraduate" : "post_graduate");
        foreach ($rows as $r) {
            if (($r["subtype"] ?? "") === $want) { $subtype = $r; break; }
        }
    }
    if ($conn->more_results()) { $conn->next_result(); }

    // Result set #3: nationalities
    if ($result = $conn->store_result()) {
        while ($r = $result->fetch_assoc()) $nationalities[] = $r;
        $result->free();
    }

    // Clear any remaining results
    while ($conn->more_results() && $conn->next_result()) {
        $tmp = $conn->store_result();
        if ($tmp) $tmp->free();
    }
} else {
    $error = "Procedure call failed: " . $conn->error;
}

// If core is missing, fallback query (should not happen once procedure fixed)
if (!$core) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.first_name, s.last_name, s.phone, s.email, s.status, s.student_type,
               p.program_id, p.program_name, sc.school_id, sc.school_name
        FROM student s
        JOIN program p ON p.program_id=s.program_id
        JOIN school sc ON sc.school_id=p.school_id
        WHERE s.student_id=?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $core = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Programs for dropdown
$programs = [];
$prog_rs = $conn->query("
    SELECT p.program_id, p.program_name, p.level, s.school_name
    FROM program p
    JOIN school s ON s.school_id=p.school_id
    ORDER BY p.program_name ASC
");
if ($prog_rs) {
    while ($row = $prog_rs->fetch_assoc()) $programs[] = $row;
}

$student_type = $core["student_type"] ?? "PC";
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Student Module</h3>
            <div class="text-muted">Core student profile (master) + repeating nationalities</div>
        </div>
        <div class="text-muted">
            <span class="badge bg-secondary">Student ID: <?php echo (int)$student_id; ?></span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <!-- MASTER (Header) SECTION -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>Student Master Profile</strong>
                <small class="text-muted">Header (Master) record</small>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="update_student">

                <div class="col-md-2">
                    <label class="form-label">Student ID</label>
                    <input class="form-control" value="<?php echo (int)$core["student_id"]; ?>" disabled>
                </div>

                <div class="col-md-5">
                    <label class="form-label">First Name *</label>
                    <input name="first_name" class="form-control" required value="<?php echo h($core["first_name"] ?? ""); ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label">Last Name *</label>
                    <input name="last_name" class="form-control" required value="<?php echo h($core["last_name"] ?? ""); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input name="phone" class="form-control" value="<?php echo h($core["phone"] ?? ""); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Email *</label>
                    <input name="email" type="email" class="form-control" required value="<?php echo h($core["email"] ?? ""); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php
                        $statuses = ["Active","Graduated","Inactive"];
                        foreach ($statuses as $st):
                            $sel = (($core["status"] ?? "") === $st) ? "selected" : "";
                        ?>
                            <option value="<?php echo h($st); ?>" <?php echo $sel; ?>><?php echo h($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Student Type</label>
                    <input class="form-control" value="<?php echo h($student_type); ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Program *</label>
                    <select name="program_id" class="form-select" required>
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?php echo (int)$p["program_id"]; ?>"
                                <?php echo ((int)($core["program_id"] ?? 0) === (int)$p["program_id"]) ? "selected" : ""; ?>>
                                <?php echo h($p["program_name"] . " (" . $p["level"] . ") - " . $p["school_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">School</label>
                    <input class="form-control" value="<?php echo h($core["school_name"] ?? ""); ?>" disabled>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Master Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SUBTYPE SECTION -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>Student Type Details</strong>
            <small class="text-muted ms-2">(Subtype: <?php echo h($student_type); ?>)</small>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="update_subtype">
                <input type="hidden" name="student_type" value="<?php echo h($student_type); ?>">

                <?php if ($student_type === "PC"): ?>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Name *</label>
                        <input name="guardian_name" class="form-control" required value="<?php echo h($subtype["col1"] ?? ""); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Contact *</label>
                        <input name="guardian_contact" class="form-control" required value="<?php echo h($subtype["col2"] ?? ""); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Placement Test Score</label>
                        <input name="placement_test_score" type="number" class="form-control" value="<?php echo h($subtype["col3"] ?? ""); ?>">
                    </div>

                <?php elseif ($student_type === "UG"): ?>
                    <div class="col-md-6">
                        <label class="form-label">High School Name</label>
                        <input name="high_school_name" class="form-control" value="<?php echo h($subtype["col1"] ?? ""); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Admission Score</label>
                        <input name="admission_score" type="number" step="0.01" class="form-control" value="<?php echo h($subtype["col3"] ?? ""); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <?php
                            // We didn't include scholarship_flag in procedure union; load directly for accuracy
                            $sf = 0;
                            $sf_stmt = $conn->prepare("SELECT scholarship_flag FROM undergraduate WHERE student_id=?");
                            $sf_stmt->bind_param("i", $student_id);
                            $sf_stmt->execute();
                            $sf_row = $sf_stmt->get_result()->fetch_assoc();
                            $sf_stmt->close();
                            $sf = (int)($sf_row["scholarship_flag"] ?? 0);
                            ?>
                            <input class="form-check-input" type="checkbox" name="scholarship_flag" id="scholarship_flag" <?php echo $sf ? "checked" : ""; ?>>
                            <label class="form-check-label" for="scholarship_flag">Scholarship</label>
                        </div>
                    </div>

                <?php else: /* PG */ ?>
                    <div class="col-md-6">
                        <label class="form-label">Previous Degree *</label>
                        <input name="previous_degree" class="form-control" required value="<?php echo h($subtype["col1"] ?? ""); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supervisor Name</label>
                        <input name="supervisor_name" class="form-control" value="<?php echo h($subtype["col2"] ?? ""); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <?php
                            $tr = 0;
                            $tr_stmt = $conn->prepare("SELECT thesis_required FROM post_graduate WHERE student_id=?");
                            $tr_stmt->bind_param("i", $student_id);
                            $tr_stmt->execute();
                            $tr_row = $tr_stmt->get_result()->fetch_assoc();
                            $tr_stmt->close();
                            $tr = (int)($tr_row["thesis_required"] ?? 0);
                            ?>
                            <input class="form-check-input" type="checkbox" name="thesis_required" id="thesis_required" <?php echo $tr ? "checked" : ""; ?>>
                            <label class="form-check-label" for="thesis_required">Thesis Required</label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-save"></i> Save Subtype Details
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- REPEATING ITEMS: NATIONALITIES -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>Nationalities (Repeating Items)</strong>
                <small class="text-muted">One student can have many nationalities</small>
            </div>
        </div>

        <div class="card-body">
            <!-- Add nationality -->
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="action" value="add_nationality">
                <div class="col-md-5">
                    <label class="form-label">Country *</label>
                    <select name="country_id" class="form-select" required>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo (int)$c["country_id"]; ?>">
                                <?php echo h($c["country_name"] . " (" . $c["region"] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Acquired Date</label>
                    <input type="date" name="acquired_date" class="form-control">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary_add">
                        <label class="form-check-label" for="is_primary_add">Primary</label>
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-success w-100">
                        <i class="bi bi-plus-circle"></i> Add
                    </button>
                </div>
            </form>

            <!-- List nationalities -->
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead style="background-color: #0d6efd; color: white;">
                        <tr>
                            <th style="width: 30%;">Country</th>
                            <th style="width: 20%;">Region</th>
                            <th style="width: 20%;">Acquired Date</th>
                            <th style="width: 10%;">Primary</th>
                            <th style="width: 20%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($nationalities) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted">No nationality records found.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($nationalities as $n): ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_nationality">
                                    <input type="hidden" name="country_id_old" value="<?php echo (int)$n["country_id"]; ?>">

                                    <td>
                                        <select name="country_id_new" class="form-select">
                                            <?php foreach ($countries as $c): ?>
                                                <option value="<?php echo (int)$c["country_id"]; ?>"
                                                    <?php echo ((int)$c["country_id"] === (int)$n["country_id"]) ? "selected" : ""; ?>>
                                                    <?php echo h($c["country_name"]); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td><?php echo h($n["region"] ?? ""); ?></td>

                                    <td>
                                        <input type="date" name="acquired_date" class="form-control"
                                               value="<?php echo h($n["acquired_date"] ?? ""); ?>">
                                    </td>

                                    <td class="text-center">
                                        <input type="checkbox" name="is_primary" <?php echo ((int)$n["is_primary"] === 1) ? "checked" : ""; ?>>
                                    </td>

                                    <td class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary">
                                            <i class="bi bi-save"></i> Update
                                        </button>
                                </form>

                                <form method="POST" onsubmit="return confirm('Delete this nationality?');">
                                    <input type="hidden" name="action" value="delete_nationality">
                                    <input type="hidden" name="country_id" value="<?php echo (int)$n["country_id"]; ?>">
                                    <button class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . "/footer.php"; ?>
