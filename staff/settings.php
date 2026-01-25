<?php
// staff/settings.php

$page_title = "Staff Settings - ISU";
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

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = "";
$error   = "";

// ------------------------------------------------------------
// Upload folder (inside /staff/uploads/profile/)
// SAME AS staff/profile.php
// ------------------------------------------------------------
$uploadDirRel = "uploads/profile/";            // relative to /staff/
$uploadDirAbs = __DIR__ . "/" . $uploadDirRel; // absolute path

if (!is_dir($uploadDirAbs)) {
    @mkdir($uploadDirAbs, 0775, true);
}

// ------------------------------------------------------------
// Handle profile photo save (base64 from Cropper.js)  ✅ NEW
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_cropped_profile_photo') {
    try {
        $img = trim($_POST['cropped_image'] ?? '');
        if ($img === '') {
            throw new Exception("No cropped image received.");
        }

        // Expect: data:image/png|jpeg|jpg|webp;base64,....
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
        clearStoredResults($conn);

        if ($old && !preg_match('/^https?:\/\//i', $old)) {
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
        clearStoredResults($conn);

        $success = "Profile photo updated successfully.";

    } catch (Throwable $e) {
        $error = $e->getMessage();
        clearStoredResults($conn);
    }
}

// ------------------------------------------------------------
// Handle other POST actions (update_profile / change_password)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // 1) Update profile details
    if ($action === "update_profile") {
        $first_name  = trim($_POST["first_name"] ?? "");
        $last_name   = trim($_POST["last_name"] ?? "");
        $phone       = trim($_POST["phone"] ?? "");
        $department  = trim($_POST["department"] ?? "");

        if ($first_name === "" || $last_name === "") {
            $error = "First name and last name are required.";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE staff
                    SET first_name=?, last_name=?, phone=?, department=?
                    WHERE staff_id=?
                ");
                $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $department, $staff_id);

                if ($stmt->execute()) {
                    $success = $success ?: "Profile updated successfully.";
                } else {
                    $error = "Profile update failed: " . $stmt->error;
                }
                $stmt->close();
                clearStoredResults($conn);
            } catch (Throwable $e) {
                $error = "Profile update error: " . $e->getMessage();
                clearStoredResults($conn);
            }
        }
    }

    // 2) Change password
    if ($action === "change_password") {
        $current_password = (string)($_POST["current_password"] ?? "");
        $new_password     = (string)($_POST["new_password"] ?? "");
        $confirm_password = (string)($_POST["confirm_password"] ?? "");

        if ($new_password === "" || $confirm_password === "") {
            $error = "Please fill in the new password fields.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            try {
                $stmt = $conn->prepare("SELECT password FROM staff WHERE staff_id=? LIMIT 1");
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                clearStoredResults($conn);

                $hash = $row["password"] ?? null;

                $ok = true;
                if ($hash && $hash !== "") {
                    $ok = password_verify($current_password, $hash);
                }

                if (!$ok) {
                    $error = "Current password is incorrect.";
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE staff SET password=? WHERE staff_id=?");
                    $stmt->bind_param("si", $new_hash, $staff_id);

                    if ($stmt->execute()) {
                        $success = $success ?: "Password updated successfully.";
                    } else {
                        $error = "Password update failed: " . $stmt->error;
                    }
                    $stmt->close();
                    clearStoredResults($conn);
                }
            } catch (Throwable $e) {
                $error = "Password update error: " . $e->getMessage();
                clearStoredResults($conn);
            }
        }
    }
}

// ------------------------------------------------------------
// Load current staff info
// ------------------------------------------------------------
$staff = null;

try {
    $stmt = $conn->prepare("
        SELECT staff_id, first_name, last_name, email, role, department, phone, status, created_at, profile_photo
        FROM staff
        WHERE staff_id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        $staff = $res->fetch_assoc();
        $res->free();
    }
    $stmt->close();
    clearStoredResults($conn);
} catch (Throwable $e) {
    $error = $error ?: ("Staff load error: " . $e->getMessage());
    clearStoredResults($conn);
}

$staff = $staff ?: [
    'staff_id' => $staff_id,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'role' => '',
    'department' => '',
    'phone' => '',
    'status' => '',
    'created_at' => '',
    'profile_photo' => null
];

// ------------------------------------------------------------
// Profile photo path (SAME AS staff/profile.php)
// ------------------------------------------------------------
$profilePhoto = $staff['profile_photo'] ?? null;

if ($profilePhoto) {
    if (preg_match('/^https?:\/\//i', $profilePhoto)) {
        $photoUrl = $profilePhoto;
    } else {
        $photoUrl = $profilePhoto; // "uploads/profile/xx.png" loads from /staff/...
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
        width: 160px;
        height: 160px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
        background: #fff;
    }

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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Staff Settings</h3>
            <div class="text-muted">Manage your profile, password, and profile photo</div>
        </div>
        <div class="text-muted">
            <span class="badge bg-secondary">Staff ID: <?php echo (int)$staff_id; ?></span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Profile card -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <strong>My Profile</strong>
                </div>
                <div class="card-body text-center">

                    <img id="currentProfilePhoto"
                         src="<?php echo h($photoUrl); ?>?v=<?php echo time(); ?>"
                         alt="Profile Photo"
                         class="profile-photo-preview mb-3"
                         onerror="this.onerror=null;this.src='<?php echo h('uploads/default_image.png'); ?>';">

                    <div class="text-muted small mb-3">
                        Choose a photo. Then edit (zoom + rotate + crop) and save.
                    </div>

                    <input type="file"
                           id="pickPhoto"
                           class="form-control"
                           accept="image/png,image/jpeg,image/webp">

                    <form method="post" id="saveCroppedForm" class="mt-3">
                        <input type="hidden" name="action" value="save_cropped_profile_photo">
                        <input type="hidden" name="cropped_image" id="cropped_image">
                        <button type="button" id="openEditorBtn" class="btn btn-primary w-100" disabled>
                            Edit & Crop
                        </button>
                    </form>

                </div>
            </div>
        </div>

        <!-- Right: Forms -->
        <div class="col-lg-8">
            <!-- Update profile info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <strong>Profile Details</strong>
                    <small class="text-muted ms-2">Update your contact and department</small>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input class="form-control" name="first_name" required value="<?php echo h($staff["first_name"] ?? ""); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input class="form-control" name="last_name" required value="<?php echo h($staff["last_name"] ?? ""); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="phone" value="<?php echo h($staff["phone"] ?? ""); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input class="form-control" name="department" value="<?php echo h($staff["department"] ?? ""); ?>">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change password -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <strong>Change Password</strong>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="change_password">

                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" autocomplete="current-password">
                            <small class="text-muted">If your password is empty, you can leave this blank.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" required autocomplete="new-password">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required autocomplete="new-password">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-shield-lock"></i> Update Password
                            </button>
                        </div>
                    </form>

                    <div class="text-muted mt-2">
                        <small>Tip: Use at least 6+ characters. Use a mix of letters and numbers.</small>
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

    const base64 = canvas.toDataURL('image/png'); // match PHP regex
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
