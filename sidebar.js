// Menyuntikkan struktur HTML sidebar secara otomatis ke dalam wadah aplikasi
function muatSidebarLokal() {
    const htmlSidebar = `
        <div class="sidebar-overlay" id="overlay-samping" onclick="tutupSidebar()"></div>
        <aside class="sidebar-container" id="sidebar-wrapper">
            <div class="sidebar-header">
                <div class="sidebar-user-avatar">👤</div>
                <div class="sidebar-user-name">Pelanggan Setia</div>
                <div style="font-size: 11px; color: #aaa; margin-top: 2px;">rsi-member@mail.com</div>
            </div>
            <nav class="sidebar-menu-list">
                <div class="sidebar-menu-item" onclick="aksiSidebar('Voucher Saya')">
                    <span class="sidebar-menu-icon">🎟️</span><span>Voucher Saya</span>
                </div>
                <div class="sidebar-menu-item" onclick="aksiSidebar('Alamat Tersimpan')">
                    <span class="sidebar-menu-icon">📍</span><span>Alamat Tersimpan</span>
                </div>
                <div class="sidebar-menu-item" onclick="aksiSidebar('Metode Pembayaran')">
                    <span class="sidebar-menu-icon">💳</span><span>Metode Pembayaran</span>
                </div>
                <div class="sidebar-menu-item" onclick="aksiSidebar('Pusat Bantuan')">
                    <span class="sidebar-menu-icon">ℹ️</span><span>Pusat Bantuan</span>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-menu-item" onclick="aksiSidebar('Keluar Akun')" style="color: #e74c3c;">
                    <span class="sidebar-menu-icon">🚪</span><span>Keluar Akun</span>
                </div>
            </div>
        </aside>
    `;
    
    const wadah = document.getElementById('wadah-sidebar');
    if (wadah) {
        wadah.innerHTML = htmlSidebar;
    }
}

// Jalankan pemanggilan fungsi secara otomatis
muatSidebarLokal();
