/**
 * Rich Comment Editor
 * Handles emoji picker with cursor insertion, formatting controls, and live preview
 * Lightweight vanilla JavaScript - no external dependencies
 */
class RichCommentEditor {
  constructor(options = {}) {
    this.formSelector = options.formSelector || '#commentaire_form';
    this.previewId = options.previewId || 'commentaire-preview';
    this.emojiPickerId = options.emojiPickerId || 'emoji-picker-container';
    
    // Popular Unicode emojis - standard Unicode characters
    this.emojis = [
      '😀', '😂', '😍', '😭', '😱', '👍', '👎', '❤️', '💯', '🎉',
      '🤔', '😎', '🙏', '💪', '⭐', '🔥', '👏', '🤣', '😘', '😊',
      '😌', '😴', '😡', '😤', '🤯', '🥳', '😇', '🤐', '😶', '🤫',
      '🙊', '🙈', '🙉', '😻', '😸', '😹', '🐶', '🐱', '🐭', '🐹',
      '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽',
      '🐸', '🐵', '🐒', '🐔', '🐧', '🐦', '🐤', '🦆', '🦅', '🦉',
      '🦇', '🐺', '🐗', '🐴', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜',
      '🕷️', '🦂', '🐢', '🐍', '🦎', '🐙', '🦑', '🦐', '🦞', '🦀',
      '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆',
      '🦓', '🦍', '🦧', '🐘', '💪', '🚀', '🎁', '🎂', '🍕', '🍔',
      '🍟', '🍗', '🍜', '🍱', '🍛', '🍝', '🍰', '☕', '🍷', '🍺',
      '⚽', '🏀', '🎾', '🏐', '🏈', '🎮', '🎯', '🎲', '🎪', '🎭',
      '🎬', '🎤', '🎧', '🎸', '🎹', '✈️', '🚂', '🚗', '🏠', '⛺',
      '🏖️', '🏝️', '🌋', '⛰️', '🏔️', '🗻', '🌌', '🌃', '🌆', '🌇'
    ];
    
    this.form = document.querySelector(this.formSelector);
    if (!this.form) return;

    this.textarea = this.form.querySelector('[name="contenu"]');
    this.init();
  }

  init() {
    this.setupEmojiPicker();
    this.setupEventListeners();
    this.updatePreview();
  }

  /**
   * Setup emoji picker with grid of buttons
   * Clicking an emoji inserts it at the cursor position in the textarea
   */
  setupEmojiPicker() {
    const emojiButton = document.querySelector('[data-emoji-toggle]');
    const gridContainer = document.getElementById('emoji-grid');
    if (!emojiButton) {
      console.error('Emoji button not found!');
      return;
    }
    if (!this.textarea) {
      console.error('Textarea not found!');
      return;
    }
    if (!gridContainer) {
      console.error('Emoji grid container not found!');
      return;
    }
    console.log('Emoji picker initialized!');
    // Generate emoji buttons that insert at cursor
    this.emojis.forEach(emoji => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'emoji-btn';
      btn.textContent = emoji;
      btn.title = `Insert ${emoji}`;
      btn.setAttribute('aria-label', `Insert emoji ${emoji}`);
      
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        this.insertEmojiAtCursor(emoji);
      });
      
      gridContainer.appendChild(btn);
    });

    // Toggle emoji picker visibility
    emojiButton.addEventListener('click', (e) => {
      e.preventDefault();
      const container = document.getElementById(this.emojiPickerId);
      if (container) {
        container.classList.toggle('show');
        emojiButton.classList.toggle('active');
      }
    });

    // Close emoji picker when clicking outside
    document.addEventListener('click', (e) => {
      const container = document.getElementById(this.emojiPickerId);
      if (!container) return;

      const isClickOnButton = emojiButton.contains(e.target);
      const isClickOnPicker = container.contains(e.target);

      // Close if clicked outside picker and button
      if (!isClickOnButton && !isClickOnPicker && container.classList.contains('show')) {
        container.classList.remove('show');
        emojiButton.classList.remove('active');
      }
    });
  }

  /**
   * Insert emoji at current cursor position in textarea
   * Maintains cursor position for continuous input
   */
  insertEmojiAtCursor(emoji) {
    if (!this.textarea) return;

    // Get current cursor position
    const start = this.textarea.selectionStart;
    const end = this.textarea.selectionEnd;
    const text = this.textarea.value;

    // Insert emoji at cursor position
    this.textarea.value = text.substring(0, start) + emoji + text.substring(end);

    // Move cursor after the inserted emoji
    const newPosition = start + emoji.length;
    this.textarea.setSelectionRange(newPosition, newPosition);

    // Update form preview
    this.updatePreview();

    // Maintain focus on textarea
    this.textarea.focus();

    // Trigger input event for any listeners (form validation, etc.)
    this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /**
   * Setup event listeners for formatting controls
   */
  setupEventListeners() {
    const contenu = this.form.querySelector('[name="contenu"]');
    const fontStyle = this.form.querySelector('[name="fontStyle"]');
    const fontSize = this.form.querySelector('[name="fontSize"]');
    const textColor = this.form.querySelector('[name="textColor"]');

    if (contenu) contenu.addEventListener('input', () => this.updatePreview());
    if (fontStyle) fontStyle.addEventListener('change', () => this.updatePreview());
    if (fontSize) fontSize.addEventListener('change', () => this.updatePreview());
    if (textColor) textColor.addEventListener('change', () => this.updatePreview());
  }

  /**
   * Get CSS styles based on formatting controls
   */
  getCommentStyle() {
    const fontStyle = this.form.querySelector('[name="fontStyle"]')?.value || 'normal';
    const fontSize = this.form.querySelector('[name="fontSize"]')?.value || 'medium';
    const textColor = this.form.querySelector('[name="textColor"]')?.value;

    let styles = {};

    // Font style
    if (fontStyle === 'bold') styles['font-weight'] = 'bold';
    if (fontStyle === 'italic') styles['font-style'] = 'italic';
    if (fontStyle === 'underline') styles['text-decoration'] = 'underline';
    if (fontStyle === 'strikethrough') styles['text-decoration'] = 'line-through';

    // Font size
    switch (fontSize) {
      case 'small': styles['font-size'] = '0.85rem'; break;
      case 'medium': styles['font-size'] = '0.95rem'; break;
      case 'large': styles['font-size'] = '1.15rem'; break;
    }

    // Text color
    if (textColor) styles.color = textColor;

    return styles;
  }

  /**
   * Update preview pane with current textarea content and formatting
   */
  updatePreview() {
    const preview = document.getElementById(this.previewId);
    if (!preview) return;

    const contenu = this.form.querySelector('[name="contenu"]')?.value || '';
    const styles = this.getCommentStyle();

    let html = `<div style="${this.stylesToString(styles)}">`;
    html += escapeHtml(contenu).replace(/\n/g, '<br>');
    html += '</div>';

    preview.innerHTML = html;
  }

  /**
   * Convert styles object to inline CSS string
   */
  stylesToString(styles) {
    return Object.entries(styles)
      .map(([key, value]) => `${key}:${value}`)
      .join(';');
  }
}

/**
 * Utility function to escape HTML and prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Initialize editor when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    const form = document.querySelector('#commentaire_form');
    if (form) {
      window.commentEditor = new RichCommentEditor();
      console.log('RichCommentEditor initialized!');
    } else {
      console.error('Comment form not found!');
    }
  }, 100);
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = RichCommentEditor;
}
