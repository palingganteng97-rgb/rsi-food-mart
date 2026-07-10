// catalog_handler.js

let currentDietFilter = '';
let currentQuery = '';
let keranjangBelanja = []; 
let detailProductModalInstance = null;

const grid = document.getElementById('catalogGrid');

function openDetailProduct(data) {
    document.getElementById('detail_product_name').innerText = data.name;
    document.getElementById('detail_product_category').innerText = data.category_name || 'General';
    document.getElementById('detail_product_description').innerText = data.description || 'Tidak ada deskripsi untuk menu sehat ini.';
    
    const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.base_price);
    document.getElementById('detail_product_price').innerText = formattedPrice;

    // =========================================================================
    // LOGIKA DINAMIS: MEMUAT VARIAN PRODUK DARI DATABASE KE DROPDOWN
    // =========================================================================
    const variantSelect = document.getElementById('detail_product_variant_select');
    if (variantSelect) {
        variantSelect.innerHTML = '<option value="">Memuat varian...</option>';
        fetch(`get_variants.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(variants => {
                variantSelect.innerHTML = '';
                if (Array.isArray(variants) && variants.length > 0) {
                    variants.forEach(v => {
                        const option = document.createElement('option');
                        option.value = v.id;
                        option.className = 'bg-dark text-white';
                        option.innerText = v.name;
                        variantSelect.appendChild(option);
                    });
                } else {
                    variantSelect.innerHTML = '<option value="" class="bg-dark text-white">Original / Normal</option>';
                }
            })
            .catch(err => { variantSelect.innerHTML = '<option value="">Gagal memuat</option>'; });
    }

        // =========================================================================
    // LOGIKA DINAMIS: MEMUAT RIWAYAT ULASAN & TESTIMONI DARI DATABASE
    // =========================================================================
    const reviewsContainer = document.getElementById('detail_product_reviews_container');
    if (reviewsContainer) {
        reviewsContainer.innerHTML = '<div class="text-white-50 small"><div class="spinner-border spinner-border-sm text-warning me-2"></div>Memuat ulasan pasien...</div>';
        
        fetch(`get_reviews.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(reviews => {
                reviewsContainer.innerHTML = ''; // Bersihkan loading
                
                if (Array.isArray(reviews) && reviews.length > 0) {
                    reviews.forEach(r => {
                        // Membuat representasi visual bintang emas (★) dan abu-abu (☆)
                        let starsHtml = '';
                        const starCount = parseInt(r.rating);
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += i <= starCount ? '★' : '☆';
                        }

                        // Menyuntikkan template boks ulasan dengan tema premium gelap transparan
                        reviewsContainer.innerHTML += `
                            <div class="p-3 rounded-3" style="background: rgba(2, 6, 23, 0.4); border: 1px solid rgba(148, 163, 184, 0.1);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-warning fw-bold" style="letter-spacing: 1px;">${starsHtml}</span>
                                    <span class="text-muted small" style="font-size: 0.75rem;">Pasien RSI</span>
                                </div>
                                <p class="text-light-50 m-0 small" style="line-height: 1.5;">"${r.review}"</p>
                            </div>
                        `;
                    });
                } else {
                    // Tampilan jika produk tersebut belum pernah diulas oleh pasien mana pun
                    reviewsContainer.innerHTML = '<div class="text-white-50 small opacity-50 text-center py-2">Belum ada ulasan untuk menu sehat ini.</div>';
                }
            })
            .catch(err => {
                console.error('Error fetching reviews:', err);
                reviewsContainer.innerHTML = '<div class="text-danger small">Gagal memuat ulasan produk.</div>';
            });
    }

    // =========================================================================
    // LOGIKA DINAMIS: MEMUAT TOPPING / ADDON PRODUK (SELECT DROPDOWN)
    // =========================================================================
    const addonsSelect = document.getElementById('detail_product_addons_select');
    if (addonsSelect) {
        addonsSelect.innerHTML = '<option value="" class="bg-dark text-white">Memuat topping...</option>';
        
        fetch(`get_addons.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(addons => {
                addonsSelect.innerHTML = ''; // Bersihkan loading
                
                // Tambahkan opsi default teratas jika sifatnya opsional
                const defaultOption = document.createElement('option');
                defaultOption.value = "";
                defaultOption.className = "bg-dark text-white";
                defaultOption.innerText = "-- Tanpa Topping Tambahan --";
                addonsSelect.appendChild(defaultOption);

                if (Array.isArray(addons) && addons.length > 0) {
                    addons.forEach(addon => {
                        const option = document.createElement('option');
                        option.value = addon.addon_name;
                        option.className = 'bg-dark text-white';
                        
                        // Tandai label teks jika diatur sebagai topping wajib beli di database
                        const badgeRequired = parseInt(addon.required) === 1 ? ' (Wajib)' : '';
                        option.innerText = addon.addon_name + badgeRequired;
                        
                        addonsSelect.appendChild(option);
                    });
                }
            })
            .catch(err => { 
                console.error(err);
                addonsSelect.innerHTML = '<option value="" class="bg-dark text-white">Gagal memuat topping</option>'; 
            });
    }

    // =========================================================================
    // LOGIKA DINAMIS: RESET AREA INPUTAN TEXT CATATAN KUSTOM SETIAP BUKA MODAL
    // =========================================================================
    const notesInput = document.getElementById('detail_product_notes_input');
    if (notesInput) { notesInput.value = ""; }

    // =========================================================================
    // LOGIKA SLIDER GAMBAR GALERI
    // =========================================================================
    const carouselInner = document.getElementById('detail_product_carousel_inner');
    const prevBtn = document.getElementById('carousel_btn_prev');
    const nextBtn = document.getElementById('carousel_btn_next');
    
    if (carouselInner) {
        carouselInner.innerHTML = '<div class="text-center p-3 text-white-50 small"><div class="spinner-border spinner-border-sm text-success me-2"></div>Memuat foto...</div>';
        fetch(`api_get_gallery.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(galleryImages => {
                carouselInner.innerHTML = ''; 
                let allImages = [];
                const mainImage = data.image ? `uploads/products/${data.image}` : 'uploads/products/default.png';
                allImages.push(mainImage);

                if (Array.isArray(galleryImages) && galleryImages.length > 0) {
                    galleryImages.forEach(imgName => { allImages.push(`uploads/products/gallery/${imgName}`); });
                }

                allImages.forEach((src, index) => {
                    const activeClass = index === 0 ? 'active' : '';
                    carouselInner.innerHTML += `
                        <div class="carousel-item ${activeClass}" style="overflow: hidden !important;">
                            <img src="${src}" class="d-block mx-auto" style="width: 380px !important; min-width: 380px !important; max-width: 380px !important; height: 320px !important; object-fit: cover; border-radius: 14px;" onerror="this.src='uploads/products/default.png'">
                        </div>
                    `;
                });

                if (prevBtn && nextBtn) {
                    const showControls = allImages.length > 1 ? 'block' : 'none';
                    prevBtn.style.display = showControls;
                    nextBtn.style.display = showControls;
                }
                const carouselEl = document.getElementById('carouselDetailProduct');
                if (carouselEl) { bootstrap.Carousel.getOrCreateInstance(carouselEl).to(0); }
            });
    }
    
    // =========================================================================
    // LOGIKA TOMBOL KERANJANG BELI (GABUNGAN VARIAN, TOPPING & CATATAN)
    // =========================================================================
    const cartBtn = document.getElementById('btn_detail_add_cart');
    cartBtn.onclick = function() {
        // 1. Ambil varian terpilh
        const selectedVariantText = variantSelect && variantSelect.options[variantSelect.selectedIndex] ? variantSelect.options[variantSelect.selectedIndex].text : '';
        let variantPart = (selectedVariantText && !selectedVariantText.includes('Original') && !selectedVariantText.includes('Memuat')) ? selectedVariantText : '';

        // 2. Ambil semua topping yang dicentang pembeli
        let selectedToppings = [];
        document.querySelectorAll('.product-addon-checkbox:checked').forEach(cb => {
            selectedToppings.push(cb.value);
        });
        let toppingPart = selectedToppings.length > 0 ? 'Topping: ' + selectedToppings.join(', ') : '';

        // Gabungkan varian dan topping ke nama produk akhir
        let extraInfo = [variantPart, toppingPart].filter(Boolean).join(' | ');
        const finalProductName = extraInfo ? `${data.name} (${extraInfo})` : data.name;
        
        // 3. Ambil catatan ketikan kustom
        const userNotes = notesInput ? notesInput.value.trim() : "";
        
        tambahKeKeranjang(data.id, finalProductName, data.base_price, data.image, userNotes);
        
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
