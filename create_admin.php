<?php
require_once 'Config/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $message = 'All fields are required!';
        $messageType = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters!';
        $messageType = 'danger';
    } else {
        $checkEmail = $conn->query("SELECT user_id FROM users WHERE email = '$email'");

        if ($checkEmail->num_rows > 0) {
            $message = "Email already exists!";
            $messageType = "danger";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password_hash, phone, role, password_changed_at) 
                    VALUES ('$name', '$email', '$hash', '$phone', 'admin', NOW())";

            if ($conn->query($sql)) {
                $userId = $conn->insert_id;
                $conn->query("INSERT INTO password_history (user_id, password_hash) VALUES ($userId, '$hash')");
                $message = " Admin account created successfully! <a href='Auth/login.php'>Login here</a>";
                $messageType = "success";
            } else {
                $message = "Error creating admin account";
                $messageType = "danger";
            }
        }
    }
}

$pageTitle = 'Create Admin Account';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container min-vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow-lg p-4 auth-bootstrap-card">

        <h3 class="text-center mb-3"> Create Admin Account</h3>

        <p class="text-center text-muted mb-4">
            First-time setup: Create an admin account to manage the system
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Admin Name" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="9800000000" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Create Admin Account
            </button>

        </form>

        <p class="text-center mt-3">
            Already have an account?
            <a href="Auth/login.php">Login here</a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
