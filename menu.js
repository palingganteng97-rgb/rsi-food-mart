let listProduk = [];
const API_URL = 'ambil-produk.php';
const CART_KEY = 'cart_belanja';

// 1. Memuat data makanan dari server lokal PHP
async function ambilDataDariServer() {
  try {
    const res = await fetch(`${API_URL}?t=${new Date().getTime()}`);
    if (!res.ok) throw new Error('Respon server bermasalah');
    listProduk = await res.json();
    if (!listProduk || !Array.isArray(listProduk)) listProduk = [];
    perbaruiMainKonten();
  } catch (e) {
    console.error('Gagal mengambil data:', e);
    listProduk = [];
    perbaruiMainKonten();
  }
}

// 2. Mencetak daftar menu kuliner secara dinamis ke layar
function perbaruiMainKonten(query = '') {
  const containerList = document.getElementById('menu-list-container');
  if (!containerList) return;

  const hasilFilter = query ? listProduk.filter(p => 
    (p.nama || '').toLowerCase().includes(query) || 
    (p.deskripsi || '').toLowerCase().includes(query)
  ) : listProduk;

  if (hasilFilter.length === 0) {
    containerList.innerHTML = '<div style="text-align:center;padding:30px;color:#999;width:100%;">Menu tidak ditemukan</div>';
    return;
  }

  let htmlKonten = '';
  hasilFilter.forEach((item, index) => {
    htmlKonten += `
      <div class="menu-card">
        <img class="image-placeholder" src="${item.foto || ''}" alt="${item.nama}" onerror="this.style.display='none'">
        <div class="menu-details">
          <div>
            <h3 class="menu-title">${item.nama || '-'}</h3>
            <p class="menu-desc">${item.deskripsi || '-'}</p>
          </div>
          <div>
            <div class="menu-price">Rp ${item.harga || '0'}</div>
            <button class="btn-tambah" type="button" onclick="tambahKeKeranjang(${index})">Tambah</button>
          </div>
        </div>
      </div>`;
  });
  containerList.innerHTML = htmlKonten;
}

// 3. Logika tombol TAMBAH untuk menyimpan data ke LocalStorage halaman keranjang
window.tambahKeKeranjang = function(index){
  const produk=listProduk[index];
  if (!produk) return;
  const btn=event&&event.target?event.target.closest&&event.target.closest('button.btn-tambah')||event.target:null;

  let cart = [];
  try {
    cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
  } catch (e) {
    cart = [];
  }

  const itemAda = cart.find(item => item.nama === produk.nama);
  if (itemAda) {
    itemAda.jumlah += 1;
  } else {
    cart.push({
      id: produk.id || Date.now().toString(),
      nama: produk.nama,
      harga: produk.harga,
      deskripsi: produk.deskripsi || '',
      foto: produk.foto || '',
      jumlah: 1
    });
  }

  localStorage.setItem(CART_KEY, JSON.stringify(cart));

  // Memicu update angka notifikasi merah di bottom navigation secara instan
  if (typeof perbaruiBadgeAngkaNavigasi === 'function') {
    perbaruiBadgeAngkaNavigasi();
  }
};

// 4. Inisialisasi event listener pencarian saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('search');
  const clearSearchBtn = document.getElementById('clearSearch');

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const value = e.target.value.toLowerCase().trim();
      if (clearSearchBtn) clearSearchBtn.style.display = value.length > 0 ? 'block' : 'none';
      perbaruiMainKonten(value);
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        perbaruiMainKonten('');
      }
    });
  }

  ambilDataDariServer();
});

window.addEventListener('resize', () => {
  if (document.getElementById('menu-list-container')) perbaruiMainKonten();
});
