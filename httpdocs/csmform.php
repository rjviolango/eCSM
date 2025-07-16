<?php
// eCSM - ARTA-2242-3
// csmform.php - Included by admin.php to view individual CSM responses.

// --- HELPER FUNCTION ---
function getScoreText($score) {
    if ($score === null) return '<span class="text-muted">N/A</span>';
    $scores = [
        1 => '<span class="badge bg-danger">Strongly Disagree</span>',
        2 => '<span class="badge bg-warning text-dark">Disagree</span>',
        3 => '<span class="badge bg-info text-dark">Neither</span>',
        4 => '<span class="badge bg-primary">Agree</span>',
        5 => '<span class="badge bg-success">Strongly Agree</span>'
    ];
    return $scores[$score] ?? '<span class="text-muted">N/A</span>';
}

// --- INITIALIZE & FETCH DATA ---
$params = [];
$where_clauses = [];

// Date Filtering
$where_clauses[] = "r.submission_date BETWEEN :date_start AND :date_end_plus_one";
$params[':date_start'] = $date_start;
$params[':date_end_plus_one'] = date('Y-m-d', strtotime($date_end . ' +1 day'));

// Search Term Filtering
$search_term = $_GET['search'] ?? '';
if (!empty($search_term)) {
    $where_clauses[] = "(s.service_name LIKE :search OR r.suggestions LIKE :search OR r.region_of_residence LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

// Department & Service Filtering
$filter_dept_id = $_GET['department_id'] ?? null;
$filter_service_id = $_GET['service_id'] ?? null;

if (is_admin()) {
    if (!empty($filter_dept_id)) {
        $where_clauses[] = "d.id = :dept_id";
        $params[':dept_id'] = $filter_dept_id;
    }
} else {
    // Dept users are always filtered by their own department
    $where_clauses[] = "d.id = :user_dept_id";
    $params[':user_dept_id'] = $_SESSION['department_id'];
}

if (!empty($filter_service_id)) {
    $where_clauses[] = "s.id = :service_id";
    $params[':service_id'] = $filter_service_id;
}


$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Pagination logic
$per_page = 20;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($current_page - 1) * $per_page;

// Get total number of records for pagination
$count_sql = "SELECT COUNT(*) FROM csm_responses r JOIN services s ON r.service_id = s.id JOIN departments d ON s.department_id = d.id $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$sql = "SELECT r.id, r.submission_date, r.region_of_residence, r.sqd0, r.suggestions, s.service_name, d.name as department_name 
        FROM csm_responses r 
        JOIN services s ON r.service_id = s.id 
        JOIN departments d ON s.department_id = d.id 
        $where_sql 
        ORDER BY r.submission_date DESC
        LIMIT :per_page OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="card">
    <div class="card-header"><h5><i class="bi bi-filter"></i> View & Search Feedback</h5></div>
    <div class="card-body">
        <form id="csmDataFilterForm" action="admin.php" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="csmform">
            <input type="hidden" name="date_start" id="csm_date_start" value="<?php echo e($date_start); ?>">
            <input type="hidden" name="date_end" id="csm_date_end" value="<?php echo e($date_end); ?>">
            
            <div class="col-md-3"><label for="csm_reportrange" class="form-label">Date Range</label><div id="csm_reportrange" class="form-control" style="cursor: pointer;"><i class="bi bi-calendar-event"></i>&nbsp;<span></span> <i class="bi bi-caret-down-fill"></i></div></div>
            
            <?php if (is_admin()): ?>
            <div class="col-md-3">
                <label for="f_department_id" class="form-label">Department</label>
                <select name="department_id" id="f_department_id" class="form-select">
                    <option value="">-- All Departments --</option>
                    <?php
                    $depts = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($depts as $dept) {
                        $selected = ($filter_dept_id == $dept['id']) ? 'selected' : '';
                        echo "<option value='{$dept['id']}' {$selected}>" . e($dept['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="department_id" id="f_department_id" value="<?php echo $_SESSION['department_id']; ?>">
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="f_service_id" class="form-label">Service</label>
                <select name="service_id" id="f_service_id" class="form-select" <?php echo (is_admin() && empty($filter_dept_id)) ? 'disabled' : ''; ?>>
                    <option value="">-- All Services --</option>
                    <?php
                    $service_fetch_id = is_admin() ? $filter_dept_id : $_SESSION['department_id'];
                    if (!empty($service_fetch_id)) {
                        $stmt = $pdo->prepare("SELECT id, service_name FROM services WHERE department_id = ? ORDER BY service_name");
                        $stmt->execute([$service_fetch_id]);
                        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach($services as $service) {
                            $selected = ($filter_service_id == $service['id']) ? 'selected' : '';
                             echo "<option value='{$service['id']}' {$selected}>" . e($service['service_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-3"><label for="search" class="form-label">Search Remarks/Region</label><input type="text" name="search" id="search" class="form-control" value="<?php echo e($search_term); ?>"></div>

            <div class="col-md-12 d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Filter / Search</button>
                <button type="button" id="export-csm-data-btn" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Export</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Feedback Results (<?php echo count($responses); ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Dept / Service</th>
                        <th>Timestamp</th>
                        <th>Region</th>
                        <th>Satisfaction Score (SQD0)</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($responses) > 0): ?>
                        <?php foreach($responses as $row): ?>
                        <tr class="view-response-btn" data-id="<?php echo $row['id']; ?>" style="cursor: pointer;">
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <strong><?php echo e($row['department_name']); ?></strong><br>
                                <small class="text-muted"><?php echo e($row['service_name']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y, h:i A', strtotime($row['submission_date'])); ?></td>
                            <td><?php echo e($row['region_of_residence']); ?></td>
                            <td><?php echo getScoreText($row['sqd0']); ?></td>
                            <td><?php echo e(mb_strimwidth($row['suggestions'], 0, 70, "...")); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No feedback found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($total_pages > 1): ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                            <a class="page-link" href="?page=csmform&p=<?php echo $i; ?>&<?php echo http_build_query($_GET, '', '&amp;'); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>