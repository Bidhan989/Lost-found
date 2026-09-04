<?php
require_once '../Config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setAlert('Please login to access this page', 'error');
    redirect('Auth/login.php');
}

$userId = $_SESSION['user_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setAlert('All fields are required', 'error');
    } elseif ($newPassword !== $confirmPassword) {
        setAlert('New passwords do not match', 'error');
    } elseif (strlen($newPassword) < 6) {
        setAlert('Password must be at least 6 characters', 'error');
    } else {
        // Verify current password
        $userQuery = $conn->query("SELECT password_hash FROM users WHERE user_id = $userId");
        $user = $userQuery->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            setAlert('Current password is incorrect', 'error');
        } else {
            // Check if new password was used before (last 3 passwords)
            $historyQuery = $conn->query("SELECT password_hash FROM password_history 
                                          WHERE user_id = $userId 
                                          ORDER BY created_at DESC 
                                          LIMIT 3");
            
            $passwordUsedBefore = false;
            while ($history = $historyQuery->fetch_assoc()) {
                if (password_verify($newPassword, $history['password_hash'])) {
                    $passwordUsedBefore = true;
                    break;
                }
            }
            
            if ($passwordUsedBefore) {
                setAlert('You cannot reuse your last 3 passwords', 'error');
            } else {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $updateQuery = "UPDATE users SET password_hash = '$newHash', password_changed_at = NOW() 
                               WHERE user_id = $userId";
                
                if ($conn->query($updateQuery)) {
                    // Add to password history
                    $conn->query("INSERT INTO password_history (user_id, password_hash) 
                                 VALUES ($userId, '$newHash')");
                    
                    // Keep only last 5 passwords in history
                    $conn->query("DELETE FROM password_history 
                                 WHERE user_id = $userId 
                                 AND history_id NOT IN (
                                     SELECT history_id FROM (
                                         SELECT history_id FROM password_history 
                                         WHERE user_id = $userId 
                                         ORDER BY created_at DESC 
                                         LIMIT 5
                                     ) AS temp
                                 )");
                    
                    setAlert('Password changed successfully!', 'success');
                    redirect('Tables/user.php');
                } else {
                    setAlert('Failed to change password', 'error');
                }
            }
        }
    }
}

// Get user's password history
$historyQuery = $conn->query("SELECT * FROM password_history 
                              WHERE user_id = $userId 
                              ORDER BY created_at DESC");

// Get user info
$userQuery = $conn->query("SELECT * FROM users WHERE user_id = $userId");
$userInfo = $userQuery->fetch_assoc();

$pageTitle = 'Profile - Change Password';
include '../Partials/Header.php';
?>

<div class="container">
    <h2>  Profile Security</h2>
        <p>Manage your account password securely.</p>

    
    <!-- Change Password Form -->
    <div class="form-container">
        <h3>Change Password</h3>
        
        <?php if ($userInfo['password_changed_at']): ?>
        <div class="alert alert-info">
            Last password change: <?php echo date('F d, Y H:i', strtotime($userInfo['password_changed_at'])); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="passwordForm">
            <div class="form-group">
                <label for="current_password">Current Password *</label>
                <input type="password" id="current_password" name="current_password" 
                       placeholder="Enter your current password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" 
                       placeholder="Minimum 6 characters" required>
                <small id="password-strength" style="display:block; margin-top: 5px;"></small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Re-enter new password" required>
            </div>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li>✓ Minimum 6 characters</li>
                    <li>✓ Cannot be same as last 3 passwords</li>
                    <li>✓ Recommended: Mix of letters, numbers, and symbols</li>
                </ul>
            </div>
            
            <button type="submit" name="change_password" class="btn btn-primary">
                Change Password
            </button>
            <a href="../Tables/user.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    
    <!-- Password History Table -->
    <div class="table-container" style="margin-top: 2rem;">
        <h3>Password Change History</h3>
        <p style="color: #666; margin-bottom: 1rem;">
            Your password change activity. For security, only the last 5 changes are kept.
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Changed On</th>
                    <th>Time Ago</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($historyQuery->num_rows > 0):
                    $count = 1;
                    while ($history = $historyQuery->fetch_assoc()):
                        $timeAgo = time() - strtotime($history['created_at']);
                        $days = floor($timeAgo / 86400);
                        $hours = floor(($timeAgo % 86400) / 3600);
                        
                        if ($days > 0) {
                            $timeAgoText = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                        } elseif ($hours > 0) {
                            $timeAgoText = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                        } else {
                            $timeAgoText = 'Less than an hour ago';
                        }
                        
                        $isCurrent = ($count === 1);
                ?>
                <tr>
                    <td><?php echo $count; ?></td>
                    <td><?php echo date('F d, Y - h:i A', strtotime($history['created_at'])); ?></td>
                    <td><?php echo $timeAgoText; ?></td>
                    <td>
                        <?php if ($isCurrent): ?>
                            <span class="badge badge-success">Current Password</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Previous Password</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                        $count++;
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="4" style="text-align:center;">No password history available</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Security Tips -->
    <div class="security-tips" style="margin-top: 2rem;">
        <div class="alert alert-info">
            <h4> Security Tips</h4>
            <ul style="margin: 10px 0 0 20px;">
                <li>Change your password regularly (recommended: every 90 days)</li>
                <li>Never share your password with anyone</li>
                <li>Use a unique password for this account</li>
                <li>Enable two-factor authentication if available</li>
                <li>Avoid using personal information in passwords</li>
                <li>If you suspect unauthorized access, change your password immediately</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../Partials/Footer.php'; ?>

<!-- Additional JavaScript for password strength -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthIndicator = document.getElementById('password-strength');
    
    // Password strength checker
    newPasswordInput.addEventListener('keyup', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#17a2b8', '#28a745', '#20c997'];
        
        strengthIndicator.textContent = 'Strength: ' + (strengthText[strength - 1] || 'Too Short');
        strengthIndicator.style.color = strengthColors[strength - 1] || '#dc3545';
        strengthIndicator.style.fontWeight = 'bold';
    });
    
    // Confirm password match
    confirmPasswordInput.addEventListener('keyup', function() {
        if (this.value !== newPasswordInput.value && this.value !== '') {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = '#ddd';
        }
    });
    
    // Form validation
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const newPass = newPasswordInput.value;
        const confirmPass = confirmPasswordInput.value;
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match!');
            confirmPasswordInput.focus();
            return false;
        }
        
        if (newPass.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            newPasswordInput.focus();
            return false;
        }
    });
});
</script>

<style>
.password-requirements {
    background: #f9f9f9;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border-left: 4px solid #667eea;
}

.password-requirements h4 {
    margin-bottom: 0.5rem;
    color: #333;
}

.password-requirements ul {
    margin-left: 20px;
    color: #666;
}

.password-requirements li {
    margin: 0.3rem 0;
}

.security-tips ul li {
    margin: 0.5rem 0;
    line-height: 1.6;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}
</style>