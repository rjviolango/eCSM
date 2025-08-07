<?php
// eCSM - ARTA-2242-3
// admin.php - Final Version with all features and fixes
require_once 'includes.php';

// --- LOGIN & LOGOUT LOGIC ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- AJAX ENDPOINTS ---
if (isset($_GET['action'])) {
    if (is_logged_in() && $_GET['action'] == 'get_filter_items' && isset($_GET['level'])) {
        header('Content-Type: application/json');
        $level = $_GET['level'];
        $items = [];
        if ($level === 'department' && is_admin()) {
            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($level === 'service') {
            $sql = "SELECT id, service_name as name FROM services";
            if (!is_admin()) {
                $sql .= " WHERE department_id = " . (int)$_SESSION['department_id'];
            }
            $sql .= " ORDER BY service_name";
            $stmt = $pdo->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($items);
        exit;
    }
    // *** NEW: AJAX endpoint to get services for a specific department ***
    if (is_logged_in() && $_GET['action'] == 'get_services_for_dept' && isset($_GET['department_id'])) {
        header('Content-Type: application/json');
        $dept_id = (int)$_GET['department_id'];
        $stmt = $pdo->prepare("SELECT id, service_name as name FROM services WHERE department_id = ? ORDER BY service_name");
        $stmt->execute([$dept_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    if (is_logged_in() && $_GET['action'] == 'change_password') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_password = $_POST['confirm_password'];
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?"); $stmt->execute([$_SESSION['user_id']]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($current_password, $user['password_hash'])) {
                if ($new_password === $confirm_password) {
                    $complexity = $CONFIG['password_complexity'] ?? 'low';
                    $pattern = '/^.{8,}$/'; if ($complexity === 'medium') $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/'; if ($complexity === 'high') $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
                    if (preg_match($pattern, $new_password)) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $update_stmt->execute([$new_hash, $_SESSION['user_id']]);
                        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
                    } else { echo json_encode(['success' => false, 'message' => 'New password does not meet the complexity requirements.']); }
                } else { echo json_encode(['success' => false, 'message' => 'New passwords do not match.']); }
            } else { echo json_encode(['success' => false, 'message' => 'Incorrect current password.']); }
        }
        exit;
    }
    if (is_logged_in() && $_GET['action'] == 'export_csv') {
        $date_start = $_GET['date_start'] ?? date('Y-m-d', strtotime('-29 days')); $date_end = $_GET['date_end'] ?? date('Y-m-d');
        $report_level = $_GET['report_level'] ?? 'agency'; $filter_id = !empty($_GET['filter_id']) ? $_GET['filter_id'] : null;
        $params = []; $where_clauses = ["r.submission_date BETWEEN :date_start AND :date_end_plus_one"];
        $params[':date_start'] = $date_start; $params[':date_end_plus_one'] = date('Y-m-d', strtotime($date_end . ' +1 day'));
        if ($report_level === 'department') {
            $dept_id_to_filter = is_admin() ? $filter_id : $_SESSION['department_id'];
            if ($dept_id_to_filter) {
                $where_clauses[] = "d.id = :filter_id";
                $params[':filter_id'] = $dept_id_to_filter;
            } else {
                // If admin and no department is selected, don't show any data.
                $where_clauses[] = "1 = 0"; 
            }
        } elseif ($report_level === 'service' && $filter_id) {
            $where_clauses[] = "s.id = :filter_id";
            $params[':filter_id'] = $filter_id;
            if (!is_admin()) {
                $where_clauses[] = "d.id = :user_dept_id";
                $params[':user_dept_id'] = $_SESSION['department_id'];
            }
        }
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $sql = "SELECT r.*, s.service_name, d.name as department_name FROM csm_responses r JOIN services s ON r.service_id = s.id JOIN departments d ON s.department_id = d.id $where_sql";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="csm_report_'.date('Y-m-d').'.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Add UTF-8 BOM
        fputcsv($output, ['Response ID', 'Submission Date', 'Department', 'Service', 'Affiliation', 'Client Type', 'Age', 'Sex', 'Region', 'Client Reference #', 'CC1', 'CC2', 'CC3', 'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8', 'Suggestions', 'Email']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['submission_date'] = convert_to_user_timezone($row['submission_date']);
            fputcsv($output, [ $row['id'], $row['submission_date'], $row['department_name'], $row['service_name'], $row['affiliation'], $row['client_type'], $row['age'], $row['sex'], $row['region_of_residence'], $row['ref_id'], $row['cc1'], $row['cc2'], $row['cc3'], $row['sqd0'], $row['sqd1'], $row['sqd2'], $row['sqd3'], $row['sqd4'], $row['sqd5'], $row['sqd6'], $row['sqd7'], $row['sqd8'], $row['suggestions'], $row['email_address'] ]);
        }
        fclose($output);
        exit;
    }
    // *** NEW: AJAX endpoint to get a single response's full details ***
    if (is_logged_in() && $_GET['action'] == 'get_response_details' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $response_id = (int)$_GET['id'];
        $sql = "SELECT r.*, s.service_name, d.name as department_name 
                FROM csm_responses r 
                JOIN services s ON r.service_id = s.id 
                JOIN departments d ON s.department_id = d.id 
                WHERE r.id = :response_id";
        
        // Security check for department users
        if (!is_admin()) {
            $sql .= " AND d.id = :user_dept_id";
            $params = [':response_id' => $response_id, ':user_dept_id' => $_SESSION['department_id']];
        } else {
            $params = [':response_id' => $response_id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $response = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($response) {
            // Convert timestamp before sending to the client
            $response['submission_date'] = convert_to_user_timezone($response['submission_date']); // Use default Y-m-d H:i:s format
            echo json_encode(['success' => true, 'data' => $response]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Response not found or access denied.']);
        }
        exit;
    }
    // *** NEW: AJAX endpoint for exporting filtered CSM data ***
    if (is_logged_in() && $_GET['action'] == 'export_csm_data_csv') {
        $params = [];
        $where_clauses = [];
        $date_start = $_GET['date_start'] ?? date('Y-m-d', strtotime('-29 days'));
        $date_end = $_GET['date_end'] ?? date('Y-m-d');
        $where_clauses[] = "r.submission_date BETWEEN :date_start AND :date_end_plus_one";
        $params[':date_start'] = $date_start;
        $params[':date_end_plus_one'] = date('Y-m-d', strtotime($date_end . ' +1 day'));
        $search_term = $_GET['search'] ?? '';
        if (!empty($search_term)) {
            $where_clauses[] = "(s.service_name LIKE :search OR r.suggestions LIKE :search OR r.region_of_residence LIKE :search)";
            $params[':search'] = '%' . $search_term . '%';
        }
        $filter_dept_id = $_GET['department_id'] ?? null;
        $filter_service_id = $_GET['service_id'] ?? null;
        if (is_admin()) {
            if (!empty($filter_dept_id)) {
                $where_clauses[] = "d.id = :dept_id";
                $params[':dept_id'] = $filter_dept_id;
            }
        } else {
            $where_clauses[] = "d.id = :user_dept_id";
            $params[':user_dept_id'] = $_SESSION['department_id'];
        }
        if (!empty($filter_service_id)) {
            $where_clauses[] = "s.id = :service_id";
            $params[':service_id'] = $filter_service_id;
        }
        $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        $sql = "SELECT r.*, s.service_name, d.name as department_name FROM csm_responses r JOIN services s ON r.service_id = s.id JOIN departments d ON s.department_id = d.id $where_sql ORDER BY r.submission_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="csm_data_export_'.date('Y-m-d').'.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Add UTF-8 BOM
        fputcsv($output, ['Response ID', 'Submission Date', 'Department', 'Service', 'Affiliation', 'Client Type', 'Age', 'Sex', 'Region', 'Client Reference #', 'Email', 'CC1', 'CC2', 'CC3', 'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8', 'Suggestions']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['submission_date'] = convert_to_user_timezone($row['submission_date']);
            fputcsv($output, [ $row['id'], $row['submission_date'], $row['department_name'], $row['service_name'], $row['affiliation'], $row['client_type'], $row['age'], $row['sex'], $row['region_of_residence'], $row['ref_id'], $row['email_address'], $row['cc1'], $row['cc2'], $row['cc3'], $row['sqd0'], $row['sqd1'], $row['sqd2'], $row['sqd3'], $row['sqd4'], $row['sqd5'], $row['sqd6'], $row['sqd7'], $row['sqd8'], $row['suggestions'] ]);
        }
        fclose($output);
        exit;
    }
}

if (!is_logged_in()) {
    $error_message = '';
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
        $username = trim($_POST['username']); $password = trim($_POST['password']);
        if (empty($username) || empty($password)) { $error_message = "Username and password are required."; }
        else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $stmt->execute([$username]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role']; $_SESSION['department_id'] = $user['department_id'];
                log_system_action($pdo, $user['id'], 'User logged in');
                header("Location: admin.php"); exit;
            } else { $error_message = "Invalid username or password."; }
        }
    }
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Admin Login</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;background-color:#f0f2f5;}.login-card{width:100%;max-width:400px;padding:2rem;}</style></head><body><div class="card login-card shadow-sm"><div class="card-body"><h3 class="card-title text-center mb-4">Admin Panel Login</h3><?php if($error_message):?><div class="alert alert-danger"><?php echo $error_message;?></div><?php endif;?><form action="admin.php" method="post"><div class="mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" required></div><div class="mb-3"><label for="password" class="form-label">Password</label><input type="password" class="form-control" id="password" name="password" required></div><button type="submit" name="login" class="btn btn-primary w-100">Login</button></form></div></div></body></html>
    <?php
    exit;
}

// ===================================================================================
// --- START: PRE-HEADER PHP LOGIC FOR ALL ADMIN ACTIONS (CRUD) ---
// ===================================================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_admin() && $page === 'settings' && isset($_POST['update_settings'])) {
        // *** MODIFIED: Added 'timezone' to allowed settings ***
        $allowed_settings = ['agency_name', 'province_name', 'region_name', 'password_complexity', 'timezone'];
        foreach ($allowed_settings as $setting) { if (isset($_POST[$setting])) { $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = ?"); $stmt->execute([$_POST[$setting], $setting]); } }
        if (isset($_FILES['agency_logo']) && $_FILES['agency_logo']['error'] == 0) {
            $target_dir = "img/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
            
            $file_info = getimagesize($_FILES["agency_logo"]["tmp_name"]);
            if ($file_info !== false) {
                $extension = image_type_to_extension($file_info[2]);
                if (in_array($extension, ['.jpg', '.png', '.jpeg', '.gif'])) {
                    $random_name = bin2hex(random_bytes(16)) . $extension;
                    $target_file = $target_dir . $random_name;
                    if (move_uploaded_file($_FILES["agency_logo"]["tmp_name"], $target_file)) {
                        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'agency_logo'");
                        $stmt->execute([$random_name]);
                    }
                }
            }
        }
        log_system_action($pdo, $_SESSION['user_id'], 'Updated system settings');
        $action_message = '<div class="alert alert-success">Settings updated successfully.</div>';
        // Reload config after update
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM settings"); while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $CONFIG[$row['setting_name']] = $row['setting_value']; }
    }
    if ($page === 'services' && isset($_POST['save_service'])) {
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        $service_name = trim($_POST['service_name']);
        $service_type = $_POST['service_type'];
        $service_details_html = trim($_POST['service_details_html']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (!is_admin() && $department_id != $_SESSION['department_id']) {
            $action_message = '<div class="alert alert-danger">Access Denied.</div>';
        } else {
            if (empty($service_name) || empty($service_type)) {
                $action_message = '<div class="alert alert-danger">Service name and type are required.</div>';
            } elseif (strlen($service_name) > 255) {
                $action_message = '<div class="alert alert-danger">Service name cannot be longer than 255 characters.</div>';
            } else {
                if ($service_id) {
                    $stmt = $pdo->prepare("UPDATE services SET department_id=?, service_name=?, service_type=?, service_details_html=?, is_active=? WHERE id=?");
                    $stmt->execute([$department_id, $service_name, $service_type, $service_details_html, $is_active, $service_id]);
                    log_system_action($pdo, $_SESSION['user_id'], "Updated service '{$service_name}'");
                    $action_message = '<div class="alert alert-success">Service updated.</div>';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO services (department_id, service_name, service_type, service_details_html, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$department_id, $service_name, $service_type, $service_details_html, $is_active]);
                    log_system_action($pdo, $_SESSION['user_id'], "Added new service '{$service_name}'");
                    $action_message = '<div class="alert alert-success">Service added.</div>';
                }
            }
        }
    }
    if ($page === 'services' && isset($_POST['delete_service'])) {
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare("SELECT department_id FROM services WHERE id = ?"); $stmt->execute([$service_id]); $service_dept = $stmt->fetchColumn();
        if (is_admin() || $service_dept == $_SESSION['department_id']) { $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?"); $stmt->execute([$service_id]); log_system_action($pdo, $_SESSION['user_id'], "Deleted service with ID {$service_id}"); $action_message = '<div class="alert alert-success">Service deleted.</div>'; }
    }
    if (is_admin() && $page === 'departments' && isset($_POST['save_department'])) {
        $dept_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        $dept_name = trim($_POST['department_name']);
        if (!empty($dept_name)) {
            if (strlen($dept_name) > 255) {
                $action_message = '<div class="alert alert-danger">Department name cannot be longer than 255 characters.</div>';
            } else {
                if ($dept_id) {
                    $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
                    $stmt->execute([$dept_name, $dept_id]);
                    log_system_action($pdo, $_SESSION['user_id'], "Updated department '{$dept_name}'");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
                    $stmt->execute([$dept_name]);
                    log_system_action($pdo, $_SESSION['user_id'], "Added new department '{$dept_name}'");
                }
                $action_message = '<div class="alert alert-success">Department saved.</div>';
            }
        }
    }
    if (is_admin() && $page === 'departments' && isset($_POST['delete_department'])) { $dept_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT); $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?"); $stmt->execute([$dept_id]); log_system_action($pdo, $_SESSION['user_id'], "Deleted department with ID {$dept_id}"); $action_message = '<div class="alert alert-success">Department deleted.</div>'; }
    if (is_admin() && $page === 'users' && isset($_POST['save_user'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $department_id = ($role === 'dept') ? filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT) : null;

        // Ensure department_id is valid or null
        if ($role === 'dept' && empty($department_id)) {
            $action_message = '<div class="alert alert-danger">A department must be selected for department-level users.</div>';
        } elseif (empty($username) || empty($role)) {
            $action_message = '<div class="alert alert-danger">Username and role are required.</div>';
        } elseif (strlen($username) > 255) {
            $action_message = '<div class="alert alert-danger">Username cannot be longer than 255 characters.</div>';
        } else {
            if ($user_id) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, department_id = ? WHERE id = ?");
                $stmt->execute([$username, $role, $department_id, $user_id]);
                log_system_action($pdo, $_SESSION['user_id'], "Updated user '{$username}'");
                $action_message = '<div class="alert alert-success">User updated.</div>';
            } else {
                $password = trim($_POST['password']);
                if (empty($password)) {
                    $action_message = '<div class="alert alert-danger">Password is required for new users.</div>';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, department_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $role, $department_id]);
                    log_system_action($pdo, $_SESSION['user_id'], "Added new user '{$username}'");
                    $action_message = '<div class="alert alert-success">User added.</div>';
                }
            }
        }
    }
    if (is_admin() && $page === 'users' && isset($_POST['reset_password'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); $new_password = trim($_POST['new_password']);
        if (!empty($new_password)) { $password_hash = password_hash($new_password, PASSWORD_DEFAULT); $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?"); $stmt->execute([$password_hash, $user_id]); log_system_action($pdo, $_SESSION['user_id'], "Reset password for user with ID {$user_id}"); $action_message = '<div class="alert alert-success">Password reset successfully.</div>';
        } else { $action_message = '<div class="alert alert-danger">New password cannot be empty.</div>'; }
    }
    if (is_admin() && $page === 'users' && isset($_POST['delete_user'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($user_id != 1) { $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?"); $stmt->execute([$user_id]); log_system_action($pdo, $_SESSION['user_id'], "Deleted user with ID {$user_id}"); $action_message = '<div class="alert alert-success">User deleted successfully.</div>';}
        else { $action_message = '<div class="alert alert-danger">Cannot delete the primary admin user.</div>'; }
    }
}
$date_start = $_GET['date_start'] ?? date('Y-m-d', strtotime('-29 days'));
$date_end = $_GET['date_end'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8"><title>eCSM Admin Panel</title><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --sidebar-width: 280px; }
        body { background-color: #f8f9fa; padding-top: 56px; padding-bottom: 56px; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; width: var(--sidebar-width); padding: 0; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); background-color: #0056b3; }
        .sidebar-header { padding: 1rem; background-color: rgba(0,0,0,0.2); margin-top: 56px; }
        .sidebar-sticky { height: calc(100vh - 56px); overflow-y: auto; }
        .main-content { margin-left: var(--sidebar-width); padding: 1.5rem; }
        .nav-link { color: rgba(255,255,255,.75); padding-left: 1.5em; } .nav-link .bi { margin-right: 0.8em; } .nav-link.active, .nav-link:hover { color: #fff; }
        .navbar { z-index: 101; } 
        .page-header { margin-bottom: 1.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
        .footer { position: fixed; left: 0; bottom: 0; width: 100%; z-index: 101; background-color: #343a40; }
        .form-control-plaintext { padding-left: .75rem; border: 1px solid #dee2e6; border-radius: .375rem; background-color: #e9ecef; }
    </style>
</head>
<body>
    <header class="navbar navbar-dark fixed-top bg-danger flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6" style="width: var(--sidebar-width);" href="admin.php"><?php echo e($CONFIG['agency_name']); ?></a>
        <div class="navbar-nav ms-auto flex-row">
            <div class="nav-item text-nowrap">
                <button type="button" class="nav-link px-3 btn btn-link" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="bi bi-person-circle"></i> Profile</button>
            </div>
            <div class="nav-item text-nowrap"><a class="nav-link px-3" href="admin.php?action=logout">Sign out <i class="bi bi-box-arrow-right"></i></a></div>
        </div>
    </header>
    
    <nav id="sidebarMenu" class="d-md-block sidebar collapse">
        <div class="sidebar-header text-white">
            <h6 class="mb-0"><?php echo e($_SESSION['username']); ?></h6>
            <?php
            if (!is_admin()) {
                $dept_name_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dept_name_stmt->execute([$_SESSION['department_id']]);
                echo '<small>' . e($dept_name_stmt->fetchColumn()) . '</small>';
            } else {
                echo '<small>Administrator</small>';
            }
            ?>
        </div>
        <div class="sidebar-sticky pt-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link <?php if($page=='dashboard') echo 'active';?>" href="admin.php?page=dashboard"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='reports') echo 'active';?>" href="admin.php?page=reports"><i class="bi bi-file-earmark-bar-graph-fill"></i>CSM Reports</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='csmform') echo 'active';?>" href="admin.php?page=csmform"><i class="bi bi-file-text-fill"></i>CSM Data</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='services') echo 'active';?>" href="admin.php?page=services"><i class="bi bi-card-checklist"></i>Service Mgt.</a></li>
                <?php if (is_admin()): ?>
                <li class="nav-item"><a class="nav-link <?php if($page=='departments') echo 'active';?>" href="admin.php?page=departments"><i class="bi bi-building-fill"></i>Department Mgt.</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='users') echo 'active';?>" href="admin.php?page=users"><i class="bi bi-people-fill"></i>User Management</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='settings') echo 'active';?>" href="admin.php?page=settings"><i class="bi bi-gear-fill"></i>Settings</a></li>
                <li class="nav-item"><a class="nav-link <?php if($page=='logs') echo 'active';?>" href="admin.php?page=logs"><i class="bi bi-journal-text"></i>System Logs</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h1 class="h2 mb-0"><?php echo e(ucfirst(str_replace('_', ' ', $page))); ?></h1>
            <?php 
            if(in_array($page, ['services', 'departments', 'users'])) {
                if($page == 'services' || is_admin()){
                    echo '<button type="button" class="btn btn-primary add-new-btn" data-bs-toggle="modal" data-bs-target="#'.$page.'Modal"><i class="bi bi-plus-circle-fill"></i> Add New '.ucfirst(substr($page, 0, -1)).'</button>';
                }
            }
            if(!is_admin() && $page === 'dashboard') {
                $dept_name_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dept_name_stmt->execute([$_SESSION['department_id']]);
                $dept_name = $dept_name_stmt->fetchColumn();
                echo '<button type="button" class="btn btn-success direct-download-qr-btn" data-dept-id="'.$_SESSION['department_id'].'" data-dept-name="'.e($dept_name).'"><i class="bi bi-qr-code"></i> Download QR</button>';
            }
            ?>
        </div>
        <?php echo $action_message; ?>
        <?php
        switch ($page) {
            case 'reports':
                include 'reports.php';
                break;
            case 'csmform':
                include 'csmform.php';
                break;
            case 'logs':
                include 'logs.php';
                break;
            case 'services':
                $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                $service_sql = "SELECT s.*, d.name as dept_name FROM services s JOIN departments d ON s.department_id = d.id";
                if (!is_admin()) { $service_sql .= " WHERE s.department_id = " . (int)$_SESSION['department_id']; }
                $service_sql .= " ORDER BY s.service_name";
                $services = $pdo->query($service_sql)->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card"><div class="card-header">Existing Services</div><div class="card-body">
                <?php if (count($services) > 0): ?>
                <div class="table-responsive"><table id="datatable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>Name</th><th>Department</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php foreach($services as $service): ?>
                <tr><td><?php echo e($service['id']); ?></td><td><?php echo e($service['service_name']); ?></td><td><?php echo e($service['dept_name']); ?></td><td><?php echo e($service['service_type']); ?></td><td><?php echo $service['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>'; ?></td>
                <td>
                    <button class="btn btn-sm btn-primary edit-service-btn" data-bs-toggle="modal" data-bs-target="#servicesModal" data-id="<?php echo $service['id'];?>" data-name="<?php echo e($service['service_name']);?>" data-deptid="<?php echo $service['department_id'];?>" data-type="<?php echo $service['service_type'];?>" data-details="<?php echo e($service['service_details_html']);?>" data-active="<?php echo $service['is_active'];?>"><i class="bi bi-pencil-fill"></i></button>
                    <button class="btn btn-sm btn-success generate-qr-btn" data-bs-toggle="modal" data-bs-target="#qrCodeModal" data-service-id="<?php echo $service['id'];?>" data-service-name="<?php echo e($service['service_name']);?>"><i class="bi bi-qr-code"></i> QR</button>
                    <form action="admin.php?page=services" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this service?');"><input type="hidden" name="service_id" value="<?php echo $service['id']; ?>"><button type="submit" name="delete_service" class="btn btn-sm btn-danger"><i class="bi bi-trash-fill"></i></button></form>
                </td></tr><?php endforeach; ?></tbody></table></div>
                <?php else: ?>
                    <div class="alert alert-info">No services found.</div>
                <?php endif; ?>
                </div></div>
                <?php
                break;
            case 'departments':
                if(is_admin()) {
                    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="card"><div class="card-header">Existing Departments</div><div class="card-body">
                    <?php if (count($departments) > 0): ?>
                    <div class="table-responsive"><table id="datatable" class="table table-striped"><thead><tr><th>Name</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach($departments as $dept): ?>
                    <tr><td><?php echo e($dept['name']); ?></td><td>
                    <button class="btn btn-sm btn-primary edit-dept-btn" data-bs-toggle="modal" data-bs-target="#departmentsModal" data-id="<?php echo $dept['id'];?>" data-name="<?php echo e($dept['name']);?>">Edit</button>
                    <button class="btn btn-sm btn-success generate-qr-btn" data-bs-toggle="modal" data-bs-target="#qrCodeModal" data-dept-id="<?php echo $dept['id'];?>" data-dept-name="<?php echo e($dept['name']);?>"><i class="bi bi-qr-code"></i> QR</button>
                    <form action="admin.php?page=departments" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this department?');"><input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>"><button type="submit" name="delete_department" class="btn btn-sm btn-danger">Delete</button></form>
                    </td></tr><?php endforeach; ?></tbody></table></div>
                    <?php else: ?>
                        <div class="alert alert-info">No departments found.</div>
                    <?php endif; ?>
                    </div></div>
                    <?php
                } else { echo '<div class="alert alert-danger">Access Denied.</div>'; }
                break;
            case 'users':
                if (is_admin()) {
                    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                    $users = $pdo->query("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.username")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                     <div class="card"><div class="card-header">Existing Users</div><div class="card-body">
                     <?php if (count($users) > 0): ?>
                     <div class="table-responsive"><table id="datatable" class="table table-striped table-hover"><thead><tr><th>Username</th><th>Role</th><th>Department</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach($users as $user):?>
                    <tr><td><?php echo e($user['username']);?></td><td><?php echo e(ucfirst($user['role']));?></td><td><?php echo e($user['dept_name'] ?? 'N/A');?></td>
                    <td><button class="btn btn-sm btn-primary edit-user-btn" data-bs-toggle="modal" data-bs-target="#usersModal" data-id="<?php echo $user['id'];?>" data-username="<?php echo e($user['username']);?>" data-role="<?php echo $user['role'];?>" data-deptid="<?php echo $user['department_id'];?>"><i class="bi bi-pencil-fill"></i></button>
                    <button type="button" class="btn btn-sm btn-warning reset-password-btn" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-userid="<?php echo $user['id'];?>" data-username="<?php echo e($user['username']);?>"><i class="bi bi-key-fill"></i></button>
                    <?php if($user['id'] != 1):?><form action="admin.php?page=users" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');"><input type="hidden" name="user_id" value="<?php echo $user['id'];?>"><button type="submit" name="delete_user" class="btn btn-sm btn-danger"><i class="bi bi-trash-fill"></i></button></form><?php endif;?>
                    </td></tr><?php endforeach;?></tbody></table></div>
                    <?php else: ?>
                        <div class="alert alert-info">No users found.</div>
                    <?php endif; ?>
                    </div></div>
                    <?php
                } else { echo '<div class="alert alert-danger">Access Denied.</div>'; }
                break;
            case 'settings':
                if (is_admin()) { ?>
                    <div class="card"><div class="card-header">Agency Settings</div><div class="card-body">
                    <form action="admin.php?page=settings" method="POST" enctype="multipart/form-data">
                        <div class="mb-3"><label class="form-label">Agency Name</label><input type="text" name="agency_name" class="form-control" value="<?php echo e($CONFIG['agency_name']); ?>"></div>
                        <div class="mb-3"><label class="form-label">Province Name</label><input type="text" name="province_name" class="form-control" value="<?php echo e($CONFIG['province_name']); ?>"></div>
                        <div class="mb-3"><label class="form-label">Region Name</label><input type="text" name="region_name" class="form-control" value="<?php echo e($CONFIG['region_name']); ?>"></div><hr>
                        <div class="mb-3"><label class="form-label">Timezone</label><select name="timezone" class="form-select">
                            <?php 
                                $timezones = DateTimeZone::listIdentifiers();
                                foreach($timezones as $tz) {
                                    $selected = ($CONFIG['timezone'] == $tz) ? 'selected' : '';
                                    echo "<option value='{$tz}' {$selected}>" . e($tz) . "</option>";
                                }
                            ?>
                        </select></div><hr>
                        <div class="mb-3"><label class="form-label">Password Complexity</label><select name="password_complexity" class="form-select">
                            <option value="low" <?php if($CONFIG['password_complexity'] == 'low') echo 'selected'; ?>>Low (8+ Chars)</option>
                            <option value="medium" <?php if($CONFIG['password_complexity'] == 'medium') echo 'selected'; ?>>Medium (8+ Chars, Upper, Lower, Number)</option>
                            <option value="high" <?php if($CONFIG['password_complexity'] == 'high') echo 'selected'; ?>>High (8+ Chars, Upper, Lower, Number, Symbol)</option>
                        </select></div><hr>
                        <div class="mb-3"><label class="form-label">Current Logo</label><div><img src="img/<?php echo e($CONFIG['agency_logo'] ?? ''); ?>" alt="Current Logo" style="max-height: 80px; background: #eee; padding: 5px; border-radius: 5px;"></div></div>
                        <div class="mb-3"><label for="agency_logo" class="form-label">Upload New Logo (Optional)</label><input class="form-control" type="file" name="agency_logo" id="agency_logo" accept="image/png, image/jpeg, image/gif"></div>
                        <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                    </form></div></div>
                    <?php } else { echo '<div class="alert alert-danger">Access Denied.</div>'; }
                break;
            case 'dashboard':
            default:
                if (is_admin()) {
                    $total_responses = $pdo->query('SELECT count(*) FROM csm_responses')->fetchColumn();
                    $today_responses = $pdo->query("SELECT count(*) FROM csm_responses WHERE DATE(submission_date) = CURDATE()")->fetchColumn();
                    $active_services = $pdo->query("SELECT count(*) FROM services WHERE is_active = 1")->fetchColumn();
                } else {
                    $dept_id = $_SESSION['department_id'];
                    $total_responses_stmt = $pdo->prepare('SELECT count(*) FROM csm_responses r JOIN services s ON r.service_id = s.id WHERE s.department_id = ?');
                    $total_responses_stmt->execute([$dept_id]);
                    $total_responses = $total_responses_stmt->fetchColumn();

                    $today_responses_stmt = $pdo->prepare("SELECT count(*) FROM csm_responses r JOIN services s ON r.service_id = s.id WHERE s.department_id = ? AND DATE(r.submission_date) = CURDATE()");
                    $today_responses_stmt->execute([$dept_id]);
                    $today_responses = $today_responses_stmt->fetchColumn();

                    $active_services_stmt = $pdo->prepare("SELECT count(*) FROM services WHERE is_active = 1 AND department_id = ?");
                    $active_services_stmt->execute([$dept_id]);
                    $active_services = $active_services_stmt->fetchColumn();
                }
                ?>
                <h4>Welcome, <?php echo e($_SESSION['username']); ?>!</h4><p>This is the main dashboard. Here is a summary of the system status.</p>
                <div class="row">
                    <div class="col-md-4"><div class="card text-center text-white bg-primary mb-3"><div class="card-body"><h1 class="display-4"><?php echo $total_responses; ?></h1><p class="card-text">Total Feedback Submissions</p></div></div></div>
                    <div class="col-md-4"><div class="card text-center text-white bg-danger mb-3"><div class="card-body"><h1 class="display-4"><?php echo $today_responses; ?></h1><p class="card-text">Submissions Today</p></div></div></div>
                    <div class="col-md-4"><div class="card text-center text-white bg-success mb-3"><div class="card-body"><h1 class="display-4"><?php echo $active_services; ?></h1><p class="card-text">Active Services</p></div></div></div>
                </div>
                <?php
                break;
        }
        ?>
    </main>
    
    <?php include 'footer.php'; ?>

    <div class="modal fade" id="servicesModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"></div></div></div>
    <div class="modal fade" id="departmentsModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"></div></div></div>
    <div class="modal fade" id="usersModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"></div></div></div>
    
    <div class="modal fade" id="changePasswordModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form id="changePasswordForm"><div class="modal-header"><h5 class="modal-title">Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div id="changePasswordAlert"></div>
        <div class="mb-3"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-3"><label>New Password</label><input type="password" name="new_password" id="new_password" class="form-control" required>
            <div class="progress mt-2" style="height: 5px;"><div id="password-strength-bar" class="progress-bar" role="progressbar"></div></div>
            <small id="password-strength-text" class="form-text"></small>
        </div>
        <div class="mb-3"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Password</button></div>
    </form></div></div></div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form action="admin.php?page=users" method="POST"><div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="user_id" id="resetUserId"><p>Resetting password for user: <strong id="resetUsernameLabel"></strong></p><div class="mb-3"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button></div></form></div></div></div>

    <div class="modal fade" id="qrCodeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="qrCodeModalLabel">QR Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center"><div id="qrCodePrintArea"><h4 id="qrName"></h4><div id="qrcode-container" class="p-3 d-flex justify-content-center"></div><p id="qrHelpText"></p><p><small class="text-muted" id="qrUrl"></small></p></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="downloadQrBtn"><i class="bi bi-download"></i> Download as Image</button></div>
    </div></div></div>

    <div class="modal fade" id="viewResponseModal" tabindex="-1" aria-labelledby="viewResponseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewResponseModalLabel">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="response-details-content">
                        <p class="text-center">Loading details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#datatable').length && $('#datatable tbody tr').length > 0) {
                $('#datatable').DataTable();
            }

            const currentPage = '<?php echo $page; ?>';

            $('.add-new-btn').on('click', function() {
                const modal = $('#' + currentPage + 'Modal');
                const formHtml = getModalFormHtml(currentPage);
                modal.find('.modal-content').html(formHtml);
                modal.find('#modalTitle').text('Add New ' + currentPage.charAt(0).toUpperCase() + currentPage.slice(1, -1));
                if (currentPage === 'users') { $('#user_role').trigger('change'); }
                if (currentPage === 'services' && '<?php echo $_SESSION['role']; ?>' !== 'admin') { modal.find('#department_id').val('<?php echo $_SESSION['department_id']; ?>'); }
            });

            $(document).on('click', '.edit-service-btn, .edit-dept-btn, .edit-user-btn', function() {
                const modal = $('#' + currentPage + 'Modal');
                const formHtml = getModalFormHtml(currentPage);
                modal.find('.modal-content').html(formHtml);
                
                if ($(this).hasClass('edit-service-btn')) {
                    modal.find('#modalTitle').text('Edit Service');
                    modal.find('#service_id').val($(this).data('id'));
                    modal.find('#service_id_display').val($(this).data('id'));
                    modal.find('#service_name').val($(this).data('name'));
                    modal.find('#department_id').val($(this).data('deptid')); modal.find('#service_details_html').val($(this).data('details'));
                    modal.find('input[name="service_type"][value="' + $(this).data('type') + '"]').prop('checked', true);
                    modal.find('#is_active').prop('checked', $(this).data('active') == 1);
                } else if ($(this).hasClass('edit-dept-btn')) {
                    modal.find('#modalTitle').text('Edit Department');
                    modal.find('#department_id').val($(this).data('id')); modal.find('#department_name').val($(this).data('name'));
                } else if ($(this).hasClass('edit-user-btn')) {
                    modal.find('#modalTitle').text('Edit User');
                    modal.find('#user_id').val($(this).data('id')); modal.find('#username').val($(this).data('username'));
                    modal.find('#user_role').val($(this).data('role')).trigger('change');
                    modal.find('#user_department_id').val($(this).data('deptid'));
                    modal.find('#password-wrapper').hide().find('input').prop('required', false);
                }
            });

            $('#changePasswordForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this); const alertDiv = $('#changePasswordAlert');
                $.post('admin.php?action=change_password', form.serialize(), function(response) {
                    const alertClass = response.success ? 'alert-success' : 'alert-danger';
                    alertDiv.html(`<div class="alert ${alertClass} m-0">${response.message}</div>`);
                    if (response.success) {
                        form[0].reset();
                        $('#password-strength-bar').css('width', '0%'); $('#password-strength-text').text('');
                    }
                }, 'json');
            });

            $(document).on('keyup', '#new_password', function() {
                let password = $(this).val(); let strength = 0;
                if (password.match(/[a-z]+/)) { strength += 1; } if (password.match(/[A-Z]+/)) { strength += 1; }
                if (password.match(/[0-9]+/)) { strength += 1; } if (password.match(/[\W_]+/)) { strength += 1; }
                if (password.length >= 8) { strength += 1; }
                let bar = $('#password-strength-bar'); let text = $('#password-strength-text');
                bar.removeClass('bg-danger bg-warning bg-success').css('width', (strength * 20) + '%');
                if (strength <= 2) { bar.addClass('bg-danger'); text.text('Weak'); }
                else if (strength <= 4) { bar.addClass('bg-warning'); text.text('Medium'); }
                else { bar.addClass('bg-success'); text.text('Strong'); }
            });

            const qrCodeModalEl = document.getElementById('qrCodeModal');
            if (qrCodeModalEl) {
                qrCodeModalEl.addEventListener('shown.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const deptId = button.getAttribute('data-dept-id');
                    const deptName = button.getAttribute('data-dept-name');
                    const serviceId = button.getAttribute('data-service-id');
                    const serviceName = button.getAttribute('data-service-name');
                    const baseUrl = window.location.origin + window.location.pathname.replace('admin.php', 'client.php');
                    let url = '';
                    let name = '';
                    let helpText = '';

                    if (serviceId) {
                        url = `${baseUrl}?service_id=${serviceId}`;
                        name = serviceName;
                        helpText = "Scan this QR code to go directly to this service's feedback form.";
                        $('#qrCodeModalLabel').text('Service QR Code');
                    } else {
                        url = `${baseUrl}?dept_id=${deptId}`;
                        name = deptName;
                        helpText = "Scan this QR code to go directly to this department's feedback form.";
                        $('#qrCodeModalLabel').text('Department QR Code');
                    }
                    
                    const qrContainer = document.getElementById("qrcode-container");
                    qrContainer.innerHTML = '';
                    
                    $('#qrName').text(name);
                    $('#qrHelpText').text(helpText);
                    $('#qrUrl').text(url);
                    
                    new QRCode(qrContainer, {
                        text: url, width: 256, height: 256, correctLevel : QRCode.CorrectLevel.H
                    });
                });
            }

            $('.direct-download-qr-btn').on('click', function() {
                const deptId = $(this).data('dept-id');
                const deptName = $(this).data('dept-name').replace(/ /g, '-').toLowerCase();
                const baseUrl = window.location.origin + window.location.pathname.replace('admin.php', 'client.php');
                const url = `${baseUrl}?dept_id=${deptId}`;

                const tempContainer = document.createElement('div');
                new QRCode(tempContainer, { text: url, width: 256, height: 256 });

                setTimeout(() => {
                    const canvas = tempContainer.querySelector('canvas');
                    if (canvas) {
                        const link = document.createElement('a');
                        link.download = `qr-code-${deptName}.png`;
                        link.href = canvas.toDataURL("image/png");
                        link.click();
                    }
                }, 50); 
            });

            $('#downloadQrBtn').on('click', function() {
                const canvas = document.querySelector('#qrcode-container canvas');
                const name = document.getElementById('qrName').innerText.replace(/ /g, '-').toLowerCase();
                if (canvas) {
                    const link = document.createElement('a');
                    link.download = `qr-code-${name}.png`;
                    link.href = canvas.toDataURL("image/png");
                    link.click();
                }
            });
            
            if (currentPage === 'reports') {
                const start = moment('<?php echo e($date_start); ?>'); const end = moment('<?php echo e($date_end); ?>');
                function cb(start, end) { $('#reportrange span').html(start.format('MMMM D,YYYY') + ' - ' + end.format('MMMM D,YYYY')); $('#date_start').val(start.format('YYYY-MM-DD')); $('#date_end').val(end.format('YYYY-MM-DD')); }
                $('#reportrange').daterangepicker({ startDate: start, endDate: end, ranges: { 'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],'Last 7 Days': [moment().subtract(6, 'days'), moment()],'Last 30 Days': [moment().subtract(29, 'days'), moment()],'This Month': [moment().startOf('month'), moment().endOf('month')],'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')] } }, cb);
                cb(start, end);
                
                $('#export-csv-btn').on('click', function() {
                    if ($(this).is(':disabled')) { return; }
                    const date_start = $('#date_start').val();
                    const date_end = $('#date_end').val();
                    const report_level = $('#report_level').val();
                    const filter_id = $('#filter_id').val();
                    let exportUrl = `admin.php?action=export_csv&date_start=${date_start}&date_end=${date_end}&report_level=${report_level}`;
                    if (filter_id) { exportUrl += `&filter_id=${filter_id}`; }
                    window.location.href = exportUrl;
                });

                $('#report_level, #filter_id').on('change', function() {
                    const level = $('#report_level').val();
                    const filterId = $('#filter_id').val();
                    const exportBtn = $('#export-csv-btn');

                    if (level === 'department' && !filterId && '<?php echo $_SESSION['role'] ?>' === 'admin') {
                        exportBtn.prop('disabled', true);
                    } else {
                        exportBtn.prop('disabled', <?php echo empty($report_data) ? 'true' : 'false'; ?>);
                    }
                }).trigger('change');

                $('#report_level').on('change', function() {
                    const level = $(this).val(); const filterSelect = $('#filter_id');
                    filterSelect.prop('disabled', true).html('<option>Loading...</option>');
                    if (level === 'agency' || (level === 'department' && '<?php echo $_SESSION['role'] ?>' === 'dept')) { 
                        filterSelect.prop('disabled', true).html('<option value="">N/A</option>'); 
                        if (level === 'department') {
                            filterSelect.html('<option value="<?php echo $_SESSION['department_id']; ?>" selected>My Department</option>');
                        }
                    } else { 
                        $.get('admin.php', { action: 'get_filter_items', level: level }, function(data) { 
                            let options = `<option value="">-- Select a ${level} --</option>`; 
                            data.forEach(function(item) { options += `<option value="${item.id}">${item.name}</option>`; }); 
                            filterSelect.html(options).prop('disabled', false); 
                        }, 'json'); 
                    }
                    // Trigger the change event to update the export button state
                    filterSelect.trigger('change');
                }).trigger('change');
                $('#reportFilterForm').on('submit', function(e){
                    if( ($('#report_level').val() === 'department' || $('#report_level').val() === 'service') && !$('#filter_id').val() ){
                        e.preventDefault();
                        alert('Please select an item from the dropdown.');
                    }
                });
            }
            
            if (currentPage === 'csmform') {
                const start = moment('<?php echo e($date_start); ?>'); const end = moment('<?php echo e($date_end); ?>');
                function cb(start, end) { $('#csm_reportrange span').html(start.format('MMMM D,YYYY') + ' - ' + end.format('MMMM D,YYYY')); $('#csm_date_start').val(start.format('YYYY-MM-DD')); $('#csm_date_end').val(end.format('YYYY-MM-DD')); }
                $('#csm_reportrange').daterangepicker({ startDate: start, endDate: end, ranges: { 'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],'Last 7 Days': [moment().subtract(6, 'days'), moment()],'Last 30 Days': [moment().subtract(29, 'days'), moment()],'This Month': [moment().startOf('month'), moment().endOf('month')],'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')] } }, cb);
                cb(start, end);

                // *** NEW: JS for CSM Data Page enhancements ***
                $('#f_department_id').on('change', function() {
                    const deptId = $(this).val();
                    const serviceSelect = $('#f_service_id');
                    serviceSelect.html('<option value="">-- All Services --</option>').prop('disabled', true);
                    if (deptId) {
                        serviceSelect.prop('disabled', true).html('<option value="">Loading...</option>');
                        $.get('admin.php', { action: 'get_services_for_dept', department_id: deptId }, function(data) {
                            let options = '<option value="">-- All Services --</option>';
                            data.forEach(function(item) {
                                options += `<option value="${item.id}">${item.name}</option>`;
                            });
                            serviceSelect.html(options).prop('disabled', false);
                        }, 'json');
                    }
                });

                $('#export-csm-data-btn').on('click', function() {
                    const form = $('#csmDataFilterForm');
                    const queryParams = form.serialize();
                    window.location.href = `admin.php?action=export_csm_data_csv&${queryParams}`;
                });

                const responseModal = new bootstrap.Modal(document.getElementById('viewResponseModal'));
                $('#datatable').on('click', '.view-response-btn', function() {
                    const responseId = $(this).data('id');
                    $('#response-details-content').html('<p class="text-center">Loading details...</p>');
                    responseModal.show();
                    
                    $.get('admin.php', { action: 'get_response_details', id: responseId }, function(res) {
                        if (res.success) {
                            renderResponseDetails(res.data);
                        } else {
                            $('#response-details-content').html(`<div class="alert alert-danger">${res.message}</div>`);
                        }
                    }, 'json');
                });

                function renderResponseDetails(data) {
                    const cc_options = { 1: "I know what a CC is and I saw this office's CC.", 2: "I know what a CC is but I did NOT see this office's CC.", 3: "I learned of the CC only when I saw this office's CC.", 4: "I do not know what a CC is and I did not see one in this office." };
                    const cc2_options = { 1: "Easy to see", 2: "Somewhat easy to see", 3: "Difficult to see", 4: "Not visible at all", 'null': "N/A" };
                    const cc3_options = { 1: "Helped very much", 2: "Somewhat helped", 3: "Did not help", 'null': "N/A" };
                    const sqd_scores = {1: 'Strongly Disagree', 2: 'Disagree', 3: 'Neither Agree nor Disagree', 4: 'Agree', 5: 'Strongly Agree', 'null': 'N/A' };
                    
                    let sqdRows = '';
                    const sqdQuestions = ["SQD0. I am satisfied with the service that I availed.", "SQD1. I spent a reasonable amount of time for my transaction.", "SQD2. The office followed the transaction's requirements and steps based on the information provided.", "SQD3. The steps (including payment) I needed to do for my transaction were easy and simple.", "SQD4. I could easily find information about my transaction from the office or its website.", "SQD5. I paid a reasonable amount of fees for my transaction.", "SQD6. I feel the office was fair to everyone, or 'walang palakasan', during my transaction.", "SQD7. I was treated courteously by the staff, and the staff I approached for help were helpful.", "SQD8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me."];
                    sqdQuestions.forEach((q, i) => {
                        const score = data[`sqd${i}`];
                        const scoreText = sqd_scores[String(score)] || 'N/A';
                        sqdRows += `<tr><td class="text-start">${q}</td><td>${scoreText}</td></tr>`;
                    });

                    const html = `
                        <h5>Feedback ID: ${data.id}</h5>
                        <p class="text-muted">Submitted on ${moment(data.submission_date).format('MMMM D, YYYY, h:mm:ss a')}</p>
                        <hr>
                        <h6>Client & Service Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6"><label class="form-label">Department</label><input type="text" class="form-control-plaintext" value="${data.department_name}" readonly></div>
                            <div class="col-md-6"><label class="form-label">Service Availed</label><input type="text" class="form-control-plaintext" value="${data.service_name}" readonly></div>
                            <div class="col-md-3"><label class="form-label">Client Type</label><input type="text" class="form-control-plaintext" value="${data.client_type || 'N/A'}" readonly></div>
                            <div class="col-md-3"><label class="form-label">Affiliation</label><input type="text" class="form-control-plaintext" value="${data.affiliation || 'N/A'}" readonly></div>
                            <div class="col-md-2"><label class="form-label">Sex</label><input type="text" class="form-control-plaintext" value="${data.sex || 'N/A'}" readonly></div>
                            <div class="col-md-2"><label class="form-label">Age</label><input type="text" class="form-control-plaintext" value="${data.age || 'N/A'}" readonly></div>
                            <div class="col-md-2"><label class="form-label">Email</label><input type="text" class="form-control-plaintext" value="${data.email_address || 'N/A'}" readonly></div>
                            <div class="col-md-2"><label class="form-label">Client Reference #</label><input type="text" class="form-control-plaintext" value="${data.ref_id || 'N/A'}" readonly></div>
                        </div>
                        <hr>
                        <h6>Citizen's Charter (CC)</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">CC1. Awareness of CC</label>
                            <p class="form-control-plaintext">${cc_options[data.cc1] || 'N/A'}</p>
                        </div>
                        <div class="row mb-3">
                           <div class="col-md-6"><label class="form-label fw-bold">CC2. Visibility of CC</label><p class="form-control-plaintext">${cc2_options[data.cc2] || 'N/A'}</p></div>
                           <div class="col-md-6"><label class="form-label fw-bold">CC3. Helpfulness of CC</label><p class="form-control-plaintext">${cc3_options[data.cc3] || 'N/A'}</p></div>
                        </div>
                        <hr>
                        <h6>Service Quality Dimensions (SQD)</h6>
                        <table class="table table-bordered table-sm"><thead><tr><th>Question</th><th>Response</th></tr></thead><tbody>${sqdRows}</tbody></table>
                        <hr>
                        <h6>Suggestions / Remarks</h6>
                        <textarea class="form-control" readonly rows="4">${data.suggestions || '(No suggestions provided)'}</textarea>
                    `;
                    $('#response-details-content').html(html);
                }
            }

            if (currentPage === 'users') {
                $(document).on('change', '#user_role', function() { if ($(this).val() === 'dept') { $('#department-select-wrapper').show(); } else { $('#department-select-wrapper').hide(); } });
                $('.reset-password-btn').on('click', function(){ const userId = $(this).data('userid'); const username = $(this).data('username'); $('#resetUserId').val(userId); $('#resetUsernameLabel').text(username); });
            }
        });

        function getModalFormHtml(page) {
            let depts = <?php echo json_encode($pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)); ?>;
            let deptOptions = depts.map(d => `<option value="${d.id}">${d.name}</option>`).join('');

            if (page === 'services') {
                return `
                <form action="admin.php?page=services" method="POST"><div class="modal-header"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><input type="hidden" name="service_id" id="service_id">
                <div class="mb-3"><label class="form-label">Service ID</label><input type="text" id="service_id_display" class="form-control" disabled></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Service Name</label><input type="text" name="service_name" id="service_name" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Department</label><select name="department_id" id="department_id" class="form-select" <?php echo !is_admin() ? 'disabled' : ''; ?> required>${deptOptions}</select><?php if (!is_admin()): ?><input type="hidden" name="department_id" value="<?php echo $_SESSION['department_id']; ?>"><?php endif; ?></div>
                <div class="col-md-6 mb-3"><label class="form-label">Service Type</label><div><input type="radio" class="form-check-input" name="service_type" value="Internal" id="typeInternal" required> <label for="typeInternal">Internal</label><input type="radio" class="form-check-input ms-3" name="service_type" value="External" id="typeExternal"> <label for="typeExternal">External</label></div></div>
                <div class="col-md-6 mb-3 align-self-center"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked><label class="form-check-label" for="is_active">Service is Active</label></div></div>
                <div class="col-12 mb-3"><label class="form-label">Service Details</label><textarea name="service_details_html" id="service_details_html" class="form-control" rows="4"></textarea></div>
                </div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_service" class="btn btn-primary">Save Service</button></div>
                </form>`;
            }
            if (page === 'departments') {
                return `
                <form action="admin.php?page=departments" method="POST"><div class="modal-header"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><input type="hidden" name="department_id" id="department_id"><div class="mb-3"><label class="form-label">Department Name</label><input type="text" name="department_name" id="department_name" class="form-control" required></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_department" class="btn btn-primary">Save</button></div>
                </form>`;
            }
            if (page === 'users') {
                return `
                <form action="admin.php?page=users" method="POST"><div class="modal-header"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><input type="hidden" name="user_id" id="user_id"><div class="row">
                <div class="col-md-6 mb-3"><label>Username</label><input type="text" name="username" id="username" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label>Role</label><select name="role" id="user_role" class="form-select" required><option value="admin">Admin</option><option value="dept">Department</option></select></div>
                <div class="col-md-12 mb-3" id="department-select-wrapper"><label>Department</label><select name="department_id" id="user_department_id" class="form-select"><option value="">-- Select Department --</option>${deptOptions}</select></div>
                <div class="col-md-6 mb-3" id="password-wrapper"><label>Password</label><input type="password" name="password" class="form-control"></div>
                </div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_user" class="btn btn-primary">Save User</button></div>
                </form>`;
            }
            return '';
        }
    </script>
</body>
</html>
