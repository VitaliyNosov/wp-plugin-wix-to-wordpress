/**
 * Wix to WordPress Post Migrator - Admin Script Entry Point.
 *
 * @package WixToWordPressMigrator
 */

(function () {
  'use strict';

  /**
   * Main client-side orchestrator.
   */
  class W2WAdminApp {
    constructor() {
      this.initEvents();
    }

    /**
     * Initializes DOM event listeners.
     */
    initEvents() {
      document.addEventListener('DOMContentLoaded', () => {
        this.setupTabs();
        this.setupSelectAll();
      });
    }

    /**
     * Handles tab switching in the admin UI.
     */
    setupTabs() {
      const tabLinks = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
      if (!tabLinks.length) return;

      tabLinks.forEach((tab) => {
        tab.addEventListener('click', (e) => {
          e.preventDefault();
          const targetSelector = tab.getAttribute('href');
          if (!targetSelector) return;

          tabLinks.forEach((t) => t.classList.remove('nav-tab-active'));
          tab.classList.add('nav-tab-active');

          const tabPanes = document.querySelectorAll('.w2w-tab-content');
          tabPanes.forEach((pane) => (pane.style.display = 'none'));

          const activePane = document.querySelector(targetSelector);
          if (activePane) {
            activePane.style.display = 'block';
          }
        });
      });
    }

    /**
     * Handles checkbox select-all behavior in post preview table.
     */
    setupSelectAll() {
      const selectAll = document.getElementById('w2w-select-all');
      if (!selectAll) return;

      selectAll.addEventListener('change', () => {
        const checkboxes = document.querySelectorAll('.w2w-post-checkbox');
        checkboxes.forEach((cb) => (cb.checked = selectAll.checked));
      });
    }
  }

  // Instantiate application
  window.w2wAdminApp = new W2WAdminApp();
})();
