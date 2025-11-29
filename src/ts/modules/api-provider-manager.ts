import { CONSTANTS } from "./constants";
import jQuery from "jquery";

export class ApiProviderManager {
  private $apiProviderField: JQuery;
  private $apiProviderSelect: JQuery;

  constructor() {
    this.$apiProviderField = jQuery('.api-provider-field');
    this.$apiProviderSelect = jQuery('#woocommerce_wcsdm_api_provider');
  }

  init(): void {
    if (this.$apiProviderSelect.length) {
      this.setupEventListeners();
      this.$apiProviderSelect.trigger("change");
    }
  }

  private setupEventListeners(): void {
    this.$apiProviderSelect.on("change", (event: JQuery.ChangeEvent) => {
      this.toggleApiProviderField((event.target as HTMLSelectElement).value);
    });
  }

  /**
   * Toggle visibility of API provider fields based on selected provider
   * @param apiProvider - The selected API provider value
   * @private
   */
  private toggleApiProviderField(apiProvider: string): void {
    if (!this.$apiProviderField.length) {
      console.warn("WCSDM: API provider fields not found");
      return;
    }

    this.$apiProviderField.each(function (this: HTMLElement) {
      const $field = jQuery(this);
      const dataApiProvider = $field.data("api-provider");
      const $row = $field.closest("tr");

      if (dataApiProvider === apiProvider) {
        $row.removeClass('wcsdm-hidden');
      } else {
        $row.addClass('wcsdm-hidden');
      }
    });
  }
}