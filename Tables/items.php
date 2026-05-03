<?php
require_once '../Config/config.php';

if (!isLoggedIn()) {
    redirect('Auth/login.php');
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ---------------------------
   MARK ITEM AS FOUND
----------------------------*/
if ($action === 'mark_found' && isset($_GET['id'])) {

    $itemId = (int)$_GET['id'];

    // Must be logged in
    if (!isLoggedIn()) {
        redirect('Auth/login.php');
    }

    // Make sure item exists and is active
    $check = $conn->query("
        SELECT item_id 
        FROM items 
        WHERE item_id = $itemId 
          AND is_active = 1
        LIMIT 1
    ");

    if ($check && $check->num_rows === 1) {

        // Update item as FOUND
        $conn->query("
            UPDATE items 
            SET 
                status = 'found',
                type   = 'Found'
            WHERE item_id = $itemId
            Limit 1
        ");

        setAlert('Item marked as FOUND! Users can now claim it.', 'success');

    } else {
        setAlert('Item not available or already processed.', 'warning');
    }

    header("Location: " . BASE_URL . "index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $name = clean($_POST['name']);
        $type = clean($_POST['type']);
        $category = clean($_POST['category']);
        $description = clean($_POST['description']);
        $location = clean($_POST['location']);
        $userId = $_SESSION['user_id'];
        
        $sql = "INSERT INTO items (name, type, category, description, location, user_id) 
                VALUES ('$name', '$type', '$category', '$description', '$location', $userId)";
        
        if ($conn->query($sql)) {
            $itemId = $conn->insert_id;
            
            if (isset($_FILES['images']) && $_FILES['images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp) {
                    if ($_FILES['images']['error'][$key] === 0) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'tmp_name' => $tmp,
                            'size' => $_FILES['images']['size'][$key]
                        ];
                        $upload = uploadImage($file, $itemId);
                        if ($upload['success']) {
                            $conn->query("INSERT INTO item_images (item_id, image_path) VALUES ($itemId, '{$upload['filename']}')");
                        }
                    }
                }
            }
            
            setAlert('Item added successfully!', 'success');
            redirect('Tables/items.php');
        } else {
            setAlert('Failed to add item', 'error');
        }

    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {

        $deleteId = (int)$_POST['id'];
        $checkOwner = $conn->query("SELECT user_id FROM items WHERE item_id = $deleteId");

        if ($checkOwner->num_rows > 0) {
            $item = $checkOwner->fetch_assoc();

            if ($item['user_id'] == $_SESSION['user_id'] || isAdmin()) {
                $conn->query("UPDATE items SET is_active = 0 WHERE item_id = $deleteId");
                $conn->query("UPDATE item_images SET is_active = 0 WHERE item_id = $deleteId");
                setAlert('Item deleted successfully', 'success');
            }
        }

        redirect('Tables/items.php');
    }
}

$pageTitle = 'My Items';
include '../Partials/Header.php';
?>

<div class="container">
    <?php if ($action === 'add'): ?>
    <div class="form-container">
        <h2> Report <?php echo isset($_GET['type']) && $_GET['type'] === 'Found' ? 'Found' : 'Lost'; ?> Item</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="name" placeholder="e.g., iPhone 13, Blue Wallet, Car Keys" required>
            </div>
            <div class="form-group">
                <label>Type *</label>
                <select name="type" required>
                    <option value="Lost" <?php echo (!isset($_GET['type']) || $_GET['type'] === 'Lost') ? 'selected' : ''; ?>>Lost</option>
                    <option value="Found" <?php echo (isset($_GET['type']) && $_GET['type'] === 'Found') ? 'selected' : ''; ?>>Found</option>
                </select>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <input type="text" name="category" placeholder="e.g., Electronics, Documents, Accessories, Keys, Wallet" required>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" rows="4" placeholder="Provide detailed description including color, brand, model, unique features..." required></textarea>
            </div>
            <div class="form-group">
                <label>Location *</label>
                <input type="text" name="location" placeholder="Where was it lost/found?" required>
            </div>
            <div class="form-group">
                <label>Upload Images (Optional - Multiple allowed)</label>
                <input type="file" name="images[]" multiple accept="image/*">
                <small>Max 5MB per image. Accepted: JPG, PNG, GIF</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Report</button>
                <a href="items.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <h2> My Items</h2>
    <a href="items.php?action=add&type=Lost" class="btn btn-danger">Report Lost Item</a>
    <a href="items.php?action=add&type=Found" class="btn btn-success">Report Found Item</a>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $userId = $_SESSION['user_id'];
                $sql = "SELECT * FROM items WHERE user_id = $userId AND is_active = 1 ORDER BY created_at DESC";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row['type']); ?>"><?php echo $row['type']; ?></span></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                    <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $row['item_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="7" style="text-align:center;">No items yet. Start by reporting a lost or found item!</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../Partials/Footer.php'; ?>
