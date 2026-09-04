<?php

require_once 'Config/config.php';


/* =========================================================
   GET ITEM ID
========================================================= */

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($itemId <= 0) {
    setAlert('Invalid item.', 'danger');
    redirect('index.php');
}


/* =========================================================
   GET ITEM
========================================================= */

$stmt = $conn->prepare("
    SELECT
        i.*,
        u.name AS user_name,
        u.email AS user_email
    FROM items i
    INNER JOIN users u
        ON i.user_id = u.user_id
    WHERE i.item_id = ?
      AND i.is_active = 1
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $itemId);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    setAlert('Item not found.', 'danger');

    redirect('index.php');
}

$item = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   GET IMAGES
========================================================= */

$images = [];

$imageStmt = $conn->prepare("
    SELECT image_path
    FROM item_images
    WHERE item_id = ?
      AND is_active = 1
");

if ($imageStmt) {

    $imageStmt->bind_param("i", $itemId);

    $imageStmt->execute();

    $imageResult = $imageStmt->get_result();

    while ($image = $imageResult->fetch_assoc()) {
        $images[] = $image['image_path'];
    }

    $imageStmt->close();
}


/* =========================================================
   PAGE
========================================================= */

$pageTitle = $item['name'] . ' - Lost & Found';

include 'Partials/header.php';

?>


<div class="container item-details-page">


    <!-- BACK -->

    <div class="details-back">

        <a
            href="<?php echo BASE_URL; ?>index.php"
            class="back-link"
        >
            ← Back to Items
        </a>

    </div>


    <!-- MAIN CARD -->

    <div class="item-details-card">


        <!-- =================================================
             IMAGE
        ================================================== -->

        <div class="details-gallery">

            <?php if (!empty($images)): ?>

                <div class="main-detail-image">

                    <img
                        id="mainItemImage"
                        src="<?php echo UPLOAD_URL . htmlspecialchars($images[0]); ?>"
                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                    >

                </div>


                <?php if (count($images) > 1): ?>

                    <div class="image-thumbnails">

                        <?php foreach ($images as $index => $image): ?>

                            <button
                                type="button"
                                class="image-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                onclick="changeMainImage(
                                    '<?php echo UPLOAD_URL . htmlspecialchars($image, ENT_QUOTES); ?>',
                                    this
                                )"
                            >

                                <img
                                    src="<?php echo UPLOAD_URL . htmlspecialchars($image); ?>"
                                    alt="Item image"
                                >

                            </button>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


            <?php else: ?>

                <div class="detail-no-image">

                    <span>📷</span>

                    <p>
                        No image available
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             INFORMATION
        ================================================== -->

        <div class="details-information">


            <!-- TYPE + STATUS -->

            <div class="details-top-row">

                <span
                    class="details-type
                    <?php
                    echo strtolower($item['type']) === 'lost'
                        ? 'details-type-lost'
                        : 'details-type-found';
                    ?>"
                >

                    <?php echo strtoupper(htmlspecialchars($item['type'])); ?>

                </span>


                <span class="details-status">

                    <?php echo ucfirst(htmlspecialchars($item['status'])); ?>

                </span>

            </div>


            <!-- NAME -->

            <h1>

                <?php echo htmlspecialchars($item['name']); ?>

            </h1>


            <!-- CATEGORY -->

            <div class="details-category">

                <?php echo htmlspecialchars($item['category']); ?>

            </div>


            <!-- DESCRIPTION -->

            <div class="details-section">

                <h3>
                    Description
                </h3>

                <p>

                    <?php
                    echo nl2br(
                        htmlspecialchars($item['description'])
                    );
                    ?>

                </p>

            </div>


            <!-- LOCATION -->

            <div class="details-info-row">

                <div class="details-icon">
                    📍
                </div>

                <div>

                    <span>
                        Location
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($item['location']); ?>
                    </strong>

                </div>

            </div>


            <!-- DATE -->

            <div class="details-info-row">

                <div class="details-icon">
                    📅
                </div>

                <div>

                    <span>
                        Reported
                    </span>

                    <strong>

                        <?php
                        echo date(
                            'F d, Y',
                            strtotime($item['created_at'])
                        );
                        ?>

                    </strong>

                </div>

            </div>


            <!-- USER -->

            <div class="details-info-row">

                <div class="details-icon">
                    👤
                </div>

                <div>

                    <span>
                        Reported by
                    </span>

                    <strong>
                        <?php echo htmlspecialchars($item['user_name']); ?>
                    </strong>

                </div>

            </div>


            <!-- =================================================
                 ACTION
            ================================================== -->

            <div class="details-actions">


                <?php if (!isLoggedIn()): ?>

                    <a
                        href="<?php echo BASE_URL; ?>Auth/login.php"
                        class="details-action details-action-primary"
                    >
                        Login to Take Action
                    </a>


                <?php elseif (
                    (int)$_SESSION['user_id'] === (int)$item['user_id']
                ): ?>

                    <div class="details-owner-message">

                        This is your item.

                    </div>


                <?php else: ?>


                    <?php if (
                        strtolower($item['type']) === 'lost'
                    ): ?>

                        <a
                            href="<?php echo BASE_URL; ?>Tables/items.php?action=mark_found&id=<?php echo $item['item_id']; ?>"
                            class="details-action details-action-warning"
                        >
                            I Found This Item
                        </a>

                    <?php endif; ?>


                    <?php if (
                        strtolower($item['type']) === 'found'
                    ): ?>

                        <a
                            href="<?php echo BASE_URL; ?>Tables/claims.php?action=claim&item=<?php echo $item['item_id']; ?>"
                            class="details-action details-action-primary"
                        >
                            Claim This Item
                        </a>

                    <?php endif; ?>


                <?php endif; ?>


            </div>

        </div>

    </div>


    <!-- SAFETY -->

    <div class="details-note">

        <strong>
            Safety reminder
        </strong>

        <p>
            Do not share passwords, banking information,
            or other sensitive information when arranging
            the return of an item.
        </p>

    </div>


</div>


<script>

function changeMainImage(imageUrl, button) {

    const mainImage =
        document.getElementById('mainItemImage');

    if (mainImage) {
        mainImage.src = imageUrl;
    }


    document
        .querySelectorAll('.image-thumbnail')
        .forEach(function (thumbnail) {

            thumbnail.classList.remove('active');

        });


    button.classList.add('active');
}

</script>


<?php include 'Partials/footer.php'; ?>