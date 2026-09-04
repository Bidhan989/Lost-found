<?php
require_once 'Config/config.php';

$pageTitle = 'Home - Lost & Found System';
include 'Partials/Header.php';

/* =========================================================
   GET FILTER VALUES
========================================================= */

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$type      = isset($_GET['type']) ? trim($_GET['type']) : '';
$category  = isset($_GET['category']) ? trim($_GET['category']) : '';
$location  = isset($_GET['location']) ? trim($_GET['location']) : '';
$status    = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom  = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo    = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$sort      = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';


/* =========================================================
   ALLOWED FILTER VALUES
========================================================= */

// Only allow valid item types
$allowedTypes = ['Lost', 'Found'];

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}


// Only allow statuses that are publicly displayed
$allowedStatuses = ['pending', 'found'];

if (!in_array(strtolower($status), $allowedStatuses, true)) {
    $status = '';
}


// Only allow known sorting options
$allowedSorts = [
    'newest' => 'i.created_at DESC',
    'oldest' => 'i.created_at ASC',
    'az'     => 'i.name ASC',
    'za'     => 'i.name DESC'
];

if (!array_key_exists($sort, $allowedSorts)) {
    $sort = 'newest';
}

$orderBy = $allowedSorts[$sort];


/* =========================================================
   BUILD QUERY
========================================================= */

// Only show active items that are still available
$where = [
    "i.is_active = 1",
    "i.status IN ('pending', 'found')"
];

$params = [];
$types  = '';


// Search
if ($search !== '') {

    $where[] = "(
        i.name LIKE ?
        OR i.description LIKE ?
        OR i.category LIKE ?
        OR i.location LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}


// Lost / Found
if ($type !== '') {

    $where[] = "i.type = ?";

    $params[] = $type;

    $types .= 's';
}


// Category
if ($category !== '') {

    $where[] = "i.category LIKE ?";

    $params[] = '%' . $category . '%';

    $types .= 's';
}


// Location
if ($location !== '') {

    $where[] = "i.location LIKE ?";

    $params[] = '%' . $location . '%';

    $types .= 's';
}


// Status
if ($status !== '') {

    $where[] = "i.status = ?";

    $params[] = strtolower($status);

    $types .= 's';
}


// Date from
if ($dateFrom !== '') {

    $where[] = "DATE(i.created_at) >= ?";

    $params[] = $dateFrom;

    $types .= 's';
}


// Date to
if ($dateTo !== '') {

    $where[] = "DATE(i.created_at) <= ?";

    $params[] = $dateTo;

    $types .= 's';
}


$whereClause = implode(' AND ', $where);


/* =========================================================
   FETCH ITEMS
========================================================= */

$sql = "SELECT
            i.*,
            u.name AS user_name,

            (
                SELECT image_path
                FROM item_images
                WHERE item_id = i.item_id
                  AND is_active = 1
                ORDER BY image_id ASC
                LIMIT 1
            ) AS image

        FROM items i

        JOIN users u
            ON i.user_id = u.user_id

        WHERE $whereClause

        ORDER BY $orderBy

        LIMIT 50";


$stmt = $conn->prepare($sql);

$items = false;

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $items = $stmt->get_result();
}


/* =========================================================
   COUNT RESULTS
========================================================= */

$resultCount = $items ? $items->num_rows : 0;


/* =========================================================
   FETCH CATEGORIES
========================================================= */

$categories = [];

