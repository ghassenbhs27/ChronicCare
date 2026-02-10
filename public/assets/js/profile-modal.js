(() => {
  const modalEl = document.getElementById('profileModal');
  const modalBody = document.getElementById('profileModalBody');
  if (!modalEl || !modalBody) return;

  const bsModal = new bootstrap.Modal(modalEl);

  function setLoader() {
    modalBody.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border" role="status" aria-label="Loading"></div>
      </div>
    `;
  }

  function setError(message) {
    modalBody.innerHTML = `
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>${message}</div>
      </div>
    `;
  }

  async function load(url, options = {}) {
    try {
      setLoader();

      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
        ...options,
      });

      // si redirection (ex: vers /login)
      if (res.redirected) {
        window.location.href = res.url;
        return;
      }

      const html = await res.text();

      if (!res.ok) {
        setError(html?.trim() ? html : `Erreur HTTP ${res.status}`);
        return;
      }

      modalBody.innerHTML = html;
    } catch (e) {
      console.error(e);
      setError("Impossible de charger le profil. Vérifie la console / Network.");
    }
  }

  // Ouvrir modal depuis bouton Profil (navbar)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-open-profile-modal]');
    if (!btn) return;

    e.preventDefault();
    bsModal.show();

    const url = btn.getAttribute('data-url');
    if (!url) return setError("URL du profil manquante.");
    await load(url);
  });

  // Boutons داخل modal (Modifier / Annuler)
  document.addEventListener('click', async (e) => {
    const actionBtn = e.target.closest('[data-profile-action]');
    if (!actionBtn) return;

    e.preventDefault();
    const url = actionBtn.getAttribute('data-url');
    if (!url) return;

    await load(url);
  });

  // Submit form en AJAX (UNE SEULE FOIS)
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#profile-edit-form');
    if (!form) return;

    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    const oldHtml = submitBtn ? submitBtn.innerHTML : null;

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...`;
    }

    await load(form.action, {
      method: 'POST',
      body: new FormData(form),
    });

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldHtml;
    }
  });
})();
