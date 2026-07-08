// Membaca data yang diinput dari file tambah-produk.html lewat localStorage
let listProduk = JSON.parse(localStorage.getItem('data_produk')) || [];

// Array baru untuk menampung item yang dibeli di dalam keranjang belanja
let keranjang = [];

// Fungsi utama menampilkan semua menu dengan konsep Split Screen RM Sadigit (Diperbaiki untuk Mobile)
function perbaruiMainKonten(dataTampil = listProduk) {
  const mainKonten = document.getElementById('main-konten');
  if (!mainKonten) return;

  // Mengatur kontainer utama agar membungkus elemen secara vertikal dan pas di layar HP
  mainKonten.style.display = 'flex';
  mainKonten.style.flexDirection = 'column';
  mainKonten.style.width = '100%';
  mainKonten.style.boxSizing = 'border-box';

  let htmlSisiKiri = '';
  if (dataTampil.length === 0) {
    htmlSisiKiri = `
      <div style="width: 100%; box-sizing: border-box; padding: 30px 15px; text-align: center;">
        <div style="background-color: #2c3e50; color: white; padding: 25px 15px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
          <p style="margin: 0; font-size: 14px; font-weight: 500; letter-spacing: 0.5px;">No product in this widget</p>
        </div>
      </div>
    `;
  } else {
    htmlSisiKiri = '<div class="menu-list" style="width: 100%; box-sizing: border-box;">';

    dataTampil.forEach((item) => {
      htmlSisiKiri += `
        <div class="menu-card">
          <div class="image-placeholder">
            <div class="bag-icon"></div>
          </div>

          <div class="menu-details">
            <div>
              <div class="menu-title">${item.nama}</div>
              <div class="menu-desc">${item.deskripsi}</div>
            </div>

            <div>
              <div class="menu-price">Rp ${item.harga}</div>
              <!-- Tombol Tambah Hijau Khas RM Sadigit -->
              <button
                onclick="tambahKeKeranjang('${item.nama}', '${item.harga}')"
                style="width:100%; background-color:#76c000; color:white; border:none; padding:8px; border-radius:6px; font-weight:bold; cursor:pointer; font-size:12px;"
              >
                Tambah
              </button>
            </div>
          </div>
        </div>
      `;
    });

    htmlSisiKiri += '</div>';
  }

  let htmlSisiKanan = '';
  if (keranjang.length === 0) {
    htmlSisiKanan = `
      <div class="desktop-cart-panel" style="width: 100%; box-sizing: border-box; display: flex; flex-direction: column; align-items: center; padding: 20px;">
        <span style="font-size: 50px; margin-bottom: 15px;">🍽️</span>
        <b style="color: #333; margin-bottom: 5px; font-size: 15px;">Belum ada menu yang dipilih</b>
        <p style="font-size: 11px; text-align: center; margin: 0; color: #888;">Silakan pilih hidangan lezat favoritmu di sebelah kiri.</p>
      </div>
    `;
  } else {
    let htmlItemKeranjang = '';
    let totalBelanja = 0;

    keranjang.forEach((item, idx) => {
      const hargaAngka = parseInt(item.harga.replace(/\./g, ''), 10);
      const subTotal = hargaAngka * item.jumlah;
      totalBelanja += subTotal;

      htmlItemKeranjang += `
        <div style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-bottom:12px; border-bottom:1px dashed #eee; padding-bottom:8px; box-sizing: border-box;">
          <div style="text-align:left; max-width:60%;">
            <div style="font-size:13px; font-weight:bold; color:#333;">${item.nama}</div>
            <div style="font-size:11px; color:#76c000; font-weight:bold;">Rp ${subTotal.toLocaleString('id-ID')}</div>
          </div>

          <!-- Tombol pengatur kuantitas item -->
          <div style="display:flex; align-items:center; gap:8px;">
            <button
              onclick="ubahJumlahItem(${idx}, -1)"
              style="background:#f0f2f5; border:none; width:24px; height:24px; border-radius:50%; font-weight:bold; cursor:pointer;"
            >
              -
            </button>

            <span style="font-size:13px; font-weight:bold; min-width:15px; text-align:center;">${item.jumlah}</span>

            <button
              onclick="ubahJumlahItem(${idx}, 1)"
              style="background:#76c000; color:white; border:none; width:24px; height:24px; border-radius:50%; font-weight:bold; cursor:pointer;"
            >
              +
            </button>
          </div>
        </div>
      `;
    });

    htmlSisiKanan = `
      <div class="desktop-cart-panel" style="width: 100%; box-sizing: border-box; padding: 20px; display: flex; flex-direction: column; justify-content: flex-start; align-items: stretch;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; color:#333;">🛒 Detail Pesanan</h3>

        <div style="flex-grow: 1; overflow-y: auto; max-height: 280px; padding-right: 5px;">
          ${htmlItemKeranjang}
        </div>

        <!-- Bagian kalkulasi Total Ringkasan Biaya -->
        <div style="border-top: 2px solid #f0f2f5; padding-top: 15px; margin-top: 10px;">
          <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:15px; color:#333; margin-bottom:15px;">
            <span>Total Bayar:</span>
            <span style="color:#76c000;">Rp ${totalBelanja.toLocaleString('id-ID')}</span>
          </div>

          <button
            onclick="alert('Pesanan diproses!')"
            style="width:100%; background-color:#76c000; color:white; border:none; padding:12px; border-radius:25px; font-weight:bold; cursor:pointer; font-size:14px; box-shadow:0 4px 10px rgba(118,192,0,0.2);"
          >
            PROSES PESANAN
          </button>
        </div>
      </div>
    `;
  }

  mainKonten.innerHTML = htmlSisiKiri + htmlSisiKanan;
}

