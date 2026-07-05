<?php
// student/profile.php

$page_title = "My Profile - ISU Student Portal";
require_once __DIR__ . "/header.php"; // session + db ($conn) + $student_id

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function clearStoredResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$success = "";
$error   = "";

// ------------------------------------------------------------
// Upload folder (inside /student/uploads/profile/)
// ------------------------------------------------------------
$uploadDirRel = "uploads/profile/";            // relative to /student/
$uploadDirAbs = __DIR__ . "/" . $uploadDirRel; // absolute path

if (!is_dir($uploadDirAbs)) {
    @mkdir($uploadDirAbs, 0775, true);
}

// ------------------------------------------------------------
// Handle profile photo save (base64 PNG from Cropper.js)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_cropped_profile_photo') {
    try {
        require_csrf();
        $img = trim($_POST['cropped_image'] ?? '');
        if ($img === '') {
            throw new Exception("No cropped image received.");
        }

        // Expect: data:image/png;base64,....
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $img, $m)) {
            throw new Exception("Invalid image data.");
        }

        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
        $allowedExt = ['png','jpg','webp'];
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception("Invalid image type.");
        }

        $imgB64 = preg_replace('/^data:image\/(png|jpeg|jpg|webp);base64,/', '', $img);
        $bin = base64_decode($imgB64, true);
        if ($bin === false) {
            throw new Exception("Failed to decode image.");
        }

        // size guard (3MB)
        if (strlen($bin) > 3 * 1024 * 1024) {
            throw new Exception("Cropped image too large (max ~3MB).");
        }

        // Remove old photo if exists
        $old = null;
        $stmt = $conn->prepare("SELECT profile_photo FROM student WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
        $stmt->close();

        if ($old) {
            // old is stored like "uploads/profile/xxx.png"
            $oldAbs = __DIR__ . "/" . ltrim($old, "/");
            if (is_file($oldAbs)) @unlink($oldAbs);
        }

        // Save new file
        $newName = "stu_" . (int)$student_id . "_" . date("Ymd_His") . "." . $ext;
        $destAbs = $uploadDirAbs . $newName;

        if (file_put_contents($destAbs, $bin) === false) {
            throw new Exception("Failed to save image to disk.");
        }

        // Store relative path in DB (relative to /student/)
        $pathToStore = $uploadDirRel . $newName;

        $stmt = $conn->prepare("UPDATE student SET profile_photo = ? WHERE student_id = ?");
        $stmt->bind_param("si", $pathToStore, $student_id);
        $stmt->execute();
        $stmt->close();

        $success = "Profile picture updated successfully.";
        log_audit($conn, 'student_updated_profile_photo', 'student', $student_id, 'Student updated profile photo.');

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ------------------------------------------------------------
// Load profile using stored procedure: sp_get_student_profile
// ------------------------------------------------------------
$profile = null;
try {
    $stmt = $conn->prepare("CALL sp_get_student_profile(?)");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    $res1 = $stmt->get_result();
    if ($res1) {
        $profile = $res1->fetch_assoc();
        $res1->free();
    }
    $stmt->close();
    clearStoredResults($conn);
} catch (Throwable $e) {
    $error = $error ?: ("Profile load error: " . $e->getMessage());
    clearStoredResults($conn);
}

// ------------------------------------------------------------
// Nationality list (student -> nationality -> country)
// ------------------------------------------------------------
$nationalities = [];
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
} catch (Throwable $e) {
    $error = $error ?: ("Nationality load error: " . $e->getMessage());
}

// ------------------------------------------------------------
// Academic dates (program_id specific OR global)
// ------------------------------------------------------------
$academicDates = [];
$programId = isset($profile['program_id']) ? (int)$profile['program_id'] : 0;

