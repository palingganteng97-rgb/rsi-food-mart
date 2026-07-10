// catalog_handler.js

let currentDietFilter = '';
let currentQuery = '';
let keranjangBelanja = []; 
let detailProductModalInstance = null;

const grid = document.getElementById('catalogGrid');

// Fungsi utama pemicu buka detail dan pengisian bodi carousel gambar dinamis
function openDetailProduct(data) {
    document.getElementById('detail_product_name').innerText = data.name;
    document.getElementById('detail_product_category').innerText = data.category_name || 'General';
    document.getElementById('detail_product_description').innerText = data.description || 'Tidak ada deskripsi untuk menu sehat ini.';
    
    const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.base_price);
    document.getElementById('detail_product_price').innerText = formattedPrice;

    const carouselInner = document.getElementById('detail_product_carousel_inner');
    const prevBtn = document.getElementById('carousel_btn_prev');
    const nextBtn = document.getElementById('carousel_btn_next');
    
    if (carouselInner) {
        carouselInner.innerHTML = '<div class="text-center p-3 text-white-50 small"><div class="spinner-border spinner-border-sm text-success me-2"></div>Memuat foto...</div>';
        
        // Memanggil API Fetch independen
        fetch(`api_get_gallery.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(galleryImages => {
                carouselInner.innerHTML = ''; 
                let allImages = [];

                const mainImage = data.image ? `uploads/products/${data.image}` : 'uploads/products/default.png';
                allImages.push(mainImage);

                if (Array.isArray(galleryImages) && galleryImages.length > 0) {
                    galleryImages.forEach(imgName => {
                        allImages.push(`uploads/products/gallery/${imgName}`);
                    });
                }

                allImages.forEach((src, index) => {
                    const activeClass = index === 0 ? 'active' : '';
                    carouselInner.innerHTML += `
                        <div class="carousel-item ${activeClass}" style="overflow: hidden !important;">
                            <img src="${src}" class="d-block mx-auto" style="width: 250px !important; min-width: 250px !important; max-width: 250px !important; height: 220px !important; object-fit: cover; border-radius: 12px;" onerror="this.src='uploads/products/default.png'">
                        </div>
                    `;
                });

                if (prevBtn && nextBtn) {
                    const showControls = allImages.length > 1 ? 'block' : 'none';
                    prevBtn.style.display = showControls;
                    nextBtn.style.display = showControls;
                }

                const carouselEl = document.getElementById('carouselDetailProduct');
                if (carouselEl) {
                    bootstrap.Carousel.getOrCreateInstance(carouselEl).to(0);
                }
            })
            .catch(err => {
                const mainSrc = data.image ? `uploads/products/${data.image}` : 'uploads/products/default.png';
                carouselInner.innerHTML = `
                    <div class="carousel-item active">
                        <img src="${mainSrc}" class="d-block w-100" style="height: 220px; object-fit: cover; border-radius: 12px;">
                    </div>
                `;
                if (prevBtn && nextBtn) {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                }
            });
    }
    
    const cartBtn = document.getElementById('btn_detail_add_cart');
    cartBtn.onclick = function() {
        tambahKeKeranjang(data.id, data.name, data.base_price, data.image);
        
        // Penutupan modal yang aman dari resiko backdrop hitam transparan tersangkut macet
        const modalEl = document.getElementById('modalDetailProduct');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
    };

    if (!detailProductModalInstance) {
        detailProductModalInstance = new bootstrap.Modal(document.getElementById('modalDetailProduct'));
    }
    detailProductModalInstance.show();
}

function setDietFilter(diet){
  currentDietFilter = diet;
  document.querySelectorAll('[data-filter]').forEach(btn=>{
    const isActive = btn.getAttribute('data-filter') === diet;
    btn.dataset.active = isActive ? 'true' : 'false';
    btn.classList.toggle('btn-success', isActive);
  });
  btnStyleRefresh();
  applyFilters();
}

function btnStyleRefresh(){
  document.querySelectorAll('[data-filter]').forEach(btn=>{
    const active = btn.dataset.active === 'true';
    btn.classList.toggle('diet-pill', true);
    btn.style.background = active ? 'rgba(34,197,94,.92)' : 'rgba(34,197,94,.08)';
    btn.style.color = active ? '#06210f' : '#86efac';
    btn.style.borderColor = active ? 'rgba(34,197,94,.65)' : 'rgba(34,197,94,.35)';
  });
}

// Event listener kotak pencarian input katalog
const searchInp = document.getElementById('searchInput');
if (searchInp) {
    searchInp.addEventListener('input', (e)=>{
      currentQuery = (e.target.value || '').trim().toLowerCase();
      applyFilters();
    });
}

function applyFilters(){
  if(!grid) return;
  const cards = grid.querySelectorAll('.col');
  cards.forEach(card=>{
    const foodCard = card.querySelector('.card-food');
    if(!foodCard) return;

    const title = (foodCard.dataset.title || '').toLowerCase();
    const dietList = (foodCard.dataset.diet || '').toLowerCase();

    const matchQuery = !currentQuery || title.includes(currentQuery);
    const matchDiet = !currentDietFilter || dietList.includes(currentDietFilter.toLowerCase());

    card.style.display = (matchQuery && matchDiet) ? '' : 'none';
  });
}

function resetFilters(){
  currentDietFilter = '';
  currentQuery = '';
  const input = document.getElementById('searchInput');
  if(input) input.value = '';
  document.querySelectorAll('[data-filter]').forEach(btn=>{
    const isActive = btn.getAttribute('data-filter') === '';
    btn.dataset.active = isActive ? 'true' : 'false';
  });
  btnStyleRefresh();
  applyFilters();
}

function tambahKeKeranjang(id, title, price, image) {
  const itemAda = keranjangBelanja.find(item => item.id === id);
  if (itemAda) {
      itemAda.qty += 1;
  } else {
      keranjangBelanja.push({
          id: id,
          title: title,
          price: parseFloat(price) || 0,
          image: image ? image : 'default.png',
          qty: 1
      });
  }
  updateRingkasanNavbar();
  
  const el = document.createElement('div');
  el.className = 'toast align-items-center text-bg-success border-0 position-fixed bottom-0 end-0 m-3';
  el.role = 'alert';
  el.style.zIndex = 2000;
  el.innerHTML = `<div class="d-flex"><div class="toast-body">Ditambahkan: <strong>${title}</strong></div></div>`;
  document.body.appendChild(el);
  const toast = new bootstrap.Toast(el, { delay: 1400 });
  toast.show();
  setTimeout(() => el.remove(), 1600);
}

function updateRingkasanNavbar() {
    const counterText = document.getElementById('cartTotalText');
    if (counterText) {
        const totalItem = keranjangBelanja.reduce((sum, item) => sum + item.qty, 0);
        counterText.textContent = totalItem + ' item';
    }
}

function openCart() {
    const bodyContainer = document.getElementById('cartModalBody');
    const totalContainer = document.getElementById('cartModalTotal');
    const btnCheckout = document.getElementById('btnCheckout');
    
    if(!bodyContainer || !totalContainer || !btnCheckout) return;

    let myModal = bootstrap.Modal.getInstance(document.getElementById('modalCartDetail'));
    if (!myModal) {
        myModal = new bootstrap.Modal(document.getElementById('modalCartDetail'));
    }
    myModal.show();

    if (keranjangBelanja.length > 0) {
        bodyContainer.innerHTML = ''; 
        let grandTotal = 0;

        keranjangBelanja.forEach(item => {
            const subtotalItem = item.price * item.qty;
            grandTotal += subtotalItem;

            const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(item.price);
            const formattedSubtotal = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotalItem);

            bodyContainer.innerHTML += `
            <div class="d-flex align-items-center mb-3 border-bottom pb-2" style="border-color: rgba(148, 163, 184, 0.12) !important;">
                <img src="uploads/products/${item.image}" style="width:50px; height:50px; object-fit:cover;" class="rounded me-3" onerror="this.src='uploads/products/default.png'">
                <div class="flex-grow-1">
                    <h6 class="mb-0 text-white small fw-semibold">${item.title}</h6>
                    <small class="text-muted">${formattedPrice} x ${item.qty}</small>
                </div>
                <div class="text-end">
                    <span class="text-success small fw-bold">${formattedSubtotal}</span>
                </div>
            </div>`;
        });
        
        totalContainer.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(grandTotal);
        btnCheckout.removeAttribute('disabled');
    } else {
        bodyContainer.innerHTML = '<div class="text-center py-4 text-muted small">Keranjang belanja Anda masih kosong.</div>';
        totalContainer.innerText = 'Rp 0';
        btnCheckout.setAttribute('disabled', 'disabled');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    updateRingkasanNavbar();
    btnStyleRefresh();
    applyFilters();
});
