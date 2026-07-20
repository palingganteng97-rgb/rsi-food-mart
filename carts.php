<?php
ob_start();
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Keranjang</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <style>
        body { background:#0f172a; color:#e5e7eb; }
        .glass { background: rgba(2,6,23,.40); border:1px solid rgba(148,163,184,.22); border-radius:18px; }
        .muted { color: rgba(229,231,235,.55); }
        .btn-pill { border-radius:999px; }
        .thumb { width:72px; height:72px; object-fit:cover; border-radius:14px; border:1px solid rgba(148,163,184,.18); }
        .line { border-color: rgba(148,163,184,.18); }
        body, body.modal-open { overflow: auto !important; padding-right: 0px !important; pointer-events: auto !important; }
        #modalEditPesanan:not(.show) ~ .modal-backdrop { display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; }
    </style>
</head>
<body>
<?php include 'bottom_nav.php'; ?>
<div class="container py-4" style="max-width: 920px; padding-bottom: 96px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-basket2 me-2 text-success"></i> Keranjang</h4>
        <a class="btn btn-outline-light btn-sm btn-pill px-3" href="products.php">+ Tambah</a>
    </div>
    <?php if ($status === 'error' && !empty($msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($status === 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Item berhasil dihapus.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="glass p-3 p-md-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold">Total item: <span id="totalItems">0</span></div>
                        <div class="muted" style="font-size:.9rem;">Klik detail item untuk melihat topping & catatan</div>
                    </div>
                    <button class="btn btn-success btn-pill" id="btnCheckout" style="font-weight:700;">Checkout</button>
                </div>
                <hr class="line my-3" />
            </div>
            <div class="col-12">
                <div id="cartList" class="d-flex flex-column gap-2"></div>
                <div id="emptyState" class="text-center py-5" style="display:none;">
                    <div class="display-6">🛒</div>
                    <div class="fw-semibold mt-2">Keranjang Anda masih kosong</div>
                    <div class="muted mt-2">Silakan pilih menu terlebih dahulu.</div>
                    <a href="products.php" class="btn btn-outline-light btn-pill mt-3">Cari menu</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const API_URL = 'api_cart.php';
    function money(val) {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }
    async function loadCart() {
        try {
            const [totalRes, itemsRes] = await Promise.all([
                fetch(API_URL + '?action=get_total'),
                fetch(API_URL + '?action=get_cart_items')
            ]);
            const totalJson = await totalRes.json();
            const itemsJson = await itemsRes.json();
            const totalItems = parseInt(totalJson?.total_items ?? 0, 10);
            document.getElementById('totalItems').textContent = totalItems;
            const cartList = document.getElementById('cartList');
            const emptyState = document.getElementById('emptyState');
            cartList.innerHTML = '';
            if (!Array.isArray(itemsJson) || itemsJson.length === 0) {
                emptyState.style.display = 'block';
                return;
            }
            emptyState.style.display = 'none';
            for (const item of itemsJson) {
                const productId = item.id;
                const name = item.name || 'Menu';
                const qty = parseInt(item.qty ?? 1, 10);
                const price = Number(item.price ?? 0);
                const notes = item.notes || '';
                const image = item.image || '';
                let imgSrc = image ? ('uploads/products/' + image) : 'uploads/products/default.png';
                const total = price * qty;
                const wrapper = document.createElement('div');
                wrapper.className = 'card glass';
                wrapper.style.borderRadius = '16px';
                wrapper.style.background = 'rgba(2,6,23,.32)';
                wrapper.innerHTML = `
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-3 col-sm-2">
                                <img class="thumb" src="${imgSrc}" alt="${name}" onerror="this.src='uploads/products/default.png'" />
                            </div>
                            <div class="col-6">
                                <div class="fw-semibold text-white">${name}</div>
                                <div class="muted" style="font-size:.9rem;">Qty: <span class="text-white fw-semibold">${qty}</span></div>
                                ${notes ? `<div class="muted" style="font-size:.85rem; margin-top:6px;">Catatan: ${escapeHtml(notes)}</div>` : ''}
                            </div>
                            <div class="col-4 col-sm-3 text-end">
                                <div class="fw-bold text-success">${money(total)}</div>
                                <a class="btn btn-outline-light btn-sm btn-pill mt-2" href="cart_item.php?key=${productId}">Detail</a>
                            </div>
                        </div>
                    </div>
                `;
                cartList.appendChild(wrapper);
            }
        } catch (e) {
            console.error(e);
        }
    }
    function escapeHtml(str) {
        return String(str).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }
    function paksaBersihkanBackdrop() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        document.body.style.pointerEvents = 'auto';
    }
    document.getElementById('btnCheckout').addEventListener('click', async () => {
        const totalItems = parseInt(document.getElementById('totalItems').textContent || '0', 10);
        if (totalItems <= 0) {
            window.location.href = 'carts.php?status=error&msg=' + encodeURIComponent('Keranjang kosong');
            return;
        }
        if (typeof bootstrap !== 'undefined') {
            const modalElement = document.getElementById('modalEditPesanan');
            if (modalElement) {
                const bsModal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                bsModal.hide();
            }
        }
        paksaBersihkanBackdrop();
        try {
            const pmRes = await fetch('payments.php?action=first_payment_method');
            let pmId = null;
            if (pmRes.ok) {
                const j = await pmRes.json().catch(() => null);
                pmId = j?.payment_method_id ?? null;
            }
            if (!pmId) pmId = 0;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout_process.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payment_method_id';
            input.value = pmId;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        } catch (e) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout_process.php';
            document.body.appendChild(form);
            form.submit();
        }
    });
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target.id === 'modalEditPesanan') paksaBersihkanBackdrop();
    });
    setInterval(() => {
        const m = document.getElementById('modalEditPesanan');
        if (m && !m.classList.contains('show') && document.querySelector('.modal-backdrop')) {
            paksaBersihkanBackdrop();
        }
    }, 300);
    loadCart();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
