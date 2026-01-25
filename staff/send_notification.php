<?php
// staff/send_notification.php
// Updated to match your notifications table:
// notifications(notification_id PK, student_id, title, message, is_read, created_at)

$page_title = "Send Notification - ISU Staff Portal";
require_once __DIR__ . "/header.php"; // provides $conn, $staff_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Fetch filter options (program, school) - optional
// If you don't have these tables/columns, you can remove the filter UI.
// ------------------------------------------------------------
$programs = [];
$schools  = [];

try {
    $res = $conn->query("SELECT program_id, program_name FROM program ORDER BY program_name");
    if ($res) { $programs = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }

    $res = $conn->query("SELECT school_id, school_name FROM school ORDER BY school_name");
    if ($res) { $schools = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
} catch (Throwable $e) {
    // ignore
}

// ------------------------------------------------------------
// Handle Send Notification POST
// Tables used: notifications (insert), student (select recipients)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title   = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");
    $target  = $_POST["target"] ?? "all"; // all | by_program | by_school | by_students

    $program_id = (int)($_POST["program_id"] ?? 0);
    $school_id  = (int)($_POST["school_id"] ?? 0);

    $student_ids = $_POST["student_ids"] ?? [];
    if (!is_array($student_ids)) $student_ids = [];

    if ($title === "" || $message === "") {
        $error = "Title and message are required.";
    } else {
        try {
            // 1) Build recipient list
            $recipients = [];

            if ($target === "all") {
                $stmt = $conn->prepare("SELECT student_id FROM student");
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $recipients[] = (int)$r["student_id"];
                }
                if ($res) $res->free();
                $stmt->close();
                clearStoredResults($conn);

            } elseif ($target === "by_program") {
                if ($program_id <= 0) throw new Exception("Please select a program.");
                // Adjust column name if different in your DB
                $stmt = $conn->prepare("SELECT student_id FROM student WHERE program_id = ?");
                $stmt->bind_param("i", $program_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $recipients[] = (int)$r["student_id"];
                }
                if ($res) $res->free();
                $stmt->close();
                clearStoredResults($conn);

            } elseif ($target === "by_school") {
                if ($school_id <= 0) throw new Exception("Please select a school.");
                // If student table doesn't have school_id directly, you need a JOIN via program table
                $stmt = $conn->prepare("SELECT student_id FROM student WHERE school_id = ?");
                $stmt->bind_param("i", $school_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $recipients[] = (int)$r["student_id"];
                }
                if ($res) $res->free();
                $stmt->close();
                clearStoredResults($conn);

            } elseif ($target === "by_students") {
                $clean = [];
                foreach ($student_ids as $sid) {
                    $sid = (int)$sid;
                    if ($sid > 0) $clean[] = $sid;
                }
                $recipients = array_values(array_unique($clean));
                if (!$recipients) throw new Exception("Please select at least one student.");

            } else {
                throw new Exception("Invalid target.");
            }

            if (!$recipients) {
                throw new Exception("No recipients found for this selection.");
            }

            // 2) Insert notifications (MATCHES YOUR TABLE)
            // Columns you have: student_id, title, message, is_read (default 0), created_at (auto)
            $conn->begin_transaction();

            $sql = "INSERT INTO notifications (student_id, title, message, is_read)
                    VALUES (?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);

            foreach ($recipients as $sid) {
                $stmt->bind_param("iss", $sid, $title, $message);
                if (!$stmt->execute()) {
                    throw new Exception("Insert failed for student_id {$sid}: " . $stmt->error);
                }
            }

            $stmt->close();
            $conn->commit();

            $success = "Notification sent to " . count($recipients) . " student(s).";

        } catch (Throwable $e) {
            // rollback if transaction started
            if ($conn->errno) {
                $conn->rollback();
            } else {
                // safer rollback attempt anyway
                try { $conn->rollback(); } catch (Throwable $t) {}
            }
            $error = $e->getMessage();
            clearStoredResults($conn);
        }
    }
}

