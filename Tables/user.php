<?php
require_once '../Config/config.php';

if (!isLoggedIn()) {
    redirect('Auth/login.php');
}
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $name  = clean($_POST['name']);
    $phone = clean($_POST['phone']);
    
    if (empty($name) || empty($phone)) {
        setAlert('Name and Phone are required.', 'error');
    } else {
        $sql = "UPDATE users 
                SET name = '$name', phone = '$phone' 
                WHERE user_id = $userId";
        
        if ($conn->query($sql)) {
            $_SESSION['name'] = $name;
            setAlert('Profile updated successfully!', 'success');
        } else {
            setAlert('Failed to update profile', 'error');
        }
    }

    redirect('Tables/user.php');
}

$user = $conn->query("SELECT * FROM users WHERE user_id = $userId")->fetch_assoc();

$pageTitle = 'My Profile';
include '../Partials/Header.php';
?>

<div class="container">

    <div class="row">

        <div class="col-md-7">
            <div class="form-container">
                <h2>👤 My Profile</h2>

                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-group">
                        <label>Full Name *</label>
                        <input 
                            type="text" 
                            name="name" 
                            value="<?php echo htmlspecialchars($user['name']); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label>Email (Cannot be changed)</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars($user['email']); ?>" 
                            disabled>
                    </div>

                    <div class="form-group">
                        <label>Phone *</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($user['phone']); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <input 
                            type="text" 
                            value="<?php echo ucfirst($user['role']); ?>" 
                            disabled>
                    </div>

                    <div class="form-group">
                        <label>Member Since</label>
                        <input 
                            type="text" 
                            value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" 
                            disabled>
                    </div>

                    <button type="submit" class="btn btn-primary">
                         Update Profile
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-5">
            <div class="form-container">

                <h3> Security</h3>

                <?php if (!empty($user['password_changed_at'])): ?>
                    <p>
                        <strong>Last Password Change:</strong><br>
                        <?php echo date('F d, Y h:i A', strtotime($user['password_changed_at'])); ?>
                    </p>
                <?php else: ?>
                    <p><em>Password has never been changed.</em></p>
                <?php endif; ?>

                <a href="password_history.php" class="btn btn-warning btn-block" style="margin-top:10px;">
                     Change Password
                </a>

                <hr>

                <p style="font-size: 14px; color:#666;">
                    Keep your account secure by changing your password regularly.
                </p>

            </div>
        </div>

    </div>

</div>

<?php include '../Partials/Footer.php'; ?>
