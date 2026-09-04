<?php

require_once 'Config/config.php';


/* =========================================================
   ADMIN ACCESS
========================================================= */

if (!isAdmin()) {

    setAlert('Access denied. Admin only.', 'error');

    redirect('index.php');
}


/* =========================================================
   POST ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =====================================================
       UPDATE CLAIM
    ===================================================== */

    if (isset($_POST['update_claim'])) {

        $claimId = (int)($_POST['claim_id'] ?? 0);
        $itemId  = (int)($_POST['item_id'] ?? 0);
        $status  = $_POST['status'] ?? '';

        if (
            $claimId <= 0 ||
            $itemId <= 0 ||
            !in_array($status, ['approved', 'rejected'], true)
        ) {

            setAlert('Invalid claim action.', 'error');

            redirect('admin_dashboard.php?tab=claims');
        }


        /* =================================================
           APPROVE CLAIM
        ================================================= */

        if ($status === 'approved') {

            $conn->begin_transaction();

            try {

                /*
                 * Lock item to prevent two admins from
                 * approving different claims simultaneously.
                 */

                $itemStmt = $conn->prepare("
                    SELECT
                        item_id,
                        user_id,
                        name,
                        is_active,
                        status
                    FROM items
                    WHERE item_id = ?
                    FOR UPDATE
                ");

                $itemStmt->bind_param("i", $itemId);

                $itemStmt->execute();

                $itemResult = $itemStmt->get_result();


                if ($itemResult->num_rows === 0) {

                    $itemStmt->close();

                    throw new Exception('Item not found.');
                }


                $item = $itemResult->fetch_assoc();

                $itemStmt->close();


                /*
                 * Item has already been claimed/resolved.
                 */

                if ((int)$item['is_active'] !== 1) {

                    throw new Exception(
                        'This item has already been claimed or resolved. This claim cannot be approved.'
                    );
                }


                /*
                 * Make sure selected claim is still pending.
                 */

                $claimCheck = $conn->prepare("
                    SELECT claim_id, claimant_id
                    FROM claims
                    WHERE claim_id = ?
                      AND item_id = ?
                      AND is_active = 1
                      AND status = 'pending'
                    LIMIT 1
                ");

                $claimCheck->bind_param(
                    "ii",
                    $claimId,
                    $itemId
                );

                $claimCheck->execute();

                $claimResult = $claimCheck->get_result();


                if ($claimResult->num_rows === 0) {

                    $claimCheck->close();

                    throw new Exception(
                        'This claim has already been processed.'
                    );
                }


                $claimantId = (int)$claimResult->fetch_assoc()['claimant_id'];

                $claimCheck->close();


                $adminId = (int)$_SESSION['user_id'];


                /*
                 * APPROVE SELECTED CLAIM
                 */

                $approveStmt = $conn->prepare("
                    UPDATE claims
                    SET
                        status = 'approved',
                        admin_id = ?
                    WHERE claim_id = ?
                      AND item_id = ?
                      AND status = 'pending'
                      AND is_active = 1
                ");

                $approveStmt->bind_param(
                    "iii",
                    $adminId,
                    $claimId,
                    $itemId
                );


                if (!$approveStmt->execute()) {

                    $approveStmt->close();

                    throw new Exception(
                        'Failed to approve claim.'
                    );
                }


                $approveStmt->close();


                /*
                 * REJECT ALL OTHER PENDING CLAIMS.
                 *
                 * IMPORTANT:
                 * We keep their records.
                 *
                 * This means the admin can still see:
                 *
                 * - Who applied
                 * - Their contact information
                 * - Their reason
                 * - Their claim date
                 * - Their rejected status
                 */

                // Get the other pending claimants before rejecting their claims.
                $otherClaimants = [];

                $otherClaimantStmt = $conn->prepare("
                    SELECT claim_id, claimant_id
                    FROM claims
                    WHERE item_id = ?
                      AND claim_id != ?
                      AND status = 'pending'
                      AND is_active = 1
                ");

                $otherClaimantStmt->bind_param("ii", $itemId, $claimId);
                $otherClaimantStmt->execute();
                $otherClaimantResult = $otherClaimantStmt->get_result();

                while ($other = $otherClaimantResult->fetch_assoc()) {
                    $otherClaimants[] = [
                        'claim_id' => (int)$other['claim_id'],
                        'claimant_id' => (int)$other['claimant_id']
                    ];
                }

                $otherClaimantStmt->close();

                $rejectStmt = $conn->prepare("
                    UPDATE claims
                    SET
                        status = 'rejected',
                        admin_id = ?
                    WHERE item_id = ?
                      AND claim_id != ?
                      AND status = 'pending'
                      AND is_active = 1
                ");

                $rejectStmt->bind_param(
                    "iii",
                    $adminId,
                    $itemId,
                    $claimId
                );


                if (!$rejectStmt->execute()) {

                    $rejectStmt->close();

                    throw new Exception(
                        'Failed to reject the other claims.'
                    );
                }


                $rejectedCount =
                    $rejectStmt->affected_rows;

                $rejectStmt->close();


                /*
                 * MARK ITEM AS CLAIMED.
                 */

                $itemUpdate = $conn->prepare("
                    UPDATE items
                    SET
                        status = 'claimed',
                        is_active = 0
                    WHERE item_id = ?
                      AND is_active = 1
                ");

                $itemUpdate->bind_param(
                    "i",
                    $itemId
                );


                if (!$itemUpdate->execute()) {

                    $itemUpdate->close();

                    throw new Exception(
                        'Failed to update item status.'
                    );
                }


                $itemUpdate->close();


                $conn->commit();

                // Notify the claimant whose claim was approved.
                createNotification(
                    $claimantId,
                    'Claim Approved',
                    'Your claim for the item "' . $item['name'] . '" has been approved by the administrator.',
                    'success',
                    $itemId,
                    $claimId
                );

                // Notify claimants whose pending claims were automatically rejected.
                foreach ($otherClaimants as $otherClaimant) {
                    createNotification(
                        $otherClaimant['claimant_id'],
                        'Claim Rejected',
                        'Your claim for the item "' . $item['name'] . '" was not selected because another claim was approved.',
                        'warning',
                        $itemId,
                        $otherClaimant['claim_id']
                    );
                }

                // Notify the person who posted the found item.
                if (isset($item['user_id'])) {
                    createNotification(
                        (int)$item['user_id'],
                        'Item Claim Approved',
                        'A claim for your found item "' . $item['name'] . '" has been approved.',
                        'success',
                        $itemId,
                        $claimId
                    );
                }


                if ($rejectedCount > 0) {

                    setAlert(
                        'Claim approved successfully. ' .
                        $rejectedCount .
                        ' other pending claim(s) were automatically rejected.',
                        'success'
                    );

                } else {

                    setAlert(
                        'Claim approved successfully. The item has been marked as claimed.',
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


            redirect(
                'admin_dashboard.php?tab=claims'
            );
        }


        /* =================================================
           REJECT CLAIM
        ================================================= */

        if ($status === 'rejected') {

            $adminId = (int)$_SESSION['user_id'];


            $rejectStmt = $conn->prepare("
                UPDATE claims
                SET
                    status = 'rejected',
                    admin_id = ?
                WHERE claim_id = ?
                  AND item_id = ?
                  AND status = 'pending'
                  AND is_active = 1
            ");

            $rejectStmt->bind_param(
                "iii",
                $adminId,
                $claimId,
                $itemId
            );


            if ($rejectStmt->execute()) {

                if ($rejectStmt->affected_rows > 0) {

                    createNotification(
                        $claim['claimant_id'],
                        'Claim Rejected',
                        'Your claim for the item "' . $claim['item_name'] . '" has been rejected by the administrator.',
                        'warning',
                        $claim['item_id'],
                        $claimId
                    );

                    setAlert(
                        'Claim rejected successfully.',
                        'success'
                    );

                } else {

                    setAlert(
                        'This claim has already been processed.',
                        'error'
                    );
                }

            } else {

                setAlert(
                    'Failed to reject claim.',
                    'error'
                );
            }


            $rejectStmt->close();


            redirect(
                'admin_dashboard.php?tab=claims'
            );
        }
    }


    /* =====================================================
       TOGGLE USER
    ===================================================== */

    if (isset($_POST['toggle_user'])) {

        $userId = (int)$_POST['user_id'];


        if ($userId != $_SESSION['user_id']) {

            $stmt = $conn->prepare("
                UPDATE users
                SET is_active = NOT is_active
                WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);

            $stmt->execute();

            $stmt->close();

            setAlert(
                'User status updated.',
                'success'
            );
        }


        redirect(
            'admin_dashboard.php?tab=users'
        );
    }


    /* =====================================================
       DELETE ITEM
    ===================================================== */

    if (isset($_POST['delete_item'])) {

        $itemId = (int)$_POST['item_id'];


        $stmt = $conn->prepare("
            UPDATE items
            SET is_active = 0
            WHERE item_id = ?
        ");

        $stmt->bind_param("i", $itemId);

        $stmt->execute();

        $stmt->close();


        $stmt = $conn->prepare("
            UPDATE item_images
            SET is_active = 0
            WHERE item_id = ?
        ");

        $stmt->bind_param("i", $itemId);

        $stmt->execute();

        $stmt->close();


        setAlert(
            'Item deleted successfully.',
            'success'
        );


        redirect(
            'admin_dashboard.php?tab=items'
        );
    }
}


/* =========================================================
   CURRENT TAB
========================================================= */

$tab = $_GET['tab'] ?? 'claims';


/* =========================================================
   PENDING CLAIM COUNT
========================================================= */

$pendingClaimsResult = $conn->query("
    SELECT COUNT(*) AS count
    FROM claims
    WHERE status = 'pending'
      AND is_active = 1
");

$pendingClaims = 0;

if ($pendingClaimsResult) {

    $pendingClaims =
        (int)$pendingClaimsResult->fetch_assoc()['count'];
}


/* =========================================================
   PAGE
========================================================= */

$pageTitle = 'Admin Dashboard';

include 'Partials/Header.php';

?>


<div class="container">


    <!-- =====================================================
         DASHBOARD HEADER
    ====================================================== -->

    <div class="admin-dashboard-header">

        <div>

            <h2>
                Admin Dashboard
            </h2>

            <p>
                Manage users, items and claims.
            </p>

        </div>


        <?php if ($pendingClaims > 0): ?>

            <a
                href="?tab=claims"
                class="admin-pending-alert"
            >

                🔔

                <?php echo $pendingClaims; ?>

                Pending Claim<?php
                echo $pendingClaims != 1 ? 's' : '';
                ?>

            </a>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         TABS
    ====================================================== -->

    <div class="admin-tabs">

        <a
            href="?tab=claims"
            class="tab <?php
                echo $tab === 'claims' ? 'active' : '';
            ?>"
        >

            Claims

            <?php if ($pendingClaims > 0): ?>

                <span class="admin-tab-count">

                    <?php
                    echo $pendingClaims;
                    ?>

                </span>

            <?php endif; ?>

        </a>


        <a
            href="?tab=items"
            class="tab <?php
                echo $tab === 'items' ? 'active' : '';
            ?>"
        >

            All Items

        </a>


        <a
            href="?tab=users"
            class="tab <?php
                echo $tab === 'users' ? 'active' : '';
            ?>"
        >

            Users

        </a>


        <a
            href="?tab=stats"
            class="tab <?php
                echo $tab === 'stats' ? 'active' : '';
            ?>"
        >

            Statistics

        </a>

    </div>


    <div class="tab-content">


<?php
/* =========================================================
   CLAIMS TAB
========================================================= */

if ($tab === 'claims'):
?>


    <div class="admin-section-header">

        <div>

            <h3>
                Claims Management
            </h3>

            <p>
                Review claims grouped by item. All applicants remain visible after a claim is approved.
            </p>

        </div>

    </div>


<?php

/*
 * Get every item that has claims.
 *
 * We intentionally DO NOT check i.is_active.
 *
 * Therefore, even after an item has been claimed,
 * its complete claim history remains visible.
 */

$itemClaimsQuery = $conn->query("
    SELECT

        i.item_id,
        i.name AS item_name,
        i.type,
        i.category,
        i.location,
        i.status AS item_status,
        i.is_active AS item_active,

        COUNT(c.claim_id) AS total_claims,

        SUM(
            CASE
                WHEN c.status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_count,

        SUM(
            CASE
                WHEN c.status = 'approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_count,

        SUM(
            CASE
                WHEN c.status = 'rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected_count

    FROM items i

    JOIN claims c
        ON i.item_id = c.item_id

    WHERE c.is_active = 1

    GROUP BY
        i.item_id,
        i.name,
        i.type,
        i.category,
        i.location,
        i.status,
        i.is_active

    ORDER BY

        CASE

            WHEN SUM(
                CASE
                    WHEN c.status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ) > 0

            THEN 1

            ELSE 2

        END,

        i.created_at DESC
");


if ($itemClaimsQuery && $itemClaimsQuery->num_rows > 0):


    while ($itemSummary = $itemClaimsQuery->fetch_assoc()):

        $itemId = (int)$itemSummary['item_id'];


        /*
         * Get ALL claims for this item.
         */

        $claimsQuery = $conn->query("
            SELECT

                c.*,

                u.name AS claimant_name,
                u.email AS claimant_email,
                u.phone AS claimant_phone

            FROM claims c

            JOIN users u
                ON c.claimant_id = u.user_id

            WHERE c.item_id = $itemId

              AND c.is_active = 1

            ORDER BY

                CASE c.status

                    WHEN 'pending' THEN 1
                    WHEN 'approved' THEN 2
                    WHEN 'rejected' THEN 3
                    ELSE 4

                END,

                c.created_at ASC
        ");

?>


        <!-- =================================================
             ITEM CLAIM GROUP
        ================================================== -->

        <div
            class="claim-item-group"
            style="
                margin-bottom:2rem;
                border:1px solid #ddd;
                border-radius:10px;
                overflow:hidden;
                background:#fff;
            "
        >


            <!-- =============================================
                 ITEM HEADER
            ============================================== -->

            <div
                class="claim-item-header"
                style="
                    padding:1rem;
                    background:#f5f5f5;
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:1rem;
                    flex-wrap:wrap;
                "
            >

                <div>

                    <h3 style="margin:0 0 8px 0;">

                        <?php
                        echo htmlspecialchars(
                            $itemSummary['item_name']
                        );
                        ?>

                    </h3>


                    <div>

                        <span
                            class="badge badge-<?php
                                echo strtolower(
                                    $itemSummary['type']
                                );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $itemSummary['type']
                            );
                            ?>

                        </span>


                        <span
                            class="badge"
                            style="margin-left:5px;"
                        >

                            ID #<?php echo $itemId; ?>

                        </span>


<?php if (
    $itemSummary['item_status'] === 'claimed'
): ?>

                        <span
                            class="badge badge-success"
                            style="margin-left:5px;"
                        >

                            ✓ Item Claimed

                        </span>


<?php elseif (
    (int)$itemSummary['item_active'] === 1
): ?>

                        <span
                            class="badge badge-pending"
                            style="margin-left:5px;"
                        >

                            Item Active

                        </span>


<?php else: ?>

                        <span
                            class="badge badge-danger"
                            style="margin-left:5px;"
                        >

                            Item Inactive

                        </span>

<?php endif; ?>

                    </div>


                    <p style="margin:8px 0 0;">

                        <strong>Category:</strong>

                        <?php
                        echo htmlspecialchars(
                            $itemSummary['category']
                        );
                        ?>

                        &nbsp; | &nbsp;

                        <strong>Location:</strong>

                        <?php
                        echo htmlspecialchars(
                            $itemSummary['location']
                        );
                        ?>

                    </p>

                </div>


                <!-- =========================================
                     ITEM ACTIONS + COUNTS
                ========================================== -->

                <div
                    style="
                        display:flex;
                        align-items:center;
                        gap:8px;
                        flex-wrap:wrap;
                    "
                >

                    <!-- NEW VIEW ITEM BUTTON -->

                    <a
                        href="item_details.php?id=<?php echo $itemId; ?>"
                        class="btn btn-sm btn-primary"
                    >

                        👁 View Item

                    </a>


                    <span class="badge">

                        Total:
                        <?php
                        echo (int)$itemSummary['total_claims'];
                        ?>

                    </span>


                    <?php if (
                        (int)$itemSummary['pending_count'] > 0
                    ): ?>

                        <span
                            class="badge badge-pending"
                        >

                            Pending:
                            <?php
                            echo (int)$itemSummary['pending_count'];
                            ?>

                        </span>

                    <?php endif; ?>


                    <?php if (
                        (int)$itemSummary['approved_count'] > 0
                    ): ?>

                        <span
                            class="badge badge-success"
                        >

                            Approved:
                            <?php
                            echo (int)$itemSummary['approved_count'];
                            ?>

                        </span>

                    <?php endif; ?>


                    <?php if (
                        (int)$itemSummary['rejected_count'] > 0
                    ): ?>

                        <span
                            class="badge badge-danger"
                        >

                            Rejected:
                            <?php
                            echo (int)$itemSummary['rejected_count'];
                            ?>

                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =================================================
                 CLAIMS TABLE
            ================================================== -->

            <div
                class="table-container"
                style="
                    margin:0;
                    border-radius:0;
                "
            >

                <table>

                    <thead>

                        <tr>

                            <th>Claim ID</th>

                            <th>Claimant</th>

                            <th>Why They Claim It</th>

                            <th>Contact</th>

                            <th>Claim Date</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>


<?php

if (
    $claimsQuery &&
    $claimsQuery->num_rows > 0
):

    while (
        $claim = $claimsQuery->fetch_assoc()
    ):

        $claimStatus =
            strtolower($claim['status']);

?>


                        <tr>


                            <!-- CLAIM ID -->

                            <td>

                                <strong>
                                    #<?php
                                    echo (int)$claim['claim_id'];
                                    ?>
                                </strong>

                            </td>


                            <!-- CLAIMANT -->

                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $claim['claimant_name']
                                    );
                                    ?>

                                </strong>

                                <br>

                                <small>

                                    📧
                                    <?php
                                    echo htmlspecialchars(
                                        $claim['claimant_email']
                                    );
                                    ?>

                                </small>

                                <br>

                                <small>

                                    📞
                                    <?php
                                    echo htmlspecialchars(
                                        $claim['claimant_phone']
                                    );
                                    ?>

                                </small>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $claim[
                                            'claim_description'
                                        ]
                                    )
                                );
                                ?>

                            </td>


                            <!-- CONTACT -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $claim['contact_info']
                                );
                                ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?php
                                echo date(
                                    'M d, Y',
                                    strtotime(
                                        $claim['claim_date']
                                    )
                                );
                                ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="badge badge-<?php
                                        echo $claimStatus;
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        htmlspecialchars(
                                            $claim['status']
                                        )
                                    );
                                    ?>

                                </span>


<?php if (
    $claimStatus === 'approved'
): ?>

                                <br>

                                <small
                                    style="color:#16803c;"
                                >

                                    ✓ Selected claimant

                                </small>


<?php elseif (
    $claimStatus === 'rejected'
): ?>

                                <br>

                                <small
                                    style="color:#b42318;"
                                >

                                    Other claim / not selected

                                </small>


<?php elseif (
    $claimStatus === 'pending'
): ?>

                                <br>

                                <small
                                    style="color:#a15c00;"
                                >

                                    Waiting for admin

                                </small>

<?php endif; ?>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <a
                                    href="claim_details.php?id=<?php echo (int)$claim['claim_id']; ?>"
                                    class="btn btn-sm btn-primary"
                                    style="margin-bottom:5px; display:inline-block;"
                                >
                                    👁 View Details
                                </a>

                                <br>

<?php if (
    $claimStatus === 'pending'
): ?>


                                <!-- APPROVE -->

                                <form
                                    method="POST"
                                    style="
                                        display:inline;
                                        margin-right:4px;
                                    "
                                    onsubmit="
                                        return confirm(
                                            'Approve this claim? All other pending claims for this item will automatically be rejected.'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="claim_id"
                                        value="<?php
                                            echo (int)
                                            $claim['claim_id'];
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?php
                                            echo $itemId;
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="approved"
                                    >

                                    <button
                                        type="submit"
                                        name="update_claim"
                                        class="btn btn-sm btn-success"
                                    >

                                        ✓ Approve

                                    </button>

                                </form>


                                <!-- REJECT -->

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Reject this claim?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="claim_id"
                                        value="<?php
                                            echo (int)
                                            $claim['claim_id'];
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?php
                                            echo $itemId;
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="rejected"
                                    >

                                    <button
                                        type="submit"
                                        name="update_claim"
                                        class="btn btn-sm btn-danger"
                                    >

                                        ✕ Reject

                                    </button>

                                </form>


<?php else: ?>


                                <span
                                    style="
                                        color:#777;
                                        font-size:0.9rem;
                                    "
                                >

                                    No actions

                                </span>

<?php endif; ?>


                            </td>

                        </tr>


<?php

    endwhile;

else:

?>


                        <tr>

                            <td
                                colspan="7"
                                style="text-align:center;"
                            >

                                No claims found for this item.

                            </td>

                        </tr>


<?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


<?php

    endwhile;

else:

?>


        <div
            class="alert alert-info"
            style="text-align:center;"
        >

            No claims have been submitted yet.

        </div>


<?php endif; ?>


<?php
/* =========================================================
   ITEMS TAB
========================================================= */

elseif ($tab === 'items'):
?>


    <h3>
        All Items
    </h3>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Posted By</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>


<?php

$items = $conn->query("
    SELECT
        i.*,
        u.name AS user_name,
        u.email

    FROM items i

    JOIN users u
        ON i.user_id = u.user_id

    WHERE i.is_active = 1

    ORDER BY i.created_at DESC
");


if (
    $items &&
    $items->num_rows > 0
):

    while (
        $item = $items->fetch_assoc()
    ):

?>


                <tr>

                    <td>
                        <?php echo $item['item_id']; ?>
                    </td>


                    <td>
                        <?php
                        echo htmlspecialchars(
                            $item['name']
                        );
                        ?>
                    </td>


                    <td>

                        <span
                            class="badge badge-<?php
                                echo strtolower(
                                    $item['type']
                                );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $item['type']
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $item['category']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $item['user_name']
                        );
                        ?>

                        <br>

                        <small>

                            <?php
                            echo htmlspecialchars(
                                $item['email']
                            );
                            ?>

                        </small>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $item['location']
                        );
                        ?>

                    </td>


                    <td>

                        <span
                            class="badge badge-<?php
                                echo strtolower(
                                    $item['status']
                                );
                            ?>"
                        >

                            <?php
                            echo ucfirst(
                                htmlspecialchars(
                                    $item['status']
                                )
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php
                        echo date(
                            'M d, Y',
                            strtotime(
                                $item['created_at']
                            )
                        );
                        ?>

                    </td>


                    <td>

                        <!-- VIEW ITEM -->

                        <a
                            href="item_details.php?id=<?php
                                echo $item['item_id'];
                            ?>"
                            class="btn btn-sm btn-primary"
                        >

                            👁 View

                        </a>


                        <!-- DELETE ITEM -->

                        <form
                            method="POST"
                            style="
                                display:inline;
                                margin-left:4px;
                            "
                            onsubmit="
                                return confirm(
                                    'Delete this item?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="item_id"
                                value="<?php
                                    echo $item['item_id'];
                                ?>"
                            >


                            <button
                                type="submit"
                                name="delete_item"
                                class="btn btn-sm btn-danger"
                            >

                                Delete

                            </button>

                        </form>

                    </td>

                </tr>


<?php

    endwhile;

else:

?>


                <tr>

                    <td
                        colspan="9"
                        style="text-align:center;"
                    >

                        No items found.

                    </td>

                </tr>


<?php endif; ?>


            </tbody>

        </table>

    </div>


<?php
/* =========================================================
   USERS TAB
========================================================= */

elseif ($tab === 'users'):
?>


    <h3>
        Users Management
    </h3>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>


<?php

$users = $conn->query("
    SELECT *
    FROM users
    ORDER BY created_at DESC
");


if ($users):

    while (
        $user = $users->fetch_assoc()
    ):

?>


                <tr>

                    <td>
                        <?php echo $user['user_id']; ?>
                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $user['name']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $user['email']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $user['phone']
                        );
                        ?>

                    </td>


                    <td>

                        <span
                            class="badge badge-<?php
                                echo $user['role'];
                            ?>"
                        >

                            <?php
                            echo ucfirst(
                                $user['role']
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <span
                            class="badge badge-<?php
                                echo $user['is_active']
                                    ? 'success'
                                    : 'danger';
                            ?>"
                        >

                            <?php
                            echo $user['is_active']
                                ? 'Active'
                                : 'Inactive';
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php
                        echo date(
                            'M d, Y',
                            strtotime(
                                $user['created_at']
                            )
                        );
                        ?>

                    </td>


                    <td>


<?php if (
    $user['user_id'] != $_SESSION['user_id']
): ?>


                        <form
                            method="POST"
                            style="display:inline;"
                        >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?php
                                    echo $user['user_id'];
                                ?>"
                            >


                            <button
                                type="submit"
                                name="toggle_user"
                                class="btn btn-sm btn-warning"
                            >

                                <?php
                                echo $user['is_active']
                                    ? 'Deactivate'
                                    : 'Activate';
                                ?>

                            </button>

                        </form>


<?php else: ?>


                        <em>
                            (You)
                        </em>


<?php endif; ?>


                    </td>

                </tr>


<?php

    endwhile;

endif;

?>


            </tbody>

        </table>

    </div>


<?php
/* =========================================================
   STATISTICS TAB
========================================================= */

elseif ($tab === 'stats'):
?>


    <h3>
        📊 System Statistics
    </h3>


    <div class="stats-grid">


<?php

$totalUsers = $conn->query("
    SELECT COUNT(*) AS count
    FROM users
    WHERE is_active = 1
")->fetch_assoc()['count'];


$totalItems = $conn->query("
    SELECT COUNT(*) AS count
    FROM items
    WHERE is_active = 1
")->fetch_assoc()['count'];


$lostItems = $conn->query("
    SELECT COUNT(*) AS count
    FROM items
    WHERE type = 'Lost'
      AND is_active = 1
      AND status = 'pending'
")->fetch_assoc()['count'];


$foundItems = $conn->query("
    SELECT COUNT(*) AS count
    FROM items
    WHERE type = 'Found'
      AND is_active = 1
      AND status = 'pending'
")->fetch_assoc()['count'];


$pendingClaimsStat = $conn->query("
    SELECT COUNT(*) AS count
    FROM claims
    WHERE status = 'pending'
      AND is_active = 1
")->fetch_assoc()['count'];


$approvedClaims = $conn->query("
    SELECT COUNT(*) AS count
    FROM claims
    WHERE status = 'approved'
")->fetch_assoc()['count'];


/*
 * IMPORTANT:
 *
 * Claimed items are counted regardless of is_active.
 */

$claimedItems = $conn->query("
    SELECT COUNT(*) AS count
    FROM items
    WHERE status = 'claimed'
")->fetch_assoc()['count'];


$totalClaims = $conn->query("
    SELECT COUNT(*) AS count
    FROM claims
    WHERE is_active = 1
")->fetch_assoc()['count'];

?>


        <div class="stat-card">

            <h4>
                👥 Active Users
            </h4>

            <p class="stat-number">
                <?php echo $totalUsers; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                📦 Active Items
            </h4>

            <p class="stat-number">
                <?php echo $totalItems; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                🔍 Lost Items
            </h4>

            <p class="stat-number">
                <?php echo $lostItems; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                📍 Found Items
            </h4>

            <p class="stat-number">
                <?php echo $foundItems; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                ⏳ Pending Claims
            </h4>

            <p class="stat-number">
                <?php echo $pendingClaimsStat; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                ✅ Approved Claims
            </h4>

            <p class="stat-number">
                <?php echo $approvedClaims; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                📦 Claimed Items
            </h4>

            <p class="stat-number">
                <?php echo $claimedItems; ?>
            </p>

        </div>


        <div class="stat-card">

            <h4>
                📋 Total Claims
            </h4>

            <p class="stat-number">
                <?php echo $totalClaims; ?>
            </p>

        </div>


    </div>


    <!-- =====================================================
         RECENT ACTIVITY
    ====================================================== -->

    <h3 style="margin-top:2rem;">

        Recent Activity

    </h3>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Activity</th>
                    <th>User</th>
                    <th>Date</th>

                </tr>

            </thead>


            <tbody>


<?php

$recentItems = $conn->query("
    SELECT
        i.name,
        i.type,
        u.name AS user_name,
        i.created_at

    FROM items i

    JOIN users u
        ON i.user_id = u.user_id

    ORDER BY i.created_at DESC

    LIMIT 10
");


if (
    $recentItems &&
    $recentItems->num_rows > 0
):

    while (
        $activity =
        $recentItems->fetch_assoc()
    ):

?>


                <tr>

                    <td>

                        <span
                            class="badge badge-<?php
                                echo strtolower(
                                    $activity['type']
                                );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $activity['type']
                            );
                            ?>

                        </span>


                        <?php
                        echo htmlspecialchars(
                            $activity['name']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $activity['user_name']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo date(
                            'M d, Y H:i',
                            strtotime(
                                $activity['created_at']
                            )
                        );
                        ?>

                    </td>

                </tr>


<?php

    endwhile;

else:

?>


                <tr>

                    <td
                        colspan="3"
                        style="text-align:center;"
                    >

                        No recent activity.

                    </td>

                </tr>


<?php endif; ?>


            </tbody>

        </table>

    </div>


<?php endif; ?>


    </div>

</div>


<?php include 'Partials/Footer.php'; ?>