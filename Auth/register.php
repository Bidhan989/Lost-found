<?php
require_once '../Config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        setAlert('All fields are required', 'error');
    } elseif ($password !== $confirm) {
        setAlert('Passwords do not match', 'error');
    } elseif (strlen($password) < 6) {
        setAlert('Password must be at least 6 characters', 'error');
    } else {
        $checkEmail = $conn->query("SELECT user_id FROM users WHERE email = '$email'");
        if ($checkEmail->num_rows > 0) {
            setAlert('Email already exists', 'error');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password_hash, phone, password_changed_at) 
                    VALUES ('$name', '$email', '$hash', '$phone', NOW())";
            
            if ($conn->query($sql)) {
                $userId = $conn->insert_id;
                $conn->query("INSERT INTO password_history (user_id, password_hash) VALUES ($userId, '$hash')");
                setAlert('Registration successful! Please login.', 'success');
                redirect('Auth/login.php');
            } else {
                setAlert('Registration failed', 'error');
            }
        }
    }
}

$pageTitle = 'Register - Lost & Found';
include '../Partials/Header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Create Account</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>
        <p class="auth-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<?php include '../Partials/Footer.php'; ?>