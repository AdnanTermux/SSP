<?php
/**
 * Sigma SMS A2P — Test Panel
 * Real testing with available numbers from ranges
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Test Panel';
$user = getCurrentUser();
$role = $user['role'];
$userId = (int)$user['id'];

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="animate-in"><i class="ri-test-tube-line me-2"></i>Test Panel</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Test Panel</li>
      </ol>
    </nav>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <!-- Available Test Numbers -->
    <div class="card animate-in">
      <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="ri-phone-line me-2"></i>Available Test Numbers</span>
        <button class="btn btn-sm btn-light" onclick="loadTestNumbers()">
          <i class="ri-refresh-line me-1"></i>Refresh
        </button>
      </div>
      <div class="card-body">
        <div class="alert alert-info">
          <i class="ri-information-line me-2"></i>
          <strong>How to test:</strong> Select a country and service to see available test numbers. 
          Click on a number to use it for testing. Send OTP to that number and it will appear below.
        </div>

        <!-- Filters -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label" for="filterCountry">
              <i class="ri-global-line me-1"></i>Country
            </label>
            <select class="form-select" id="filterCountry" onchange="loadTestNumbers()">
              <option value="">All Countries</option>
              <?php foreach (allCountries() as $code => $name): ?>
              <option value="<?= h($code) ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="filterService">
              <i class="ri-apps-line me-1"></i>Service
            </label>
            <select class="form-select" id="filterService" onchange="loadTestNumbers()">
              <option value="">All Services</option>
              <option value="WhatsApp">WhatsApp</option>
              <option value="Telegram">Telegram</option>
              <option value="Facebook">Facebook</option>
              <option value="Google">Google</option>
              <option value="Instagram">Instagram</option>
              <option value="Twitter">Twitter</option>
              <option value="TikTok">TikTok</option>
              <option value="Uber">Uber</option>
              <option value="Amazon">Amazon</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="filterLimit">
              <i class="ri-list-check me-1"></i>Show Numbers
            </label>
            <select class="form-select" id="filterLimit" onchange="loadTestNumbers()">
              <option value="10">10 Numbers</option>
              <option value="20">20 Numbers</option>
              <option value="50" selected>50 Numbers</option>
              <option value="100">100 Numbers</option>
            </select>
          </div>
        </div>

        <!-- Numbers Grid -->
        <div id="testNumbersGrid" class="row g-2">
          <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading available numbers...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Received OTPs -->
    <div class="card mt-3 animate-in" style="--delay:.1s">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <span><i class="ri-message-2-line me-2"></i>Received OTPs (Test Mode)</span>
        <div>
          <button class="btn btn-sm btn-light me-2" onclick="loadReceivedOTPs()">
            <i class="ri-refresh-line me-1"></i>Refresh
          </button>
          <button class="btn btn-sm btn-light" onclick="clearTestOTPs()">
            <i class="ri-delete-bin-line me-1"></i>Clear
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" id="receivedOTPsTable">
            <thead>
              <tr>
                <th>Time</th>
                <th>Number</th>
                <th>Service</th>
                <th>OTP Code</th>
                <th>Message Preview</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="receivedOTPsBody">
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  <i class="ri-inbox-line me-1"></i>No OTPs received yet. Select a number and send test SMS.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Selected Number Info -->
    <div class="card animate-in" style="--delay:.15s;display:none;" id="selectedNumberCard">
      <div class="card-header bg-gradient-info text-white">
        <i class="ri-phone-fill me-2"></i>Selected Number
      </div>
      <div class="card-body">
        <div class="text-center mb-3">
          <div class="display-6 fw-bold text-primary" id="selectedNumber">-</div>
          <div class="text-muted small" id="selectedCountry">-</div>
        </div>

        <div class="alert alert-warning small mb-3">
          <i class="ri-error-warning-line me-1"></i>
          <strong>Instructions:</strong>
          <ol class="mb-0 mt-2 ps-3">
            <li>Copy the number above</li>
            <li>Go to the service (e.g., WhatsApp)</li>
            <li>Enter this number for verification</li>
            <li>Wait for OTP to appear below</li>
          </ol>
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-primary" onclick="copyNumber()">
            <i class="ri-file-copy-line me-1"></i>Copy Number
          </button>
          <button class="btn btn-outline-secondary" onclick="clearSelection()">
            <i class="ri-close-line me-1"></i>Clear Selection
          </button>
        </div>

        <hr>

        <div class="small">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Service:</span>
            <strong id="selectedService">-</strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Status:</span>
            <span class="badge bg-success" id="selectedStatus">Available</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Waiting for OTP:</span>
            <span id="waitingTime">0s</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics -->
    <div class="card mt-3 animate-in" style="--delay:.2s">
      <div class="card-header">
        <i class="ri-bar-chart-box-line me-2"></i>Test Statistics
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
          <span class="text-muted">Available Numbers:</span>
          <strong id="statAvailable">0</strong>
        </div>
        <div class="d-flex justify-content-between mb-3">
          <span class="text-muted">OTPs Received:</span>
          <strong class="text-success" id="statReceived">0</strong>
        </div>
        <div class="d-flex justify-content-between mb-3">
          <span class="text-muted">Active Tests:</span>
          <strong class="text-primary" id="statActive">0</strong>
        </div>
        <div class="d-flex justify-content-between">
          <span class="text-muted">Success Rate:</span>
          <strong class="text-info" id="statRate">0%</strong>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div class="card mt-3 animate-in" style="--delay:.25s">
      <div class="card-header bg-warning text-dark">
        <i class="ri-error-warning-line me-2"></i>Important Notes
      </div>
      <div class="card-body">
        <ul class="small mb-0">
          <li>Test numbers are real and functional</li>
          <li>OTPs are received in real-time</li>
          <li>Service names are masked for privacy (e.g., WHA****)</li>
          <li>Messages are partially hidden (******)</li>
          <li>Test data is cleared after 1 hour</li>
          <li>Maximum 10 active tests at once</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- OTP Detail Modal -->
<div class="modal fade" id="otpDetailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ri-message-2-line me-2"></i>OTP Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label text-muted small">Number</label>
          <div class="form-control-plaintext fw-bold" id="modalNumber">-</div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small">Service</label>
          <div class="form-control-plaintext" id="modalService">-</div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small">OTP Code</label>
          <div class="form-control-plaintext">
            <span class="badge bg-success fs-5" id="modalOTP">-</span>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small">Full Message</label>
          <div class="form-control-plaintext small" id="modalMessage">-</div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small">Received At</label>
          <div class="form-control-plaintext small" id="modalTime">-</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="copyOTP()">
          <i class="ri-file-copy-line me-1"></i>Copy OTP
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.number-card {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    cursor: pointer;
    transition: all .2s;
    background: #fff;
}
.number-card:hover {
    border-color: #4f46e5;
    box-shadow: 0 4px 12px rgba(79,70,229,.15);
    transform: translateY(-2px);
}
.number-card.selected {
    border-color: #4f46e5;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
}
.number-card .number {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0f172a;
    font-family: 'Courier New', monospace;
}
.number-card .service-badge {
    font-size: .7rem;
    padding: .2rem .5rem;
}
</style>

<script>
let selectedNumberData = null;
let waitingInterval = null;
let autoRefreshInterval = null;

// Load available test numbers
function loadTestNumbers() {
    const country = $('#filterCountry').val();
    const service = $('#filterService').val();
    const limit = $('#filterLimit').val();
    
    $('#testNumbersGrid').html('<div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>');
    
    $.ajax({
        url: '<?= APP_URL ?>/ajax/get_test_numbers.php',
        method: 'GET',
        data: { country, service, limit },
        dataType: 'json'
    }).done(function(d) {
        if (d.status === 'success' && d.numbers && d.numbers.length > 0) {
            let html = '';
            d.numbers.forEach(function(num) {
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="number-card" onclick='selectNumber(${JSON.stringify(num)})'>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="number">${num.number}</div>
                                <span class="badge bg-primary service-badge">${num.country}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">${num.service || 'Any'}</small>
                                <span class="badge bg-success" style="font-size:.65rem;">Available</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            $('#testNumbersGrid').html(html);
            $('#statAvailable').text(d.numbers.length);
        } else {
            $('#testNumbersGrid').html(`
                <div class="col-12 text-center py-5">
                    <i class="ri-inbox-line" style="font-size:3rem;opacity:.3;"></i>
                    <p class="text-muted mt-2">No test numbers available for selected filters</p>
                </div>
            `);
            $('#statAvailable').text('0');
        }
    }).fail(function() {
        $('#testNumbersGrid').html(`
            <div class="col-12 text-center py-4">
                <p class="text-danger">Failed to load numbers. Please try again.</p>
            </div>
        `);
    });
}

// Select a number for testing
function selectNumber(num) {
    selectedNumberData = num;
    
    // Update UI
    $('.number-card').removeClass('selected');
    event.currentTarget.classList.add('selected');
    
    $('#selectedNumber').text(num.number);
    $('#selectedCountry').text(num.country_name || num.country);
    $('#selectedService').text(num.service || 'Any');
    $('#selectedNumberCard').show();
    
    // Start waiting timer
    let seconds = 0;
    if (waitingInterval) clearInterval(waitingInterval);
    waitingInterval = setInterval(function() {
        seconds++;
        $('#waitingTime').text(seconds + 's');
    }, 1000);
    
    showToast('Number selected! Copy and use it for testing.', 'info');
}

// Copy number to clipboard
function copyNumber() {
    if (!selectedNumberData) return;
    
    navigator.clipboard.writeText(selectedNumberData.number).then(function() {
        showToast('✅ Number copied to clipboard!', 'success');
    }).catch(function() {
        // Fallback
        const temp = document.createElement('input');
        temp.value = selectedNumberData.number;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        showToast('✅ Number copied!', 'success');
    });
}

// Clear selection
function clearSelection() {
    selectedNumberData = null;
    $('.number-card').removeClass('selected');
    $('#selectedNumberCard').hide();
    if (waitingInterval) clearInterval(waitingInterval);
    $('#waitingTime').text('0s');
}

// Load received OTPs
function loadReceivedOTPs() {
    $.ajax({
        url: '<?= APP_URL ?>/ajax/get_test_otps.php',
        method: 'GET',
        dataType: 'json'
    }).done(function(d) {
        if (d.status === 'success' && d.otps && d.otps.length > 0) {
            let html = '';
            d.otps.forEach(function(otp) {
                html += `
                    <tr>
                        <td><small class="text-muted">${otp.time_ago}</small></td>
                        <td><code class="text-primary">${otp.number}</code></td>
                        <td><span class="badge bg-info">${otp.service_masked}</span></td>
                        <td><strong class="text-success fs-5">${otp.otp}</strong></td>
                        <td><small class="text-muted">${otp.message_preview}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='showOTPDetail(${JSON.stringify(otp)})'>
                                <i class="ri-eye-line"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#receivedOTPsBody').html(html);
            $('#statReceived').text(d.otps.length);
            
            // Update stats
            if (d.stats) {
                $('#statActive').text(d.stats.active || 0);
                $('#statRate').text((d.stats.success_rate || 0) + '%');
            }
        } else {
            $('#receivedOTPsBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="ri-inbox-line me-1"></i>No OTPs received yet
                    </td>
                </tr>
            `);
        }
    });
}

// Show OTP detail modal
function showOTPDetail(otp) {
    $('#modalNumber').text(otp.number);
    $('#modalService').text(otp.service);
    $('#modalOTP').text(otp.otp);
    $('#modalMessage').text(otp.message);
    $('#modalTime').text(otp.received_at);
    
    // Store for copy function
    window.currentOTP = otp.otp;
    
    new bootstrap.Modal(document.getElementById('otpDetailModal')).show();
}

// Copy OTP
function copyOTP() {
    if (!window.currentOTP) return;
    
    navigator.clipboard.writeText(window.currentOTP).then(function() {
        showToast('✅ OTP copied to clipboard!', 'success');
    });
}

// Clear test OTPs
function clearTestOTPs() {
    if (!confirm('Clear all received test OTPs?')) return;
    
    $.ajax({
        url: '<?= APP_URL ?>/ajax/clear_test_otps.php',
        method: 'POST',
        data: { csrf_token: '<?= h(csrfToken()) ?>' },
        dataType: 'json'
    }).done(function(d) {
        if (d.status === 'success') {
            loadReceivedOTPs();
            showToast('✅ Test OTPs cleared', 'success');
        }
    });
}

// Auto-refresh OTPs every 5 seconds
function startAutoRefresh() {
    autoRefreshInterval = setInterval(function() {
        loadReceivedOTPs();
    }, 5000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// Initialize
$(document).ready(function() {
    loadTestNumbers();
    loadReceivedOTPs();
    startAutoRefresh();
});

// Stop auto-refresh when leaving page
$(window).on('beforeunload', function() {
    stopAutoRefresh();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
