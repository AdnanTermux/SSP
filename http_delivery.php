<?php
/**
 * Sigma SMS A2P — HTTP Delivery Configuration
 * Configure IP, Port, and HTTP delivery URL for SMS forwarding
 */
require_once __DIR__ . '/functions.php';
requireRole('admin');
$pageTitle = 'HTTP Delivery Settings';
$user = getCurrentUser();

$pdo = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $deliveryUrl = trim($_POST['delivery_url'] ?? '');
    $deliveryIp = trim($_POST['delivery_ip'] ?? '');
    $deliveryPort = trim($_POST['delivery_port'] ?? '');
    $deliveryEnabled = isset($_POST['delivery_enabled']) ? '1' : '0';
    $deliveryMethod = $_POST['delivery_method'] ?? 'POST';
    $deliveryFormat = $_POST['delivery_format'] ?? 'json';
    $deliveryAuth = trim($_POST['delivery_auth'] ?? '');
    
    // Validate URL
    if ($deliveryEnabled === '1' && !empty($deliveryUrl)) {
        if (!filter_var($deliveryUrl, FILTER_VALIDATE_URL)) {
            flashMessage('danger', 'Invalid delivery URL format');
        } else {
            setSetting('http_delivery_url', $deliveryUrl);
            setSetting('http_delivery_ip', $deliveryIp);
            setSetting('http_delivery_port', $deliveryPort);
            setSetting('http_delivery_enabled', $deliveryEnabled);
            setSetting('http_delivery_method', $deliveryMethod);
            setSetting('http_delivery_format', $deliveryFormat);
            setSetting('http_delivery_auth', $deliveryAuth);
            flashMessage('success', 'HTTP delivery settings saved successfully');
            redirect(APP_URL . '/http_delivery.php');
        }
    } else {
        setSetting('http_delivery_enabled', '0');
        flashMessage('info', 'HTTP delivery disabled');
        redirect(APP_URL . '/http_delivery.php');
    }
}

