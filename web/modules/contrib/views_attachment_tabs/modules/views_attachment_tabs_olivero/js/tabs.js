/**
 * @file
 * Provides interactivity for showing and hiding the tabs.
 */

((Drupal, once) => {

  /**
   * Controls tab button on click events.
   *
   * @param {Event} e
   *   The event object.
   */
  function handleTabClick(e) {
    const clickedButton = e.currentTarget;
    const activeClass = 'is-active';
    const tabList = clickedButton.closest('[role="tablist"]');
    const tabPanels = tabList.closest('[role="navigation"]').nextElementSibling;
    const mobileTabTrigger = tabList.querySelector('.tabs__trigger');

    if (clickedButton.classList.contains(activeClass)) {
      return;
    }

    /**
     * Determine if the tabs dropdown is expanded on mobile.
     *
     * @returns {boolean}
     *   Whether the tabs trigger element is expanded.
     */
    function isTabsMobileLayout() {
      return tabList.querySelector('.tabs__trigger').clientHeight > 0;
    }

    // Hide all tab panels.
    tabPanels.querySelectorAll(':scope > [role="tabpanel"]').forEach(panel => {
      panel.hidden = true;
    });

    // Mark all tabs as unselected.
    tabList.querySelectorAll(':scope [role="tab"]').forEach(tab => {
      tab.parentElement.classList.remove(activeClass);
      tab.classList.remove(activeClass);
      tab.setAttribute('aria-selected', false);
    });

    // Mark the clicked tab as selected.
    const activeTab = clickedButton.parentElement;
    clickedButton.setAttribute('aria-selected', true);
    clickedButton.classList.add(activeClass);
    activeTab.classList.add(activeClass);

    // Find the associated tabPanel and show it!
    const { id } = clickedButton;
    const tabPanel = tabPanels.querySelector(`[aria-labelledby="${id}"]`);
    tabPanel.hidden = false;

    // Applying mobile related workflow.
    if (isTabsMobileLayout() && !activeTab.matches('.tabs__tab:first-child')) {
      const newActiveTab = activeTab.cloneNode(true);
      const firstTab = tabList.querySelector('.tabs__tab:first-child');
      newActiveTab.appendChild(mobileTabTrigger);
      // ensure that we re-attach the click handle for the tab.
      newActiveTab.querySelector('[data-views-attachment-tabs]')
        .addEventListener('click', handleTabClick);
      // Moving the active tab to the top of the list.
      tabList.insertBefore(newActiveTab, firstTab);
      tabList.removeChild(activeTab);

      // Closing the dropdown on click.
      mobileTabTrigger.click();
    }

    e.preventDefault();
  }

  /**
   * Initialize the tabs.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Applies tabs behavior according to button clicked.
   */
  Drupal.behaviors.viewsAttachmentTabsOlivero = {
    attach(context) {
      once('views-attachment-tabs-olivero', '[data-views-attachment-tabs]', context).forEach(
        button => button.addEventListener('click', handleTabClick)
      );
    },
  };
})(Drupal, once);
