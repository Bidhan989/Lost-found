<?php
require_once '../Config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        setAlert('All fields are required', 'error');
    } else {
        $sql = "SELECT * FROM users WHERE email = '$email' AND is_active = 1";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
                $remainingTime = ceil((strtotime($user['lock_until']) - time()) / 60);
                setAlert("Account locked. Try again in $remainingTime minutes.", 'error');
            } elseif (password_verify($password, $user['password_hash'])) {
                $conn->query("UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE user_id = " . $user['user_id']);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                setAlert('Welcome back, ' . $user['name'] . '!', 'success');
                redirect($user['role'] === 'admin' ? 'admin_dashboard.php' : 'index.php');
            } else {
                $attempts = $user['failed_attempts'] + 1;
                $lockUntil = $attempts >= 5 ? "'" . date('Y-m-d H:i:s', strtotime('+30 minutes')) . "'" : 'NULL';
                $conn->query("UPDATE users SET failed_attempts = $attempts, lock_until = $lockUntil WHERE user_id = " . $user['user_id']);
                
                if ($attempts >= 5) {
                    setAlert('Account locked for 30 minutes due to too many failed attempts', 'error');
                } else {
                    setAlert('Invalid credentials. ' . (5 - $attempts) . ' attempts remaining', 'error');
                }
            }
        } else {
            setAlert('Invalid credentials', 'error');
        }
    }
}

$pageTitle = 'Login - Lost & Found';
include '../Partials/Header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Login</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>

<?php include '../Partials/Footer.php'; ?>
