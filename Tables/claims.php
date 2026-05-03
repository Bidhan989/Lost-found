<?php
require_once '../Config/config.php';

if (!isLoggedIn()) {
    redirect('Auth/login.php');
}

$action = $_GET['action'] ?? 'list';
$itemId = isset($_GET['item']) ? (int)$_GET['item'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {

    $itemId      = (int)$_POST['item_id'];
    $description = clean($_POST['claim_description']);
    $contact     = clean($_POST['contact_info']);
    $userId      = (int)$_SESSION['user_id'];

    if (!$itemId || empty($description) || empty($contact)) {
        setAlert('All fields are required', 'error');
        redirect('Tables/claims.php');
    }

    $checkItem = $conn->query("SELECT item_id FROM items WHERE item_id = $itemId AND is_active = 1");
    $checkExisting = $conn->query("SELECT claim_id FROM claims 
                                   WHERE item_id = $itemId 
                                   AND claimant_id = $userId 
                                   AND is_active = 1");

    if ($checkItem->num_rows === 0) {
        setAlert('Item not found', 'error');
        redirect('Tables/claims.php');
    }

    if ($checkExisting->num_rows > 0) {
        setAlert('You have already claimed this item', 'error');
        redirect('Tables/claims.php');
    }

    // Get admin
    $adminResult = $conn->query("SELECT user_id FROM users WHERE role='admin' AND is_active=1 LIMIT 1");
    $adminId = ($adminResult->num_rows > 0) 
                ? (int)$adminResult->fetch_assoc()['user_id'] 
                : 1;

    $sql = "INSERT INTO claims (item_id, claimant_id, admin_id, claim_date, claim_description, contact_info) 
            VALUES ($itemId, $userId, $adminId, CURDATE(), '$description', '$contact')";

    if ($conn->query($sql)) {
        setAlert('Claim submitted successfully!', 'success');
    } else {
        setAlert('Failed to submit claim', 'error');
    }

    redirect('Tables/claims.php');
}

$pageTitle = 'My Claims';
include '../Partials/Header.php';
?>

<div class="container">

<?php if ($action === 'claim' && $itemId): ?>

<?php
$itemQuery = $conn->query("
    SELECT i.*, u.name AS owner_name 
    FROM items i 
    JOIN users u ON i.user_id = u.user_id 
    WHERE i.item_id = $itemId AND i.is_active = 1
");

if ($itemQuery->num_rows === 0):
?>
    <div class="alert alert-error">Item not found.</div>
    <a href="../index.php" class="btn btn-primary">Back</a>

<?php else: 
$item = $itemQuery->fetch_assoc();

$alreadyClaimed = $conn->query("
    SELECT claim_id FROM claims 
    WHERE item_id = $itemId 
    AND claimant_id = {$_SESSION['user_id']} 
    AND is_active = 1
");

if ($alreadyClaimed->num_rows > 0):
?>
    <div class="alert alert-info">You already submitted a claim for this item.</div>
    <a href="../index.php" class="btn btn-primary">Back</a>

<?php else: ?>

<div class="form-container">
    <h2> Claim Item: <?php echo htmlspecialchars($item['name']); ?></h2>

    <div class="item-details-box">
        <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?></p>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($item['description']); ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?></p>
        <p><strong>Posted by:</strong> <?php echo htmlspecialchars($item['owner_name']); ?></p>
    </div>

    <form method="POST">
        <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">

        <div class="form-group">
            <label>Why is this yours?</label>
            <textarea name="claim_description" required></textarea>
        </div>

        <div class="form-group">
            <label>Contact Info</label>
            <input type="text" name="contact_info" required>
        </div>

        <button type="submit" name="submit_claim" class="btn btn-primary">Submit Claim</button>
        <a href="../index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php endif; endif; ?>

<?php else: ?>

<h2> My Claims</h2>

<div class="table-container">
<table>
<thead>
<tr>
    <th>Item</th>
    <th>Date</th>
    <th>Status</th>
    <th>Description</th>
</tr>
</thead>
<tbody>

<?php
$userId = (int)$_SESSION['user_id'];

$result = $conn->query("
    SELECT c.*, i.name AS item_name 
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    WHERE c.claimant_id = $userId AND c.is_active = 1
    ORDER BY c.created_at DESC
");

if ($result->num_rows > 0):
while ($row = $result->fetch_assoc()):
?>

<tr>
    <td><?php echo htmlspecialchars($row['item_name']); ?></td>
    <td><?php echo date('M d, Y', strtotime($row['claim_date'])); ?></td>
    <td><?php echo ucfirst($row['status']); ?></td>
    <td><?php echo htmlspecialchars(substr($row['claim_description'], 0, 80)); ?>...</td>
</tr>

<?php endwhile; else: ?>
<tr>
    <td colspan="4" style="text-align:center;">No claims found.</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

<?php endif; ?>

</div>

<?php include '../Partials/Footer.php'; ?>
