<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-4 nav-container">

        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>index.php" class="navbar-brand nav-logo">
             Lost & Found
        </a>

        <!-- Hamburger Menu for Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 nav-menu">

                <!-- Home -->
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>index.php" class="nav-link"> Home</a>
                </li>

                <?php if (isLoggedIn()): ?>
                    <!-- User Links -->
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Tables/items.php" class="nav-link"> My Items</a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Tables/claims.php" class="nav-link"> My Claims</a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Tables/user.php" class="nav-link"> Profile</a>
                    </li>

                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin_dashboard.php" class="nav-link"> Admin</a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Auth/logout.php" class="nav-link">
                             Logout (<?php echo htmlspecialchars($_SESSION['name']); ?>)
                        </a>
                    </li>

                <?php else: ?>
                    <!-- Guest Links -->
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Auth/login.php" class="nav-link"> Login</a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>Auth/register.php" class="nav-link"> Register</a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