try {
    if ($programId > 0) {
        $stmt = $conn->prepare("
            SELECT id, event_name, date, program_id, academic_year, description
            FROM academic_dates
            WHERE program_id = ? OR program_id IS NULL
            ORDER BY date ASC, id ASC
            LIMIT 200
        ");
        $stmt->bind_param("i", $programId);
    } else {
        $stmt = $conn->prepare("
            SELECT id, event_name, date, program_id, academic_year, description
            FROM academic_dates
            WHERE program_id IS NULL
            ORDER BY date ASC, id ASC
            LIMIT 200
        ");
    }
    $stmt->execute();
    $academicDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    $error = $error ?: ("Academic dates load error: " . $e->getMessage());
}

// ------------------------------------------------------------
// Profile photo path
// ------------------------------------------------------------
$profilePhoto = null;
try {
    $stmt = $conn->prepare("SELECT profile_photo FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $profilePhoto = $stmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
    $stmt->close();
} catch (Throwable $e) {}

// Correct URL for photo (stored relative to /student/)
if ($profilePhoto) {
    if (preg_match('/^https?:\/\//i', $profilePhoto)) {
        $photoUrl = $profilePhoto;
    } else {
        $photoUrl = $profilePhoto; // like "uploads/profile/xx.png" -> loads from student/...
    }
} else {
    // Default profile image inside /student/uploads/
    $photoUrl = "uploads/default_image.png";
}

?>

<!-- Cropper.js -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<style>
    .profile-photo-preview {
        width: 170px;
        height: 170px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
        background: #fff;
    }

    /* Cropper preview MUST be a DIV */
    .crop-preview-container {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto;
        border: 2px solid #ddd;
        background-color: #f8f9fa;
    }
    .crop-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    #editorImage {
        max-width: 100%;
        max-height: 420px;
        display: block;
    }
</style>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-0">My Profile</h2>
            <div class="text-muted">View your personal and academic information.</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Left -->
        <div class="col-lg-4">

            <div class="card">
                <div class="card-header fw-semibold">Profile Picture</div>
                <div class="card-body text-center">

                    <img id="currentProfilePhoto"
                         src="<?php echo h($photoUrl); ?>?v=<?php echo time(); ?>"
                         alt="Profile Photo"
                         class="profile-photo-preview mb-3">

                    <div class="text-muted small mb-3">
                        Choose a photo. Then edit (zoom + rotate + crop) and save.
                    </div>

                    <input type="file"
                           id="pickPhoto"
                           class="form-control"
                           accept="image/png,image/jpeg,image/webp">

                    <form method="post" id="saveCroppedForm" class="mt-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_cropped_profile_photo">
                        <input type="hidden" name="cropped_image" id="cropped_image">
                        <button type="button" id="openEditorBtn" class="btn btn-primary w-100" disabled>
                            Edit & Crop
                        </button>
                    </form>

                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header fw-semibold">Personal Details</div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="small text-muted">Student ID</div>
                        <div class="fw-semibold"><?php echo (int)$student_id; ?></div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Name</div>
                        <div class="fw-semibold">
                            <?php
                            echo h(
                                ($profile['first_name'] ?? $student['first_name'] ?? '') . " " .
                                ($profile['last_name'] ?? $student['last_name'] ?? '')
                            );
                            ?>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold"><?php echo h($profile['email'] ?? $student['email'] ?? ''); ?></div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Phone</div>
                        <div class="fw-semibold"><?php echo h($profile['phone'] ?? '-'); ?></div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Status</div>
                        <span class="badge bg-dark"><?php echo h($profile['status'] ?? '-'); ?></span>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Student Type</div>
                        <div class="fw-semibold"><?php echo h($profile['student_type'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right -->
        <div class="col-lg-8">

            <div class="card">
                <div class="card-header fw-semibold">Academic Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Program</div>
                            <div class="fw-semibold"><?php echo h($profile['program_name'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Level</div>
                            <div class="fw-semibold"><?php echo h($profile['level'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Faculty</div>
                            <div class="fw-semibold"><?php echo h($profile['faculty'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Duration (Years)</div>
                            <div class="fw-semibold"><?php echo h($profile['duration_years'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="small text-muted">School</div>
                            <div class="fw-semibold"><?php echo h($profile['school_name'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header fw-semibold">Nationality</div>
                <div class="card-body">
                    <?php if (!$nationalities): ?>
                        <div class="text-muted">No nationality records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Region</th>
                                    <th>Acquired Date</th>
                                    <th>Primary</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($nationalities as $n): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($n['country_name']); ?></td>
                                        <td><?php echo h($n['region'] ?? '-'); ?></td>
                                        <td><?php echo h($n['acquired_date'] ?? '-'); ?></td>
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
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header fw-semibold">Academic Calendar</div>
                <div class="card-body">
                    <?php if (!$academicDates): ?>
                        <div class="text-muted">No academic events found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Academic Year</th>
                                    <th>Description</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($academicDates as $e): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo h($e['date']); ?></td>
                                        <td><?php echo h($e['event_name']); ?></td>
                                        <td><?php echo h($e['academic_year']); ?></td>
                                        <td><?php echo h($e['description'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Modal: Image Editor -->
<div class="modal fade" id="photoEditorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Edit Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div style="max-height: 420px; overflow: hidden;">
                            <img id="editorImage" src="" alt="Editor">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <div class="small text-muted mb-2">Preview</div>
                            <!-- MUST be a DIV for cropper preview -->
                            <div class="crop-preview-container" id="cropPreview"></div>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-dark" id="zoomInBtn">Zoom In</button>
                            <button type="button" class="btn btn-outline-dark" id="zoomOutBtn">Zoom Out</button>

                            <button type="button" class="btn btn-outline-primary" id="rotateLeftBtn">Rotate Left</button>
                            <button type="button" class="btn btn-outline-primary" id="rotateRightBtn">Rotate Right</button>

                            <button type="button" class="btn btn-outline-secondary" id="resetBtn">Reset</button>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                Drag to move. Use zoom/rotate. Then click Save.
                            </small>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveCroppedBtn">Save Changes</button>
            </div>

        </div>
    </div>
</div>

<script>
let selectedFile = null;
let cropper = null;

const pickPhoto = document.getElementById('pickPhoto');
const openEditorBtn = document.getElementById('openEditorBtn');
const editorImage = document.getElementById('editorImage');
const croppedInput = document.getElementById('cropped_image');
const saveForm = document.getElementById('saveCroppedForm');

// pick file
pickPhoto.addEventListener('change', function (e) {
    const files = e.target.files;
    if (!files || files.length === 0) {
        selectedFile = null;
        openEditorBtn.disabled = true;
        return;
    }
    selectedFile = files[0];
    openEditorBtn.disabled = false;
});

// open modal
openEditorBtn.addEventListener('click', function () {
    if (!selectedFile) return;

    const url = URL.createObjectURL(selectedFile);
    editorImage.src = url;

    const modalEl = document.getElementById('photoEditorModal');
    const modal = new bootstrap.Modal(modalEl);

    // When modal is fully visible, THEN init cropper
    modalEl.addEventListener('shown.bs.modal', function onShown() {
        modalEl.removeEventListener('shown.bs.modal', onShown);

        // Wait for image to be ready
        editorImage.onload = function () {
            if (cropper) cropper.destroy();

            cropper = new Cropper(editorImage, {
                aspectRatio: 1,
                viewMode: 2,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                background: false,
                preview: '#cropPreview'
            });
        };

        // If image already cached/loaded
        if (editorImage.complete) {
            editorImage.onload();
        }
    });

    modal.show();
});


// controls
document.getElementById('zoomInBtn').addEventListener('click', () => cropper && cropper.zoom(0.1));
document.getElementById('zoomOutBtn').addEventListener('click', () => cropper && cropper.zoom(-0.1));
document.getElementById('rotateLeftBtn').addEventListener('click', () => cropper && cropper.rotate(-90));
document.getElementById('rotateRightBtn').addEventListener('click', () => cropper && cropper.rotate(90));
document.getElementById('resetBtn').addEventListener('click', () => cropper && cropper.reset());

// save
document.getElementById('saveCroppedBtn').addEventListener('click', function () {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({
        width: 300,
        height: 300,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });

    if (!canvas) {
        alert("Could not crop image. Please try again.");
        return;
    }

    // ✅ Use PNG so PHP regex always matches
    const base64 = canvas.toDataURL('image/png');
    croppedInput.value = base64;

    saveForm.submit();
});

// cleanup
document.getElementById('photoEditorModal').addEventListener('hidden.bs.modal', function () {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }

    // revoke blob url
    if (editorImage.src && editorImage.src.startsWith('blob:')) {
        URL.revokeObjectURL(editorImage.src);
    }

    editorImage.src = '';
    pickPhoto.value = '';
    selectedFile = null;
    openEditorBtn.disabled = true;
});
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
