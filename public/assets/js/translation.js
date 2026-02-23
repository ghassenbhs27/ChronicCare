/**
 * Translation Manager for Forum
 * Handles individual translate buttons on publications and comments
 * Uses MyMemory API via backend
 */

class TranslationManager {
  constructor() {
    this.cache = this.loadCache();
    this.currentTranslations = new Map(); // Track current state of each element
    this.init();
  }

  /**
   * Initialize translation buttons
   */
  init() {
    this.attachTranslationButtons();
  }

  /**
   * Attach click handlers to all translation buttons
   */
  attachTranslationButtons() {
    // For publications (in index and show pages)
    document.querySelectorAll('[data-translate-publication]').forEach(btn => {
      btn.addEventListener('click', (e) => this.handlePublicationTranslation(e));
    });

    // For comments
    document.querySelectorAll('[data-translate-comment]').forEach(btn => {
      btn.addEventListener('click', (e) => this.handleCommentTranslation(e));
    });
  }

  /**
   * Handle publication translation
   */
  async handlePublicationTranslation(event) {
    event.preventDefault();
    const button = event.currentTarget;
    const publicationId = button.dataset.translatePublication;
    
    // Get the content elements
    const titleElement = document.querySelector(`[data-publication-title="${publicationId}"]`);
    const contentElement = document.querySelector(`[data-publication-content="${publicationId}"]`);

    if (!titleElement || !contentElement) {
      console.error('Publication elements not found');
      return;
    }

    // Check current state
    const isTranslated = this.currentTranslations.get(`pub-${publicationId}`) || false;

    if (isTranslated) {
      // Restore original
      this.restoreOriginalPublication(publicationId, titleElement, contentElement, button);
    } else {
      // Translate
      await this.translatePublication(publicationId, titleElement, contentElement, button);
    }
  }

  /**
   * Handle comment translation
   */
  async handleCommentTranslation(event) {
    event.preventDefault();
    const button = event.currentTarget;
    const commentId = button.dataset.translateComment;
    
    const contentElement = document.querySelector(`[data-comment-content="${commentId}"]`);

    if (!contentElement) {
      console.error('Comment element not found');
      return;
    }

    const isTranslated = this.currentTranslations.get(`com-${commentId}`) || false;

    if (isTranslated) {
      this.restoreOriginalComment(commentId, contentElement, button);
    } else {
      await this.translateComment(commentId, contentElement, button);
    }
  }

  /**
   * Translate publication title and content
   */
  async translatePublication(publicationId, titleElement, contentElement, button) {
    // Store originals if not already stored
    if (!titleElement.dataset.originalText) {
      titleElement.dataset.originalText = titleElement.textContent;
    }
    if (!contentElement.dataset.originalHtml) {
      contentElement.dataset.originalHtml = contentElement.innerHTML;
    }

    const originalTitle = titleElement.dataset.originalText;
    const originalContent = this.stripHtml(contentElement.dataset.originalHtml);

    // Show loading state
    this.setButtonLoading(button, true);

    try {
      // Detect source language
      const sourceLang = this.detectLanguage(originalTitle + ' ' + originalContent);
      const targetLang = sourceLang === 'fr' ? 'en' : 'fr';

      // Check cache
      const titleCacheKey = this.getCacheKey(originalTitle, sourceLang, targetLang);
      const contentCacheKey = this.getCacheKey(originalContent, sourceLang, targetLang);

      let translatedTitle, translatedContent;

      // Try cache first
      if (this.cache[titleCacheKey]) {
        translatedTitle = this.cache[titleCacheKey];
      } else {
        const titleResult = await this.translateText(originalTitle, sourceLang, targetLang);
        if (!titleResult.success) {
          throw new Error(titleResult.error || 'Translation failed');
        }
        translatedTitle = titleResult.translatedText;
        this.saveToCache(titleCacheKey, translatedTitle);
      }

      if (this.cache[contentCacheKey]) {
        translatedContent = this.cache[contentCacheKey];
      } else {
        const contentResult = await this.translateText(originalContent, sourceLang, targetLang);
        if (!contentResult.success) {
          throw new Error(contentResult.error || 'Translation failed');
        }
        translatedContent = contentResult.translatedText;
        this.saveToCache(contentCacheKey, translatedContent);
      }

      // Update UI
      titleElement.textContent = translatedTitle;
      contentElement.innerHTML = this.nl2br(this.escapeHtml(translatedContent));

      // Update button
      this.setButtonTranslated(button, targetLang);
      this.currentTranslations.set(`pub-${publicationId}`, true);

    } catch (error) {
      console.error('Translation error:', error);
      this.showError(button, error.message);
    } finally {
      this.setButtonLoading(button, false);
    }
  }

