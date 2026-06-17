/**
 * Tabs — frontend behaviour.
 *
 * Plain, dependency-free tab switching. Clicking a nav item activates the
 * matching panel by index. Progressive enhancement: the first panel is already
 * marked active server-side, so content shows even if this script never runs.
 */

/**
 * Initialise one tabs container.
 *
 * @param {HTMLElement} root The [data-vk-tabs] element.
 */
function initTabs( root ) {
	const tabs = Array.from(
		root.querySelectorAll( '.wp-block-vandrekalender-tabs__navigation-item' )
	);
	const panels = Array.from(
		root.querySelectorAll( '.wp-block-vandrekalender-tabs__content-item' )
	);

	if ( ! tabs.length || tabs.length !== panels.length ) {
		return;
	}

	function activate( index ) {
		tabs.forEach( ( tab, i ) => {
			const selected = i === index;
			tab.classList.toggle(
				'wp-block-vandrekalender-tabs__navigation-item--active',
				selected
			);
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
		} );
		panels.forEach( ( panel, i ) => {
			panel.classList.toggle(
				'wp-block-vandrekalender-tabs__content-item--active',
				i === index
			);
		} );
	}

	tabs.forEach( ( tab, index ) => {
		tab.addEventListener( 'click', () => activate( index ) );
	} );
}

document.addEventListener( 'DOMContentLoaded', () => {
	document
		.querySelectorAll( '[data-vk-tabs]' )
		.forEach( ( root ) => initTabs( root ) );
} );
