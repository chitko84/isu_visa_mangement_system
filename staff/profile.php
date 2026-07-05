<?php
// staff/profile.php

$page_title = "My Profile - ISU Staff Portal";
require_once __DIR__ . "/header.php"; // provides $conn and $staff_id

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
// Upload folder (inside /staff/uploads/profile/)
// ------------------------------------------------------------
$uploadDirRel = "uploads/profile/";            // relative to /staff/
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
        $stmt = $conn->prepare("SELECT profile_photo FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc()['profile_photo'] ?? null;
        $stmt->close();

        if ($old) {
            $oldAbs = __DIR__ . "/" . ltrim($old, "/");
            if (is_file($oldAbs)) @unlink($oldAbs);
        }

        // Save new file
        $newName = "staff_" . (int)$staff_id . "_" . date("Ymd_His") . "." . $ext;
        $destAbs = $uploadDirAbs . $newName;

        if (file_put_contents($destAbs, $bin) === false) {
            throw new Exception("Failed to save image to disk.");
        }

        // Store relative path in DB (relative to /staff/)
        $pathToStore = $uploadDirRel . $newName;

        $stmt = $conn->prepare("UPDATE staff SET profile_photo = ? WHERE staff_id = ?");
        $stmt->bind_param("si", $pathToStore, $staff_id);
        $stmt->execute();
        $stmt->close();

        $success = "Profile picture updated successfully.";
        log_audit($conn, 'staff_updated_profile_photo', 'staff', $staff_id, 'Staff updated profile photo.');

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ------------------------------------------------------------
// Load staff profile
// ------------------------------------------------------------
$profile = null;
try {
    $stmt = $conn->prepare("
        SELECT staff_id, first_name, last_name, email, phone, role, department, status, profile_photo
        FROM staff
        WHERE staff_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();

    $res = $stmt->get_result();
    if ($res) {
        $profile = $res->fetch_assoc();
        $res->free();
    }
    $stmt->close();
    clearStoredResults($conn);
} catch (Throwable $e) {
    $error = $error ?: ("Profile load error: " . $e->getMessage());
    clearStoredResults($conn);
}

// If somehow still null, avoid warnings
$profile = $profile ?: [
    'staff_id' => $staff_id,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'role' => '',
    'department' => '',
    'status' => '',
    'profile_photo' => null
];

// ------------------------------------------------------------
// Profile photo path
// ------------------------------------------------------------
$profilePhoto = $profile['profile_photo'] ?? null;

if ($profilePhoto) {
    if (preg_match('/^https?:\/\//i', $profilePhoto)) {
        $photoUrl = $profilePhoto;
    } else {
        $photoUrl = $profilePhoto; // like "uploads/profile/xx.png" -> loads from staff/...
    }
} else {
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
            <div class="text-muted">View your staff information.</div>
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
                <div class="card-header fw-semibold">Staff Details</div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="small text-muted">Staff ID</div>
                        <div class="fw-semibold"><?php echo (int)$profile['staff_id']; ?></div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Name</div>
                        <div class="fw-semibold">
                            <?php echo h(($profile['first_name'] ?? '') . " " . ($profile['last_name'] ?? '')); ?>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold"><?php echo h($profile['email'] ?? '-'); ?></div>
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
                        <div class="small text-muted">Role</div>
                        <div class="fw-semibold"><?php echo h($profile['role'] ?? '-'); ?></div>
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Department</div>
                        <div class="fw-semibold"><?php echo h($profile['department'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header fw-semibold">Work Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Role</div>
                            <div class="fw-semibold"><?php echo h($profile['role'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Department</div>
                            <div class="fw-semibold"><?php echo h($profile['department'] ?? '-'); ?></div>
                        </div>
                    </div>
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

    modalEl.addEventListener('shown.bs.modal', function onShown() {
        modalEl.removeEventListener('shown.bs.modal', onShown);

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

    // Use PNG so PHP regex always matches
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