  /**
   * Translate comment content
   */
  async translateComment(commentId, contentElement, button) {
    // Store original if not already stored
    if (!contentElement.dataset.originalHtml) {
      contentElement.dataset.originalHtml = contentElement.innerHTML;
    }

    const originalContent = this.stripHtml(contentElement.dataset.originalHtml);

    this.setButtonLoading(button, true);

    try {
      const sourceLang = this.detectLanguage(originalContent);
      const targetLang = sourceLang === 'fr' ? 'en' : 'fr';

      const cacheKey = this.getCacheKey(originalContent, sourceLang, targetLang);

      let translatedContent;

      if (this.cache[cacheKey]) {
        translatedContent = this.cache[cacheKey];
      } else {
        const result = await this.translateText(originalContent, sourceLang, targetLang);
        if (!result.success) {
          throw new Error(result.error || 'Translation failed');
        }
        translatedContent = result.translatedText;
        this.saveToCache(cacheKey, translatedContent);
      }

      // Preserve the inline styles from original (for rich text formatting)
      const originalStyle = contentElement.getAttribute('style');
      contentElement.innerHTML = this.nl2br(this.escapeHtml(translatedContent));
      if (originalStyle) {
        contentElement.setAttribute('style', originalStyle);
      }

      this.setButtonTranslated(button, targetLang);
      this.currentTranslations.set(`com-${commentId}`, true);

    } catch (error) {
      console.error('Translation error:', error);
      this.showError(button, error.message);
    } finally {
      this.setButtonLoading(button, false);
    }
  }

  /**
   * Restore original publication
   */
  restoreOriginalPublication(publicationId, titleElement, contentElement, button) {
    titleElement.textContent = titleElement.dataset.originalText;
    contentElement.innerHTML = contentElement.dataset.originalHtml;
    
    this.setButtonOriginal(button);
    this.currentTranslations.set(`pub-${publicationId}`, false);
  }

  /**
   * Restore original comment
   */
  restoreOriginalComment(commentId, contentElement, button) {
    contentElement.innerHTML = contentElement.dataset.originalHtml;
    
    this.setButtonOriginal(button);
    this.currentTranslations.set(`com-${commentId}`, false);
  }

  /**
   * Call backend translation API
   */
  async translateText(text, sourceLang, targetLang) {
    const response = await fetch('/api/translation/translate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        text: text,
        sourceLang: sourceLang,
        targetLang: targetLang
      })
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'HTTP error ' + response.status);
    }

    return await response.json();
  }

  /**
   * Simple language detection (fr vs en)
   */
  detectLanguage(text) {
    const lowerText = text.toLowerCase();
    
    // French indicators
    const frenchScore = (
      (lowerText.match(/\b(le|la|les|de|et|un|une|des|dans|pour|que|qui|est|sont|avec)\b/g) || []).length +
      (lowerText.match(/[éèêëàâäùûüôöîïç]/g) || []).length * 2
    );

    // English indicators
    const englishScore = (
      (lowerText.match(/\b(the|is|are|and|of|to|in|for|that|with|on|at)\b/g) || []).length
    );

    return frenchScore > englishScore ? 'fr' : 'en';
  }

  /**
   * Update button to loading state
   */
  setButtonLoading(button, isLoading) {
    if (isLoading) {
      button.disabled = true;
      button.dataset.originalHtml = button.innerHTML;
      button.innerHTML = '<i class="bi bi-hourglass-split"></i> <span class="d-none d-sm-inline">Traduction...</span>';
      button.classList.add('translating');
    } else {
      button.disabled = false;
      button.classList.remove('translating');
    }
  }

  /**
   * Update button to translated state
   */
  setButtonTranslated(button, targetLang) {
    const langLabel = targetLang === 'en' ? 'EN' : 'FR';
    button.innerHTML = `<i class="bi bi-arrow-repeat"></i> <span class="d-none d-sm-inline">Original</span> <span class="translate-lang-badge">${langLabel}</span>`;
    button.classList.add('translated');
    button.title = 'Afficher le texte original';
  }

  /**
   * Update button to original state
   */
  setButtonOriginal(button) {
    button.innerHTML = '<i class="bi bi-translate"></i> <span class="d-none d-sm-inline">Traduire</span>';
    button.classList.remove('translated');
    button.title = 'Traduire ce contenu';
  }

  /**
   * Show error message
   */
  showError(button, message) {
    const originalHtml = button.dataset.originalHtml || button.innerHTML;
    button.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erreur';
    button.classList.add('btn-danger');
    
    setTimeout(() => {
      button.innerHTML = originalHtml;
      button.classList.remove('btn-danger');
    }, 3000);

    console.error('Translation error:', message);
  }

  /**
   * Cache management
   */
  getCacheKey(text, sourceLang, targetLang) {
    // Simple hash function
    const hash = text.split('').reduce((a, b) => {
      a = ((a << 5) - a) + b.charCodeAt(0);
      return a & a;
    }, 0);
    return `${sourceLang}-${targetLang}-${hash}`;
  }

  loadCache() {
    try {
      const cached = localStorage.getItem('translation_cache');
      return cached ? JSON.parse(cached) : {};
    } catch (e) {
      console.error('Failed to load translation cache:', e);
      return {};
    }
  }

  saveToCache(key, value) {
    this.cache[key] = value;
    try {
      // Keep only last 100 translations to avoid localStorage bloat
      const keys = Object.keys(this.cache);
      if (keys.length > 100) {
        keys.slice(0, keys.length - 100).forEach(k => delete this.cache[k]);
      }
      localStorage.setItem('translation_cache', JSON.stringify(this.cache));
    } catch (e) {
      console.error('Failed to save translation cache:', e);
    }
  }

  /**
   * Utility functions
   */
  stripHtml(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent || temp.innerText || '';
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  nl2br(text) {
    return text.replace(/\n/g, '<br>');
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  window.translationManager = new TranslationManager();
});