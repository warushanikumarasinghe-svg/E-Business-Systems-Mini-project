// InCart Main Vanilla JavaScript Engine
document.addEventListener('DOMContentLoaded', () => {

  // 1. Toast Notification System
  window.showToast = function(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> <span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  };

  // 2. Dynamic Image Gallery Switcher (Product Details Page)
  const thumbs = document.querySelectorAll('.thumb');
  const mainImg = document.getElementById('mainProductImg');
  if (thumbs.length > 0 && mainImg) {
    thumbs.forEach(thumb => {
      thumb.addEventListener('click', function() {
        thumbs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        mainImg.src = this.src;
      });
    });
  }

  // 3. Product Tabs Switcher
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');
  if (tabBtns.length > 0) {
    tabBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const target = this.getAttribute('data-tab');
        tabBtns.forEach(b => b.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        const targetEl = document.getElementById(target);
        if (targetEl) targetEl.classList.add('active');
      });
    });
  }

  // 4. Quantity Controls (+ / -)
  const qtyMinus = document.querySelectorAll('.qty-minus');
  const qtyPlus = document.querySelectorAll('.qty-plus');
  qtyMinus.forEach(btn => {
    btn.addEventListener('click', function() {
      const input = this.nextElementSibling;
      let val = parseInt(input.value) || 1;
      if (val > 1) {
        input.value = val - 1;
        input.dispatchEvent(new Event('change'));
      }
    });
  });
  qtyPlus.forEach(btn => {
    btn.addEventListener('click', function() {
      const input = this.previousElementSibling;
      let val = parseInt(input.value) || 1;
      const max = parseInt(input.getAttribute('max')) || 99;
      if (val < max) {
        input.value = val + 1;
        input.dispatchEvent(new Event('change'));
      } else {
        showToast('Maximum available stock limit reached', 'error');
      }
    });
  });

  // 5. AJAX Add to Cart
  const addCartBtns = document.querySelectorAll('.add-cart-btn');
  addCartBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const productId = this.getAttribute('data-id');
      const qtyInput = document.getElementById('productQty');
      const qty = qtyInput ? parseInt(qtyInput.value) : 1;

      fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${productId}&quantity=${qty}`
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(data.message, 'success');
          // Update cart badge in header
          const badge = document.getElementById('headerCartBadge');
          if (badge) badge.innerText = data.cart_count;
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(err => {
        showToast('Failed to update cart. Try again.', 'error');
      });
    });
  });

  // 6. AJAX Wishlist Toggle
  const wishlistBtns = document.querySelectorAll('.wishlist-btn');
  wishlistBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const productId = this.getAttribute('data-id');

      fetch('api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&product_id=${productId}`
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(data.message, 'success');
          if (data.is_in_wishlist) {
            this.classList.add('active');
            this.querySelector('i').className = 'fas fa-heart';
          } else {
            this.classList.remove('active');
            this.querySelector('i').className = 'far fa-heart';
          }
          const badge = document.getElementById('headerWishlistBadge');
          if (badge) badge.innerText = data.wishlist_count;
        } else {
          showToast(data.message, 'error');
          if (data.require_login) {
            setTimeout(() => window.location.href = 'login.php', 1200);
          }
        }
      })
      .catch(err => {
        showToast('Wishlist operation failed.', 'error');
      });
    });
  });

  // 7. Sort Dropdown Change on Shop Page
  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) {
    sortSelect.addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('sort', this.value);
      window.location.href = url.toString();
    });
  }

});