function tambahKeKeranjang(nama, harga) {
  // Cek apakah produk tersebut sudah pernah diklik sebelumnya
  const itemSama = keranjang.find((item) => item.nama === nama);
  if (itemSama) {
    itemSama.jumlah += 1;
  } else {
    keranjang.push({ nama, harga, jumlah: 1 });
  }

  perbaruiMainKonten(); // Re-render layar untuk memperbarui tampilan struk
}

function ubahJumlahItem(index, perubahan) {
  keranjang[index].jumlah += perubahan;

  // Jika porsi item diturunkan di bawah 1, otomatis hapus item dari daftar struk belanja
  if (keranjang[index].jumlah <= 0) {
    keranjang.splice(index, 1);
  }

  perbaruiMainKonten(); // Re-render layar
}

function bukaKolomCari() {
  const searchBar = document.getElementById('search-bar-wrapper');
  if (!searchBar) return;

  searchBar.classList.add('show');
  document.getElementById('input-pencarian')?.focus();
}

function tutupKolomCari() {
  const searchBar = document.getElementById('search-bar-wrapper');
  if (!searchBar) return;

  searchBar.classList.remove('show');
  const input = document.getElementById('input-pencarian');
  if (input) input.value = '';

  perbaruiMainKonten();
}

function filterMakanan(kataKunci) {
  const hasilFilter = listProduk.filter(
    (produk) =>
      produk.nama.toLowerCase().includes(kataKunci.toLowerCase()) ||
      produk.deskripsi.toLowerCase().includes(kataKunci.toLowerCase())
  );

  perbaruiMainKonten(hasilFilter);
}

function bukaSidebar() {
  const sidebar = document.getElementById('sidebar-wrapper');
  const overlay = document.getElementById('overlay-samping');

  if (sidebar && overlay) {
    sidebar.classList.add('open');
    overlay.classList.add('show');
  }
}

function tutupSidebar() {
  const sidebar = document.getElementById('sidebar-wrapper');
  const overlay = document.getElementById('overlay-samping');

  if (sidebar && overlay) {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  }
}

function aksiSidebar(namaMenu) {
  alert('Anda memilih menu: ' + namaMenu);
  tutupSidebar();
}

