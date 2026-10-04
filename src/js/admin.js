/**
 * Wix to WordPress Post Migrator - Admin Modular Client Script
 *
 * @package WixToWordPressMigrator
 */

(function () {
  'use strict';

  // State Container
  const state = {
    sessionId: null,
    batchId: null,
    posts: [],
    selectedIndices: [],
    isMigrating: false,
    isPaused: false,
    isCancelled: false,
    chunkSize: 3,
    stats: {
      total: 0,
      imported: 0,
      failed: 0,
      media: 0,
    },
  };

  /**
   * Helper: Sends authenticated WordPress AJAX requests.
   *
   * @param {string} action AJAX action name.
   * @param {Object} data   Payload object.
   * @returns {Promise<Object>}
   */
  async function ajaxPost(action, data = {}) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    formData.append('nonce', window.w2wAdmin?.nonce || '');

    for (const [key, value] of Object.entries(data)) {
      if (Array.isArray(value) || typeof value === 'object') {
        formData.append(key, JSON.stringify(value));
      } else {
        formData.append(key, value);
      }
    }

    const response = await fetch(window.w2wAdmin?.ajax_url || 'admin-ajax.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: formData.toString(),
    });

    const json = await response.json();
    if (!json.success) {
      throw new Error(json.data?.message || window.w2wAdmin?.i18n?.error_generic || 'Request failed');
    }
    return json.data;
  }

  /**
   * Appends a log line to the live activity stream.
   *
   * @param {string} text  Message text.
   * @param {string} level 'info' | 'warn' | 'error'
   */
  function appendLog(text, level = 'info') {
    const stream = document.getElementById('w2w-log-stream');
    if (!stream) return;

    const line = document.createElement('div');
    line.className = `w2w-log-line w2w-log-${level}`;
    const time = new Date().toLocaleTimeString();
    line.textContent = `[${time}] ${text}`;
    stream.appendChild(line);
    stream.scrollTop = stream.scrollHeight;
  }

  /**
   * Updates real-time migration progress bar and counters.
   */
  function updateProgress() {
    const total = state.stats.total;
    const processed = state.stats.imported + state.stats.failed;
    const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

    const bar = document.getElementById('w2w-progress-bar');
    const percentText = document.getElementById('w2w-progress-percentage');
    if (bar) bar.style.width = `${percent}%`;
    if (percentText) percentText.textContent = `${percent}%`;

    const elTotal = document.getElementById('w2w-stat-total');
    const elImported = document.getElementById('w2w-stat-imported');
    const elFailed = document.getElementById('w2w-stat-failed');
    const elMedia = document.getElementById('w2w-stat-media');

    if (elTotal) elTotal.textContent = total;
    if (elImported) elImported.textContent = state.stats.imported;
    if (elFailed) elFailed.textContent = state.stats.failed;
    if (elMedia) elMedia.textContent = state.stats.media;
  }

  /**
   * Generates a client-side UUID v4.
   *
   * @returns {string}
   */
  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  // ============================================================
  // Tab 1: Migration Flow (Preview & Chunk Import)
  // ============================================================

  /**
   * Initializes Preview Feed listener.
   */
  function initPreview() {
    const btnPreview = document.getElementById('w2w-btn-preview');
    if (!btnPreview) return;

    // Wire up mini-documentation example buttons
    document.querySelectorAll('.w2w-btn-use-example').forEach((btn) => {
      btn.addEventListener('click', function () {
        const url = this.dataset.url;
        const input = document.getElementById('w2w_source_url');
        if (input && url) {
          input.value = url;
          input.classList.remove('w2w-input-highlight');
          void input.offsetWidth; // Force CSS reflow
          input.classList.add('w2w-input-highlight');
          input.focus();
          input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });

    btnPreview.addEventListener('click', async function () {
      const urlInput = document.getElementById('w2w_source_url');
      const spinner = document.getElementById('w2w-preview-spinner');
      const previewSection = document.getElementById('w2w-preview-section');
      const tbody = document.getElementById('w2w-preview-tbody');
      const countBadge = document.getElementById('w2w-posts-count-badge');

      let url = urlInput ? urlInput.value.trim() : '';
      if (!url) {
        alert('Please enter a valid Wix URL (Sitemap XML, Single Post, or RSS feed).');
        if (urlInput) urlInput.focus();
        return;
      }

      // Auto-prepend https:// if omitted by user
      if (!/^https?:\/\//i.test(url) && !url.startsWith('<')) {
        url = 'https://' + url;
        if (urlInput) urlInput.value = url;
      }

      btnPreview.disabled = true;
      if (spinner) spinner.classList.add('is-active');

      try {
        const data = await ajaxPost('w2w_preview_feed', {
          source_type: 'auto',
          source_url: url,
        });

        state.sessionId = data.session_id;
        state.posts = data.posts || [];

        if (countBadge) {
          let sourceLabel = '';
          if (data.source_type === 'sitemap') {
            sourceLabel = ' (via Full Sitemap XML)';
          } else if (data.source_type === 'single_post') {
            sourceLabel = ' (via Single Post Scraper)';
          } else if (data.source_type === 'rss') {
            sourceLabel = ' (via RSS Feed)';
          }
          countBadge.textContent = `${state.posts.length} post${state.posts.length === 1 ? '' : 's'} found${sourceLabel}`;
        }

        // Render rows
        if (tbody) {
          tbody.innerHTML = '';
          state.posts.forEach((post) => {
            const tr = document.createElement('tr');

            const thumbHtml = post.featured_image_url
              ? `<img src="${post.featured_image_url}" class="w2w-thumb-img" alt="" />`
              : `<div class="w2w-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>`;

            const categoriesHtml = (post.categories || [])
              .map((c) => `<span class="w2w-badge w2w-badge-neutral">${c}</span>`)
              .join(' ');

            tr.innerHTML = `
              <th scope="row" class="check-column">
                <input type="checkbox" class="w2w-post-cb" data-index="${post.index}" checked="checked" />
              </th>
              <td>${thumbHtml}</td>
              <td>
                <strong>${post.title}</strong>
                <div class="row-actions">
                  <span class="view"><a href="${post.original_url}" target="_blank" rel="noopener">Wix Link &#8599;</a></span>
                  ${post.slug ? ` | <code>/${post.slug}</code>` : ''}
                </div>
              </td>
              <td>${categoriesHtml || '<span class="description">—</span>'}</td>
              <td>${post.author_name || '—'}</td>
              <td>${post.date_published ? post.date_published.split(' ')[0] : '—'}</td>
            `;
            tbody.appendChild(tr);
          });
        }

        if (previewSection) {
          previewSection.style.display = 'block';
          previewSection.scrollIntoView({ behavior: 'smooth' });
        }
      } catch (err) {
        alert(`Error fetching preview: ${err.message}`);
      } finally {
        btnPreview.disabled = false;
        if (spinner) spinner.classList.remove('is-active');
      }
    });

    // Select / Deselect All
    const selectAllCb = document.getElementById('w2w-cb-select-all');
    if (selectAllCb) {
      selectAllCb.addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('.w2w-post-cb');
        checkboxes.forEach((cb) => (cb.checked = selectAllCb.checked));
      });
    }

    const btnSelectAll = document.getElementById('w2w-select-all');
    if (btnSelectAll) {
      btnSelectAll.addEventListener('click', () => {
        document.querySelectorAll('.w2w-post-cb').forEach((cb) => (cb.checked = true));
        if (selectAllCb) selectAllCb.checked = true;
      });
    }

    const btnDeselectAll = document.getElementById('w2w-deselect-all');
    if (btnDeselectAll) {
      btnDeselectAll.addEventListener('click', () => {
        document.querySelectorAll('.w2w-post-cb').forEach((cb) => (cb.checked = false));
        if (selectAllCb) selectAllCb.checked = false;
      });
    }
  }

  /**
   * Initializes Migration Runner loop.
   */
  function initMigrationRunner() {
    const btnStart = document.getElementById('w2w-btn-start-migration');
    const runnerSection = document.getElementById('w2w-runner-section');
    const btnPause = document.getElementById('w2w-btn-pause');
    const btnCancel = document.getElementById('w2w-btn-cancel');

    if (!btnStart) return;

    btnStart.addEventListener('click', async function () {
      // Gather checked indices
      const checkedBoxes = Array.from(document.querySelectorAll('.w2w-post-cb:checked'));
      if (checkedBoxes.length === 0) {
        alert(window.w2wAdmin?.i18n?.no_posts_select || 'Please select at least one post.');
        return;
      }

      state.selectedIndices = checkedBoxes.map((cb) => parseInt(cb.dataset.index, 10));
      state.batchId = generateUUID();
      state.isMigrating = true;
      state.isPaused = false;
      state.isCancelled = false;

      // Extract batch configuration
      const chunkSizeSelect = document.getElementById('w2w-chunk-size');
      state.chunkSize = chunkSizeSelect ? parseInt(chunkSizeSelect.value, 10) : 3;

      const authorSelect = document.getElementById('w2w_target_author');
      const authorId = authorSelect ? authorSelect.value : 1;

      const catInput = document.getElementById('w2w_default_category');
      const defaultCategory = catInput ? catInput.value.trim() : '';

      const imgCheckbox = document.getElementById('w2w_import_images');
      const importImages = imgCheckbox ? (imgCheckbox.checked ? 'true' : 'false') : 'true';

      // Reset Stats
      state.stats.total = state.selectedIndices.length;
      state.stats.imported = 0;
      state.stats.failed = 0;
      state.stats.media = 0;

      updateProgress();

      if (runnerSection) {
        runnerSection.style.display = 'block';
        runnerSection.scrollIntoView({ behavior: 'smooth' });
      }

      appendLog(`Starting migration batch [${state.batchId}] with ${state.stats.total} posts...`, 'info');

      // Chunk processing queue
      const queue = [...state.selectedIndices];

      while (queue.length > 0 && !state.isCancelled) {
        if (state.isPaused) {
          await new Promise((r) => setTimeout(r, 500));
          continue;
        }

        const chunkIndices = queue.splice(0, state.chunkSize);

        try {
          const chunkData = await ajaxPost('w2w_import_chunk', {
            batch_id: state.batchId,
            session_id: state.sessionId,
            indices: chunkIndices,
            author_id: authorId,
            default_category: defaultCategory,
            import_images: importImages,
          });

          if (chunkData.results && Array.isArray(chunkData.results)) {
            chunkData.results.forEach((res) => {
              if (res.success) {
                state.stats.imported++;
                state.stats.media += res.media_count || 0;
                appendLog(`✓ Imported: "${res.title || 'Post'}" (ID: ${res.post_id})`, 'info');
              } else {
                state.stats.failed++;
                appendLog(`✗ Failed: "${res.title || 'Post'}" - ${res.error || 'Unknown error'}`, 'error');
              }
            });
          }

          updateProgress();
        } catch (err) {
          appendLog(`Chunk request failed: ${err.message}`, 'error');
          state.stats.failed += chunkIndices.length;
          updateProgress();
        }

        // Brief delay between chunks to keep server responsive
        await new Promise((r) => setTimeout(r, 200));
      }

      state.isMigrating = false;
      if (state.isCancelled) {
        appendLog('Migration cancelled by user.', 'warn');
      } else {
        appendLog(`Migration finished! Imported: ${state.stats.imported}, Failed: ${state.stats.failed}, Media: ${state.stats.media}`, 'info');
        alert(window.w2wAdmin?.i18n?.complete || 'Migration completed successfully!');
      }
    });

    if (btnPause) {
      btnPause.addEventListener('click', function () {
        state.isPaused = !state.isPaused;
        btnPause.textContent = state.isPaused ? 'Resume' : 'Pause';
        appendLog(state.isPaused ? 'Migration paused.' : 'Migration resumed.', 'warn');
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', function () {
        if (confirm('Are you sure you want to cancel the migration? Current post will finish.')) {
          state.isCancelled = true;
        }
      });
    }
  }

  // ============================================================
  // Tab 2: Rollback (Undo) Flow
  // ============================================================

  /**
   * Initializes Rollback listeners.
   */
  function initRollback() {
    const btnCheck = document.getElementById('w2w-btn-check-rollback');
    const btnExecute = document.getElementById('w2w-btn-execute-rollback');
    const inputBatch = document.getElementById('w2w_rollback_batch_id');
    const impactBox = document.getElementById('w2w-rollback-impact-box');
    const spinner = document.getElementById('w2w-rollback-spinner');

    if (!btnCheck || !inputBatch) return;

    btnCheck.addEventListener('click', async function () {
      const batchId = inputBatch.value.trim();
      if (!batchId) {
        alert(window.w2wAdmin?.i18n?.select_batch || 'Please enter a Batch UUID.');
        inputBatch.focus();
        return;
      }

      btnCheck.disabled = true;
      if (spinner) spinner.classList.add('is-active');

      try {
        const data = await ajaxPost('w2w_check_rollback', { batch_id: batchId });

        const countPosts = document.getElementById('w2w-rollback-posts-count');
        const countMedia = document.getElementById('w2w-rollback-media-count');

        if (countPosts) countPosts.textContent = data.posts || 0;
        if (countMedia) countMedia.textContent = data.attachments || 0;

        if (impactBox) impactBox.style.display = 'block';
        if (btnExecute) btnExecute.style.display = 'inline-block';
      } catch (err) {
        alert(`Error checking batch: ${err.message}`);
      } finally {
        btnCheck.disabled = false;
        if (spinner) spinner.classList.remove('is-active');
      }
    });

    if (btnExecute) {
      btnExecute.addEventListener('click', async function () {
        const batchId = inputBatch.value.trim();
        const confirmMsg = window.w2wAdmin?.i18n?.confirm_rollback || 'Are you sure you want to permanently delete this batch?';
        if (!confirm(confirmMsg)) return;

        btnExecute.disabled = true;
        if (spinner) spinner.classList.add('is-active');

        try {
          const res = await ajaxPost('w2w_rollback_batch', { batch_id: batchId });
          alert(`Batch successfully rolled back! ${res.posts_deleted || 0} posts and ${res.attachments_deleted || 0} attachments deleted.`);
          window.location.reload();
        } catch (err) {
          alert(`Rollback failed: ${err.message}`);
          btnExecute.disabled = false;
        } finally {
          if (spinner) spinner.classList.remove('is-active');
        }
      });
    }

    // Recent batch row selection
    document.querySelectorAll('.w2w-btn-select-batch').forEach((btn) => {
      btn.addEventListener('click', function () {
        const batch = this.dataset.batch;
        if (inputBatch && batch) {
          inputBatch.value = batch;
          btnCheck.click();
        }
      });
    });
  }

  // ============================================================
  // Tab 3: 301 Redirects Export Flow
  // ============================================================

  /**
   * Initializes 301 Redirects listeners.
   */
  function initRedirects() {
    const btnGenerate = document.getElementById('w2w-btn-generate-redirects');
    const spinner = document.getElementById('w2w-redirects-spinner');
    const outputContainer = document.getElementById('w2w-redirects-output-container');
    const txtCsv = document.getElementById('w2w-redirects-csv');
    const txtHtaccess = document.getElementById('w2w-redirects-htaccess');
    const txtNginx = document.getElementById('w2w-redirects-nginx');
    const countSpan = document.getElementById('w2w-redirects-count');
    const btnDownloadCsv = document.getElementById('w2w-btn-download-csv');

    if (!btnGenerate) return;

    btnGenerate.addEventListener('click', async function () {
      btnGenerate.disabled = true;
      if (spinner) spinner.classList.add('is-active');

      try {
        const data = await ajaxPost('w2w_export_redirects');

        if (countSpan) countSpan.textContent = data.count || 0;
        if (txtCsv) txtCsv.value = data.csv || '';
        if (txtHtaccess) txtHtaccess.value = data.htaccess || '';
        if (txtNginx) txtNginx.value = data.nginx || '';

        if (outputContainer) outputContainer.style.display = 'block';
      } catch (err) {
        alert(`Error generating redirects: ${err.message}`);
      } finally {
        btnGenerate.disabled = false;
        if (spinner) spinner.classList.remove('is-active');
      }
    });

    if (btnDownloadCsv) {
      btnDownloadCsv.addEventListener('click', function () {
        const csvContent = txtCsv ? txtCsv.value : '';
        if (!csvContent) return;

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `wix-to-wp-301-redirects-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      });
    }

    // Copy to clipboard buttons
    document.querySelectorAll('.w2w-btn-copy').forEach((btn) => {
      btn.addEventListener('click', function () {
        const targetSelector = this.dataset.target;
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (target && target.value) {
          navigator.clipboard.writeText(target.value).then(() => {
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
            setTimeout(() => {
              this.innerHTML = originalText;
            }, 2000);
          });
        }
      });
    });
  }

  // ============================================================
  // Tab 4: Logs Viewer Flow
  // ============================================================

  /**
   * Initializes Logs tab listeners.
   */
  function initLogs() {
    const btnRefresh = document.getElementById('w2w-btn-refresh-logs');
    const btnClear = document.getElementById('w2w-btn-clear-logs');
    const terminal = document.getElementById('w2w-terminal');

    if (!btnRefresh) return;

    btnRefresh.addEventListener('click', async function () {
      btnRefresh.disabled = true;
      try {
        const data = await ajaxPost('w2w_get_logs', { limit: 100 });
        if (terminal && data.entries) {
          terminal.innerHTML = '';
          if (data.entries.length === 0) {
            terminal.innerHTML = '<div class="w2w-terminal-empty">No log entries recorded yet.</div>';
          } else {
            data.entries.forEach((entry) => {
              const div = document.createElement('div');
              div.className = 'w2w-terminal-line';
              div.textContent = entry.raw || `${entry.timestamp || ''} [${entry.level || ''}] ${entry.message || ''}`;
              terminal.appendChild(div);
            });
            terminal.scrollTop = terminal.scrollHeight;
          }
        }
      } catch (err) {
        alert(`Error fetching logs: ${err.message}`);
      } finally {
        btnRefresh.disabled = false;
      }
    });

    if (btnClear) {
      btnClear.addEventListener('click', async function () {
        if (!confirm('Are you sure you want to clear all migration log entries?')) return;

        btnClear.disabled = true;
        try {
          await ajaxPost('w2w_clear_logs');
          if (terminal) {
            terminal.innerHTML = '<div class="w2w-terminal-empty">Logs cleared.</div>';
          }
        } catch (err) {
          alert(`Error clearing logs: ${err.message}`);
        } finally {
          btnClear.disabled = false;
        }
      });
    }
  }

  // ============================================================
  // DOM Ready Bootstrap
  // ============================================================
  document.addEventListener('DOMContentLoaded', function () {
    initPreview();
    initMigrationRunner();
    initRollback();
    initRedirects();
    initLogs();
  });
})();
