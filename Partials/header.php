<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Lost & Found Management System - Report and find lost items">
    <meta name="keywords" content="lost, found, items, management, system">
    <meta name="author" content="Lost & Found System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


    <title>
        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Lost & Found System'; ?>
    </title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>Images/favicon.png">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>Css/style.css">

    <!-- Additional CSS -->
    <?php if (isset($additionalCSS)) echo $additionalCSS; ?>
</head>
<body>
<!-- Alert Messages -->
<?php 
$alert = getAlert();
if ($alert): 
?>
<div class="alert alert-<?php echo htmlspecialchars($alert['type']); ?>" role="alert">
    <?php echo htmlspecialchars($alert['message']); ?>
            <button 
                type="button"
                class="btn-close"
                aria-label="Close"
                onclick="this.closest('.alert').remove()">
            </button>

</div>
<?php endif; ?>

<!-- Navbar -->
<?php include __DIR__ . '/navbar.php'; ?>
