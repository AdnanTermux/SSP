<?php
/**
 * Sigma SMS A2P — Crypto Payment Requests
 * Users request payouts to their USDT TRC-20 or Binance ID wallets
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Payment Requests';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

// Get user's crypto wallets
$walletStmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE user_id = ? ORDER BY is_primary DESC");
$walletStmt->execute([$userId]);
$userWallets = $walletStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'submit_request') {
        $amount = (float)($_POST['amount'] ?? 0);
        $walletId = (int)($_POST['wallet_id'] ?? 0);
        
        if ($amount <= 0) {
            flashMessage('danger', 'Amount must be positive.');
        } elseif (empty($walletId)) {
            flashMessage('danger', 'Please select a crypto wallet.');
        } else {
            // Get wallet details
            $walletStmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE id = ? AND user_id = ?");
            $walletStmt->execute([$walletId, $userId]);
            $wallet = $walletStmt->fetch();
            
            if ($wallet) {
                $pdo->prepare("
                    INSERT INTO payment_requests 
                    (user_id, amount, currency, payout_method, payout_address, created_at) 
                    VALUES (?, ?, 'USDT', ?, ?, NOW())
                ")->execute([$userId, $amount, $wallet['wallet_type'], $wallet['wallet_address']]);
                flashMessage('success', 'Payment request submitted successfully!');
            } else {
                flashMessage('danger', 'Invalid wallet selected.');
            }
        }
    }

    if (in_array($role, ['admin','manager'])) {
        if ($action === 'approve' && $id) {
            $txHash = trim($_POST['transaction_hash'] ?? '');
            $pdo->prepare("
                UPDATE payment_requests 
                SET status='approved', transaction_hash=?, transaction_date=NOW() 
                WHERE id=?
            ")->execute([$txHash, $id]);
            
            // Notify requester
            $req = $pdo->prepare("SELECT * FROM payment_requests WHERE id=?");
            $req->execute([$id]);
            $r = $req->fetch();
            if ($r) {
                addNotification((int)$r['user_id'], "Your payment request #{$id} for \${$r['amount']} USDT has been approved and processed!");
            }
            flashMessage('success', 'Request approved and marked as paid.');
        }
        
        if ($action === 'reject' && $id) {
            $reason = trim($_POST['reject_reason'] ?? '');
            $pdo->prepare("UPDATE payment_requests SET status='rejected' WHERE id=?")->execute([$id]);
            
            $req = $pdo->prepare("SELECT * FROM payment_requests WHERE id=?");
            $req->execute([$id]);
            $r = $req->fetch();
            if ($r) {
                $msg = "Your payment request #{$id} for \${$r['amount']} USDT has been rejected.";
                if ($reason) $msg .= " Reason: $reason";
                addNotification((int)$r['user_id'], $msg);
            }
            flashMessage('warning', 'Request rejected.');
        }
    }

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM payment_requests WHERE id=? AND user_id=? AND status='pending'")->execute([$id, $userId]);
        flashMessage('success', 'Request cancelled.');
    }
    redirect(APP_URL . '/payment_requests.php');
}

// Scope
if (in_array($role, ['admin','manager'])) {
    $stmt = $pdo->query("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id ORDER BY pr.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.user_id=? ORDER BY pr.created_at DESC");
    $stmt->execute([$userId]);
}
$requests = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<style>
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.crypto-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 1.5rem;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    animation: fadeInUp 0.5s ease;
    position: relative;
    overflow: hidden;
}

.crypto-stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.crypto-stat-card.success {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.crypto-stat-card.warning {
    background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
}

.crypto-stat-card.danger {
    background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    position: relative;
    z-index: 1;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

.request-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 4px solid #e2e8f0;
    animation: fadeInUp 0.5s ease;
}

.request-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.request-card.pending {
    border-left-color: #f6ad55;
}

.request-card.approved {
    border-left-color: #48bb78;
}

.request-card.rejected {
    border-left-color: #fc8181;
}

.crypto-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    animation: bounceIn 0.6s ease;
}

@keyframes bounceIn {
    0% { transform: scale(0); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.crypto-badge.usdt {
    background: linear-gradient(135deg, #26a17b 0%, #22c55e 100%);
    color: white;
}

.crypto-badge.binance {
    background: linear-gradient(135deg, #f3ba2f 0%, #fbbf24 100%);
    color: white;
}

.btn-crypto {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-crypto:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.wallet-address {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 8px;
    word-break: break-all;
}

.info-alert {
    background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
    border-radius: 15px;
    padding: 1.5rem;
    border-left: 4px solid #667eea;
    animation: fadeInUp 0.7s ease;
}
</style>

<div class="page-header">
  <div>
    <h2 class="animate-in"><i class="ri-wallet-3-line me-2"></i>Payment Requests</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Payment Requests</li>
      </ol>
    </nav>
  </div>
  <?php if (empty($userWallets) && !in_array($role, ['admin','manager'])): ?>
  <a href="<?= APP_URL ?>/crypto_wallets.php" class="btn btn-crypto">
    <i class="ri-add-line me-1"></i>Add Wallet First
  </a>
  <?php else: ?>
  <button class="btn btn-crypto" data-bs-toggle="modal" data-bs-target="#submitPayModal">
    <i class="ri-add-line me-1"></i>New Request
  </button>
  <?php endif; ?>
</div>

<!-- Stats -->
<?php
$pending  = count(array_filter($requests, fn($r) => $r['status']==='pending'));
$approved = array_sum(array_map(fn($r) => $r['status']==='approved' ? (float)$r['amount'] : 0, $requests));
$rejected = count(array_filter($requests, fn($r) => $r['status']==='rejected'));
?>
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="crypto-stat-card warning">
      <div class="stat-value"><?= $pending ?></div>
      <div class="stat-label"><i class="ri-time-line me-1"></i>Pending Requests</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="crypto-stat-card success">
      <div class="stat-value">$<?= number_format($approved, 2) ?></div>
      <div class="stat-label"><i class="ri-check-double-line me-1"></i>Total Approved</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="crypto-stat-card danger">
      <div class="stat-value"><?= $rejected ?></div>
      <div class="stat-label"><i class="ri-close-circle-line me-1"></i>Rejected</div>
    </div>
  </div>
</div>

<?php if (empty($userWallets) && !in_array($role, ['admin','manager'])): ?>
<div class="info-alert">
  <div class="d-flex align-items-start">
    <i class="ri-information-line me-3" style="font-size: 1.5rem; color: #667eea;"></i>
    <div>
      <h6 class="fw-bold mb-2" style="color: #667eea;">Add Your Crypto Wallet First</h6>
      <p class="mb-2" style="font-size: 0.9rem; color: #4a5568;">
        Before requesting a payout, you need to add your USDT TRC-20 address or Binance ID.
      </p>
      <a href="<?= APP_URL ?>/crypto_wallets.php" class="btn btn-sm btn-crypto mt-2">
        <i class="ri-wallet-3-line me-1"></i>Add Crypto Wallet
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Requests List -->
<?php if (empty($requests)): ?>
<div class="card">
  <div class="card-body text-center py-5">
    <i class="ri-file-list-3-line" style="font-size: 4rem; color: #cbd5e0;"></i>
    <h5 class="mt-3 mb-2">No Payment Requests Yet</h5>
    <p class="text-muted">Submit your first payout request to get started</p>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <i class="ri-file-list-3-line me-2"></i>All Requests
  </div>
  <div class="card-body p-3">
    <?php foreach ($requests as $r): ?>
    <div class="request-card <?= strtolower($r['status']) ?>">
      <div class="row align-items-center">
        <div class="col-md-2">
          <div class="fw-bold" style="font-size: 1.1rem;">
            $<?= number_format((float)$r['amount'], 2) ?>
          </div>
          <small class="text-muted">USDT</small>
        </div>
        
        <div class="col-md-3">
          <?php if ($r['payout_method']): ?>
          <span class="crypto-badge <?= strtolower(str_replace('_', '-', $r['payout_method'])) ?>">
            <?= $r['payout_method'] === 'USDT_TRC20' ? '₮ USDT TRC-20' : '💰 Binance ID' ?>
          </span>
          <div class="wallet-address mt-2">
            <?= h(substr($r['payout_address'], 0, 20)) ?>...
          </div>
          <?php endif; ?>
        </div>
        
        <div class="col-md-2">
          <?php if ($r['status'] === 'pending'): ?>
            <span class="badge bg-warning text-dark"><i class="ri-time-line me-1"></i>Pending</span>
          <?php elseif ($r['status'] === 'approved'): ?>
            <span class="badge bg-success"><i class="ri-check-line me-1"></i>Approved</span>
          <?php else: ?>
            <span class="badge bg-danger"><i class="ri-close-line me-1"></i>Rejected</span>
          <?php endif; ?>
        </div>
        
        <div class="col-md-3">
          <?php if (in_array($role, ['admin','manager'])): ?>
            <small class="text-muted d-block">User: <?= h($r['username']) ?></small>
          <?php endif; ?>
          <small class="text-muted d-block">
            <i class="ri-calendar-line me-1"></i><?= date('M d, Y', strtotime($r['created_at'])) ?>
          </small>
          <?php if ($r['transaction_hash']): ?>
          <small class="text-success d-block">
            <i class="ri-check-double-line me-1"></i>TX: <?= h(substr($r['transaction_hash'], 0, 10)) ?>...
          </small>
          <?php endif; ?>
        </div>
        
        <div class="col-md-2 text-end">
          <?php if ($r['status'] === 'pending'): ?>
            <?php if (in_array($role, ['admin','manager'])): ?>
            <button class="btn btn-sm btn-success" onclick="approveRequest(<?= $r['id'] ?>)">
              <i class="ri-check-line me-1"></i>Approve
            </button>
            <button class="btn btn-sm btn-outline-danger mt-1" onclick="rejectRequest(<?= $r['id'] ?>)">
              <i class="ri-close-line me-1"></i>Reject
            </button>
            <?php elseif ($r['user_id'] == $userId): ?>
            <form method="POST" onsubmit="return confirm('Cancel this request?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="ri-close-line me-1"></i>Cancel
              </button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Submit Request Modal -->
<div class="modal fade" id="submitPayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius: 20px; border: none;">
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="submit_request">
        <div class="modal-header" style="border-bottom: 2px solid #f1f5f9;">
          <h5 class="modal-title"><i class="ri-wallet-3-line me-2 text-primary"></i>New Payment Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Amount (USDT) *</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="10" required placeholder="0.00">
            <small class="text-muted">Minimum: $10 USDT</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Select Wallet *</label>
            <select name="wallet_id" class="form-select" required>
              <option value="">Choose your crypto wallet...</option>
              <?php foreach ($userWallets as $wallet): ?>
              <option value="<?= $wallet['id'] ?>">
                <?= $wallet['wallet_type'] === 'USDT_TRC20' ? '₮ USDT TRC-20' : '💰 Binance ID' ?> - 
                <?= h(substr($wallet['wallet_address'], 0, 20)) ?>...
                <?= $wallet['is_primary'] ? ' (Primary)' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">
              <a href="<?= APP_URL ?>/crypto_wallets.php">Manage wallets</a>
            </small>
          </div>
          
          <div class="alert alert-info" style="border-radius: 12px; border-left: 4px solid #3b82f6;">
            <i class="ri-information-line me-2"></i>
            <strong>Processing Time:</strong> 24-48 hours after approval
          </div>
        </div>
        <div class="modal-footer" style="border-top: 2px solid #f1f5f9;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-crypto">
            <i class="ri-send-plane-line me-1"></i>Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius: 20px;">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="id" id="approveId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ri-check-line me-2 text-success"></i>Approve Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Transaction Hash (Optional)</label>
            <input type="text" name="transaction_hash" class="form-control" placeholder="Enter blockchain transaction hash">
            <small class="text-muted">Provide the TX hash for transparency</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="ri-check-line me-1"></i>Approve & Mark Paid
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius: 20px;">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" id="rejectId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ri-close-line me-2 text-danger"></i>Reject Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Reason (Optional)</label>
            <textarea name="reject_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="ri-close-line me-1"></i>Reject Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function approveRequest(id) {
    document.getElementById('approveId').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectRequest(id) {
    document.getElementById('rejectId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
