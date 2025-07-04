<?php
// eCSM - ARTA-2242-3
// client.php - Final Version with all features and fixes
require_once 'includes.php';

// --- INITIALIZE VARIABLES ---
$is_kiosk_mode = false;
$kiosk_dept_id = null;
$kiosk_dept_name = '';
$departments = [];

// --- CHECK FOR KIOSK MODE ---
if (isset($_GET['dept_id']) && filter_var($_GET['dept_id'], FILTER_VALIDATE_INT)) {
    $is_kiosk_mode = true;
    $kiosk_dept_id = (int)$_GET['dept_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$kiosk_dept_id]);
        $kiosk_dept_name = $stmt->fetchColumn();
        if (!$kiosk_dept_name) {
            // If dept_id is invalid, revert to normal mode
            $is_kiosk_mode = false;
        }
    } catch (PDOException $e) {
        die("Error: Could not connect to the service. Please try again later.");
    }
}

// --- DATA FETCHING (Only if not in kiosk mode) ---
if (!$is_kiosk_mode) {
    try {
        $departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
        $departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: Could not connect to the service. Please try again later.");
    }
}

// --- AJAX REQUEST HANDLING ---
if (!empty($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'get_services' && isset($_POST['department_id'], $_POST['affiliation'])) {
        $dept_id = filter_var($_POST['department_id'], FILTER_VALIDATE_INT);
        $affiliation = in_array($_POST['affiliation'], ['Internal', 'External']) ? $_POST['affiliation'] : '';
        if ($dept_id && $affiliation) {
            $sql = "SELECT id, service_name, service_details_html FROM services WHERE department_id = :dept_id AND service_type = :affiliation AND is_active = 1 ORDER BY service_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['dept_id' => $dept_id, 'affiliation' => $affiliation]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'services' => $services]);
            exit;
        }
    }
    if ($_POST['action'] === 'submit_feedback') {
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $sql = "INSERT INTO csm_responses (service_id, affiliation, client_type, age, sex, region_of_residence, cc1, cc2, cc3, sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, suggestions, email_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $params = [
            $service_id, $_POST['affiliation'], $_POST['client_type'], empty($_POST['age']) ? null : $_POST['age'],
            $_POST['sex'], $_POST['region_of_residence'], $_POST['cc1'], 
            ($_POST['cc2'] ?? 'N/A') === 'N/A' ? null : $_POST['cc2'],
            ($_POST['cc3'] ?? 'N/A') === 'N/A' ? null : $_POST['cc3'],
            $_POST['sqd0'] === 'N/A' ? null : $_POST['sqd0'],
            $_POST['sqd1'] === 'N/A' ? null : $_POST['sqd1'], $_POST['sqd2'] === 'N/A' ? null : $_POST['sqd2'],
            $_POST['sqd3'] === 'N/A' ? null : $_POST['sqd3'], $_POST['sqd4'] === 'N/A' ? null : $_POST['sqd4'],
            $_POST['sqd5'] === 'N/A' ? null : $_POST['sqd5'], $_POST['sqd6'] === 'N/A' ? null : $_POST['sqd6'],
            $_POST['sqd7'] === 'N/A' ? null : $_POST['sqd7'], $_POST['sqd8'] === 'N/A' ? null : $_POST['sqd8'],
            trim($_POST['suggestions']), trim($_POST['email_address'])
        ];
        if ($stmt->execute($params)) { echo json_encode(['success' => true, 'message' => 'Thank you for your feedback!']); }
        else { echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']); }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eCSM - Client Satisfaction Measurement</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root { --custom-blue: #002366; }
        body { display: flex; flex-direction: column; background-color: #f0f2f5; font-family: 'Poppins', sans-serif; }
        .header-bar { background-color: var(--custom-blue); color: white; padding: 1rem; }
        .header-bar img { max-height: 80px; }
        .main-content { flex: 1 0 auto; }
        .main-title-section { padding: 2.5rem 1rem; background-color: white; border-bottom: 1px solid #dee2e6; }
        #department-selection { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; padding: 2rem; }
        .dept-button { background-color: var(--custom-blue); border: none; border-radius: 12px; padding: 1.5rem; font-size: 1.1rem; font-weight: 600; color: white; text-align: center; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .dept-button:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0, 35, 102, 0.2); color: white; }
        .modal-header { background-color: var(--custom-blue); color: white; }
        .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        #thankYouMessage, #kiosk-start-button { display: none; }
        .form-check-label.affiliation { width: 100%; text-align: center; padding: 1rem; border: 1px solid #dee2e6; cursor: pointer; border-radius: 8px; font-weight: 500; }
        .form-check-input:checked + .form-check-label.affiliation { background-color: #0d6efd; color: white; border-color: #0d6efd; }
        .footer { flex-shrink: 0; }
    </style>
</head>
<body class="d-flex flex-column h-100" <?php if ($is_kiosk_mode) echo "data-kiosk-mode='true' data-dept-id='{$kiosk_dept_id}' data-dept-name='" . e($kiosk_dept_name) . "'"; ?>>

    <div class="main-content">
        <header class="header-bar text-center">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-2 text-md-start"><img src="img/<?php echo e($CONFIG['agency_logo']); ?>" alt="Agency Logo" onerror="this.style.display='none'"></div>
                    <div class="col-md-8"><p class="mb-0">Republic of the Philippines</p><h5><?php echo e(strtoupper($CONFIG['agency_name'])); ?></h5><p class="mb-0"><?php echo e($CONFIG['province_name']); ?>, <?php echo e($CONFIG['region_name']); ?></p></div>
                    <div class="col-md-2"></div>
                </div>
            </div>
        </header>

        <main class="container-fluid p-0">
            <div class="main-title-section text-center">
                <h3 class="fw-bold">Client Satisfaction Measurement (CSM)</h3>
                <p class="lead text-muted" id="page-subtitle">Your feedback on your recently concluded transaction will help this office provide a better service.</p>
            </div>

            <div id="thankYouMessage" class="alert alert-success text-center container mt-4 animate__animated">
                <h4>Thank You!</h4>
                <p>Your feedback has been successfully submitted.</p>
            </div>

            <?php if ($is_kiosk_mode): ?>
                <div class="text-center p-4 d-flex align-items-center justify-content-center">
                    <button id="kiosk-start-button" class="btn btn-primary btn-lg">Click Here to Leave Feedback</button>
                </div>
            <?php else: ?>
                <div id="department-selection" class="container">
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                            <button class="dept-button" data-bs-toggle="modal" data-bs-target="#csmModal" data-dept-id="<?php echo e($dept['id']); ?>" data-dept-name="<?php echo e($dept['name']); ?>">
                                <?php echo e($dept['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">No departments have been configured in the system yet.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <!-- CSM Modal -->
    <div class="modal fade" id="csmModal" tabindex="-1" aria-labelledby="csmModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form id="csmForm">
                    <input type="hidden" name="department_id" id="form_department_id">
                    <input type="hidden" name="service_id" id="form_service_id">
                    <input type="hidden" name="action" value="submit_feedback">
                    <div class="modal-header"><h5 class="modal-title" id="csmModalLabel">Feedback Form for <span id="modalDeptName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body"><!-- Wizard steps will be injected here --></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" id="btnBack" style="display:none;">Back</button><button type="button" class="btn btn-primary" id="btnNext">Next</button><button type="submit" class="btn btn-danger" id="btnSubmit" style="display:none;">Submit Feedback</button></div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Service Info Modal -->
    <div class="modal fade" id="serviceInfoModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Service Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="serviceInfoBody"></div></div></div></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentStep = 1;
        const csmModalEl = document.getElementById('csmModal');
        const csmModal = new bootstrap.Modal(csmModalEl);
        const serviceInfoModal = new bootstrap.Modal(document.getElementById('serviceInfoModal'));
        const body = $('body');
        const isKiosk = body.data('kiosk-mode') === true;

        function getWizardHTML() {
            const regions = ["National Capital Region (NCR)", "Cordillera Administrative Region (CAR)", "Ilocos Region (Region I)", "Cagayan Valley (Region II)", "Central Luzon (Region III)", "Calabarzon (Region IV-A)", "Mimaropa (Region IV-B)", "Bicol Region (Region V)", "Western Visayas (Region VI)", "Central Visayas (Region VII)", "Eastern Visayas (Region VIII)", "Zamboanga Peninsula (Region IX)", "Northern Mindanao (Region X)", "Davao Region (Region XI)", "Soccsksargen (Region XII)", "Caraga (Region XIII)", "Bangsamoro (BARMM)"];
            let regionOptions = '<option value="">Please select...</option>';
            regions.forEach(region => { const selected = (region === "Northern Mindanao (Region X)") ? 'selected' : ''; regionOptions += `<option value="${region}" ${selected}>${region}</option>`; });
            const sqdQuestions = ["SQD0. I am satisfied with the service that I availed.", "SQD1. I spent a reasonable amount of time for my transaction.", "SQD2. The office followed the transaction's requirements and steps based on the information provided.", "SQD3. The steps (including payment) I needed to do for my transaction were easy and simple.", "SQD4. I could easily find information about my transaction from the office or its website.", "SQD5. I paid a reasonable amount of fees for my transaction.", "SQD6. I feel the office was fair to everyone, or 'walang palakasan', during my transaction.", "SQD7. I was treated courteously by the staff, and the staff I approached for help were helpful.", "SQD8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me."];
            let sqdRows = '';
            sqdQuestions.forEach((question, index) => { sqdRows += `<tr><td class="text-start">${question}</td><td><input class="form-check-input" type="radio" name="sqd${index}" value="1" required></td><td><input class="form-check-input" type="radio" name="sqd${index}" value="2"></td><td><input class="form-check-input" type="radio" name="sqd${index}" value="3"></td><td><input class="form-check-input" type="radio" name="sqd${index}" value="4"></td><td><input class="form-check-input" type="radio" name="sqd${index}" value="5"></td><td><input class="form-check-input" type="radio" name="sqd${index}" value="N/A"></td></tr>`; });
            return `
                <div class="wizard-step" id="step1"><h5>Step 1: Service Selection</h5><div class="mb-3"><label class="form-label fw-bold">Are you an Internal or External client?</label><div class="row"><div class="col-6"><input type="radio" class="form-check-input visually-hidden" name="affiliation" value="Internal" id="affiliationInternal" required><label class="form-check-label affiliation" for="affiliationInternal">Internal (Employee)</label></div><div class="col-6"><input type="radio" class="form-check-input visually-hidden" name="affiliation" value="External" id="affiliationExternal" required><label class="form-check-label affiliation" for="affiliationExternal">External (Citizen/Business)</label></div></div></div><div id="service-list-container" class="mt-3" style="display:none;"><label class="form-label fw-bold">Which service/s did you avail?</label><div id="service-list" class="list-group"></div></div></div>
                <div class="wizard-step" id="step2" style="display:none;"><h5>Step 2: Your Information</h5><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Client Type</label><div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="client_type" id="typeCitizen" value="Citizen" required><label class="form-check-label" for="typeCitizen">Citizen</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="client_type" id="typeBusiness" value="Business"><label class="form-check-label" for="typeBusiness">Business</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="client_type" id="typeGovernment" value="Government"><label class="form-check-label" for="typeGovernment">Government</label></div></div></div><div class="col-md-6 mb-3"><label class="form-label">Sex</label><div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sex" id="sexMale" value="Male" required><label class="form-check-label" for="sexMale">Male</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="sex" id="sexFemale" value="Female"><label class="form-check-label" for="sexFemale">Female</label></div></div></div><div class="col-md-6 mb-3"><label for="age" class="form-label">Age</label><input type="number" class="form-control" id="age" name="age" min="1" max="120"></div><div class="col-md-6 mb-3"><label for="region_of_residence" class="form-label">Region of Residence</label><select class="form-select" name="region_of_residence" id="region_of_residence">${regionOptions}</select></div><div class="col-md-6 mb-3"><label for="email_address" class="form-label">Email Address (Optional)</label><input type="email" class="form-control" id="email_address" name="email_address"></div><div class="col-md-6 mb-3"><label for="ref_id" class="form-label">Reference ID (Optional)</label><input type="text" class="form-control" id="ref_id" name="ref_id"></div></div></div>
                <div class="wizard-step" id="step3" style="display:none;"><h5>Step 3: Survey Proper</h5><div class="card mb-3"><div class="card-header fw-bold">Citizen's Charter (CC)</div><div class="card-body"><p class="fw-bold">CC1. Which of the following best describes your awareness of a CC?</p><div class="form-check"><input class="form-check-input" type="radio" name="cc1" id="cc1_opt1" value="1" required><label class="form-check-label" for="cc1_opt1">1. I know what a CC is and I saw this office's CC.</label></div><div class="form-check"><input class="form-check-input" type="radio" name="cc1" id="cc1_opt2" value="2"><label class="form-check-label" for="cc1_opt2">2. I know what a CC is but I did NOT see this office's CC.</label></div><div class="form-check"><input class="form-check-input" type="radio" name="cc1" id="cc1_opt3" value="3"><label class="form-check-label" for="cc1_opt3">3. I learned of the CC only when I saw this office's CC.</label></div><div class="form-check"><input class="form-check-input" type="radio" name="cc1" id="cc1_opt4" value="4"><label class="form-check-label" for="cc1_opt4">4. I do not know what a CC is and I did not see one in this office.</label></div><hr><p class="fw-bold">CC2. If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was...?</p><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc2" id="cc2_opt1" value="1" required><label class="form-check-label" for="cc2_opt1">1. Easy to see</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc2" id="cc2_opt2" value="2"><label class="form-check-label" for="cc2_opt2">2. Somewhat easy to see</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc2" id="cc2_opt3" value="3"><label class="form-check-label" for="cc2_opt3">3. Difficult to see</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc2" id="cc2_opt4" value="4"><label class="form-check-label" for="cc2_opt4">4. Not visible at all</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc2" id="cc2_opt5" value="N/A"><label class="form-check-label" for="cc2_opt5">5. N/A</label></div><hr><p class="fw-bold">CC3. If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</p><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc3" id="cc3_opt1" value="1" required><label class="form-check-label" for="cc3_opt1">1. Helped very much</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc3" id="cc3_opt2" value="2"><label class="form-check-label" for="cc3_opt2">2. Somewhat helped</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc3" id="cc3_opt3" value="3"><label class="form-check-label" for="cc3_opt3">3. Did not help</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cc3" id="cc3_opt4" value="N/A"><label class="form-check-label" for="cc3_opt4">4. N/A</label></div></div></div><div class="card"><div class="card-header fw-bold">Service Quality Dimensions (SQD)</div><div class="card-body table-responsive"><table class="table table-bordered text-center align-middle"><thead class="table-light"><tr><th>Question</th><th>Strongly<br>Disagree</th><th>Disagree</th><th>Neither Agree<br>nor Disagree</th><th>Agree</th><th>Strongly<br>Agree</th><th>N/A</th></tr></thead><tbody>${sqdRows}</tbody></table><div class="mt-3"><label for="suggestions" class="form-label">Suggestions on how we can further improve our services (optional):</label><textarea class="form-control" name="suggestions" id="suggestions" rows="3"></textarea></div></div></div></div>`;
        }

        function updateWizard() {
            $('.wizard-step').hide(); $('#step' + currentStep).show();
            $('#btnBack').toggle(currentStep > 1); $('#btnNext').toggle(currentStep < 3); $('#btnSubmit').toggle(currentStep === 3);
        }

        csmModalEl.addEventListener('show.bs.modal', function(event) {
            const button = $(event.relatedTarget); const deptId = button.data('dept-id'); const deptName = button.data('dept-name');
            $('#csmForm')[0].reset(); $('#csmModal .modal-body').html(getWizardHTML());
            currentStep = 1; updateWizard();
            $('#form_department_id').val(deptId); $('#modalDeptName').text(deptName);
        });

        if (isKiosk) {
            const kioskDeptId = body.data('dept-id'); const kioskDeptName = body.data('dept-name');
            $('#page-subtitle').text('Please leave your feedback for the ' + kioskDeptName);
            $('#kiosk-start-button').show().on('click', function() {
                $(this).attr({'data-bs-toggle': 'modal', 'data-bs-target': '#csmModal'}).data({'dept-id': kioskDeptId, 'dept-name': kioskDeptName});
                csmModal.show($(this)[0]);
            });
        }

        $(document).on('change', 'input[name="affiliation"]', function() {
            const affiliation = $(this).val(); const deptId = $('#form_department_id').val();
            $('#service-list-container').show(); $('#service-list').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            $.post('client.php', { action: 'get_services', department_id: deptId, affiliation: affiliation }, function(response) {
                if (response.success) {
                    let serviceHtml = '';
                    if(response.services.length > 0){ response.services.forEach(function(service) { serviceHtml += `<a href="#" class="list-group-item list-group-item-action service-item" data-service-id="${service.id}">${service.service_name}</a>`; });
                    } else { serviceHtml = '<div class="list-group-item">No services found for this selection.</div>'; }
                    $('#service-list').html(serviceHtml);
                }
            }, 'json');
        });
        
        $(document).on('click', '.service-item', function(e) {
            e.preventDefault(); $('.service-item').removeClass('active'); $(this).addClass('active');
            $('#form_service_id').val($(this).data('service-id'));
        });

        $(document).on('change', 'input[name="cc1"]', function() {
            const cc2Radios = $('input[name="cc2"]'); const cc3Radios = $('input[name="cc3"]');
            if ($(this).val() == '4') {
                cc2Radios.prop('disabled', true).prop('checked', false); cc3Radios.prop('disabled', true).prop('checked', false);
                $('input[name="cc2"][value="N/A"]').prop('checked', true); $('input[name="cc3"][value="N/A"]').prop('checked', true);
            } else {
                 cc2Radios.prop('disabled', false); cc3Radios.prop('disabled', false);
                 $('input[name="cc2"][value="N/A"]').prop('checked', false); $('input[name="cc3"][value="N/A"]').prop('checked', false);
            }
        });

        $('#btnNext').on('click', function() { if (currentStep === 1 && !$('#form_service_id').val()) { alert('Please select a service.'); return; } if (currentStep < 3) { currentStep++; updateWizard(); } });
        $('#btnBack').on('click', function() { if (currentStep > 1) { currentStep--; updateWizard(); } });

        $('#csmForm').on('submit', function(e) {
            e.preventDefault();
            $('#btnSubmit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
            $.post('client.php', $(this).serialize(), function(response) {
                if (response.success) {
                    csmModal.hide(); $('#thankYouMessage').addClass('animate__tada').show();
                    if (isKiosk) { setTimeout(function() { $('#thankYouMessage').removeClass('animate__tada').fadeOut(); }, 5000); }
                    else { $('html, body').animate({ scrollTop: 0 }, 'slow'); }
                } else { alert('Error: ' + response.message); }
            }, 'json').fail(function() { alert('A server error occurred.'); })
            .always(function() { $('#btnSubmit').prop('disabled', false).text('Submit Feedback'); });
        });
    });
    </script>
</body>
</html>
