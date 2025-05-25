<?php
session_start();
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Plesdr fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "The new password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Thre new password must be at least 6 charaters long.";
    } else {
        // Verificar senha atual
        $admin_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($update_stmt->execute()) {
                    $success = "Password successfully changed!";
                    $current_password = $new_password = $confirm_password = '';
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
                
                $update_stmt->close();
            } else {
                $error = "Incorrect current password.";
            }
        } else {
            $error = "User not found.";
        }
        
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-purple text-white">
                    <h3 class="mb-0"><i class="fas fa-key"></i>&nbsp; Change Password</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="change_password.php">
                        <div class="form-group mb-3">
                            <label for="current_password">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="new_password">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <small class="form-text text-muted">The password must be at least 6 charaters long.</small>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-group d-flex justify-content-between">
                            <a href="feeds.php" class="btn btn-purple text-white">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Change
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light">
                    <div class="text-center text-muted">
                        <small><i class="fas fa-shield-alt"></i> For security, choose a strong and unique password.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>