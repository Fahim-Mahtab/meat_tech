<?php
require 'config.php';
requireAuth();

// Only admin can access user management
$user = getCurrentUser($pdo);
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add new user
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $hashedPassword,
            $_POST['role']
        ]);
        
        header('Location: users.php');
        exit;
    } elseif ($action === 'edit') {
        // Update user
        $data = [
            $_POST['name'],
            $_POST['email'],
            $_POST['role'],
            $_POST['status'],
            $_POST['id']
        ];
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users SET
                    name = ?,
                    email = ?,
                    password = ?,
                    role = ?,
                    status = ?
                WHERE id = ?
            ");
            array_splice($data, 2, 0, $hashedPassword);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users SET
                    name = ?,
                    email = ?,
                    role = ?,
                    status = ?
                WHERE id = ?
            ");
        }
        
        $stmt->execute($data);
        
        header('Location: users.php');
        exit;
    } elseif ($action === 'delete') {
        // Delete user (can't delete self)
        if ($_GET['id'] != $user['id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
        }
        
        header('Location: users.php');
        exit;
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
if ($action === 'edit') {
    $editUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $editUser->execute([$_GET['id']]);
    $editUser = $editUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$editUser) {
        header('Location: users.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">User Management</h1>
                    <a href="users.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add User
                    </a>
                </div>
                
                <?php if ($action === 'list'): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>System Users</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Last Login</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $u): ?>
                                                    <tr>
                                                        <td><?= $u['name'] ?></td>
                                                        <td><?= $u['email'] ?></td>
                                                        <td><?= ucfirst($u['role']) ?></td>
                                                        <td><?= $u['last_login'] ? date('M j, Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($u['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($u['id'] != $user['id']): ?>
                                                                <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-6 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5><?= $action === 'add' ? 'Add New' : 'Edit' ?> User</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <?php if ($action === 'edit'): ?>
                                            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                value="<?= $editUser['name'] ?? '' ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?= $editUser['email'] ?? '' ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                <?= $action === 'add' ? 'required' : '' ?>>
                                            <?php if ($action === 'edit'): ?>
                                                <small class="text-muted">Leave blank to keep current password</small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="role" class="form-label">Role</label>
                                                <select class="form-select" id="role" name="role" required>
                                                    <option value="admin" <?= isset($editUser) && $editUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                    <option value="manager" <?= isset($editUser) && $editUser['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>

                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($action === 'edit'): ?>
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status" required>
                                                        <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    </select>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>