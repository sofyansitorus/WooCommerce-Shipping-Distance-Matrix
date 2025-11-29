import { TabManager } from "./modules/tab-manager";
import { ApiProviderManager } from "./modules/api-provider-manager";
import { RateManager } from "./modules/rate-manager";

((): void => {
	"use strict";
	/**
	 * Initialize the backend module
	 * @public
	 */
	const init = (): void => {
		try {
			// Initialize managers
			const tabManager = new TabManager();
			const apiProviderManager = new ApiProviderManager();
			const rateManager = new RateManager();

			// Initialize each manager
			tabManager.init();
			apiProviderManager.init();
			rateManager.init();
		} catch (error) {
			console.error("WCSDM: Error initializing backend module:", error);
		}
	};

	document.addEventListener("DOMContentLoaded", init);
})();
