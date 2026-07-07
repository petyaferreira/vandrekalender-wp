/**
 * Tabs — frontend behaviour.
 *
 * Plain, dependency-free tab switching. Clicking a nav item activates the
 * matching panel by index. Progressive enhancement: the first panel is already
 * marked active server-side, so content shows even if this script never runs.
 */

/**
 * @param {string} text
 * @return {string}
 */
function slugify(text) {
  return text
    .toLowerCase()
    .replace(/[æ]/g, 'ae')
    .replace(/[ø]/g, 'oe')
    .replace(/[å]/g, 'aa')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

/**
 * Initialise one tabs container.
 *
 * @param {HTMLElement} root The [data-vk-tabs] element.
 */
function initTabs(root) {
  const tabs = Array.from(
    root.querySelectorAll('.wp-block-vandrekalender-tabs__navigation-item')
  );
  const panels = Array.from(
    root.querySelectorAll('.wp-block-vandrekalender-tabs__content-item')
  );

  if (!tabs.length || tabs.length !== panels.length) {
    return;
  }

  function activate(index) {
    tabs.forEach((tab, i) => {
      const selected = i === index;
      tab.classList.toggle(
        'wp-block-vandrekalender-tabs__navigation-item--active',
        selected
      );
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
    panels.forEach((panel, i) => {
      panel.classList.toggle(
        'wp-block-vandrekalender-tabs__content-item--active',
        i === index
      );
    });
    // Let embedded blocks react to becoming visible — e.g. the Event Map
    // re-measures itself, since Leaflet cannot size against a hidden panel.
    panels[index].dispatchEvent(
      new CustomEvent('vk:tab-shown', { bubbles: true })
    );
  }

  const slugs = tabs.map(tab => slugify(tab.textContent.trim()));

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      activate(index);
      history.replaceState(null, '', `#${slugs[index]}`);
    });
  });

  const hash = location.hash.slice(1);
  const index = slugs.indexOf(hash);
  if (index !== -1) {
    activate(index);
    // The hash matches a tab label, not a real element id, so the browser
    // has nothing to scroll to on its own — bring the tabs into view.
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-vk-tabs]').forEach(root => initTabs(root));
});
