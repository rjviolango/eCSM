<?php
// eCSM - ARTA-2242-3
// logs.php - Included by admin.php to view system logs.

if (!is_admin()) {
    echo '<div class="alert alert-danger">Access Denied.</div>';
    return;
}

$logs = $pdo->query("SELECT sl.*, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id ORDER BY sl.timestamp DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-journal-text"></i> System Logs</h5>
    </div>
    <div class="card-body">
        <?php if (count($logs) > 0): ?>
        <div class="table-responsive">
            <table id="datatable" class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Action</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo convert_to_user_timezone($log['timestamp'], 'M d, Y, h:i A'); ?></td>
                            <td><?php echo e($log['username'] ?? 'N/A'); ?></td>
                            <td><?php echo e($log['ip_address']); ?></td>
                            <td><?php echo e($log['action']); ?></td>
                            <td><?php echo e($log['user_agent']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info">No logs found.</div>
        <?php endif; ?>
    </div>
</div>
