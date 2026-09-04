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
   GET CLAIM ID
========================================================= */

$claimId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($claimId <= 0) {

    setAlert(
        'Invalid claim ID.',
        'error'
    );

    redirect('admin_dashboard.php?tab=claims');
}


/* =========================================================
   GET COMPLETE CLAIM DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT

        c.claim_id,
        c.item_id,
        c.claimant_id,
        c.admin_id,
        c.claim_date,
        c.claim_description,
        c.contact_info,
        c.status AS claim_status,
        c.created_at,

        /* ITEM */
        i.name AS item_name,
        i.description AS item_description,
        i.type AS item_type,
        i.category AS item_category,
        i.location AS item_location,
        i.status AS item_status,
        i.is_active AS item_active,
        i.created_at AS item_created_at,

        /* CLAIMANT */
        u.name AS claimant_name,
        u.email AS claimant_email,
        u.phone AS claimant_phone,

        /* ADMIN WHO PROCESSED CLAIM */
        a.name AS admin_name,
        a.email AS admin_email

    FROM claims c

    JOIN items i
        ON c.item_id = i.item_id

    JOIN users u
        ON c.claimant_id = u.user_id

    LEFT JOIN users a
        ON c.admin_id = a.user_id

    WHERE c.claim_id = ?

    LIMIT 1
");


$stmt->bind_param(
    "i",
    $claimId
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    setAlert(
        'Claim not found.',
        'error'
    );

    redirect('admin_dashboard.php?tab=claims');
}


$claim = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   PAGE
========================================================= */

$pageTitle = 'Claim Details';

include 'Partials/Header.php';

?>


<div class="container">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
            margin-bottom:25px;
        "
    >

        <div>

            <h2>
                Claim Details
            </h2>

            <p>
                Complete information about this claim.
            </p>

        </div>


        <a
            href="admin_dashboard.php?tab=claims"
            class="btn btn-secondary"
        >

            ← Back to Claims

        </a>

    </div>


    <!-- =====================================================
         CLAIM STATUS
    ====================================================== -->

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <div
            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:15px;
                flex-wrap:wrap;
            "
        >

            <div>

                <h2 style="margin-bottom:5px;">

                    Claim #<?php
                    echo (int)$claim['claim_id'];
                    ?>

                </h2>

                <p style="margin:0;">

                    Item ID:

                    <strong>
                        #<?php
                        echo (int)$claim['item_id'];
                        ?>
                    </strong>

                </p>

            </div>


            <div>

                <?php

                $claimStatus =
                    strtolower(
                        $claim['claim_status']
                    );

                ?>


                <span
                    class="badge badge-<?php
                        echo $claimStatus;
                    ?>"
                    style="
                        font-size:1rem;
                        padding:10px 16px;
                    "
                >

                    <?php
                    echo ucfirst(
                        htmlspecialchars(
                            $claim['claim_status']
                        )
                    );
                    ?>

                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ITEM INFORMATION
    ====================================================== -->

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <h2>
            📦 Item Information
        </h2>


        <div
            class="item-details-box"
            style="margin-top:15px;"
        >

            <p>

                <strong>
                    Item Name:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['item_name']
                );
                ?>

            </p>


            <p>

                <strong>
                    Item ID:
                </strong>

                #<?php
                echo (int)$claim['item_id'];
                ?>

            </p>


            <p>

                <strong>
                    Type:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['item_type']
                );
                ?>

            </p>


            <p>

                <strong>
                    Category:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['item_category']
                );
                ?>

            </p>


            <p>

                <strong>
                    Location:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['item_location']
                );
                ?>

            </p>


            <p>

                <strong>
                    Item Status:
                </strong>


                <?php

                if (
                    $claim['item_status'] === 'claimed'
                ):

                ?>

                    <span class="badge badge-success">

                        ✓ Claimed

                    </span>

                <?php else: ?>

                    <span class="badge">

                        <?php
                        echo ucfirst(
                            htmlspecialchars(
                                $claim['item_status']
                            )
                        );
                        ?>

                    </span>

                <?php endif; ?>

            </p>


            <p>

                <strong>
                    Item Active:
                </strong>


                <?php if (
                    (int)$claim['item_active'] === 1
                ): ?>

                    <span class="badge badge-success">

                        Active

                    </span>

                <?php else: ?>

                    <span class="badge badge-danger">

                        Inactive

                    </span>

                <?php endif; ?>

            </p>


            <p>

                <strong>
                    Description:
                </strong>

                <br>

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $claim['item_description']
                    )
                );
                ?>

            </p>

        </div>

    </div>


    <!-- =====================================================
         CLAIMANT INFORMATION
    ====================================================== -->

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <h2>
            👤 Claimant Information
        </h2>


        <div
            class="item-details-box"
            style="margin-top:15px;"
        >

            <p>

                <strong>
                    Name:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['claimant_name']
                );
                ?>

            </p>


            <p>

                <strong>
                    Email:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['claimant_email']
                );
                ?>

            </p>


            <p>

                <strong>
                    Phone:
                </strong>

                <?php
                echo htmlspecialchars(
                    $claim['claimant_phone']
                );
                ?>

            </p>

        </div>

    </div>


    <!-- =====================================================
         CLAIM INFORMATION
    ====================================================== -->

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <h2>
            📝 Claim Information
        </h2>


        <div
            class="item-details-box"
            style="margin-top:15px;"
        >

            <p>

                <strong>
                    Claim Date:
                </strong>

                <?php
                echo date(
                    'M d, Y',
                    strtotime(
                        $claim['claim_date']
                    )
                );
                ?>

            </p>


            <p>

                <strong>
                    Submitted:
                </strong>

                <?php
                echo date(
                    'M d, Y H:i',
                    strtotime(
                        $claim['created_at']
                    )
                );
                ?>

            </p>


            <p>

                <strong>
                    Contact Information Provided:
                </strong>

                <br>

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $claim['contact_info']
                    )
                );
                ?>

            </p>


            <p>

                <strong>
                    Why does this person claim the item?
                </strong>

            </p>


            <div
                style="
                    padding:15px;
                    background:#f7f7f7;
                    border-radius:8px;
                    border-left:4px solid #b09a8a;
                "
            >

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $claim['claim_description']
                    )
                );
                ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ADMIN PROCESSING INFORMATION
    ====================================================== -->

    <?php if (
        $claim['claim_status'] !== 'pending'
    ): ?>

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <h2>
            🛡️ Processing Information
        </h2>


        <div
            class="item-details-box"
            style="margin-top:15px;"
        >

            <p>

                <strong>
                    Final Status:
                </strong>


                <span
                    class="badge badge-<?php
                        echo $claimStatus;
                    ?>"
                >

                    <?php
                    echo ucfirst(
                        htmlspecialchars(
                            $claim['claim_status']
                        )
                    );
                    ?>

                </span>

            </p>


            <?php if (
                !empty($claim['admin_name'])
            ): ?>

                <p>

                    <strong>
                        Processed By:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $claim['admin_name']
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Admin Email:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $claim['admin_email']
                    );
                    ?>

                </p>

            <?php endif; ?>


