<?php
// eCSM - ARTA-2242-3
// reports.php - Included by admin.php (Final Version with All Fixes)
?>
<style>
@media print {
    body, html {
        width: 100%;
        margin: 0;
        padding: 0;
        background-color: #fff; /* Make background white for printing */
    }
    .main-content, .card-body, .report-section {
        padding: 0 !important;
        margin: 0 !important;
    }
    .sidebar, .navbar, .footer, .card-header, #reportFilterForm, .d-flex.justify-content-end.mb-3, .btn {
        display: none !important; /* Hide non-report elements */
    }
    #printableReportArea {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        box-shadow: none !important;
        border: none !important;
        font-size: 10pt; /* Adjust font size for print */
    }
    .report-section {
        page-break-inside: avoid; /* Try to keep sections from splitting across pages */
        margin-bottom: 20px;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    .table th, .table td {
        border: 1px solid #dee2e6;
        padding: .4rem;
    }
    .table-light th {
        background-color: #f8f9fa !important; /* Ensure background color prints */
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
    .badge {
        border: 1px solid #000; /* Make badges visible */
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .text-center {
        text-align: center;
    }
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }
    h4, h5, h6, .lead {
        margin: 0;
        padding: 5px 0;
    }
    /* Ensure colors print for badges */
    .bg-success { background-color: #198754 !important; color: white !important; }
    .bg-primary { background-color: #0d6efd !important; color: white !important; }
    .bg-info { background-color: #0dcaf0 !important; color: black !important; }
    .bg-warning { background-color: #ffc107 !important; color: black !important; }
    .bg-danger { background-color: #dc3545 !important; color: white !important; }
}
</style>
<?php
// --- REPORTING HELPER FUNCTIONS ---
function getRating($score) {
    if ($score >= 95) return '<span class="badge bg-success">Outstanding</span>';
    if ($score >= 90) return '<span class="badge bg-primary">Very Satisfactory</span>';
    if ($score >= 80) return '<span class="badge bg-info text-dark">Satisfactory</span>';
    if ($score >= 70) return '<span class="badge bg-warning text-dark">Fair</span>';
    return '<span class="badge bg-danger">Poor</span>';
}

// --- INITIALIZE REPORT VARIABLES ---
$report_data = null;
$report_title_scope = 'Agency'; // Default title scope
$show_report = isset($_GET['generate']);

if ($show_report) {
    // 1. BUILD FILTERS
    $params = [];
    $where_clauses = [];
    $where_clauses[] = "r.submission_date BETWEEN :date_start AND :date_end_plus_one";
    $params[':date_start'] = $date_start;
    $params[':date_end_plus_one'] = date('Y-m-d', strtotime($date_end . ' +1 day'));
    
    $report_level = $_GET['report_level'] ?? (is_admin() ? 'agency' : 'department');
    if (!is_admin() && !in_array($report_level, ['department', 'service'])) {
        // A non-admin user is trying to access a report level they shouldn't.
        // Default them to 'department' level for security.
        $report_level = 'department';
    }

    $filter_id = !empty($_GET['filter_id']) ? (int)$_GET['filter_id'] : null;
    
    $join_clause = "FROM csm_responses r JOIN services s ON r.service_id = s.id JOIN departments d ON s.department_id = d.id";

    // --- CONSTRUCT WHERE CLAUSE AND DYNAMIC TITLE ---
    if ($report_level === 'department') {
        $dept_id_to_filter = is_admin() ? $filter_id : $_SESSION['department_id'];
        if ($dept_id_to_filter) {
            $where_clauses[] = "d.id = :filter_id";
            $params[':filter_id'] = $dept_id_to_filter;
            $title_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $title_stmt->execute([$dept_id_to_filter]);
            $report_title_scope = $title_stmt->fetchColumn();
        }
    } elseif ($report_level === 'service' && $filter_id) {
        $where_clauses[] = "s.id = :filter_id";
        $params[':filter_id'] = $filter_id;
        // Security check for dept users to ensure they can only access their own services
        if (!is_admin()) {
             $where_clauses[] = "d.id = :user_dept_id";
             $params[':user_dept_id'] = $_SESSION['department_id'];
        }
        $title_stmt = $pdo->prepare("SELECT s.service_name, d.name as dept_name FROM services s JOIN departments d ON s.department_id = d.id WHERE s.id = ?");
        $title_stmt->execute([$filter_id]);
        $title_data = $title_stmt->fetch(PDO::FETCH_ASSOC);
        $report_title_scope = e($title_data['dept_name']) . ' - ' . e($title_data['service_name']);
    }

    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // 2. FETCH DATA FROM DATABASE
    $sql = "SELECT r.*, s.service_name, d.name as department_name " . $join_clause . " " . $where_sql;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_respondents = count($responses);

    // 3. CALCULATE ALL METRICS
    $report_data = [];
    if ($total_respondents > 0) {
        $sqd0_scores = array_filter(array_column($responses, 'sqd0'), 'is_numeric');
        $report_data['overall_satisfaction'] = count($sqd0_scores) > 0 ? (array_sum($sqd0_scores) / count($sqd0_scores) - 1) / 4 * 100 : 0;
        $report_data['overall_satisfaction_count'] = count($sqd0_scores);
        $cc1_aware = count(array_filter($responses, fn($r) => in_array($r['cc1'], [1, 2, 3])));
        $report_data['cc_awareness'] = ($cc1_aware / $total_respondents) * 100;
        $report_data['cc_awareness_count'] = $cc1_aware;
        $aware_responses = array_filter($responses, fn($r) => in_array($r['cc1'], [1, 2, 3]));
        $aware_responses_count = count($aware_responses);
        if ($aware_responses_count > 0) {
            $cc2_easy = count(array_filter($aware_responses, fn($r) => $r['cc2'] == 1));
            $report_data['cc_visibility'] = ($cc2_easy / $aware_responses_count) * 100;
            $report_data['cc_visibility_count'] = $cc2_easy;
            $cc3_helped = count(array_filter($aware_responses, fn($r) => $r['cc3'] == 1));
            $report_data['cc_helpfulness'] = ($cc3_helped / $aware_responses_count) * 100;
            $report_data['cc_helpfulness_count'] = $cc3_helped;
        } else { 
            $report_data['cc_visibility'] = 0; 
            $report_data['cc_visibility_count'] = 0;
            $report_data['cc_helpfulness'] = 0; 
            $report_data['cc_helpfulness_count'] = 0;
        }
        $report_data['client_types'] = array_count_values(array_filter(array_column($responses, 'client_type'), fn($value) => !is_null($value)));
        $sex_column = array_column($responses, 'sex');
        $report_data['sex_distribution'] = array_count_values(array_filter($sex_column, fn($value) => !is_null($value)));
        $report_data['sex_distribution']['Not Stated'] = count(array_filter($sex_column, fn($value) => is_null($value)));
    }
}
?>
<!-- START OF REPORTING PAGE HTML -->
<div class="card">
    <div class="card-header"><h5><i class="bi bi-filter"></i> Report Filters</h5></div>
    <div class="card-body">
        <form id="reportFilterForm" action="admin.php" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="generate" value="true">
            <input type="hidden" name="date_start" id="date_start" value="<?php echo e($date_start); ?>">
            <input type="hidden" name="date_end" id="date_end" value="<?php echo e($date_end); ?>">
            
            <div class="col-md-3"><label for="reportrange" class="form-label">Date Range</label><div id="reportrange" class="form-control" style="cursor: pointer;"><i class="bi bi-calendar-event"></i>&nbsp;<span></span> <i class="bi bi-caret-down-fill"></i></div></div>
            <div class="col-md-3">
                <label for="report_level" class="form-label">Report Level</label>
                <select name="report_level" id="report_level" class="form-select">
                    <?php if (is_admin()): ?><option value="agency" <?php if(($_GET['report_level'] ?? '') == 'agency') echo 'selected'; ?>>Agency</option><?php endif; ?>
                    <option value="department" <?php if(($_GET['report_level'] ?? 'department') == 'department') echo 'selected'; ?>>Department</option>
                    <option value="service" <?php if(($_GET['report_level'] ?? '') == 'service') echo 'selected'; ?>>Service</option>
                </select>
            </div>
            <div class="col-md-3"><label for="filter_id" class="form-label">Select Item</label><select name="filter_id" id="filter_id" class="form-select" disabled><option value="">-- Select a Level First --</option></select></div>
            <div class="col-md-3 d-flex">
                <button type="submit" id="generateBtn" class="btn btn-primary w-50 me-2"><i class="bi bi-bar-chart-line-fill"></i> Generate</button>
                <button type="button" id="export-csv-btn" class="btn btn-success w-50" <?php if(empty($report_data)) echo 'disabled'; ?>><i class="bi bi-file-earmark-spreadsheet-fill"></i> Export</button>
            </div>
        </form>
    </div>
</div>

<?php if ($show_report): ?>
<hr>
<div class="d-flex justify-content-end mb-3">
    <?php if (!empty($report_data)): ?>
    <button class="btn btn-secondary" onclick="window.print();"><i class="bi bi-printer-fill"></i> Print Report</button>
    <?php endif; ?>
</div>
<div id="printableReportArea">
    <div class="text-center mb-4">
        <h4>CSM Report</h4>
        <p class="lead">For the period: <?php echo date('F d, Y', strtotime($date_start)) . ' to ' . date('F d, Y', strtotime($date_end)); ?></p>
    </div>
    <?php if (!empty($report_data)): ?>
    <!-- Section 1: Overall Summary -->
    <div class="report-section">
        <h5>Overall Performance Summary - <?php echo e($report_title_scope); ?></h5>
        <table class="table table-bordered table-hover">
            <thead class="table-light"><tr><th>Measure</th><th>Score</th><th>Rating</th></tr></thead>
            <tbody>
                <tr><td>Overall Satisfaction</td><td><?php echo number_format($report_data['overall_satisfaction'], 2); ?>% (<?php echo $report_data['overall_satisfaction_count']; ?>)</td><td><?php echo getRating($report_data['overall_satisfaction']); ?></td></tr>
                <tr><td>CC Awareness</td><td><?php echo number_format($report_data['cc_awareness'], 2); ?>% (<?php echo $report_data['cc_awareness_count']; ?>)</td><td><?php echo getRating($report_data['cc_awareness']); ?></td></tr>
                <tr><td>CC Visibility</td><td><?php echo number_format($report_data['cc_visibility'], 2); ?>% (<?php echo $report_data['cc_visibility_count']; ?>)</td><td><?php echo getRating($report_data['cc_visibility']); ?></td></tr>
                <tr><td>CC Helpfulness</td><td><?php echo number_format($report_data['cc_helpfulness'], 2); ?>% (<?php echo $report_data['cc_helpfulness_count']; ?>)</td><td><?php echo getRating($report_data['cc_helpfulness']); ?></td></tr>
            </tbody>
        </table>
    </div>
    <!-- Section 2: Client Demographics -->
    <div class="report-section">
        <h5>Client Demographics - <?php echo e($report_title_scope); ?></h5>
        <div class="row">
            <div class="col-md-6">
                <h6>By Client Type</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light"><tr><th>Client Type</th><th>Respondents</th><th>Percentage</th></tr></thead>
                        <tbody>
                        <?php if (!empty($report_data['client_types'])): $total_clients = array_sum($report_data['client_types']); foreach($report_data['client_types'] as $type => $count): ?>
                        <tr><td><?php echo e($type); ?></td><td><?php echo $count; ?></td><td><?php echo number_format(($count / $total_clients) * 100, 2); ?>%</td></tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold"><td class="text-end">Total</td><td><?php echo $total_clients; ?></td><td>100.00%</td></tr>
                        <?php else: ?>
                        <tr><td colspan="3" class="text-center">No data available.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                 <h6>By Sex</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light"><tr><th>Sex</th><th>Respondents</th><th>Percentage</th></tr></thead>
                        <tbody>
                        <?php
                        $sex_distribution = $report_data['sex_distribution'];
                        $total_sex = array_sum($sex_distribution);
                        ?>
                        <tr><td>Male</td><td><?php echo $sex_distribution['Male'] ?? 0; ?></td><td><?php echo $total_sex > 0 ? number_format((($sex_distribution['Male'] ?? 0) / $total_sex) * 100, 2) : 0; ?>%</td></tr>
                        <tr><td>Female</td><td><?php echo $sex_distribution['Female'] ?? 0; ?></td><td><?php echo $total_sex > 0 ? number_format((($sex_distribution['Female'] ?? 0) / $total_sex) * 100, 2) : 0; ?>%</td></tr>
                        <tr><td>Not Stated</td><td><?php echo $sex_distribution['Not Stated'] ?? 0; ?></td><td><?php echo $total_sex > 0 ? number_format((($sex_distribution['Not Stated'] ?? 0) / $total_sex) * 100, 2) : 0; ?>%</td></tr>
                        <tr class="fw-bold"><td class="text-end">Total</td><td><?php echo $total_sex; ?></td><td>100.00%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning mt-4">No data found for the selected filters. Please try a different date range or selection.</div>
    <?php endif; ?>
</div>
<?php endif; ?>
<!-- END OF REPORTING PAGE HTML -->
