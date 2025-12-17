document.addEventListener('DOMContentLoaded', function() {
	const $jsBlocks = document.querySelectorAll('[data-js-block]');

	if ($jsBlocks.length) {
		for (let i = 0; i < $jsBlocks.length; i++) {
			const $container = $jsBlocks[i];
			const $block = $container.dataset.jsBlock
				? document.querySelector($container.dataset.jsBlock)
				: false;
			
			if ($block) {
				$container.appendChild($block);
				$container.removeAttribute(['data-js-block'])
			}
		}
	}
})