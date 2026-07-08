function muatBottomNavGlobal() {
  if (document.getElementById('bottom-nav-global')) return;
  
  // LOGIKA MURNI: Semua tombol navigasi bawah murni berfungsi pindah halaman html
  const nav = `
    <div id="bottom-nav-global" class="bottom-nav">
      <a href="menu.html" class="nav-item" data-bn="home">🏠<div>Home</div></a>
      <a href="keranjang.html" class="nav-item" data-bn="cart">
        <span class="bn-cart-wrap"><span class="bn-cart-ic">🛒</span><span id="cart-badge" class="cart-badge">0</span></span>
        <div>Keranjang</div>
      </a>
      <a href="#" class="nav-item" data-bn="search" onclick="muatFocusCari(event)">🔍<div>Cari</div></a>
      <a href="#" class="nav-item" data-bn="chat">💬<div>Chat</div></a>
      <!-- KUNCI LOGIKA: Tombol Profile murni mengarah ke user.html, tidak mengurus sidebar! -->
      <a href="user.html" class="nav-item" data-bn="profile">👤<div>Profile</div></a>
    </div>`;
  
  document.body.insertAdjacentHTML('beforeend', nav);

  if (window.location.pathname.includes('menu.html')) {
    const searchBar = document.querySelector('.searchbar');
    if (searchBar) searchBar.style.setProperty('display', 'none', 'important');
  }
}

function muatFocusCari(e) {
  if (e) e.preventDefault();
  const searchBar = document.querySelector('.searchbar');
  const searchInput = document.getElementById('search');
  if (searchBar) {
    if (searchBar.style.display === 'none' || searchBar.style.display === '') {
      searchBar.style.setProperty('display', 'flex', 'important');
      if (searchInput) searchInput.focus();
    } else {
      searchBar.style.setProperty('display', 'none', 'important');
      if (searchInput) searchInput.value = '';
      if (typeof perbaruiMainKonten === 'function') perbaruiMainKonten('');
    }
  }
}

function updateCartBadge() {
  try {
    const c = JSON.parse(localStorage.getItem('cart_belanja')) || [];
    const n = c.reduce((a, b) => a + (b.jumlah || 0), 0);
    const el = document.getElementById('cart-badge');
    if (el) { el.textContent = String(n); el.style.display = n > 0 ? 'block' : 'none'; }
  } catch (_) {}
}

window.muatBottomNavGlobal = muatBottomNavGlobal;
window.muatFocusCari = muatFocusCari;
window.updateCartBadge = updateCartBadge;
window.addEventListener('DOMContentLoaded', () => { muatBottomNavGlobal(); updateCartBadge(); });
window.addEventListener('storage', e => { if (e && e.key === 'cart_belanja') updateCartBadge(); });