<?php if (
    $claim['claim_status'] === 'approved'
): ?>

                <div
                    class="alert alert-success"
                    style="margin-top:15px;"
                >

                    ✓ This claimant was approved for
                    this item.

                </div>

<?php elseif (
    $claim['claim_status'] === 'rejected'
): ?>

                <div
                    class="alert alert-error"
                    style="margin-top:15px;"
                >

                    ✕ This claim was rejected.

                </div>

<?php endif; ?>


        </div>

    </div>

    <?php endif; ?>


    <!-- =====================================================
         OTHER CLAIMANTS FOR THIS ITEM
    ====================================================== -->

    <?php

    $otherClaimsStmt = $conn->prepare("
        SELECT
            c.claim_id,
            c.status,
            c.claim_date,
            c.claim_description,
            u.name AS claimant_name,
            u.email AS claimant_email,
            u.phone AS claimant_phone
        FROM claims c
        INNER JOIN users u
            ON c.claimant_id = u.user_id
        WHERE c.item_id = ?
        ORDER BY
            CASE c.status
                WHEN 'approved' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4
            END,
            c.created_at ASC
    ");

    $otherClaimsStmt->bind_param("i", $claim['item_id']);
    $otherClaimsStmt->execute();
    $otherClaimsResult = $otherClaimsStmt->get_result();

    ?>

    <div
        class="form-container"
        style="margin-bottom:25px;"
    >

        <h2>
            👥 All Applicants for This Item
        </h2>

        <p style="margin-top:5px; color:#666;">
            These records are kept for admin history even after another claimant has been approved.
        </p>

        <div class="table-container" style="margin-top:15px;">

            <table>

                <thead>
                    <tr>
                        <th>Claim ID</th>
                        <th>Claimant</th>
                        <th>Contact</th>
                        <th>Claim Date</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($otherClaimsResult && $otherClaimsResult->num_rows > 0): ?>

                    <?php while ($other = $otherClaimsResult->fetch_assoc()): ?>

                        <?php $otherStatus = strtolower($other['status']); ?>

                        <tr>
                            <td>#<?php echo (int)$other['claim_id']; ?></td>

                            <td>
                                <strong><?php echo htmlspecialchars($other['claimant_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($other['claimant_email']); ?></small>
                            </td>

                            <td><?php echo htmlspecialchars($other['claimant_phone']); ?></td>

                            <td>
                                <?php echo date('M d, Y', strtotime($other['claim_date'])); ?>
                            </td>

                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($otherStatus); ?>">
                                    <?php echo ucfirst(htmlspecialchars($other['status'])); ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    href="claim_details.php?id=<?php echo (int)$other['claim_id']; ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    View
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" style="text-align:center;">
                            No other claims found for this item.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <?php $otherClaimsStmt->close(); ?>


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

<?php if (
    $claim['claim_status'] === 'pending'
): ?>


    <div
        class="form-container"
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >

        <!-- APPROVE -->

        <form
            method="POST"
            action="admin_dashboard.php"
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
                    echo (int)$claim['claim_id'];
                ?>"
            >


            <input
                type="hidden"
                name="item_id"
                value="<?php
                    echo (int)$claim['item_id'];
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
                class="btn btn-success"
            >

                ✓ Approve Claim

            </button>

        </form>


        <!-- REJECT -->

        <form
            method="POST"
            action="admin_dashboard.php"
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
                    echo (int)$claim['claim_id'];
                ?>"
            >


            <input
                type="hidden"
                name="item_id"
                value="<?php
                    echo (int)$claim['item_id'];
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
                class="btn btn-danger"
            >

                ✕ Reject Claim

            </button>

        </form>

    </div>


<?php endif; ?>


</div>


<?php

include 'Partials/Footer.php';

?>