$categoryQuery = $conn->query("
    SELECT DISTINCT category
    FROM items
    WHERE is_active = 1
      AND category IS NOT NULL
      AND category != ''
    ORDER BY category ASC
");

if ($categoryQuery) {

    while ($cat = $categoryQuery->fetch_assoc()) {

        $categories[] = $cat['category'];
    }
}


/* =========================================================
   FETCH LOCATIONS
========================================================= */

$locations = [];

$locationQuery = $conn->query("
    SELECT DISTINCT location
    FROM items
    WHERE is_active = 1
      AND location IS NOT NULL
      AND location != ''
    ORDER BY location ASC
");

if ($locationQuery) {

    while ($loc = $locationQuery->fetch_assoc()) {

        $locations[] = $loc['location'];
    }
}

?>


<div class="container">

    <!-- =====================================================
         HERO
    ====================================================== -->

    <div class="hero">

        <h1>Lost & Found System</h1>

        <p>
            Help reunite people with their belongings
        </p>

    </div>


    <!-- =====================================================
         ADVANCED SEARCH
    ====================================================== -->

    <div class="search-box">

        <form method="GET" action="">

            <!-- Search -->
            <div class="search-field">

                <label for="search">
                    Search
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Search name, description, category or location..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

            </div>


            <!-- Type -->
            <div class="search-field">

                <label for="type">
                    Type
                </label>

                <select name="type" id="type">

                    <option value="">
                        All Types
                    </option>

                    <option
                        value="Lost"
                        <?php echo $type === 'Lost' ? 'selected' : ''; ?>
                    >
                        Lost
                    </option>

                    <option
                        value="Found"
                        <?php echo $type === 'Found' ? 'selected' : ''; ?>
                    >
                        Found
                    </option>

                </select>

            </div>


            <!-- Category -->
            <div class="search-field">

                <label for="category">
                    Category
                </label>

                <select name="category" id="category">

                    <option value="">
                        All Categories
                    </option>

                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?php echo htmlspecialchars($cat); ?>"
                            <?php echo $category === $cat ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($cat); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Location -->
            <div class="search-field">

                <label for="location">
                    Location
                </label>

                <select name="location" id="location">

                    <option value="">
                        All Locations
                    </option>

                    <?php foreach ($locations as $loc): ?>

                        <option
                            value="<?php echo htmlspecialchars($loc); ?>"
                            <?php echo $location === $loc ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($loc); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Status -->
            <div class="search-field">

                <label for="status">
                    Status
                </label>

                <select name="status" id="status">

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="pending"
                        <?php echo strtolower($status) === 'pending' ? 'selected' : ''; ?>
                    >
                        Pending
                    </option>

                    <option
                        value="found"
                        <?php echo strtolower($status) === 'found' ? 'selected' : ''; ?>
                    >
                        Found
                    </option>

                </select>

            </div>


            <!-- Date From -->
            <div class="search-field">

                <label for="date_from">
                    From Date
                </label>

                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >

            </div>


            <!-- Date To -->
            <div class="search-field">

                <label for="date_to">
                    To Date
                </label>

                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >

            </div>


            <!-- Sort -->
            <div class="search-field">

                <label for="sort">
                    Sort By
                </label>

                <select name="sort" id="sort">

                    <option
                        value="newest"
                        <?php echo $sort === 'newest' ? 'selected' : ''; ?>
                    >
                        Newest First
                    </option>

                    <option
                        value="oldest"
                        <?php echo $sort === 'oldest' ? 'selected' : ''; ?>
                    >
                        Oldest First
                    </option>

                    <option
                        value="az"
                        <?php echo $sort === 'az' ? 'selected' : ''; ?>
                    >
                        Name A-Z
                    </option>

                    <option
                        value="za"
                        <?php echo $sort === 'za' ? 'selected' : ''; ?>
                    >
                        Name Z-A
                    </option>

                </select>

            </div>


            <!-- Buttons -->
            <div class="search-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    🔎 Search
                </button>


                <a
                    href="<?php echo BASE_URL; ?>"
                    class="btn btn-secondary"
                >
                    ↻ Reset
                </a>

            </div>

        </form>

    </div>


    <!-- =====================================================
         RESULT INFORMATION
    ====================================================== -->

    <div class="results-header">

        <h2>
            Available Items
        </h2>

        <p>
            <?php echo $resultCount; ?>
            <?php echo $resultCount === 1 ? 'item' : 'items'; ?>
            found
        </p>

    </div>


    <!-- =====================================================
         ACTION BUTTONS
    ====================================================== -->

    <?php if (isLoggedIn()): ?>

        <div class="action-buttons">

            <a
                href="<?php echo BASE_URL; ?>Tables/items.php?action=add&type=Lost"
                class="btn btn-danger"
            >
                Report Lost Item
            </a>


            <a
                href="<?php echo BASE_URL; ?>Tables/items.php?action=add&type=Found"
                class="btn btn-success"
            >
                Report Found Item
            </a>

        </div>

    <?php else: ?>

        <div class="action-buttons">

            <p style="text-align:center; width:100%;">

                <a href="Auth/login.php">
                    Login
                </a>

                or

                <a href="Auth/register.php">
                    Register
                </a>

                to report items

            </p>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ITEMS GRID
    ====================================================== -->

    <div class="items-grid">


        <?php if ($items && $items->num_rows > 0): ?>


            <?php while ($item = $items->fetch_assoc()): ?>


                <div class="item-card">


                    <!-- IMAGE -->
                    <div class="item-image">


                        <?php if (!empty($item['image'])): ?>

                            <img
                                src="<?php echo UPLOAD_URL . htmlspecialchars($item['image']); ?>"
                                alt="<?php echo htmlspecialchars($item['name']); ?>"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                No Image
                            </div>

                        <?php endif; ?>


                        <!-- TYPE BADGE -->
                        <span
                            class="item-badge badge-<?php echo strtolower(htmlspecialchars($item['type'])); ?>"
                        >
                            <?php echo strtoupper(htmlspecialchars($item['type'])); ?>
                        </span>


                    </div>


                    <!-- CONTENT -->
                    <div class="item-content">


                        <!-- NAME -->
                        <h3>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </h3>


                        <!-- CATEGORY -->
                        <p class="item-category">

                            <?php echo htmlspecialchars($item['category']); ?>

                        </p>


                        <!-- DESCRIPTION -->
                        <p class="item-description">

                            <?php

                            $description = $item['description'] ?? '';

                            echo htmlspecialchars(
                                substr($description, 0, 100)
                            );

                            if (strlen($description) > 100) {
                                echo '...';
                            }

                            ?>

                        </p>


                        <!-- LOCATION -->
                        <p class="item-location">

                            📍
                            <?php echo htmlspecialchars($item['location']); ?>

                        </p>


                        <!-- DATE -->
                        <p class="item-date">

                            📅
                            <?php echo date(
                                'M d, Y',
                                strtotime($item['created_at'])
                            ); ?>

                        </p>


                        <!-- USER -->
                        <p class="item-date">

                            Posted by:
                            <?php echo htmlspecialchars($item['user_name']); ?>

                        </p>


                        <!-- STATUS -->
                        <p class="item-date">

                            Status:

                            <?php if (strtolower($item['status']) === 'pending'): ?>

                                <strong>
                                    Pending
                                </strong>

                            <?php elseif (strtolower($item['status']) === 'found'): ?>

                                <strong>
                                    Found
                                </strong>

                            <?php else: ?>

                                <strong>
                                    <?php echo htmlspecialchars($item['status']); ?>
                                </strong>

                            <?php endif; ?>

                        </p>
                         <a
                            href="<?php echo BASE_URL; ?>item_details.php?id=<?php echo $item['item_id']; ?>"
                            class="btn btn-sm btn-details"
                            >View Details
                        </a>           

                        <!-- =================================================
                             ACTION BUTTONS
                        ================================================== -->

                        <?php if (
                            isLoggedIn() &&
                            $_SESSION['user_id'] != $item['user_id']
                        ): ?>


                            <!-- LOST → MARK FOUND -->
                            <?php if (strtolower($item['type']) === 'lost'): ?>

                                <a
                                    href="<?php echo BASE_URL; ?>Tables/items.php?action=mark_found&id=<?php echo $item['item_id']; ?>"
                                    class="btn btn-sm btn-warning"
                                >
                                    I Found This Item
                                </a>

                            <?php endif; ?>


                            <!-- FOUND → CLAIM -->
                            <?php if (strtolower($item['type']) === 'found'): ?>

                                <a
                                    href="<?php echo BASE_URL; ?>Tables/claims.php?action=claim&item=<?php echo $item['item_id']; ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    Claim This Item
                                </a>

                            <?php endif; ?>


                        <?php endif; ?>


                    </div>

                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <!-- NO RESULTS -->

            <div class="no-results">

                <h2>
                    No items found
                </h2>

                <p>
                    Try adjusting your search filters
                    or be the first to report an item!
                </p>

                <a
                    href="<?php echo BASE_URL; ?>"
                    class="btn btn-primary"
                >
                    Clear Filters
                </a>

            </div>


        <?php endif; ?>


    </div>

</div>


<?php

include 'Partials/Footer.php';

?>