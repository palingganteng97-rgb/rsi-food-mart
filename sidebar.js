// LOGIKA MURNI: Fungsi ini hanya dipanggil oleh tombol garis tiga (☰) di menu.html
async function bukaSidebar(e) {
  if (e) e.preventDefault();
  
  const overlay = document.getElementById('overlay-samping');
  const sidebar = document.getElementById('sidebar-wrapper');
  if (!sidebar) return;

  // Mengambil template HTML dari file sidebar.html asli Anda
  if (sidebar.innerHTML.trim() === "") {
    try {
      const respon = await fetch('sidebar.html');
      if (respon.ok) sidebar.innerHTML = await respon.text();
    } catch (err) { console.error('Gagal memuat sidebar.html', err); }
  }

  // Munculkan sidebar melayang ke layar browser
  if (overlay) overlay.style.setProperty('display', 'block', 'important');
  sidebar.style.setProperty('display', 'block', 'important');
}

function tutupSidebar() {
  const overlay = document.getElementById('overlay-samping');
  const sidebar = document.getElementById('sidebar-wrapper');
  if (overlay) overlay.style.display = 'none';
  if (sidebar) sidebar.style.display = 'none';
}

window.bukaSidebar = bukaSidebar;
window.tutupSidebar = tutupSidebar;
