import { CONSTANTS } from "./constants";
import { cookieUtils } from "./cookie-utils";
import jQuery from "jquery";

export class TabManager {
  private $form: JQuery;
  private $fieldGroups: JQuery;

  constructor() {
    this.$form = jQuery("#mainform");
    this.$fieldGroups = this.$form.find("h3.wcsdm-tab-title");
  }

  init(): void {
    if (this.$fieldGroups.length === 0) {
      return;
    }

    this.createTabs();
    this.showDefaultActiveTab();
  }

  private createTabs(): void {
    // Create tab container
    const $tabNav = jQuery(
      '<nav class="wcsdm-nav-tab-wrapper nav-tab-wrapper woo-nav-tab-wrapper">',
    );

    this.buildTabNav($tabNav);
    this.$form.find("h2").first().after($tabNav);

    this.setupTabEvents();
  }

  private hideAll(excludeIndex: number | null = null): void {
    // Process each field group
    this.$fieldGroups.each((index: number, element: HTMLElement) => {
      if (excludeIndex !== index) {
        const $h3 = jQuery(element);
        const $p = $h3.next("p");
        const $table = $p.length
          ? $p.next("table.form-table").first()
          : $h3.next("table.form-table").first();

        $h3.addClass("wcsdm-hidden");
        $p.addClass("wcsdm-hidden");
        $table.addClass("wcsdm-hidden");
      }
    });
  }

  private showTabByIndex(index: number): void {
    // Hide all tabs first.
    this.hideAll(index);

    jQuery(".wcsdm-tab-link").removeClass("nav-tab-active");
    jQuery(".wcsdm-tab-link").eq(index).addClass("nav-tab-active");

    const $h3 = this.$form.find("h3.wcsdm-tab-title").eq(index);
    const $p = $h3.next("p");
    const $table = $p.length
      ? $p.next("table.form-table").first()
      : $h3.next("table.form-table").first();

    $h3.removeClass("wcsdm-hidden");
    $p.removeClass("wcsdm-hidden");
    $table.removeClass("wcsdm-hidden");
  }

  private buildTabNav($tabNav: JQuery): void {
    this.$fieldGroups.each((index: number, element: HTMLElement) => {
      const $h3 = jQuery(element);
      const tabTitle = $h3.attr("data-tab-title") || $h3.text().trim();
      const tabId = "wcsdm-tab-" + index;

      const $tabLink = jQuery(
        '<a href="#" class="wcsdm-tab-link wcsdm-link nav-tab" data-tab="' +
          tabId +
          '">' +
          tabTitle +
          "</a>",
      );

      if (index === 0) {
        $tabLink.addClass("nav-tab-active");
      }

      $tabNav.append($tabLink);
    });
  }

  private setupTabEvents(): void {
    jQuery(".wcsdm-tab-link").on("click", (e: JQuery.ClickEvent) => {
      e.preventDefault();

      const clickedIndex = jQuery(e.currentTarget).index();
      this.showTabByIndex(clickedIndex);

      // Save active tab to cookie
      const cookieSaved = cookieUtils.set(
        CONSTANTS.COOKIE_NAME,
        clickedIndex.toString(),
        CONSTANTS.COOKIE_EXPIRY_DAYS,
      );

      if (!cookieSaved) {
        console.warn("WCSDM: Could not save tab preference to cookie");
      }
    });
  }

  private showDefaultActiveTab(): void {
    let validTabIndex = 0; // Default to first tab

    try {
      const savedTabIndex = cookieUtils.get(CONSTANTS.COOKIE_NAME);

      if (savedTabIndex !== null) {
        const activeTabIndex = parseInt(savedTabIndex, 10);

        // Validate tab index
        if (activeTabIndex >= 0 && activeTabIndex < this.$fieldGroups.length) {
          validTabIndex = activeTabIndex;
        }
      }
    } catch (e) {
      console.warn(
        "WCSDM: Could not restore tab preference from cookie:",
        e instanceof Error ? e.message : String(e),
      );
    }

    // Set active tab.
    this.showTabByIndex(validTabIndex);
  }
}