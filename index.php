<?php
require_once 'Config/config.php';
$pageTitle = 'Home - Lost & Found System';
include 'Partials/Header.php';

/* --------------------
   Filters
---------------------*/
$search   = isset($_GET['search']) ? clean($_GET['search']) : '';
$type     = isset($_GET['type']) ? clean($_GET['type']) : '';
$category = isset($_GET['category']) ? clean($_GET['category']) : '';

/*
    Show:
    - pending  → newly posted
    - found    → someone marked it found
    Hide:
    - claimed
*/
$where = ["i.is_active = 1", "i.status IN ('pending','found')"];

if ($search) {
    $where[] = "(i.name LIKE '%$search%' OR i.description LIKE '%$search%')";
}

if ($type) {
    $where[] = "i.type = '$type'";
}

if ($category) {
    $where[] = "i.category LIKE '%$category%'";
}

$whereClause = implode(' AND ', $where);

/* --------------------
   Fetch Items
---------------------*/
$sql = "SELECT 
            i.*, 
            u.name AS user_name, 
            (SELECT image_path 
             FROM item_images 
             WHERE item_id = i.item_id 
               AND is_active = 1 
             LIMIT 1) AS image
        FROM items i 
        JOIN users u ON i.user_id = u.user_id 
        WHERE $whereClause 
        ORDER BY i.created_at DESC 
        LIMIT 50";

$items = $conn->query($sql);
?>

<div class="container">

    <!-- Hero -->
    <div class="hero">
        <h1>Lost & Found System</h1>
        <p>Help reunite people with their belongings</p>
    </div>

    <!-- Search -->
    <div class="search-box">
        <form method="GET" action="">
            <input 
                type="text" 
                name="search" 
                placeholder="Search items..." 
                value="<?php echo htmlspecialchars($search); ?>">

            <select name="type">
                <option value="">All Types</option>
                <option value="Lost"  <?php echo $type === 'Lost'  ? 'selected' : ''; ?>>Lost</option>
                <option value="Found" <?php echo $type === 'Found' ? 'selected' : ''; ?>>Found</option>
            </select>

            <input 
                type="text" 
                name="category" 
                placeholder="Category" 
                value="<?php echo htmlspecialchars($category); ?>">

            <button type="submit" class="btn btn-primary">
                Search
            </button>
        </form>
    </div>

    <!-- Action Buttons -->
    <?php if (isLoggedIn()): ?>
        <div class="action-buttons">
            <a href="<?php echo BASE_URL; ?>Tables/items.php?action=add&type=Lost" 
               class="btn btn-danger">
                Report Lost Item
            </a>

            <a href="<?php echo BASE_URL; ?>Tables/items.php?action=add&type=Found" 
               class="btn btn-success">
                Report Found Item
            </a>
        </div>
    <?php else: ?>
        <div class="action-buttons">
            <p style="text-align:center; width:100%;">
                <a href="Auth/login.php">Login</a> or 
                <a href="Auth/register.php">Register</a> to report items
            </p>
        </div>
    <?php endif; ?>

    <!-- Items -->
    <div class="items-grid">

        <?php if ($items && $items->num_rows > 0): ?>
            <?php while ($item = $items->fetch_assoc()): ?>

                <div class="item-card">

                    <!-- Image -->
                    <div class="item-image">
                        <?php if (!empty($item['image'])): ?>
                            <img 
                                src="<?php echo UPLOAD_URL . $item['image']; ?>" 
                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>

                        <span class="item-badge badge-<?php echo strtolower($item['type']); ?>">
                            <?php echo strtoupper($item['type']); ?>
                        </span>
                    </div>

                    <!-- Content -->
                    <div class="item-content">

                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>

                        <p class="item-category">
                            <?php echo htmlspecialchars($item['category']); ?>
                        </p>

                        <p class="item-description">
                            <?php 
                                echo htmlspecialchars(
                                    substr($item['description'], 0, 100)
                                ) . (strlen($item['description']) > 100 ? '...' : '');
                            ?>
                        </p>

                        <p class="item-location">
                            <?php echo htmlspecialchars($item['location']); ?>
                        </p>

                        <p class="item-date">
                            <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                        </p>

                        <p class="item-date">
                            Posted by: <?php echo htmlspecialchars($item['user_name']); ?>
                        </p>

                        <!-- ACTION BUTTONS -->
                        <?php if (isLoggedIn() && $_SESSION['user_id'] != $item['user_id']): ?>

                            <!-- LOST ITEM → MARK AS FOUND -->
                            <?php if (strtolower($item['type']) === 'lost'): ?>
                                <a 
                                    href="<?php echo BASE_URL . 'Tables/items.php?action=mark_found&id=' . $item['item_id']; ?>"
                                    class="btn btn-sm btn-warning">
                                    I Found This Item
                                </a>
                            <?php endif; ?>

                            <!-- FOUND ITEM → CLAIM -->
                            <?php if (strtolower($item['type']) === 'found'): ?>
                                <a 
                                    href="<?php echo BASE_URL; ?>Tables/claims.php?action=claim&item=<?php echo $item['item_id']; ?>" 
                                    class="btn btn-sm btn-primary">
                                    Claim This Item
                                </a>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>
            <div class="no-results">
                <h2>No items found</h2>
                <p>Try adjusting your search or be the first to report an item!</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'Partials/Footer.php'; ?>
