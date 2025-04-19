<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Member editing mode
$edit_mode = false;
$edit_member = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new member
    if (isset($_POST['add_member'])) {
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        
        // Check if phone number already exists
        $check_query = "SELECT * FROM members WHERE phone_number = '$phone_number'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Phone number already exists", 'error');
        } else {
            $query = "INSERT INTO members (phone_number, points) VALUES ('$phone_number', 0)";
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "Member with phone number $phone_number added successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error adding member: " . mysqli_error($conn), 'error');
            }
        }
    }
    
    // Update member
    if (isset($_POST['update_member'])) {
        $member_id = intval($_POST['member_id']);
        $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $points = floatval($_POST['points']);
        
        // Check if phone number already exists for other members
        $check_query = "SELECT * FROM members WHERE phone_number = '$phone_number' AND id != $member_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Phone number already exists for another member", 'error');
        } else {
            $query = "UPDATE members SET phone_number = '$phone_number', points = '$points' WHERE id = $member_id";
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "Member updated successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error updating member: " . mysqli_error($conn), 'error');
            }
        }
    }
    
    // Delete member
    if (isset($_POST['delete_member'])) {
        $member_id = intval($_POST['member_id']);
        
        // Check if member is used in any transaction
        $query = "SELECT COUNT(*) as count FROM transactions WHERE member_id = $member_id";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['count'] > 0) {
            // Member is used in transactions, cannot delete
            redirectWithMessage($_SERVER['PHP_SELF'], "Cannot delete member because they have transactions", 'error');
        } else {
            // Safe to delete
            $query = "DELETE FROM members WHERE id = $member_id";
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "Member deleted successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error deleting member: " . mysqli_error($conn), 'error');
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $member_id = intval($_GET['edit']);
    $query = "SELECT * FROM members WHERE id = $member_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_mode = true;
        $edit_member = mysqli_fetch_assoc($result);
    }
}

// Get all members
$query = "SELECT * FROM members ORDER BY id DESC";
$members = mysqli_query($conn, $query);

require_once '../../include/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1>Member Management</h1>
    </div>
</div>

<div class="row">
    <!-- Add/Edit Member Form -->
    <div class="col-md-4">
        <h2><?php echo $edit_mode ? 'Edit Member' : 'Add New Member'; ?></h2>
        
        <form method="POST" action="">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="member_id" value="<?php echo $edit_member['id']; ?>">
            <?php endif; ?>
            
            <div>
                <label>Phone Number:</label>
                <input type="text" name="phone_number" required 
                       value="<?php echo $edit_mode ? $edit_member['phone_number'] : ''; ?>">
            </div>
            
            <?php if ($edit_mode): ?>
                <div>
                    <label>Points:</label>
                    <input type="number" name="points" step="0.01" required 
                           value="<?php echo $edit_member['points']; ?>">
                </div>
                
                <div>
                    <label>First Purchase:</label>
                    <input type="text" readonly 
                           value="<?php echo $edit_member['first_purchase_date'] ? date('Y-m-d', strtotime($edit_member['first_purchase_date'])) : 'Not made first purchase'; ?>">
                </div>
                
                <div>
                    <label>Member Since:</label>
                    <input type="text" readonly 
                           value="<?php echo date('Y-m-d', strtotime($edit_member['created_at'])); ?>">
                </div>
                
                <button type="submit" name="update_member">Update Member</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="margin-left: 10px;">Cancel</a>
            <?php else: ?>
                <button type="submit" name="add_member">Add Member</button>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Member List -->
    <div class="col-md-8">
        <h2>Members</h2>
        
        <table border="1" width="100%">
            <tr>
                <th>ID</th>
                <th>Phone Number</th>
                <th>Points</th>
                <th>First Purchase</th>
                <th>Member Since</th>
                <th>Actions</th>
            </tr>
            <?php if (mysqli_num_rows($members) > 0): ?>
                <?php while ($member = mysqli_fetch_assoc($members)): ?>
                    <tr>
                        <td><?php echo $member['id']; ?></td>
                        <td><?php echo $member['phone_number']; ?></td>
                        <td><?php echo number_format($member['points'], 2); ?></td>
                        <td><?php echo $member['first_purchase_date'] ? date('Y-m-d', strtotime($member['first_purchase_date'])) : '-'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                        <td>
                            <a href="?edit=<?php echo $member['id']; ?>">Edit</a>
                            
                            <form method="POST" action="" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" name="delete_member" 
                                        onclick="return confirm('Are you sure you want to delete this member?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" align="center">No members found</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>