// Fungsi Pemuat File Komponen sidebar.html Eksternal
function muatSidebarLokal() {
  fetch('sidebar.html')
    .then((response) => {
      if (!response.ok) {
        throw new Error('File sidebar.html tidak ditemukan di server.');
      }
      return response.text();
    })
    .then((html) => {
      document.getElementById('wadah-sidebar').innerHTML = html;
    })
    .catch((error) => {
      console.error(error);
      document.getElementById('wadah-sidebar').innerHTML = '<p style="color:red; padding:10px;">Gagal memuat sidebar.</p>';
    });
}

function gantiTab(namaTab) {
  // Karena onclick memanggil gantiTab('...') tanpa parameter event,
  // maka kita hanya ubah tab berdasarkan selector global yang sesuai.
  const items = document.querySelectorAll('.nav-item');
  items.forEach((item) => item.classList.remove('active'));

  // Aktifkan item yang memanggil tab tersebut (berdasarkan urutan isi text)
  // Fallback: aktifkan home jika namaTab tidak cocok.
  const activeItem = [...items].find((el) => {
    const text = (el.textContent || '').trim().toLowerCase();
    const map = {
      home: 'home',
      orders: 'orders',
      chat: 'chat',
      profile: 'profile',
    };
    return map[namaTab] && text.includes(map[namaTab]);
  });
  if (activeItem) activeItem.classList.add('active');

  const mainKonten = document.getElementById('main-konten');
  if (!mainKonten) return;

  if (namaTab === 'home') {
    perbaruiMainKonten();
  } else if (namaTab === 'orders') {
    mainKonten.innerHTML = `<div style="padding: 30px; text-align: center; color: #666; width:100%;"><h3>Riwayat Pesanan</h3><p>Belum ada pesanan aktif saat ini.</p></div>`;
  } else if (namaTab === 'chat') {
    mainKonten.innerHTML = `<div style="padding: 30px; text-align: center; color: #666; width:100%;"><h3>Pesan Masuk</h3><p>Hubungi driver atau restoran di sini.</p></div>`;
  } else if (namaTab === 'profile') {
    mainKonten.innerHTML = `<div style="padding: 30px; text-align: center; color: #666; width:100%;"><h3>Profil Saya</h3><p>Kelola alamat dan pengaturan akun Anda.</p></div>`;
  }
}

// Expose fungsi yang dipanggil dari inline HTML (onclick/oninput)
window.perbaruiMainKonten = perbaruiMainKonten;
window.tambahKeKeranjang = tambahKeKeranjang;
window.ubahJumlahItem = ubahJumlahItem;
window.bukaKolomCari = bukaKolomCari;
window.tutupKolomCari = tutupKolomCari;
window.filterMakanan = filterMakanan;
window.bukaSidebar = bukaSidebar;
window.tutupSidebar = tutupSidebar;
window.aksiSidebar = aksiSidebar;
window.gantiTab = gantiTab;

muatSidebarLokal();
perbaruiMainKonten();

window.addEventListener('storage', function (e) {
  if (e.key === 'data_produk') {
    listProduk = JSON.parse(e.newValue) || [];
    perbaruiMainKonten();
  }
});

// Fungsi untuk memasukkan produk ke dalam keranjang belanja
function tambahKeKeranjang(nama, harga, deskripsi) {
    // Ambil data keranjang lama yang sudah tersimpan di localStorage (jika ada)
    let keranjang = JSON.parse(localStorage.getItem("keranjang_belanja")) || [];

    // Periksa apakah produk tersebut sudah pernah dimasukkan sebelumnya
    let produkAda = keranjang.find(item => item.nama === nama);

    if (produkAda) {
        // Jika sudah ada di keranjang, cukup tambahkan jumlahnya (quantity)
        produkAda.jumlah += 1;
    } else {
        // Jika belum ada, masukkan sebagai data produk baru di keranjang
        keranjang.push({
            nama: nama,
            harga: harga,
            deskripsi: deskripsi,
            jumlah: 1
        });
    }

    // Simpan kembali data keranjang yang diperbarui ke localStorage
    localStorage.setItem("keranjang_belanja", JSON.stringify(keranjang));
    
    // Tampilkan notifikasi konfirmasi ke pembeli
    alert(nama + " berhasil dimasukkan ke keranjang!");
}

