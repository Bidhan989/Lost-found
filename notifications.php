<?php

require_once 'Config/config.php';


/* =========================================================
   LOGIN REQUIRED
========================================================= */

if (!isLoggedIn()) {

    setAlert(
        'Please login to view your notifications.',
        'error'
    );

    redirect('Auth/login.php');
}


$userId = (int)$_SESSION['user_id'];


/* =========================================================
   MARK SINGLE NOTIFICATION AS READ
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_read'])
) {

    $notificationId =
        (int)($_POST['notification_id'] ?? 0);

    if ($notificationId > 0) {

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = ?
              AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $notificationId,
            $userId
        );

        $stmt->execute();

        $stmt->close();
    }

    redirect('notifications.php');
}


/* =========================================================
   MARK ALL AS READ
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_all_read'])
) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
          AND is_read = 0
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $stmt->close();

    setAlert(
        'All notifications marked as read.',
        'success'
    );

    redirect('notifications.php');
}


/* =========================================================
   GET NOTIFICATIONS
========================================================= */

$notifications = $conn->prepare("
    SELECT
        n.*,
        i.name AS item_name
    FROM notifications n

    LEFT JOIN items i
        ON n.item_id = i.item_id

    WHERE n.user_id = ?

    ORDER BY n.created_at DESC
");

$notifications->bind_param(
    "i",
    $userId
);

$notifications->execute();

$result =
    $notifications->get_result();


/* =========================================================
   PAGE
========================================================= */

$pageTitle = 'Notifications';

include 'Partials/Header.php';

?>


<div class="container">

    <div class="admin-section-header">

        <div>

            <h2>
                🔔 Notifications
            </h2>

            <p>
                View updates about your claims and items.
            </p>

        </div>


        <?php

        $unreadCount =
            getUnreadNotificationCount($userId);

        ?>


        <?php if ($unreadCount > 0): ?>

            <form method="POST">

                <button
                    type="submit"
                    name="mark_all_read"
                    class="btn btn-secondary"
                >

                    ✓ Mark All as Read

                </button>

            </form>

        <?php endif; ?>

    </div>


    <div class="notifications-list">


<?php if ($result->num_rows > 0): ?>


<?php while ($notification = $result->fetch_assoc()): ?>


        <?php

        $type =
            strtolower(
                $notification['type']
            );

        ?>


        <div
            class="notification-card <?php
                echo $notification['is_read']
                    ? 'notification-read'
                    : 'notification-unread';
            ?>"
        >


            <div class="notification-icon">

                <?php

                if ($type === 'success') {

                    echo '✅';

                } elseif ($type === 'warning') {

                    echo '❌';

                } else {

                    echo '🔔';

                }

                ?>

            </div>


            <div class="notification-content">

                <h3>

                    <?php
                    echo htmlspecialchars(
                        $notification['title']
                    );
                    ?>

                </h3>


                <p>

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $notification['message']
                        )
                    );
                    ?>

                </p>


                <?php if (
                    !empty(
                        $notification['item_name']
                    )
                ): ?>

                    <small>

                        📦 Item:

                        <?php
                        echo htmlspecialchars(
                            $notification['item_name']
                        );
                        ?>

                    </small>

                <?php endif; ?>


                <small class="notification-date">

                    <?php
                    echo date(
                        'M d, Y h:i A',
                        strtotime(
                            $notification['created_at']
                        )
                    );
                    ?>

                </small>

            </div>


            <div class="notification-action">


                <?php if (
                    !$notification['is_read']
                ): ?>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?php
                                echo (int)
                                $notification[
                                    'notification_id'
                                ];
                            ?>"
                        >

                        <button
                            type="submit"
                            name="mark_read"
                            class="btn btn-sm btn-secondary"
                        >

                            Mark Read

                        </button>

                    </form>


                <?php else: ?>

                    <span class="notification-read-label">

                        ✓ Read

                    </span>

                <?php endif; ?>


            </div>

        </div>


<?php endwhile; ?>


<?php else: ?>


        <div class="alert alert-info">

            🔔 You don't have any notifications yet.

        </div>


<?php endif; ?>


    </div>

</div>


<?php

$notifications->close();

include 'Partials/Footer.php';

?>