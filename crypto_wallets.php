<?php
/**
 * Sigma SMS A2P — Crypto Wallets Management
 * Users manage their USDT TRC-20 addresses and Binance IDs for payouts
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Crypto Wallets';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$pdo    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $walletType = trim($_POST['wallet_type'] ?? '');
    $walletAddress = trim($_POST['wallet_address'] ?? '');
    $walletLabel = trim($_POST['wallet_label'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        if (empty($walletType) || empty($walletAddress)) {
            flashMessage('danger', 'Wallet type and address are required.');
        } else {
            // Validate wallet address format
            $isValid = false;
            if ($walletType === 'USDT_TRC20') {
                // TRC-20 addresses start with 'T' and are 34 characters
                $isValid = preg_match('/^T[A-Za-z0-9]{33}$/', $walletAddress);
            } elseif ($walletType === 'BINANCE_ID') {
                // Binance ID is typically email or numeric ID
                $isValid = !empty($walletAddress);
            }
            
            if (!$isValid && $walletType === 'USDT_TRC20') {
                flashMessage('danger', 'Invalid USDT TRC-20 address format. Must start with T and be 34 characters.');
            } else {
                $pdo->prepare("INSERT INTO crypto_wallets (user_id, wallet_type, wallet_address, wallet_label, created_at) VALUES (?,?,?,?,NOW())")
                    ->execute([$userId, $walletType, $walletAddress, $walletLabel]);
                flashMessage('success', 'Crypto wallet added successfully.');
            }
        }
    }
    
    if ($action === 'set_primary' && $id) {
        // Set all to non-primary first
        $pdo->prepare("UPDATE crypto_wallets SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
        // Set selected as primary
        $pdo->prepare("UPDATE crypto_wallets SET is_primary = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        flashMessage('success', 'Primary wallet updated.');
    }
    
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM crypto_wallets WHERE id=? AND user_id=?")->execute([$id, $userId]);
        flashMessage('success', 'Crypto wallet removed.');
    }
    redirect(APP_URL . '/crypto_wallets.php');
}

$stmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE user_id=? ORDER BY is_primary DESC, id DESC");
$stmt->execute([$userId]);
$wallets = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<style>
.crypto-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    animation: slideInDown 0.6s ease;
}

.crypto-card::before {
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

.wallet-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    animation: fadeInUp 0.5s ease;
    position: relative;
    overflow: hidden;
}

.wallet-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(102, 126, 234, 0.2);
    border-color: #667eea;
}

.wallet-card.primary {
    border-color: #48bb78;
    background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
}

.wallet-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 1rem;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.wallet-icon.usdt {
    background: linear-gradient(135deg, #26a17b 0%, #22c55e 100%);
    box-shadow: 0 4px 15px rgba(38, 161, 123, 0.3);
}

.wallet-icon.binance {
    background: linear-gradient(135deg, #f3ba2f 0%, #fbbf24 100%);
    box-shadow: 0 4px 15px rgba(243, 186, 47, 0.3);
}

.wallet-address {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 10px;
    word-break: break-all;
    margin: 1rem 0;
    border: 1px solid #e2e8f0;
}

.primary-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 2px 10px rgba(72, 187, 120, 0.3);
    animation: bounceIn 0.6s ease;
}

@keyframes bounceIn {
    0% { transform: scale(0); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
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

.info-box {
    background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid #667eea;
    animation: fadeInUp 0.7s ease;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    animation: fadeInUp 0.8s ease;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e0;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}
</style>

<div class="page-header">
  <div>
    <h2 class="animate-in"><i class="ri-wallet-3-line me-2"></i>Crypto Wallets</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Crypto Wallets</li>
      </ol>
    </nav>
  </div>
  <button class="btn btn-crypto" data-bs-toggle="modal" data-bs-target="#addWalletModal">
    <i class="ri-add-line me-1"></i>Add Wallet
  </button>
</div>

<!-- Info Box -->
<div class="info-box">
  <div class="d-flex align-items-start">
    <i class="ri-information-line me-3" style="font-size: 1.5rem; color: #667eea;"></i>
    <div>
      <h6 class="fw-bold mb-2" style="color: #667eea;">💰 Crypto Payouts</h6>
      <p class="mb-2" style="font-size: 0.9rem; color: #4a5568;">
        Add your crypto wallet addresses to receive payouts. We support:
      </p>
      <ul style="font-size: 0.875rem; color: #4a5568; margin-bottom: 0;">
        <li><strong>USDT TRC-20:</strong> Tether on TRON network (low fees, fast transfers)</li>
        <li><strong>Binance ID:</strong> Your Binance email or Pay ID for direct transfers</li>
      </ul>
    </div>
  </div>
</div>

<?php if (empty($wallets)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <i class="ri-wallet-3-line"></i>
        <h5 class="mb-2">No Crypto Wallets Added</h5>
        <p class="text-muted mb-4">Add your USDT TRC-20 address or Binance ID to receive payouts</p>
        <button class="btn btn-crypto" data-bs-toggle="modal" data-bs-target="#addWalletModal">
          <i class="ri-add-line me-1"></i>Add Your First Wallet
        </button>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($wallets as $wallet): ?>
    <div class="col-md-6">
      <div class="wallet-card <?= $wallet['is_primary'] ? 'primary' : '' ?>">
        <?php if ($wallet['is_primary']): ?>
        <div class="primary-badge">
          <i class="ri-star-fill me-1"></i>Primary
        </div>
        <?php endif; ?>
        
        <div class="wallet-icon <?= $wallet['wallet_type'] === 'USDT_TRC20' ? 'usdt' : 'binance' ?>">
          <?php if ($wallet['wallet_type'] === 'USDT_TRC20'): ?>
            <span style="color: white;">₮</span>
          <?php else: ?>
            <i class="ri-currency-line" style="color: white;"></i>
          <?php endif; ?>
        </div>
        
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <h6 class="fw-bold mb-1">
              <?php if ($wallet['wallet_type'] === 'USDT_TRC20'): ?>
                <i class="ri-coin-line me-1 text-success"></i>USDT TRC-20
              <?php else: ?>
                <i class="ri-exchange-dollar-line me-1 text-warning"></i>Binance ID
              <?php endif; ?>
            </h6>
            <?php if ($wallet['wallet_label']): ?>
            <small class="text-muted"><?= h($wallet['wallet_label']) ?></small>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="wallet-address">
          <?= h($wallet['wallet_address']) ?>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small class="text-muted">
            <i class="ri-time-line me-1"></i>
            Added <?= date('M d, Y', strtotime($wallet['created_at'])) ?>
          </small>
          <div class="btn-group btn-group-sm">
            <?php if (!$wallet['is_primary']): ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="set_primary">
              <input type="hidden" name="id" value="<?= (int)$wallet['id'] ?>">
              <button type="submit" class="btn btn-outline-success action-btn" title="Set as Primary">
                <i class="ri-star-line"></i>
              </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-outline-primary action-btn" onclick="copyAddress('<?= h($wallet['wallet_address']) ?>')" title="Copy Address">
              <i class="ri-file-copy-line"></i>
            </button>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this wallet?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$wallet['id'] ?>">
              <button type="submit" class="btn btn-outline-danger action-btn" title="Delete">
                <i class="ri-delete-bin-line"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add Wallet Modal -->
<div class="modal fade" id="addWalletModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius: 20px; border: none;">
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="modal-header" style="border-bottom: 2px solid #f1f5f9;">
          <h5 class="modal-title"><i class="ri-wallet-3-line me-2 text-primary"></i>Add Crypto Wallet</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Wallet Type *</label>
            <select name="wallet_type" class="form-select" required onchange="updatePlaceholder(this.value)">
              <option value="">Select wallet type...</option>
              <option value="USDT_TRC20">USDT TRC-20 (Tether on TRON)</option>
              <option value="BINANCE_ID">Binance ID (Email or Pay ID)</option>
            </select>
            <small class="text-muted">Choose your preferred payout method</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Wallet Address / ID *</label>
            <input type="text" name="wallet_address" id="walletAddress" class="form-control" required placeholder="Enter your wallet address or Binance ID">
            <small class="text-muted" id="addressHelp">
              Enter your wallet address or Binance ID
            </small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Label (Optional)</label>
            <input type="text" name="wallet_label" class="form-control" placeholder="e.g., Main Wallet, Binance Account">
            <small class="text-muted">Give this wallet a friendly name</small>
          </div>
          
          <div class="alert alert-info" style="border-radius: 12px; border-left: 4px solid #3b82f6;">
            <i class="ri-information-line me-2"></i>
            <strong>Important:</strong> Double-check your address before saving. Incorrect addresses may result in lost funds.
          </div>
        </div>
        <div class="modal-footer" style="border-top: 2px solid #f1f5f9;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-crypto">
            <i class="ri-save-line me-1"></i>Save Wallet
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function copyAddress(address) {
    navigator.clipboard.writeText(address).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <i class="ri-check-line me-2"></i>Address copied to clipboard!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}

function updatePlaceholder(type) {
    const addressInput = document.getElementById('walletAddress');
    const helpText = document.getElementById('addressHelp');
    
    if (type === 'USDT_TRC20') {
        addressInput.placeholder = 'T... (34 characters, starts with T)';
        helpText.textContent = 'USDT TRC-20 addresses start with "T" and are 34 characters long';
    } else if (type === 'BINANCE_ID') {
        addressInput.placeholder = 'your@email.com or Binance Pay ID';
        helpText.textContent = 'Enter your Binance registered email or Binance Pay ID';
    } else {
        addressInput.placeholder = 'Enter your wallet address or Binance ID';
        helpText.textContent = 'Enter your wallet address or Binance ID';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