// Fungsi utama untuk mengatur pergantian konten berdasarkan tab yang diklik
function gantiTab(namaTab) {
    // 1. Atur efek aktif (warna hijau) pada ikon menu bawah
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    
    // 2. Ambil elemen container utama tempat menampilkan konten
    const mainKonten = document.getElementById("main-konten");

    if (namaTab === 'home') {
        // TAMPILAN HOME: Tampilkan daftar produk kuliner asli Anda
        tampilkanDaftarMenu(); 
    } 
    else if (namaTab === 'keranjang') {
        // TAMPILAN KERANJANG: Render isi keranjang belanja dari localStorage
        tampilkanHalamanKeranjang();
    } 
    else {
        // TAMPILAN TAB LAIN (Chat, Profile, dll)
        mainKonten.innerHTML = `<div style="text-align: center; padding: 40px; color: #888;">Halaman ${namaTab} sedang dalam pengembangan.</div>`;
    }
}

// Fungsi khusus untuk merender isi keranjang belanjaan di dalam main-konten
function tampilkanHalamanKeranjang() {
    const mainKonten = document.getElementById("main-konten");
    let keranjang = JSON.parse(localStorage.getItem("keranjang_belanja")) || [];

    if (keranjang.length === 0) {
        mainKonten.innerHTML = `
            <div style="text-align: center; padding: 50px 20px; color: #999;">
                <span style="font-size: 50px;">🛒</span>
                <p style="margin-top: 10px; font-weight: 600;">Keranjang belanja Anda masih kosong</p>
            </div>`;
        return;
    }

    // Bangun HTML untuk daftar item di dalam keranjang beserta total bayar
    let htmlKeranjang = `<div class="keranjang-container" style="padding: 15px;">`;
    let totalBayar = 0;

    keranjang.forEach((item, index) => {
        // Menghitung subtotal per item produk
        let hargaAngka = parseInt(item.harga.replace(/\./g, ''));
        let subTotal = hargaAngka * item.jumlah;
        totalBayar += subTotal;

        htmlKeranjang += `
            <div class="card-keranjang" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #eee;">
                <div>
                    <h4 style="margin: 0; color: #333;">${item.nama}</h4>
                    <span style="font-size: 13px; color: #76c000; font-weight: bold;">Rp ${item.harga}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button onclick="ubahJumlahItem(${index}, -1)" style="border: 1px solid #ccc; background: white; border-radius: 4px; padding: 2px 8px; cursor: pointer;">-</button>
                    <span style="font-weight: bold;">${item.jumlah}</span>
                    <button onclick="ubahJumlahItem(${index}, 1)" style="border: none; background: #76c000; color: white; border-radius: 4px; padding: 2px 8px; cursor: pointer;">+</button>
                </div>
            </div>`;
    });

    htmlKeranjang += `
        <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: center; border-top: 2px solid #eee; padding-top: 15px;">
            <strong style="font-size: 16px;">Total Bayar:</strong>
            <strong style="font-size: 18px; color: #76c000;">Rp ${totalBayar.toLocaleString('id-ID')}</strong>
        </div>
        <button class="btn-submit" style="background-color: #76c000; color: white; border: none; padding: 14px; width: 100%; border-radius: 25px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 20px; box-shadow: 0 4px 10px rgba(118,192,0,0.2);">PROSES PESANAN</button>
    </div>`;

    mainKonten.innerHTML = htmlKeranjang;
}

// Fungsi untuk menambah atau mengurangi kuantitas langsung dari halaman keranjang
function ubahJumlahItem(index, perubahan) {
    let keranjang = JSON.parse(localStorage.getItem("keranjang_belanja")) || [];
    keranjang[index].jumlah += perubahan;

    // Jika jumlahnya menjadi 0 atau minus, hapus produk dari daftar keranjang
    if (keranjang[index].jumlah <= 0) {
        keranjang.splice(index, 1);
    }

    localStorage.setItem("keranjang_belanja", JSON.stringify(keranjang));
    
    // Perbarui tampilan halaman dan angka badge merah di bawah secara real-time
    tampilkanHalamanKeranjang();
    if (typeof perbaruiBadgeKeranjang === "function") perbaruiBadgeKeranjang();
}
