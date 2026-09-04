<?php

require_once '../Config/config.php';


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isLoggedIn()) {
    redirect('Auth/login.php');
}


$userId = (int)$_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';

$itemId = isset($_GET['item'])
    ? (int)$_GET['item']
    : 0;


/* =========================================================
   SUBMIT CLAIM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_claim'])
) {

    $itemId = isset($_POST['item_id'])
        ? (int)$_POST['item_id']
        : 0;

    $description = trim($_POST['claim_description'] ?? '');
    $contact     = trim($_POST['contact_info'] ?? '');


    /* -----------------------------------------------------
       VALIDATION
    ----------------------------------------------------- */

    if (
        $itemId <= 0 ||
        $description === '' ||
        $contact === ''
    ) {

        setAlert(
            'Please complete all required fields.',
            'error'
        );

        redirect(
            'Tables/claims.php?action=claim&item=' . $itemId
        );
    }


    /* -----------------------------------------------------
       GET ITEM
    ----------------------------------------------------- */

    $itemStmt = $conn->prepare("
        SELECT
            item_id,
            user_id,
            name,
            type,
            status,
            is_active
        FROM items
        WHERE item_id = ?
        LIMIT 1
    ");

    $itemStmt->bind_param("i", $itemId);

    $itemStmt->execute();

    $itemResult = $itemStmt->get_result();

    if ($itemResult->num_rows === 0) {

        $itemStmt->close();

        setAlert(
            'Item not found.',
            'error'
        );

        redirect('index.php');
    }

    $item = $itemResult->fetch_assoc();

    $itemStmt->close();


    /* -----------------------------------------------------
       ONLY FOUND ITEMS CAN BE CLAIMED
    ----------------------------------------------------- */

    if (
        strtolower($item['type']) !== 'found' ||
        (int)$item['is_active'] !== 1
    ) {

        setAlert(
            'This item is not available for claiming.',
            'error'
        );

        redirect(
            'item_details.php?id=' . $itemId
        );
    }


    /* -----------------------------------------------------
       USER CANNOT CLAIM THEIR OWN ITEM
    ----------------------------------------------------- */

    if ((int)$item['user_id'] === $userId) {

        setAlert(
            'You cannot claim your own item.',
            'error'
        );

        redirect(
            'item_details.php?id=' . $itemId
        );
    }


    /* -----------------------------------------------------
       CHECK EXISTING CLAIM
    ----------------------------------------------------- */

    $existingStmt = $conn->prepare("
        SELECT claim_id
        FROM claims
        WHERE item_id = ?
          AND claimant_id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $existingStmt->bind_param(
        "ii",
        $itemId,
        $userId
    );

    $existingStmt->execute();

    $existingResult = $existingStmt->get_result();

    if ($existingResult->num_rows > 0) {

        $existingStmt->close();

        setAlert(
            'You already have an active claim for this item.',
            'error'
        );

        redirect('Tables/claims.php');
    }

    $existingStmt->close();


    /* -----------------------------------------------------
       GET ADMIN
    ----------------------------------------------------- */

    $adminResult = $conn->query("
        SELECT user_id
        FROM users
        WHERE role = 'admin'
          AND is_active = 1
        ORDER BY user_id ASC
        LIMIT 1
    ");

    if (!$adminResult || $adminResult->num_rows === 0) {

        setAlert(
            'No active administrator is available to review claims.',
            'error'
        );

        redirect(
            'Tables/claims.php?action=claim&item=' . $itemId
        );
    }

    $adminId = (int)$adminResult->fetch_assoc()['user_id'];


    /* -----------------------------------------------------
       INSERT CLAIM
    ----------------------------------------------------- */

    $claimStmt = $conn->prepare("
        INSERT INTO claims
        (
            item_id,
            claimant_id,
            admin_id,
            claim_date,
            claim_description,
            contact_info,
            status,
            is_active
        )
        VALUES
        (
            ?,
            ?,
            ?,
            CURDATE(),
            ?,
            ?,
            'pending',
            1
        )
    ");

    if (!$claimStmt) {

        setAlert(
            'Unable to prepare claim submission.',
            'error'
        );

        redirect(
            'Tables/claims.php?action=claim&item=' . $itemId
        );
    }


    $claimStmt->bind_param(
        "iiiss",
        $itemId,
        $userId,
        $adminId,
        $description,
        $contact
    );


    if ($claimStmt->execute()) {

        $claimId = (int)$conn->insert_id;

        // Notify the assigned administrator about the new claim.
        createNotification(
            $adminId,
            'New Claim Submitted',
            'A new claim has been submitted for the item "' . $item['name'] . '" and is waiting for review.',
            'info',
            $itemId,
            $claimId
        );

        // Notify the person who posted the found item.
        if ((int)$item['user_id'] !== $userId) {
            createNotification(
                (int)$item['user_id'],
                'New Claim on Your Item',
                'Someone has submitted a claim for your found item "' . $item['name'] . '".',
                'info',
                $itemId,
                $claimId
            );
        }

        setAlert(
            'Your claim has been submitted successfully and is now pending review.',
            'success'
        );

    } else {

        setAlert(
            'Failed to submit the claim. Please try again.',
            'error'
        );
    }


    $claimStmt->close();

    redirect('Tables/claims.php');
}


/* =========================================================
   PAGE HEADER
========================================================= */

$pageTitle = 'My Claims';

include '../Partials/Header.php';

?>


<div class="container claims-page">


<?php
/* =========================================================
   CLAIM FORM
========================================================= */

if ($action === 'claim' && $itemId > 0):


    /* -----------------------------------------------------
       GET ITEM
    ----------------------------------------------------- */

    $itemStmt = $conn->prepare("
        SELECT
            i.*,
            u.name AS owner_name
        FROM items i
        INNER JOIN users u
            ON i.user_id = u.user_id
        WHERE i.item_id = ?
          AND i.is_active = 1
        LIMIT 1
    ");

    $itemStmt->bind_param(
        "i",
        $itemId
    );

    $itemStmt->execute();

    $itemResult = $itemStmt->get_result();


    if ($itemResult->num_rows === 0):

        $itemStmt->close();

?>

        <div class="claim-message claim-message-error">

            <h2>Item Not Found</h2>

            <p>
                This item no longer exists or is no longer available.
            </p>

            <a
                href="../index.php"
                class="btn btn-primary"
            >
                Back to Items
            </a>

        </div>


<?php

    else:

        $item = $itemResult->fetch_assoc();

        $itemStmt->close();


        /* -------------------------------------------------
           CHECK OWNER
        ------------------------------------------------- */

        if ((int)$item['user_id'] === $userId):

?>

            <div class="claim-message claim-message-error">

                <h2>You Cannot Claim This Item</h2>

                <p>
                    This item was reported by your account.
                </p>

                <a
                    href="../item_details.php?id=<?php echo $itemId; ?>"
                    class="btn btn-primary"
                >
                    Back to Item
                </a>

            </div>


<?php

        /* -------------------------------------------------
           CHECK TYPE
        ------------------------------------------------- */

        elseif (strtolower($item['type']) !== 'found'):

?>

            <div class="claim-message claim-message-error">

                <h2>This Item Cannot Be Claimed</h2>

                <p>
                    Only found items can be claimed through
                    the recovery system.
                </p>

                <a
                    href="../item_details.php?id=<?php echo $itemId; ?>"
                    class="btn btn-primary"
                >
                    Back to Item
                </a>

            </div>


<?php

        else:


            /* ---------------------------------------------
               CHECK EXISTING CLAIM
            --------------------------------------------- */

            $checkStmt = $conn->prepare("
                SELECT
                    claim_id,
                    status,
                    claim_date
                FROM claims
                WHERE item_id = ?
                  AND claimant_id = ?
                  AND is_active = 1
                ORDER BY claim_id DESC
                LIMIT 1
            ");

            $checkStmt->bind_param(
                "ii",
                $itemId,
                $userId
            );

            $checkStmt->execute();

            $existingClaim = $checkStmt->get_result();

?>


<?php if ($existingClaim->num_rows > 0): ?>

    <?php
        $existing = $existingClaim->fetch_assoc();
        $checkStmt->close();
    ?>

    <div class="claim-message claim-message-info">

        <div class="claim-message-icon">
            ✓
        </div>

        <h2>
            Claim Already Submitted
        </h2>

        <p>
            You already submitted a claim for
            <strong>
                <?php echo htmlspecialchars($item['name']); ?>
            </strong>.
        </p>

        <div class="claim-current-status">

            Current status:

            <span class="claim-status status-<?php echo strtolower($existing['status']); ?>">
                <?php echo ucfirst(htmlspecialchars($existing['status'])); ?>
            </span>

        </div>

        <a
            href="claims.php"
            class="btn btn-primary"
        >
            View My Claims
        </a>

        <a
            href="../item_details.php?id=<?php echo $itemId; ?>"
            class="btn btn-secondary"
        >
            Back to Item
        </a>

    </div>


<?php else: ?>

    <?php $checkStmt->close(); ?>


    <!-- =================================================
         CLAIM FORM
    ================================================== -->

    <div class="claim-form-wrapper">


        <div class="claim-form-header">

            <span class="claim-form-icon">
                🔐
            </span>

            <div>

                <h1>
                    Claim This Item
                </h1>

                <p>
                    Provide information that helps verify
                    that this item belongs to you.
                </p>

            </div>

        </div>


        <!-- ITEM SUMMARY -->

        <div class="claim-item-summary">

            <div>

                <span class="summary-label">
                    Item
                </span>

                <strong>
                    <?php echo htmlspecialchars($item['name']); ?>
                </strong>

            </div>


            <div>

                <span class="summary-label">
                    Category
                </span>

                <strong>
                    <?php echo htmlspecialchars($item['category']); ?>
                </strong>

            </div>


            <div>

                <span class="summary-label">
                    Location
                </span>

                <strong>
                    📍 <?php echo htmlspecialchars($item['location']); ?>
                </strong>

            </div>

        </div>


        <!-- FORM -->

        <form
            method="POST"
            class="claim-form"
        >

            <input
                type="hidden"
                name="item_id"
                value="<?php echo $itemId; ?>"
            >


            <!-- CLAIM DESCRIPTION -->

            <div class="claim-form-group">

                <label for="claim_description">

                    Why do you believe this is your item?

                    <span>*</span>

                </label>

                <textarea
                    id="claim_description"
                    name="claim_description"
                    rows="6"
                    required
                    minlength="10"
                    placeholder="Describe unique details only the real owner would know. For example: identifying marks, contents, serial number, stickers, or other specific characteristics..."
                ></textarea>

                <small>
                    Please provide specific identifying information.
                </small>

            </div>


            <!-- CONTACT -->

            <div class="claim-form-group">

                <label for="contact_info">

                    Contact Information

                    <span>*</span>

                </label>

                <input
                    type="text"
                    id="contact_info"
                    name="contact_info"
                    required
                    maxlength="255"
                    placeholder="Phone number or email address"
                >

                <small>
                    The administrator may use this information
                    to contact you about your claim.
                </small>

            </div>


            <!-- AGREEMENT -->

            <div class="claim-agreement">

                <input
                    type="checkbox"
                    id="claim_agreement"
                    required
                >

                <label for="claim_agreement">

                    I confirm that the information I provide
                    is truthful and that I am the rightful owner
                    of this item.

                </label>

            </div>


            <!-- BUTTONS -->

            <div class="claim-form-actions">

                <button
                    type="submit"
                    name="submit_claim"
                    class="btn btn-primary claim-submit-btn"
                >
                    Submit Claim
                </button>

                <a
                    href="../item_details.php?id=<?php echo $itemId; ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>


        <!-- SECURITY -->

        <div class="claim-security-note">

            <strong>
                🔒 Privacy & Safety
            </strong>

            <p>
                Only provide information necessary to verify
                ownership. Never provide passwords, banking
                PINs, OTPs, or other sensitive credentials.
            </p>

        </div>

    </div>


<?php endif; ?>


<?php
        endif;
    endif;

else:


/* =========================================================
   MY CLAIMS
========================================================= */

?>

    <div class="claims-header">

        <div>

            <h1>
                My Claims
            </h1>

            <p>
                Track the status of items you have claimed.
            </p>

        </div>


        <?php if (isAdmin()): ?>

            <a
                href="manage_claims.php"
                class="btn btn-primary"
            >
                Manage Claims
            </a>

        <?php endif; ?>

    </div>


<?php

$userStmt = $conn->prepare("
    SELECT
        c.*,
        i.name AS item_name,
        i.type AS item_type,
        i.location AS item_location
    FROM claims c
    INNER JOIN items i
        ON c.item_id = i.item_id
    WHERE c.claimant_id = ?
      AND c.is_active = 1
    ORDER BY c.created_at DESC
");

$userStmt->bind_param(
    "i",
    $userId
);

$userStmt->execute();

$claimsResult = $userStmt->get_result();

?>


<?php if ($claimsResult->num_rows > 0): ?>


    <div class="claims-grid">


        <?php while ($claim = $claimsResult->fetch_assoc()): ?>

            <?php

            $claimStatus = strtolower(
                $claim['status'] ?? 'pending'
            );

            ?>


            <div class="claim-card">


                <div class="claim-card-top">

                    <span class="claim-item-type">

                        <?php echo strtoupper(
                            htmlspecialchars($claim['item_type'])
                        ); ?>

                    </span>


                    <span
                        class="claim-status status-<?php echo htmlspecialchars($claimStatus); ?>"
                    >

                        <?php echo ucfirst(
                            htmlspecialchars($claimStatus)
                        ); ?>

                    </span>

                </div>


                <h3>

                    <?php echo htmlspecialchars(
                        $claim['item_name']
                    ); ?>

                </h3>


                <p class="claim-location">

                    📍

                    <?php echo htmlspecialchars(
                        $claim['item_location']
                    ); ?>

                </p>


                <div class="claim-card-info">

                    <div>

                        <span>
                            Submitted
                        </span>

                        <strong>

                            <?php
                            echo date(
                                'M d, Y',
                                strtotime($claim['claim_date'])
                            );
                            ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            Status
                        </span>

                        <strong>

                            <?php echo ucfirst(
                                htmlspecialchars($claimStatus)
                            ); ?>

                        </strong>

                    </div>

                </div>


                <div class="claim-description-preview">

                    <span>
                        Your explanation
                    </span>

                    <p>

                        <?php

                        $claimDescription =
                            $claim['claim_description'] ?? '';

                        echo htmlspecialchars(
                            strlen($claimDescription) > 120
                                ? substr($claimDescription, 0, 120) . '...'
                                : $claimDescription
                        );

                        ?>

                    </p>

                </div>


                <?php if ($claimStatus === 'rejected'): ?>

                    <div class="claim-status-message rejected-message">

                        Your claim was rejected by the administrator.

                    </div>

                <?php elseif ($claimStatus === 'approved'): ?>

                    <div class="claim-status-message approved-message">

                        🎉 Your claim has been approved!

                    </div>

                <?php else: ?>

                    <div class="claim-status-message pending-message">

                        Your claim is waiting for review.

                    </div>

                <?php endif; ?>


            </div>


        <?php endwhile; ?>


    </div>


<?php else: ?>


    <div class="claims-empty">

        <div class="claims-empty-icon">
            📋
        </div>

        <h2>
            No Claims Yet
        </h2>

        <p>
            When you claim a found item, your claims
            will appear here.
        </p>

        <a
            href="../index.php"
            class="btn btn-primary"
        >
            Browse Found Items
        </a>

    </div>


<?php endif; ?>


<?php

$userStmt->close();

endif;

?>

</div>


<?php include '../Partials/Footer.php'; ?>