// ------------------------------------------------------------
// Load students list (for manual selection UI)
// ------------------------------------------------------------
$students = [];
try {
    $res = $conn->query("SELECT student_id, first_name, last_name, email FROM student ORDER BY first_name, last_name");
    if ($res) { $students = $res->fetch_all(MYSQLI_ASSOC); $res->free(); }
} catch (Throwable $e) {
    // ignore
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Send Notification</h3>
            <div class="text-muted">Send messages to students (all / by program / by school / selected students)</div>
        </div>
        <a href="notifications.php" class="btn btn-outline-secondary">
            <i class="bi bi-bell"></i> View Sent Notifications
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Compose Notification</strong>
        </div>
        <div class="card-body">
            <form method="post" id="sendForm" class="row g-3">

                <div class="col-12">
                    <label class="form-label">Title *</label>
                    <input class="form-control" name="title" required value="<?php echo h($_POST['title'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Message *</label>
                    <textarea class="form-control" name="message" rows="5" required><?php echo h($_POST['message'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Send To *</label>
                    <select class="form-select" name="target" id="targetSelect">
                        <option value="all" <?php echo (($_POST['target'] ?? 'all') === 'all') ? 'selected' : ''; ?>>All Students</option>
                        <option value="by_program" <?php echo (($_POST['target'] ?? '') === 'by_program') ? 'selected' : ''; ?>>By Program</option>
                        <option value="by_school" <?php echo (($_POST['target'] ?? '') === 'by_school') ? 'selected' : ''; ?>>By School</option>
                        <option value="by_students" <?php echo (($_POST['target'] ?? '') === 'by_students') ? 'selected' : ''; ?>>Selected Students</option>
                    </select>
                    <small class="text-muted">Tip: Use “Selected Students” for a small group.</small>
                </div>

                <!-- Program filter -->
                <div class="col-md-6" id="programBox" style="display:none;">
                    <label class="form-label">Program</label>
                    <select class="form-select" name="program_id">
                        <option value="0">-- Select Program --</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?php echo (int)$p['program_id']; ?>"
                                <?php echo ((int)($_POST['program_id'] ?? 0) === (int)$p['program_id']) ? 'selected' : ''; ?>>
                                <?php echo h($p['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- School filter -->
                <div class="col-md-6" id="schoolBox" style="display:none;">
                    <label class="form-label">School</label>
                    <select class="form-select" name="school_id">
                        <option value="0">-- Select School --</option>
                        <?php foreach ($schools as $s): ?>
                            <option value="<?php echo (int)$s['school_id']; ?>"
                                <?php echo ((int)($_POST['school_id'] ?? 0) === (int)$s['school_id']) ? 'selected' : ''; ?>>
                                <?php echo h($s['school_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Manual student selection -->
                <div class="col-12" id="studentsBox" style="display:none;">
                    <label class="form-label">Select Students</label>
                    <div class="border rounded p-2" style="max-height:260px; overflow:auto;">
                        <?php if (!$students): ?>
                            <div class="text-muted p-2">No students found.</div>
                        <?php else: ?>
                            <?php
                            $selected = $_POST['student_ids'] ?? [];
                            if (!is_array($selected)) $selected = [];
                            $selected = array_map('strval', $selected);
                            ?>
                            <?php foreach ($students as $st): ?>
                                <?php
                                $sid = (string)$st['student_id'];
                                $checked = in_array($sid, $selected, true) ? "checked" : "";
                                $label = trim(($st['first_name'] ?? '') . " " . ($st['last_name'] ?? ''));
                                $email = $st['email'] ?? '';
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="student_ids[]"
                                           value="<?php echo h($sid); ?>" id="sid_<?php echo h($sid); ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="sid_<?php echo h($sid); ?>">
                                        <?php echo h($label ?: ("Student " . $sid)); ?>
                                        <span class="text-muted small"><?php echo $email ? " - " . h($email) : ""; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Scroll and tick multiple students.</small>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" onclick="return confirm('Send this notification now?');">
                        <i class="bi bi-send"></i> Send Notification
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
(function () {
    const sel = document.getElementById('targetSelect');
    const programBox = document.getElementById('programBox');
    const schoolBox  = document.getElementById('schoolBox');
    const studentsBox= document.getElementById('studentsBox');

    function updateUI() {
        const v = sel.value;
        programBox.style.display  = (v === 'by_program') ? '' : 'none';
        schoolBox.style.display   = (v === 'by_school')  ? '' : 'none';
        studentsBox.style.display = (v === 'by_students')? '' : 'none';
    }

    sel.addEventListener('change', updateUI);
    updateUI();
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
