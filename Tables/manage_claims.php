<?php

require_once '../Config/config.php';


/* =========================================================
   ADMIN CHECK
========================================================= */

if (!isLoggedIn()) {
    redirect('Auth/login.php');
}

if (!isAdmin()) {

    setAlert(
        'Administrator access required.',
        'error'
    );

    redirect('Tables/claims.php');
}


$adminId = (int)$_SESSION['user_id'];


/* =========================================================
   APPROVE / REJECT CLAIM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['claim_action'])
) {

    $claimId = isset($_POST['claim_id'])
        ? (int)$_POST['claim_id']
        : 0;

    $claimAction = $_POST['claim_action'];


    if (
        $claimId <= 0 ||
        !in_array(
            $claimAction,
            ['approve', 'reject'],
            true
        )
    ) {

        setAlert(
            'Invalid claim action.',
            'error'
        );

        redirect('Tables/manage_claims.php');
    }


    /* -----------------------------------------------------
       GET CLAIM
    ----------------------------------------------------- */

    $claimStmt = $conn->prepare("
        SELECT
            c.claim_id,
            c.item_id,
            c.claimant_id,
            c.admin_id,
            c.status,
            c.is_active,
            i.name AS item_name,
            i.user_id AS item_owner_id
        FROM claims c
        INNER JOIN items i
            ON c.item_id = i.item_id
        WHERE c.claim_id = ?
        LIMIT 1
    ");

    $claimStmt->bind_param(
        "i",
        $claimId
    );

    $claimStmt->execute();

    $claimResult = $claimStmt->get_result();


    if ($claimResult->num_rows === 0) {

        $claimStmt->close();

        setAlert(
            'Claim not found.',
            'error'
        );

        redirect('Tables/manage_claims.php');
    }


    $claim = $claimResult->fetch_assoc();

    $claimStmt->close();


    /* -----------------------------------------------------
       ONLY ACTIVE PENDING CLAIMS
    ----------------------------------------------------- */

    if (
        (int)$claim['is_active'] !== 1 ||
        strtolower($claim['status']) !== 'pending'
    ) {

        setAlert(
            'This claim has already been processed.',
            'error'
        );

        redirect('Tables/manage_claims.php');
    }


    /* -----------------------------------------------------
       APPROVE
    ----------------------------------------------------- */

    /* -----------------------------------------------------
   APPROVE CLAIM
----------------------------------------------------- */

if ($claimAction === 'approve') {

    $conn->begin_transaction();

    try {

        /*
         * Lock the item while processing the claim.
         * This prevents two claims from being approved
         * for the same item at the same time.
         */

        $itemLock = $conn->prepare("
            SELECT
                item_id,
                is_active
            FROM items
            WHERE item_id = ?
            FOR UPDATE
        ");

        $itemLock->bind_param(
            "i",
            $claim['item_id']
        );

        $itemLock->execute();

        $itemResult = $itemLock->get_result();

        if ($itemResult->num_rows === 0) {
            throw new Exception(
                'The item no longer exists.'
            );
        }

        $lockedItem = $itemResult->fetch_assoc();

        $itemLock->close();


        /*
         * IMPORTANT:
         * If the item is already inactive, somebody has
         * already completed a claim for it.
         */

        if ((int)$lockedItem['is_active'] !== 1) {

            throw new Exception(
                'This item has already been claimed or resolved. This claim cannot be approved.'
            );
        }


        /*
         * Make sure the claim is still pending.
         */

        $pendingCheck = $conn->prepare("
            SELECT claim_id
            FROM claims
            WHERE claim_id = ?
              AND is_active = 1
              AND status = 'pending'
            LIMIT 1
        ");

        $pendingCheck->bind_param(
            "i",
            $claimId
        );

        $pendingCheck->execute();

        $pendingResult = $pendingCheck->get_result();

        if ($pendingResult->num_rows === 0) {

            $pendingCheck->close();

            throw new Exception(
                'This claim has already been processed.'
            );
        }

        $pendingCheck->close();


        /*
         * APPROVE THE SELECTED CLAIM
         */

        $approveStmt = $conn->prepare("
            UPDATE claims
            SET
                status = 'approved',
                admin_id = ?
            WHERE claim_id = ?
              AND is_active = 1
              AND status = 'pending'
        ");

        $approveStmt->bind_param(
            "ii",
            $adminId,
            $claimId
        );

        if (!$approveStmt->execute()) {

            $approveStmt->close();

            throw new Exception(
                'Failed to approve the claim.'
            );
        }

        $approveStmt->close();


        /*
         * Get the other pending claimants before rejecting their claims.
         */

        $otherClaimants = [];

        $otherClaimantStmt = $conn->prepare("
            SELECT claim_id, claimant_id
            FROM claims
            WHERE item_id = ?
              AND claim_id != ?
              AND status = 'pending'
              AND is_active = 1
        ");

        $otherClaimantStmt->bind_param(
            "ii",
            $claim['item_id'],
            $claimId
        );

        $otherClaimantStmt->execute();
        $otherClaimantResult = $otherClaimantStmt->get_result();

        while ($other = $otherClaimantResult->fetch_assoc()) {
            $otherClaimants[] = [
                'claim_id' => (int)$other['claim_id'],
                'claimant_id' => (int)$other['claimant_id']
            ];
        }

        $otherClaimantStmt->close();


        /*
         * REJECT EVERY OTHER PENDING CLAIM
         * FOR THE SAME ITEM
         */

        $rejectOthers = $conn->prepare("
            UPDATE claims
            SET
                status = 'rejected',
                admin_id = ?
            WHERE item_id = ?
              AND claim_id != ?
              AND status = 'pending'
              AND is_active = 1
        ");

        $rejectOthers->bind_param(
            "iii",
            $adminId,
            $claim['item_id'],
            $claimId
        );

        if (!$rejectOthers->execute()) {

            $rejectOthers->close();

            throw new Exception(
                'Failed to update other claims.'
            );
        }

        $rejectedCount =
            $rejectOthers->affected_rows;

        $rejectOthers->close();


        /*
         * CLOSE THE ITEM
         */

        $updateItem = $conn->prepare("
            UPDATE items
            SET
                status = 'claimed',
                is_active = 0
            WHERE item_id = ?
              AND is_active = 1
        ");

        $updateItem->bind_param(
            "i",
            $claim['item_id']
        );

        if (!$updateItem->execute()) {

            $updateItem->close();

            throw new Exception(
                'Failed to mark the item as resolved.'
            );
        }

        $updateItem->close();


        /*
         * EVERYTHING SUCCEEDED
         */

        $conn->commit();

        // Notify the approved claimant.
        createNotification(
            (int)$claim['claimant_id'],
            'Claim Approved',
            'Your claim for the item "' . $claim['item_name'] . '" has been approved by the administrator.',
            'success',
            (int)$claim['item_id'],
            $claimId
        );

        // Notify claimants whose pending claims were automatically rejected.
        foreach ($otherClaimants as $otherClaimant) {
            createNotification(
                $otherClaimant['claimant_id'],
                'Claim Rejected',
                'Your claim for the item "' . $claim['item_name'] . '" was not selected because another claim was approved.',
                'warning',
                (int)$claim['item_id'],
                $otherClaimant['claim_id']
            );
        }

        // Notify the person who posted the found item.
        if ((int)$claim['item_owner_id'] !== (int)$claim['claimant_id']) {
            createNotification(
                (int)$claim['item_owner_id'],
                'Item Claim Approved',
                'A claim for your found item "' . $claim['item_name'] . '" has been approved.',
                'success',
                (int)$claim['item_id'],
                $claimId
            );
        }


        if ($rejectedCount > 0) {

            setAlert(
                'Claim approved successfully. ' .
                $rejectedCount .
                ' other pending claim(s) for this item were automatically rejected.',
                'success'
            );

        } else {

            setAlert(
                'Claim approved successfully. The item has been marked as resolved.',
                'success'
            );
        }


    } catch (Exception $e) {

        $conn->rollback();

        setAlert(
            $e->getMessage(),
            'error'
        );
    }


    redirect('Tables/manage_claims.php');
}

    /* -----------------------------------------------------
       REJECT
    ----------------------------------------------------- */

    if ($claimAction === 'reject') {

        $rejectStmt = $conn->prepare("
            UPDATE claims
            SET
                status = 'rejected',
                admin_id = ?
            WHERE claim_id = ?
              AND is_active = 1
              AND status = 'pending'
        ");

        $rejectStmt->bind_param(
            "ii",
            $adminId,
            $claimId
        );


        if ($rejectStmt->execute()) {

            if ($rejectStmt->affected_rows > 0) {
                createNotification(
                    (int)$claim['claimant_id'],
                    'Claim Rejected',
                    'Your claim for the item "' . $claim['item_name'] . '" has been rejected by the administrator.',
                    'warning',
                    (int)$claim['item_id'],
                    $claimId
                );
            }

            setAlert(
                'Claim rejected.',
                'success'
            );

        } else {

            setAlert(
                'Failed to reject claim.',
                'error'
            );
        }


        $rejectStmt->close();

        redirect('Tables/manage_claims.php');
    }
}


/* =========================================================
   FILTER
========================================================= */

$filter = $_GET['status'] ?? 'all';

$allowedFilters = [
    'all',
    'pending',
    'approved',
    'rejected'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}


/* =========================================================
   FETCH CLAIMS
========================================================= */

if ($filter === 'all') {

    $sql = "
        SELECT
            c.*,
            i.name AS item_name,
            i.location AS item_location,
            u.name AS claimant_name,
            u.email AS claimant_email
        FROM claims c

        INNER JOIN items i
            ON c.item_id = i.item_id

        INNER JOIN users u
            ON c.claimant_id = u.user_id

        WHERE c.is_active = 1

        ORDER BY
            CASE
                WHEN c.status = 'pending' THEN 1
                WHEN c.status = 'approved' THEN 2
                ELSE 3
            END,
            c.created_at DESC
    ";

    $result = $conn->query($sql);

} else {

    $stmt = $conn->prepare("
        SELECT
            c.*,
            i.name AS item_name,
            i.location AS item_location,
            u.name AS claimant_name,
            u.email AS claimant_email
        FROM claims c

        INNER JOIN items i
            ON c.item_id = i.item_id

        INNER JOIN users u
            ON c.claimant_id = u.user_id

        WHERE c.is_active = 1
          AND c.status = ?

        ORDER BY c.created_at DESC
    ");

    $stmt->bind_param(
        "s",
        $filter
    );

    $stmt->execute();

    $result = $stmt->get_result();
}


/* =========================================================
   PAGE
========================================================= */

$pageTitle = 'Manage Claims';

include '../Partials/Header.php';

?>


<div class="container manage-claims-page">


    <!-- HEADER -->

    <div class="claims-header">

        <div>

            <h1>
                Manage Claims
            </h1>

            <p>
                Review and process submitted item claims.
            </p>

        </div>

        <a
            href="claims.php"
            class="btn btn-secondary"
        >
            My Claims
        </a>

    </div>


    <!-- FILTERS -->

    <div class="claim-filters">

        <a
            href="manage_claims.php?status=all"
            class="<?php echo $filter === 'all' ? 'active' : ''; ?>"
        >
            All
        </a>

        <a
            href="manage_claims.php?status=pending"
            class="<?php echo $filter === 'pending' ? 'active' : ''; ?>"
        >
            Pending
        </a>

        <a
            href="manage_claims.php?status=approved"
            class="<?php echo $filter === 'approved' ? 'active' : ''; ?>"
        >
            Approved
        </a>

        <a
            href="manage_claims.php?status=rejected"
            class="<?php echo $filter === 'rejected' ? 'active' : ''; ?>"
        >
            Rejected
        </a>

    </div>


    <!-- CLAIMS -->

    <div class="admin-claims-list">


<?php if ($result && $result->num_rows > 0): ?>


    <?php while ($claim = $result->fetch_assoc()): ?>

        <?php
        $claimStatus = strtolower(
            $claim['status'] ?? 'pending'
        );
        ?>


        <div class="admin-claim-card">


            <!-- TOP -->

            <div class="admin-claim-top">

                <div>

                    <span class="claim-status status-<?php echo htmlspecialchars($claimStatus); ?>">

                        <?php echo ucfirst(
                            htmlspecialchars($claimStatus)
                        ); ?>

                    </span>

                    <h2>

                        <?php echo htmlspecialchars(
                            $claim['item_name']
                        ); ?>

                    </h2>

                </div>


                <span class="admin-claim-date">

                    Submitted

                    <?php
                    echo date(
                        'M d, Y',
                        strtotime($claim['claim_date'])
                    );
                    ?>

                </span>

            </div>


            <!-- INFORMATION -->

            <div class="admin-claim-info">


                <div>

                    <span>
                        Claimant
                    </span>

                    <strong>
                        <?php echo htmlspecialchars(
                            $claim['claimant_name']
                        ); ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Contact
                    </span>

                    <strong>
                        <?php echo htmlspecialchars(
                            $claim['contact_info']
                        ); ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Location
                    </span>

                    <strong>
                        📍 <?php echo htmlspecialchars(
                            $claim['item_location']
                        ); ?>
                    </strong>

                </div>

            </div>


            <!-- DESCRIPTION -->

            <div class="admin-claim-description">

                <h3>
                    Claimant's Explanation
                </h3>

                <p>

                    <?php echo nl2br(
                        htmlspecialchars(
                            $claim['claim_description']
                        )
                    ); ?>

                </p>

            </div>


            <!-- ACTIONS -->

            <div class="admin-claim-actions" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">

                <a
                    href="../claim_details.php?id=<?php echo (int)$claim['claim_id']; ?>"
                    class="btn btn-primary"
                >
                    👁 View Claim Details
                </a>

            <?php if ($claimStatus === 'pending'): ?>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">

                    <form
                        method="POST"
                        onsubmit="return confirm('Are you sure you want to approve this claim? The item will be marked as resolved.');"
                    >

                        <input
                            type="hidden"
                            name="claim_id"
                            value="<?php echo $claim['claim_id']; ?>"
                        >

                        <button
                            type="submit"
                            name="claim_action"
                            value="approve"
                            class="btn btn-success"
                        >
                            ✓ Approve Claim
                        </button>

                    </form>


                    <form
                        method="POST"
                        onsubmit="return confirm('Are you sure you want to reject this claim?');"
                    >

                        <input
                            type="hidden"
                            name="claim_id"
                            value="<?php echo $claim['claim_id']; ?>"
                        >

                        <button
                            type="submit"
                            name="claim_action"
                            value="reject"
                            class="btn btn-danger"
                        >
                            ✕ Reject Claim
                        </button>

                    </form>

                </div>

            <?php elseif ($claimStatus === 'approved'): ?>

                <div class="admin-result-message admin-approved">
                    ✓ This claim was approved.
                </div>

            <?php elseif ($claimStatus === 'rejected'): ?>

                <div class="admin-result-message admin-rejected">
                    ✕ This claim was rejected.
                </div>

            <?php endif; ?>

            </div>

        </div>


    <?php endwhile; ?>


<?php else: ?>


    <div class="claims-empty">

        <div class="claims-empty-icon">
            📋
        </div>

        <h2>
            No Claims Found
        </h2>

        <p>
            There are currently no claims matching this filter.
        </p>

    </div>


<?php endif; ?>


    </div>

</div>


<?php include '../Partials/Footer.php'; ?>