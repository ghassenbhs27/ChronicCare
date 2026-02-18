/**
* Template Name: MediTrust
* Template URL: https://bootstrapmade.com/meditrust-bootstrap-hospital-website-template/
* Updated: Jul 04 2025 with Bootstrap v5.3.7
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";
  window.__ccAjaxFilterReady = true;

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader) return;
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

  function mobileNavToogle() {
    document.querySelector('body').classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  }
  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
  }

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', () => {
      if (document.querySelector('.mobile-nav-active')) {
        mobileNavToogle();
      }
    });

  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(navmenu => {
    navmenu.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentNode.classList.toggle('active');
      this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  if (scrollTop) {
    scrollTop.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    if (typeof AOS === 'undefined') return;
    AOS.init({
      duration: 600,
      easing: 'ease-in-out',
      once: true,
      mirror: false
    });
  }
  window.addEventListener('load', aosInit);

  /**
   * Initiate Pure Counter
   */
  if (typeof PureCounter !== 'undefined') {
    new PureCounter();
  }

  /**
   * Init swiper sliders
   */
  function initSwiper() {
    if (typeof Swiper === 'undefined') return;
    document.querySelectorAll(".init-swiper").forEach(function(swiperElement) {
      var cfgNode = swiperElement.querySelector(".swiper-config");
      if (!cfgNode) return;
      let config = {};
      try {
        config = JSON.parse(cfgNode.innerHTML.trim());
      } catch (e) {
        return;
      }

      if (swiperElement.classList.contains("swiper-tab") && typeof initSwiperWithCustomPagination === 'function') {
        initSwiperWithCustomPagination(swiperElement, config);
      } else {
        new Swiper(swiperElement, config);
      }
    });
  }

  window.addEventListener("load", initSwiper);

  /**
   * Init isotope layout and filters
   */
  if (typeof imagesLoaded !== 'undefined' && typeof Isotope !== 'undefined') {
    document.querySelectorAll('.isotope-layout').forEach(function(isotopeItem) {
    let layout = isotopeItem.getAttribute('data-layout') ?? 'masonry';
    let filter = isotopeItem.getAttribute('data-default-filter') ?? '*';
    let sort = isotopeItem.getAttribute('data-sort') ?? 'original-order';

    let initIsotope;
    imagesLoaded(isotopeItem.querySelector('.isotope-container'), function() {
      initIsotope = new Isotope(isotopeItem.querySelector('.isotope-container'), {
        itemSelector: '.isotope-item',
        layoutMode: layout,
        filter: filter,
        sortBy: sort
      });
    });

    isotopeItem.querySelectorAll('.isotope-filters li').forEach(function(filters) {
      filters.addEventListener('click', function() {
        isotopeItem.querySelector('.isotope-filters .filter-active').classList.remove('filter-active');
        this.classList.add('filter-active');
        initIsotope.arrange({
          filter: this.getAttribute('data-filter')
        });
        if (typeof aosInit === 'function') {
          aosInit();
        }
      }, false);
    });

    });
  }

  /**
   * Initiate glightbox
   */
  if (typeof GLightbox !== 'undefined') {
    GLightbox({
      selector: '.glightbox'
    });
  }

  /**
   * Frequently Asked Questions Toggle
   */
  document.querySelectorAll('.faq-item h3, .faq-item .faq-toggle, .faq-item .faq-header').forEach((faqItem) => {
    faqItem.addEventListener('click', () => {
      faqItem.parentNode.classList.toggle('faq-active');
    });
  });

  /**
   * Auto-submit filter forms (GET) when user types/selects criteria.
   */
  function initAutoFilterForms() {
    if (window.__ccAutoFilterBound) return;
    window.__ccAutoFilterBound = true;

    const filterFieldNames = new Set([
      'q', 'search', 'keyword', 'categorie', 'category',
      'sort', 'order', 'dir', 'status', 'statut',
      'evenement', 'priceMin', 'priceMax', 'role',
      'tri', 'direction', 'statusFilter', 'roleFilter'
    ]);

    const debounce = (fn, wait) => {
      let timer;
      return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
      };
    };

    const buildUrlFromForm = (form) => {
      const action = form.getAttribute('action') || window.location.pathname;
      const url = new URL(action, window.location.origin);
      const formData = new FormData(form);

      for (const [key, value] of formData.entries()) {
        if (key === '_token') continue;
        const stringValue = String(value ?? '').trim();
        if (stringValue === '') {
          url.searchParams.delete(key);
          continue;
        }
        url.searchParams.set(key, stringValue);
      }

      return url;
    };

    const getCurrentContentTarget = () => {
      return (
        document.querySelector('main.main') ||
        document.querySelector('main.flex-grow-1') ||
        document.querySelector('main')
      );
    };

    const getNextContentTarget = (doc) => {
      return (
        doc.querySelector('main.main') ||
        doc.querySelector('main.flex-grow-1') ||
        doc.querySelector('main')
      );
    };

    const normalize = (value) => {
      return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
    };

    const applyInstantRowFilter = (form) => {
      const qField =
        form.querySelector('input[name="q"]') ||
        form.querySelector('input[name="search"]') ||
        form.querySelector('input[name="keyword"]');
      if (!qField) return;

      const term = normalize(qField.value || '');
      const tokens = term.split(/\s+/).filter(Boolean);
      const table = document.querySelector('table');
      if (!table) return;
      const rows = Array.from(table.querySelectorAll('tbody tr'));
      if (rows.length === 0) return;

      rows.forEach((row) => {
        if (row.querySelector('td[colspan]')) return;
        const text = normalize(row.textContent || '');
        const match = tokens.length === 0 || tokens.every((token) => text.includes(token));
        row.style.display = match ? '' : 'none';
      });
    };

    const setLoadingState = (form, isLoading) => {
      const fields = form.querySelectorAll('input, select, textarea, button');
      fields.forEach((el) => {
        if (!(el instanceof HTMLElement)) return;
        if (isLoading) el.classList.add('cc-filter-loading');
        else el.classList.remove('cc-filter-loading');
      });
    };

    const refreshMainWithUrl = async (url, form) => {
      if (form.__autoFilterAbortController) {
        form.__autoFilterAbortController.abort();
      }

      const controller = new AbortController();
      form.__autoFilterAbortController = controller;
      setLoadingState(form, true);

      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error('Filter request failed');
      }

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const nextMain = getNextContentTarget(doc);
      const currentMain = getCurrentContentTarget();

      if (nextMain && currentMain) {
        currentMain.replaceWith(nextMain);
      } else {
        const fragmentTarget =
          document.querySelector('[data-list-container]') ||
          document.querySelector('#user-list-container') ||
          document.querySelector('[id$="list-container"]');

        if (fragmentTarget) {
          fragmentTarget.innerHTML = html;
        } else {
          window.location.assign(url.toString());
          return;
        }
      }

      window.history.replaceState({}, '', url.pathname + url.search + url.hash);
      bootInteractiveFeatures();
      setLoadingState(form, false);
    };

    const submitFilterForm = (form) => {
      const pageInput = form.querySelector('input[name="page"]');
      if (pageInput) {
        pageInput.value = '1';
      }

      const url = buildUrlFromForm(form);
      const hasList = document.querySelector('main.main table, main.main .table-responsive, main.main .pagination, main.main [data-list-container]');

      if (hasList) {
        applyInstantRowFilter(form);
        refreshMainWithUrl(url, form).catch((error) => {
          if (error && error.name === 'AbortError') return;
          window.location.assign(url.toString());
        }).finally(() => {
          setLoadingState(form, false);
        });
        return;
      }

      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    };

    const isFilterForm = (form) => {
      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'get') return false;

      const action = (form.getAttribute('action') || '').toLowerCase();
      if (action.includes('/export') || action.includes('excel') || action.includes('pdf') || action.includes('csv')) {
        return false;
      }

      const fields = Array.from(form.querySelectorAll('input[name], select[name], textarea[name]')).filter((field) => {
        const n = (field.name || '').trim();
        return n !== '' && n !== '_token' && n !== 'page';
      });

      if (fields.length === 0) return false;

      const hasFilterField = fields.some((field) => filterFieldNames.has((field.name || '').trim()));
      const hasTextSearch = fields.some((field) => {
        const type = (field.getAttribute('type') || '').toLowerCase();
        return type === 'text' || type === 'search' || field.tagName.toLowerCase() === 'select';
      });
      return hasFilterField || hasTextSearch || form.className.toLowerCase().includes('filter');
    };

    const debouncedByForm = new WeakMap();
    const getDebouncedSubmit = (form) => {
      if (debouncedByForm.has(form)) {
        return debouncedByForm.get(form);
      }
      const fn = debounce(() => submitFilterForm(form), 60);
      debouncedByForm.set(form, fn);
      return fn;
    };

    const isTextLikeField = (field) => {
      const tag = field.tagName.toLowerCase();
      const type = (field.getAttribute('type') || '').toLowerCase();
      return tag === 'textarea' || type === 'text' || type === 'search' || type === 'number';
    };

    const isImmediateField = (field) => {
      const tag = field.tagName.toLowerCase();
      const type = (field.getAttribute('type') || '').toLowerCase();
      return tag === 'select' || type === 'checkbox' || type === 'radio' || type === 'date';
    };

    const getFieldFromEvent = (event) => {
      const target = event.target;
      if (!target || !(target instanceof Element)) return null;
      if (!target.matches('input[name], select[name], textarea[name]')) return null;
      return target;
    };

    document.addEventListener('input', (event) => {
      const field = getFieldFromEvent(event);
      if (!field || !isTextLikeField(field)) return;
      const form = field.closest('form');
      if (!form || !isFilterForm(form)) return;
      getDebouncedSubmit(form)();
    }, true);

    document.addEventListener('change', (event) => {
      const field = getFieldFromEvent(event);
      if (!field || !isImmediateField(field)) return;
      const form = field.closest('form');
      if (!form || !isFilterForm(form)) return;
      submitFilterForm(form);
    }, true);

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      const field = getFieldFromEvent(event);
      if (!field || !isTextLikeField(field)) return;
      const form = field.closest('form');
      if (!form || !isFilterForm(form)) return;
      event.preventDefault();
      submitFilterForm(form);
    }, true);

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!isFilterForm(form)) return;
      event.preventDefault();
      submitFilterForm(form);
    });

    document.addEventListener('click', (event) => {
      const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
      if (!link) return;

      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

      const url = new URL(link.href, window.location.origin);
      if (url.origin !== window.location.origin) return;
      if (url.pathname !== window.location.pathname) return;

      const keys = ['page', 'q', 'search', 'keyword', 'sort', 'order', 'dir', 'tri', 'direction', 'statut', 'status', 'categorie', 'evenement', 'role'];
      const hasListQuery = keys.some((key) => url.searchParams.has(key));
      if (!hasListQuery) return;

      const listForm = document.querySelector('form[method="get"], form[method="GET"]');
      if (!listForm) return;
      if (!isFilterForm(listForm)) return;

      event.preventDefault();
      refreshMainWithUrl(url, listForm).catch(() => window.location.assign(url.toString()));
    }, true);
  }

  /**
   * Client-side validation without full refresh on invalid submit.
   */
  function initLiveFormValidation() {
    const fieldSelector = 'input, select, textarea';

    document.querySelectorAll('form').forEach((form) => {
      if (form.dataset.liveValidationBound === '1') return;

      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method === 'get') return;
      form.dataset.liveValidationBound = '1';

      const fields = Array.from(form.querySelectorAll(fieldSelector)).filter((field) => {
        const type = (field.getAttribute('type') || '').toLowerCase();
        return !field.disabled && type !== 'hidden' && type !== 'submit' && type !== 'button' && type !== 'reset';
      });

      const updateFieldState = (field) => {
        const feedbackId = field.id ? (field.id + '-feedback') : '';
        let feedback = feedbackId ? form.querySelector('#' + feedbackId) : null;
        if (!feedback) {
          feedback = document.createElement('div');
          if (feedbackId) feedback.id = feedbackId;
          feedback.className = 'invalid-feedback';
          field.insertAdjacentElement('afterend', feedback);
        }

        if (field.checkValidity()) {
          field.classList.remove('is-invalid');
          field.classList.add('is-valid');
          feedback.textContent = '';
        } else {
          field.classList.remove('is-valid');
          field.classList.add('is-invalid');
          feedback.textContent = field.validationMessage || 'Valeur invalide';
        }
      };

      fields.forEach((field) => {
        field.addEventListener('input', () => {
          if (form.classList.contains('was-validated')) {
            updateFieldState(field);
          }
        });
        field.addEventListener('change', () => {
          if (form.classList.contains('was-validated')) {
            updateFieldState(field);
          }
        });
      });

      form.addEventListener('submit', (event) => {
        if (form.checkValidity()) return;

        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');
        fields.forEach(updateFieldState);

        const firstInvalid = fields.find((field) => !field.checkValidity());
        if (firstInvalid) {
          firstInvalid.focus();
          firstInvalid.reportValidity();
        }
      });
    });
  }

  /**
   * Auto-refresh list pages (keeps active filters in query string).
   */
  function initAutoListRefresh() {
    if (window.__ccAutoListRefreshBound) return;
    window.__ccAutoListRefreshBound = true;

    // Disabled by default to avoid UI lag; enable only when explicitly requested.
    if (!document.body.hasAttribute('data-auto-list-refresh')) return;

    const hasList = document.querySelector('table, .table-responsive, .pagination, [data-list-container]') !== null;
    if (!hasList) return;

    const getForms = Array.from(document.querySelectorAll('form')).filter((form) => {
      const method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'get') return false;

      const action = (form.getAttribute('action') || '').toLowerCase();
      if (action.includes('/export') || action.includes('excel') || action.includes('pdf') || action.includes('csv')) {
        return false;
      }

      return form.querySelector('input[name], select[name], textarea[name]') !== null;
    });

    if (getForms.length === 0) return;

    const intervalMs = 120000;
    setInterval(() => {
      if (document.hidden) return;

      const active = document.activeElement;
      if (active && ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName)) {
        return;
      }

      window.location.reload();
    }, intervalMs);
  }

  function bootInteractiveFeatures() {
    initAutoFilterForms();
    initLiveFormValidation();
    initAutoListRefresh();
  }

  bootInteractiveFeatures();

})();
