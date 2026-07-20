// catalog_handler.js

let currentDietFilter = '';
let currentQuery = '';
let keranjangBelanja = []; 
let detailProductModalInstance = null;

const grid = document.getElementById('catalogGrid');

function openDetailProduct(data) {
    // Mode global untuk membedakan tombol: tambah vs simpan perubahan
    // default: tambah ke keranjang
    window.__detailMode = window.__detailMode || 'add';


    // default label & mode
    const cartBtn = document.getElementById('btn_detail_add_cart');
    // prevent duplicate declarations from earlier merge versions
    if (cartBtn) {
        cartBtn.innerHTML = '<i class="bi bi-cart-plus-fill"></i> Tambah ke Keranjang';
        cartBtn.classList.remove('btn-warning');
    }
    document.getElementById('detail_product_name').innerText = data.name;
    document.getElementById('detail_product_category').innerText = data.category_name || 'General';
    document.getElementById('detail_product_description').innerText = data.description || 'Tidak ada deskripsi untuk menu sehat ini.';
    
    const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.base_price);
    document.getElementById('detail_product_price').innerText = formattedPrice;

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

    const reviewsContainer = document.getElementById('detail_product_reviews_container');
    if (reviewsContainer) {
        reviewsContainer.innerHTML = '<div class="text-white-50 small"><div class="spinner-border spinner-border-sm text-warning me-2"></div>Memuat ulasan pasien...</div>';
        
fetch(`get_reviews.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(payload => {
                reviewsContainer.innerHTML = '';

                const latest = payload && Array.isArray(payload.reviews) ? payload.reviews : [];
                if (latest.length > 0) {
                    latest.forEach(r => {
                        let starsHtml = '';
                        const starCount = parseInt(r.rating);
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += i <= starCount ? '★' : '☆';
                        }

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
                    reviewsContainer.innerHTML = '<div class="text-white-50 small opacity-50 text-center py-2">Belum ada ulasan untuk menu sehat ini.</div>';
                }
            })
            .catch(err => {
                console.error('Error fetching reviews:', err);
                reviewsContainer.innerHTML = '<div class="text-danger small">Gagal memuat ulasan produk.</div>';
            });
    }

    const addonsContainer = document.getElementById('detail_product_addons_container');
    if (addonsContainer) {
        addonsContainer.innerHTML = '<div class="text-white-50 small"><div class="spinner-border spinner-border-sm text-success me-2"></div>Memuat topping...</div>';
        
        fetch(`get_addon_items.php?product_id=${data.id}`)
            .then(response => response.json())
            .then(addons => {
                addonsContainer.innerHTML = ''; // Bersihkan tulisan loading
                
                if (Array.isArray(addons) && addons.length > 0) {
                    addons.forEach(item => {
                        const formattedPrice = new Intl.NumberFormat('id-ID').format(item.price);
                        
                        // Membuat elemen bungkus untuk checkbox bergaya modern premium
                        const checkWrapper = document.createElement('div');
                        checkWrapper.className = 'form-check d-flex align-items-center justify-content-between p-2 rounded-2';
                        checkWrapper.style.border = '1px solid rgba(148, 163, 184, 0.08)';
                        checkWrapper.style.background = 'rgba(15, 23, 42, 0.3)';
                        
                        // Struktur Checkbox HTML
                        checkWrapper.innerHTML = `
                            <div class="d-flex align-items-center gap-2">
                                <input class="form-check-input addon-checkbox" type="checkbox" value="${item.id}" id="addon_${item.id}" data-price="${item.price}" style="cursor: pointer; background-color: rgba(2, 6, 23, 0.5); border-color: rgba(148, 163, 184, 0.3);">
                                <label class="form-check-label text-white small" for="addon_${item.id}" style="cursor: pointer; user-select: none;">
                                    ${item.item_name}
                                </label>
                            </div>
                            <span class="text-success small fw-medium">+ Rp ${formattedPrice}</span>
                        `;
                        
                        addonsContainer.appendChild(checkWrapper);
                    });

                    // Pasang event listener ke setiap checkbox baru untuk hitung harga realtime
                    const checkboxes = addonsContainer.querySelectorAll('.addon-checkbox');
                    checkboxes.forEach(cb => {
                        cb.onchange = function() {
                            updateTotalPrice(data.base_price);
                        };
                    });

                } else {
                    addonsContainer.innerHTML = '<div class="text-white-50 small opacity-50 text-center py-1">Tidak ada topping tambahan.</div>';
                }
            })
            .catch(err => {
                console.error(err);
                addonsContainer.innerHTML = '<div class="text-danger small">Gagal memuat topping</div>';
            });
    }

    // Fungsi pembantu baru untuk menjumlahkan semua topping yang dicentang secara realtime
    function updateTotalPrice(baseProductPrice) {
        let total = parseFloat(baseProductPrice) || 0;
        
        // Ambil semua checkbox yang berstatus dicentang (:checked)
        const checkedAddons = document.querySelectorAll('.addon-checkbox:checked');
        checkedAddons.forEach(cb => {
            total += parseFloat(cb.dataset.price) || 0;
        });
        
        // Update teks harga total di footer modal
        const formattedTotal = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
        const priceContainer = document.getElementById('detail_product_price');
        if (priceContainer) {
            priceContainer.innerText = formattedTotal;
        }
    }

    const notesInput = document.getElementById('detail_product_notes_input');
    if (notesInput) { notesInput.value = ""; }

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
    


    const cartBtnLocal = document.getElementById('btn_detail_add_cart');
    cartBtnLocal.onclick = function() {
        // Mode edit: simpan perubahan ke item keranjang yang sedang diedit
        if (window.__detailMode === 'edit' && window.__editCartKey && window.__editProductId) {
            // kumpulkan varian/topping saat ini
            const variantSelectLocal = document.getElementById('detail_product_variant_select');
            const variantId = variantSelectLocal && variantSelectLocal.value ? variantSelectLocal.value : '';

            const addonsIds = [];
            document.querySelectorAll('.addon-checkbox:checked').forEach(cb => {
                addonsIds.push(cb.value);
            });

            const userNotes = notesInput ? notesInput.value.trim() : "";

            const formData = new FormData();
            formData.append('old_key', window.__editCartKey);
            formData.append('id', data.id);
            formData.append('name', finalProductName || data.name);
            formData.append('price', data.base_price);
            formData.append('image', data.image);
            formData.append('notes', userNotes);
            if (variantId) formData.append('variant', variantId);
            addonsIds.forEach(aid => formData.append('addons[]', aid));

            fetch('api_cart.php?action=update_saved', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res && res.success) {
                    const modalEl = document.getElementById('modalDetailProduct');
                    const instance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                    if (instance) instance.hide();
                    window.location.href = 'keranjang.php';
                }
            })
            .catch(console.error);
            return;
        }
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

        // Untuk tombol dari MODAL: tetap gunakan tambahKeKeranjang, tapi masukkan varian & topping
        // lewat payload yang dibangun di bawah.
        const variantSelectLocal = document.getElementById('detail_product_variant_select');
        const variantIdModal = variantSelectLocal && variantSelectLocal.value ? variantSelectLocal.value : '';

        const addonsIdsModal = [];
        document.querySelectorAll('.addon-checkbox:checked').forEach(cb => addonsIdsModal.push(cb.value));

        const formData = new FormData();
        formData.append('id', data.id);
        formData.append('name', finalProductName);
        formData.append('price', data.base_price);
        formData.append('image', data.image);
        formData.append('notes', userNotes);
        if (variantIdModal) formData.append('variant', variantIdModal);
        addonsIdsModal.forEach(aid => formData.append('addons[]', aid));

        fetch('api_cart.php?action=add', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) updateCartBadgeDisplay(res.total_items);
        })
        .catch(err => console.error('add from modal error:', err));


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

function tambahKeKeranjang(id, name, price, image, notes) { 
    // Fungsi ini dipakai oleh tombol '+' di card (inline onclick di home.php)
    // Agar konsisten untuk semua produk, JANGAN bergantung pada DOM modal detail.
    const userNotes = notes ? String(notes) : '';

    const formData = new FormData();
    formData.append('id', id);
    formData.append('name', name);
    formData.append('price', price);
    formData.append('image', image);
    formData.append('notes', userNotes);

    // Pastikan payload tetap valid meskipun modal belum pernah dibuka.
    // Untuk tombol card: default tanpa varian & topping.

    fetch('api_cart.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.success) {
            updateCartBadgeDisplay(data.total_items);
        } else {
            console.error('Failed add to cart:', data);
        }
    })
    .catch(err => console.error('add to cart error:', err));
}


function updateCartBadgeDisplay(totalItems) {
    const badgeEl = document.getElementById('totalCartItemsCount'); 
    if (badgeEl) {
        badgeEl.innerText = totalItems + " item";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    fetch('api_cart.php?action=get_total')
        .then(response => response.json())
        .then(data => {
            updateCartBadgeDisplay(data.total_items);
        })
        .catch(err => console.error(err));
});

// NOTE: duplicate updateCartBadgeDisplay removed (kept the first definition).


// expose global functions for inline onclick handlers
window.openDetailProduct = openDetailProduct;
window.tambahKeKeranjang = tambahKeKeranjang;
window.updateQty = window.updateQty;
window.loadProductDetail = window.loadProductDetail;
window.closeDetailModal = window.closeDetailModal;


function openCart() {
    window.openCart = openCart;
    const bodyContainer = document.getElementById('cartModalBody');
    const totalContainer = document.getElementById('cartModalTotal');
    const btnCheckout = document.getElementById('btnCheckout');
    
    if(!bodyContainer || !totalContainer || !btnCheckout) return;

    let myModal = bootstrap.Modal.getInstance(document.getElementById('modalCartDetail'));
    if (!myModal) {
        myModal = new bootstrap.Modal(document.getElementById('modalCartDetail'));
    }
    myModal.show();

    bodyContainer.innerHTML = '<div class="text-center py-4 text-white-50 small"><div class="spinner-border spinner-border-sm text-success me-2"></div>Memuat data keranjang...</div>';

    fetch('api_cart.php?action=get_cart_items')
        .then(response => response.json())
        .then(cartItems => {
            if (Array.isArray(cartItems) && cartItems.length > 0) {
                bodyContainer.innerHTML = ''; 
                let grandTotal = 0;

                cartItems.forEach(item => {
                    const subtotalItem = item.price * item.qty;
                    grandTotal += subtotalItem;

                    const formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(item.price);
                    const formattedSubtotal = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotalItem);

                    bodyContainer.innerHTML += `
                    <div class="d-flex align-items-center mb-3 border-bottom pb-2" style="border-color: rgba(148, 163, 184, 0.12) !important;">
                        <img src="uploads/products/${item.image}" style="width:50px; height:50px; object-fit:cover;" class="rounded me-3" onerror="this.src='uploads/products/default.png'">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 text-white small fw-semibold">${item.name}</h6>
                            <small class="text-muted">${formattedPrice} x ${item.qty}</small>
                            ${item.notes ? `<div class="text-warning small mt-0.5" style="font-size: 0.75rem;"><i class="bi bi-chat-left-text me-1"></i>${item.notes}</div>` : ''}
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
        })
        .catch(err => {
            console.error(err);
            bodyContainer.innerHTML = '<div class="text-center py-4 text-danger small">Gagal memuat data keranjang.</div>';
        });
}

document.addEventListener("DOMContentLoaded", function() {
    fetch('api_cart.php?action=get_total')
        .then(response => response.json())
        .then(data => {
            updateCartBadgeDisplay(data.total_items);
        })
        .catch(err => console.error(err));
});