// Get current settings
$deliveryUrl = getSetting('http_delivery_url', '');
$deliveryIp = getSetting('http_delivery_ip', '');
$deliveryPort = getSetting('http_delivery_port', '8080');
$deliveryEnabled = getSetting('http_delivery_enabled', '0');
$deliveryMethod = getSetting('http_delivery_method', 'POST');
$deliveryFormat = getSetting('http_delivery_format', 'json');
$deliveryAuth = getSetting('http_delivery_auth', '');

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="animate-in"><i class="ri-send-plane-line me-2"></i>HTTP Delivery Settings</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">HTTP Delivery</li>
      </ol>
    </nav>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card animate-in">
      <div class="card-header">
        <i class="ri-settings-3-line me-2"></i>Configure HTTP Delivery
      </div>
      <div class="card-body">
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show">
          <?= h($flash['msg']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="deliveryEnabled" name="delivery_enabled" <?= $deliveryEnabled === '1' ? 'checked' : '' ?>>
              <label class="form-check-label" for="deliveryEnabled">
                <strong>Enable HTTP Delivery</strong>
                <small class="d-block text-muted">Forward received SMS to external HTTP endpoint</small>
              </label>
            </div>
          </div>

          <hr class="my-4">

          <div class="row">
            <div class="col-md-8 mb-3">
              <label class="form-label" for="deliveryUrl">
                <i class="ri-global-line me-1"></i>Delivery URL <span class="text-danger">*</span>
              </label>
              <input type="url" class="form-control" id="deliveryUrl" name="delivery_url" 
                     value="<?= h($deliveryUrl) ?>" placeholder="https://your-server.com/api/sms/receive" required>
              <small class="text-muted">Full HTTP/HTTPS URL where SMS will be posted</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label" for="deliveryMethod">
                <i class="ri-arrow-up-down-line me-1"></i>HTTP Method
              </label>
              <select class="form-select" id="deliveryMethod" name="delivery_method">
                <option value="POST" <?= $deliveryMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                <option value="GET" <?= $deliveryMethod === 'GET' ? 'selected' : '' ?>>GET</option>
                <option value="PUT" <?= $deliveryMethod === 'PUT' ? 'selected' : '' ?>>PUT</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8 mb-3">
              <label class="form-label" for="deliveryIp">
                <i class="ri-server-line me-1"></i>Server IP Address
              </label>
              <input type="text" class="form-control" id="deliveryIp" name="delivery_ip" 
                     value="<?= h($deliveryIp) ?>" placeholder="192.168.1.100 or leave empty">
              <small class="text-muted">Optional: Specific IP to connect to (overrides URL hostname)</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label" for="deliveryPort">
                <i class="ri-plug-line me-1"></i>Port
              </label>
              <input type="number" class="form-control" id="deliveryPort" name="delivery_port" 
                     value="<?= h($deliveryPort) ?>" placeholder="8080" min="1" max="65535">
              <small class="text-muted">Default: 80 (HTTP) or 443 (HTTPS)</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="deliveryFormat">
                <i class="ri-code-box-line me-1"></i>Data Format
              </label>
              <select class="form-select" id="deliveryFormat" name="delivery_format">
                <option value="json" <?= $deliveryFormat === 'json' ? 'selected' : '' ?>>JSON</option>
                <option value="form" <?= $deliveryFormat === 'form' ? 'selected' : '' ?>>Form Data (URL Encoded)</option>
                <option value="xml" <?= $deliveryFormat === 'xml' ? 'selected' : '' ?>>XML</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label" for="deliveryAuth">
                <i class="ri-key-line me-1"></i>Authorization Header
              </label>
              <input type="text" class="form-control" id="deliveryAuth" name="delivery_auth" 
                     value="<?= h($deliveryAuth) ?>" placeholder="Bearer your-token-here">
              <small class="text-muted">Optional: e.g., Bearer token or Basic auth</small>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-line me-1"></i>Save Settings
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="testDelivery()">
              <i class="ri-test-tube-line me-1"></i>Test Connection
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delivery Log -->
    <div class="card mt-3 animate-in" style="--delay:.1s">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="ri-history-line me-2"></i>Recent Delivery Attempts</span>
        <button class="btn btn-sm btn-outline-primary" onclick="loadDeliveryLog()">
          <i class="ri-refresh-line me-1"></i>Refresh
        </button>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover" id="deliveryLogTable">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Number</th>
                <th>Status</th>
                <th>Response</th>
              </tr>
            </thead>
            <tbody id="deliveryLogBody">
              <tr>
                <td colspan="4" class="text-center text-muted py-3">
                  <i class="ri-inbox-line me-1"></i>No delivery attempts yet
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Info Card -->
    <div class="card animate-in" style="--delay:.15s">
      <div class="card-header bg-primary text-white">
        <i class="ri-information-line me-2"></i>How It Works
      </div>
      <div class="card-body">
        <h6 class="fw-semibold mb-2">Payload Structure</h6>
        <p class="small text-muted mb-3">When SMS is received, we'll send:</p>
        
        <div class="bg-light p-3 rounded mb-3" style="font-size:.8rem;">
          <strong>JSON Format:</strong>
          <pre class="mb-0 mt-2" style="font-size:.75rem;"><code>{
  "number": "+1234567890",
  "service": "WhatsApp",
  "country": "US",
  "otp": "123456",
  "message": "Your code is 123456",
  "received_at": "2026-05-05 10:30:00"
}</code></pre>
        </div>

        <div class="alert alert-info small mb-0">
          <i class="ri-lightbulb-line me-1"></i>
          <strong>Tip:</strong> Use the Test Connection button to verify your endpoint is reachable before enabling delivery.
        </div>
      </div>
    </div>

    <!-- Stats Card -->
    <div class="card mt-3 animate-in" style="--delay:.2s">
      <div class="card-header">
        <i class="ri-bar-chart-box-line me-2"></i>Delivery Statistics
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Total Sent:</span>
          <strong id="statTotalSent">0</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Success:</span>
          <strong class="text-success" id="statSuccess">0</strong>
        </div>
        <div class="d-flex justify-content-between">
          <span class="text-muted">Failed:</span>
          <strong class="text-danger" id="statFailed">0</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function testDelivery() {
    const url = document.getElementById('deliveryUrl').value;
    if (!url) {
        alert('Please enter a delivery URL first');
        return;
    }
    
    if (!confirm('Send a test SMS to ' + url + '?')) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
    
    $.ajax({
        url: '<?= APP_URL ?>/ajax/test_http_delivery.php',
        method: 'POST',
        data: {
            csrf_token: '<?= h(csrfToken()) ?>',
            test_url: url
        },
        dataType: 'json'
    }).done(function(d) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-test-tube-line me-1"></i>Test Connection';
        if (d.status === 'success') {
            showToast('✅ Test successful! Response: ' + (d.response_code || 200), 'success');
        } else {
            showToast('❌ Test failed: ' + (d.message || 'Unknown error'), 'danger');
        }
    }).fail(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-test-tube-line me-1"></i>Test Connection';
        showToast('❌ Test request failed', 'danger');
    });
}

function loadDeliveryLog() {
    $.getJSON('<?= APP_URL ?>/ajax/http_delivery_log.php', function(d) {
        if (d.status === 'success' && d.logs && d.logs.length > 0) {
            let html = '';
            d.logs.forEach(function(log) {
                const statusBadge = log.success ? 
                    '<span class="badge bg-success">Success</span>' : 
                    '<span class="badge bg-danger">Failed</span>';
                html += '<tr>';
                html += '<td><small>' + log.timestamp + '</small></td>';
                html += '<td><code>' + log.number + '</code></td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td><small class="text-muted">' + (log.response || '–') + '</small></td>';
                html += '</tr>';
            });
            $('#deliveryLogBody').html(html);
        }
        
        if (d.stats) {
            $('#statTotalSent').text(d.stats.total || 0);
            $('#statSuccess').text(d.stats.success || 0);
            $('#statFailed').text(d.stats.failed || 0);
        }
    });
}

// Load log on page load
$(document).ready(function() {
    loadDeliveryLog();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
