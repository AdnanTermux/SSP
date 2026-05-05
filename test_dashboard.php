<?php
/**
 * Sigma SMS A2P — Test Dashboard
 * Separate dashboard for test users
 */
session_start();

// Check if test user is logged in
if (!isset($_SESSION['test_user_id'])) {
    header('Location: test_login.php');
    exit;
}

require_once __DIR__ . '/config.php';

$testUser = $_SESSION['test_username'];
$pdo = getDB();

// Get test user's allocated numbers
$stmt = $pdo->prepare("
    SELECT * FROM test_user_numbers 
    WHERE test_username = ? 
    ORDER BY allocated_at DESC
");
$stmt->execute([$testUser]);
$allocatedNumbers = $stmt->fetchAll();

// Get test user's limit
$stmt = $pdo->prepare("SELECT number_limit FROM test_users WHERE username = ?");
$stmt->execute([$testUser]);
$userLimit = $stmt->fetchColumn() ?: 10; // Default 10

$remainingSlots = $userLimit - count($allocatedNumbers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dashboard — Sigma SMS A2P</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .test-navbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }
        .test-navbar .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
        }
        .test-navbar .btn-logout {
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.3);
            color: white;
            padding: .4rem 1rem;
            border-radius: 8px;
            transition: all .2s;
        }
        .test-navbar .btn-logout:hover {
            background: rgba(255,255,255,.3);
        }
        .container {
            max-width: 1400px;
            padding-top: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 1.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        .stat-label {
            color: #718096;
            font-size: .9rem;
            margin-top: .25rem;
        }
        .number-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all .2s;
            cursor: pointer;
        }
        .number-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102,126,234,.15);
            transform: translateY(-2px);
        }
        .number-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff, #faf5ff);
        }
        .number-text {
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            color: #1a202c;
        }
        .otp-card {
            background: white;
            border-left: 4px solid #48bb78;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: .75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .otp-code {
            font-size: 1.5rem;
            font-weight: 700;
            color: #48bb78;
            font-family: 'Courier New', monospace;
        }
        .masked-text {
            color: #a0aec0;
            font-family: monospace;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: .6rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,.3);
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 1rem;
        }
        .badge-limit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: .4rem .8rem;
            border-radius: 8px;
            font-size: .85rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="test-navbar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="navbar-brand">
                    <i class="ri-test-tube-line me-2"></i>Test Panel
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-white">
                        <i class="ri-user-line me-1"></i><?= htmlspecialchars($testUser) ?>
                    </span>
                    <a href="test_logout.php" class="btn btn-logout">
                        <i class="ri-logout-circle-line me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= count($allocatedNumbers) ?></div>
                    <div class="stat-label">Allocated Numbers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= $remainingSlots ?></div>
                    <div class="stat-label">Remaining Slots</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value" id="statOTPs">0</div>
                    <div class="stat-label">OTPs Received</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= $userLimit ?></div>
                    <div class="stat-label">Total Limit</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Number Management -->
            <div class="col-lg-5">
                <!-- Allocate Numbers -->
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">
                            <i class="ri-add-circle-line me-2"></i>Allocate Numbers
                        </h5>
                        <span class="badge-limit">
                            <?= $remainingSlots ?> / <?= $userLimit ?> available
                        </span>
                    </div>

                    <?php if ($remainingSlots > 0): ?>
                    <div class="mb-3">
                        <button class="btn btn-primary w-100" onclick="showAvailableNumbers()">
                            <i class="ri-search-line me-2"></i>Browse Available Numbers
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="ri-error-warning-line me-2"></i>
                        You've reached your limit of <?= $userLimit ?> numbers. 
                        Release some numbers to allocate new ones.
                    </div>
                    <?php endif; ?>

                    <!-- Available Numbers Modal Content -->
                    <div id="availableNumbersSection" style="display:none;">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchNumber" 
                                   placeholder="Search by country or service..." 
                                   onkeyup="filterAvailableNumbers()">
                        </div>
                        <div id="availableNumbersList" style="max-height:400px;overflow-y:auto;">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Allocated Numbers -->
                <div class="card stat-card mt-3">
                    <h5 class="section-title">
                        <i class="ri-phone-line me-2"></i>My Numbers
                    </h5>
                    <div id="myNumbersList">
                        <?php if (empty($allocatedNumbers)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="ri-inbox-line" style="font-size:2rem;opacity:.3;"></i>
                            <p class="mt-2">No numbers allocated yet</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($allocatedNumbers as $num): ?>
                        <div class="number-card" onclick="selectNumber('<?= htmlspecialchars($num['number']) ?>')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="number-text"><?= htmlspecialchars($num['number']) ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($num['country']) ?> • 
                                        <?= htmlspecialchars($num['service']) ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="event.stopPropagation();releaseNumber('<?= htmlspecialchars($num['number']) ?>')">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Live OTPs -->
            <div class="col-lg-7">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="section-title mb-0">
                            <i class="ri-message-2-line me-2"></i>Live OTPs (Hidden)
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshOTPs()">
                            <i class="ri-refresh-line me-1"></i>Refresh
                        </button>
                    </div>

                    <div class="alert alert-info small">
                        <i class="ri-eye-off-line me-2"></i>
                        <strong>Privacy Mode:</strong> Service names and messages are masked. 
                        Click "View" to see full details.
                    </div>

                    <div id="otpsList" style="max-height:600px;overflow-y:auto;">
                        <div class="text-center text-muted py-4">
                            <i class="ri-inbox-line me-1"></i>No OTPs received yet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- OTP Detail Modal -->
    <div class="modal fade" id="otpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">OTP Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small text-muted">Number</label>
                        <div class="fw-bold" id="modalNumber"></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Service</label>
                        <div id="modalService"></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">OTP Code</label>
                        <div class="otp-code" id="modalOTP"></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Message</label>
                        <div class="small" id="modalMessage"></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Received</label>
                        <div class="small" id="modalTime"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="copyOTP()">
                        <i class="ri-file-copy-line me-1"></i>Copy OTP
                    </button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    let selectedNumber = null;
    let currentOTP = null;
    let autoRefresh = null;

    // Show available numbers
    function showAvailableNumbers() {
        $('#availableNumbersSection').show();
        loadAvailableNumbers();
    }

    // Load available numbers
    function loadAvailableNumbers() {
        $.getJSON('ajax/get_available_test_numbers.php', function(d) {
            if (d.status === 'success' && d.numbers) {
                let html = '';
                d.numbers.forEach(function(num) {
                    html += `
                        <div class="number-card" onclick="allocateNumber('${num.number}', '${num.country}', '${num.service}')">
                            <div class="number-text">${num.number}</div>
                            <small class="text-muted">${num.country} • ${num.service}</small>
                        </div>
                    `;
                });
                $('#availableNumbersList').html(html);
            }
        });
    }

    // Allocate number
    function allocateNumber(number, country, service) {
        if (!confirm('Allocate this number: ' + number + '?')) return;
        
        $.post('ajax/allocate_test_number.php', {
            number: number,
            country: country,
            service: service
        }, function(d) {
            if (d.status === 'success') {
                location.reload();
            } else {
                alert(d.message || 'Failed to allocate number');
            }
        }, 'json');
    }

    // Release number
    function releaseNumber(number) {
        if (!confirm('Release this number: ' + number + '?')) return;
        
        $.post('ajax/release_test_number.php', {
            number: number
        }, function(d) {
            if (d.status === 'success') {
                location.reload();
            } else {
                alert(d.message || 'Failed to release number');
            }
        }, 'json');
    }

    // Select number
    function selectNumber(number) {
        selectedNumber = number;
        $('.number-card').removeClass('selected');
        event.currentTarget.classList.add('selected');
    }

    // Refresh OTPs
    function refreshOTPs() {
        $.getJSON('ajax/get_test_user_otps.php', function(d) {
            if (d.status === 'success' && d.otps && d.otps.length > 0) {
                let html = '';
                d.otps.forEach(function(otp) {
                    html += `
                        <div class="otp-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <code class="text-primary">${otp.number}</code>
                                        <span class="badge bg-info">${otp.service_masked}</span>
                                        <small class="text-muted">${otp.time_ago}</small>
                                    </div>
                                    <div class="otp-code">${otp.otp}</div>
                                    <div class="masked-text small mt-1">${otp.message_preview}</div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick='showOTPDetail(${JSON.stringify(otp)})'>
                                    <i class="ri-eye-line"></i> View
                                </button>
                            </div>
                        </div>
                    `;
                });
                $('#otpsList').html(html);
                $('#statOTPs').text(d.otps.length);
            } else {
                $('#otpsList').html('<div class="text-center text-muted py-4"><i class="ri-inbox-line me-1"></i>No OTPs received yet</div>');
            }
        });
    }

    // Show OTP detail
    function showOTPDetail(otp) {
        $('#modalNumber').text(otp.number);
        $('#modalService').text(otp.service);
        $('#modalOTP').text(otp.otp);
        $('#modalMessage').text(otp.message);
        $('#modalTime').text(otp.received_at);
        currentOTP = otp.otp;
        new bootstrap.Modal(document.getElementById('otpModal')).show();
    }

    // Copy OTP
    function copyOTP() {
        if (!currentOTP) return;
        navigator.clipboard.writeText(currentOTP).then(function() {
            alert('OTP copied to clipboard!');
        });
    }

    // Filter available numbers
    function filterAvailableNumbers() {
        const search = $('#searchNumber').val().toLowerCase();
        $('.number-card').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(search));
        });
    }

    // Auto-refresh OTPs every 5 seconds
    $(document).ready(function() {
        refreshOTPs();
        autoRefresh = setInterval(refreshOTPs, 5000);
    });

    // Stop auto-refresh on page unload
    $(window).on('beforeunload', function() {
        if (autoRefresh) clearInterval(autoRefresh);
    });
    </script>
</body>
</html>
