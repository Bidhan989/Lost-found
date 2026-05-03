<?php
require_once 'Config/config.php';

if (!isAdmin()) {
    setAlert('Access denied. Admin only.', 'error');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_claim'])) {
        $claimId = (int)$_POST['claim_id'];
        $status = clean($_POST['status']);
        $itemId = (int)$_POST['item_id'];
        
        $conn->query("UPDATE claims SET status = '$status' WHERE claim_id = $claimId");
        
        if ($status === 'approved') {
            $conn->query("UPDATE items SET status = 'claimed' WHERE item_id = $itemId");
        }
        
        setAlert('Claim updated successfully', 'success');
        redirect('admin_dashboard.php?tab=claims');
    }
    
    if (isset($_POST['toggle_user'])) {
        $userId = (int)$_POST['user_id'];
        if ($userId != $_SESSION['user_id']) {
            $conn->query("UPDATE users SET is_active = NOT is_active WHERE user_id = $userId");
            setAlert('User status updated', 'success');
        }
        redirect('admin_dashboard.php?tab=users');
    }
    
    if (isset($_POST['delete_item'])) {
        $itemId = (int)$_POST['item_id'];
        $conn->query("UPDATE items SET is_active = 0 WHERE item_id = $itemId");
        $conn->query("UPDATE item_images SET is_active = 0 WHERE item_id = $itemId");
        setAlert('Item deleted successfully', 'success');
        redirect('admin_dashboard.php?tab=items');
    }
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'claims';

$pageTitle = 'Admin Dashboard';
include 'Partials/Header.php';
?>

<div class="container">
    <h2> Admin Dashboard</h2>
    
    <div class="admin-tabs">
        <a href="?tab=claims" class="tab <?php echo $tab === 'claims' ? 'active' : ''; ?>"> Claims</a>
        <a href="?tab=items" class="tab <?php echo $tab === 'items' ? 'active' : ''; ?>"> All Items</a>
        <a href="?tab=users" class="tab <?php echo $tab === 'users' ? 'active' : ''; ?>"> Users</a>
        <a href="?tab=stats" class="tab <?php echo $tab === 'stats' ? 'active' : ''; ?>"> Statistics</a>
    </div>
    
    <div class="tab-content">
        <?php if ($tab === 'claims'): ?>
            <h3>Claims Management</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Claimant</th>
                            <th>Description</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT c.*, i.name as item_name, i.type, u.name as claimant_name, u.email, u.phone 
                                FROM claims c 
                                JOIN items i ON c.item_id = i.item_id 
                                JOIN users u ON c.claimant_id = u.user_id 
                                WHERE c.is_active = 1 
                                ORDER BY 
                                    CASE c.status 
                                        WHEN 'pending' THEN 1 
                                        WHEN 'approved' THEN 2 
                                        WHEN 'rejected' THEN 3 
                                    END,
                                    c.created_at DESC";
                        $claims = $conn->query($sql);
                        
                        if ($claims->num_rows > 0):
                            while ($claim = $claims->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $claim['claim_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($claim['item_name']); ?></strong><br>
                                <span class="badge badge-<?php echo strtolower($claim['type']); ?>"><?php echo $claim['type']; ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($claim['claimant_name']); ?><br>
                                <small> <?php echo htmlspecialchars($claim['email']); ?></small><br>
                                <small> <?php echo htmlspecialchars($claim['phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($claim['claim_description']); ?></td>
                            <td><?php echo htmlspecialchars($claim['contact_info']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($claim['claim_date'])); ?></td>
                            <td><span class="badge badge-<?php echo $claim['status']; ?>"><?php echo ucfirst($claim['status']); ?></span></td>
                            <td>
                                <?php if ($claim['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $claim['item_id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" name="update_claim" class="btn btn-sm btn-success"> Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $claim['item_id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" name="update_claim" class="btn btn-sm btn-danger"> Reject</button>
                                </form>
                                <?php else: ?>
                                    <em>No actions</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No claims to review</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($tab === 'items'): ?>
            <h3>All Items</h3>
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
                        $items = $conn->query("SELECT i.*, u.name as user_name, u.email 
                                               FROM items i 
                                               JOIN users u ON i.user_id = u.user_id 
                                               WHERE i.is_active = 1 
                                               ORDER BY i.created_at DESC");
                        
                        if ($items->num_rows > 0):
                            while ($item = $items->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $item['item_id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($item['type']); ?>"><?php echo $item['type']; ?></span></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($item['user_name']); ?><br>
                                <small><?php echo htmlspecialchars($item['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($item['location']); ?></td>
                            <td><span class="badge badge-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    <button type="submit" name="delete_item" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="9" style="text-align:center;">No items found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($tab === 'users'): ?>
            <h3>Users Management</h3>
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
                        $users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
                        while ($user = $users->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td>
                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" name="toggle_user" class="btn btn-sm btn-warning">
                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <em>(You)</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($tab === 'stats'): ?>
            <h3>📊 System Statistics</h3>
            <div class="stats-grid">
                <?php
                $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
                $totalItems = $conn->query("SELECT COUNT(*) as count FROM items WHERE is_active = 1")->fetch_assoc()['count'];
                $lostItems = $conn->query("SELECT COUNT(*) as count FROM items WHERE type = 'Lost' AND is_active = 1 AND status = 'pending'")->fetch_assoc()['count'];
                $foundItems = $conn->query("SELECT COUNT(*) as count FROM items WHERE type = 'Found' AND is_active = 1 AND status = 'pending'")->fetch_assoc()['count'];
                $pendingClaims = $conn->query("SELECT COUNT(*) as count FROM claims WHERE status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
                $approvedClaims = $conn->query("SELECT COUNT(*) as count FROM claims WHERE status = 'approved' AND is_active = 1")->fetch_assoc()['count'];
                $claimedItems = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'claimed' AND is_active = 1")->fetch_assoc()['count'];
                $totalClaims = $conn->query("SELECT COUNT(*) as count FROM claims WHERE is_active = 1")->fetch_assoc()['count'];
                ?>
                <div class="stat-card">
                    <h4> Active Users</h4>
                    <p class="stat-number"><?php echo $totalUsers; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Total Items</h4>
                    <p class="stat-number"><?php echo $totalItems; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Lost Items</h4>
                    <p class="stat-number"><?php echo $lostItems; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Found Items</h4>
                    <p class="stat-number"><?php echo $foundItems; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Pending Claims</h4>
                    <p class="stat-number"><?php echo $pendingClaims; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Approved Claims</h4>
                    <p class="stat-number"><?php echo $approvedClaims; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Claimed Items</h4>
                    <p class="stat-number"><?php echo $claimedItems; ?></p>
                </div>
                <div class="stat-card">
                    <h4> Total Claims</h4>
                    <p class="stat-number"><?php echo $totalClaims; ?></p>
                </div>
            </div>
            
            <h3 style="margin-top: 2rem;">Recent Activity</h3>
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
                        $recentItems = $conn->query("SELECT i.name, i.type, u.name as user_name, i.created_at 
                                                     FROM items i 
                                                     JOIN users u ON i.user_id = u.user_id 
                                                     WHERE i.is_active = 1 
                                                     ORDER BY i.created_at DESC 
                                                     LIMIT 10");
                        while ($activity = $recentItems->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo strtolower($activity['type']); ?>"><?php echo $activity['type']; ?></span>
                                <?php echo htmlspecialchars($activity['name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'Partials/Footer.php'; ?>