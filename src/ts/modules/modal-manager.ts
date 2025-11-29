import { CONSTANTS } from "./constants";
import jQuery from "jquery";

interface ModalData {
  [key: string]: any;
}

type ConfirmHandler = (modal: JQuery) => void;
type CancelHandler = () => void;

export class ModalManager {
  private $modal: JQuery | null = null;
  private $body: JQuery;

  constructor() {
    this.$body = jQuery("body");
  }

  /**
   * Close any open modal with animation
   */
  closeModal(): void {
    if (!this.$modal || !this.$modal.length) {
      console.warn("WCSDM: No modal found for closing");
      return;
    }

    this.cleanup();

    // Add exit transition classes
    this.$modal.addClass('wcsdm-modal-exit');

    // Trigger exit animation
    setTimeout(() => {
      this.$modal?.addClass('wcsdm-modal-exit-active');
    }, CONSTANTS.ANIMATION_DELAY);

    // Remove modal from DOM after animation
    setTimeout(() => {
      this.$modal?.remove();
      this.$modal = null;
    }, CONSTANTS.ANIMATION_DURATION);
  }

  /**
   * Renders a modal dialog with animation and focus management
   * @param templateName - Name of the WordPress template to use
   * @param modalData - Data to pass to the template
   * @param handleConfirm - Callback for confirm action
   * @param handleCancel - Callback for cancel action
   * @returns The modal jQuery object or undefined if error
   * @private
   */
  renderModal(
    templateName: string,
    modalData: ModalData,
    handleConfirm: ConfirmHandler,
    handleCancel: CancelHandler | null = null
  ): JQuery | undefined | null {
    // Input validation
    if (!templateName || typeof templateName !== "string") {
      console.error("WCSDM: Invalid template name");
      return;
    }

    if (!modalData || typeof modalData !== "object") {
      console.error("WCSDM: Invalid modal data");
      return;
    }

    // Check if template exists
    if (!wp.template || typeof wp.template !== "function") {
      console.error("WCSDM: WordPress template function not available");
      return;
    }

    const template = wp.template(templateName);

    if (!template) {
      console.error("WCSDM: Template not found:", templateName);
      return;
    }

    try {
      const html = template(modalData);

      // Append modal to body
      this.$modal = jQuery(html).appendTo(this.$body);

      // Store focusable elements
      const focusableElements = this.$modal
        .find(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
        .toArray();

      // Add enter class for transition
      this.$modal.addClass('wcsdm-modal-enter');

      // Force reflow to ensure the initial state is applied
      // this.$modal[0].offsetHeight;

      // Add active class to trigger transition
      setTimeout(() => {
        this.$modal?.addClass('wcsdm-modal-enter-active');
      }, CONSTANTS.ANIMATION_DELAY);

      // Remove transition classes after animation completes
      setTimeout(() => {
        this.$modal?.removeClass('wcsdm-modal-enter wcsdm-modal-enter-active');
      }, CONSTANTS.ANIMATION_DURATION);

      // Confirm button handler
      const $confirmBtn = this.$modal.find(
        '.wcsdm-modal-btn--confirm'
      );
      $confirmBtn.on("click.wcsdm-modal", (event: JQuery.ClickEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (this.$modal) {
          handleConfirm(this.$modal);
        }
      });

      // Cancel button handler
      const $cancelBtn = this.$modal.find('.wcsdm-modal-btn--cancel');

      $cancelBtn.on("click.wcsdm-modal", (event: JQuery.ClickEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (handleCancel) {
          handleCancel();
        } else {
          this.closeModal();
        }
      });

      // Keyboard event handlers
      this.$modal.on("keydown.wcsdm-modal", (event: JQuery.KeyDownEvent) => {
        // Close on escape
        if (event.key === "Escape") {
          event.preventDefault();

          if (handleCancel) {
            handleCancel();
          } else {
            this.closeModal();
          }
          return;
        }

        // Handle tab key for focus trap
        if (event.key === "Tab") {
          if (!focusableElements.length) return;

          const firstElement = focusableElements[0] as HTMLElement;
          const lastElement = focusableElements[
            focusableElements.length - 1
          ] as HTMLElement;
          const activeElement = document.activeElement;

          if (event.shiftKey && activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
          } else if (!event.shiftKey && activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
          }
        }
      });
    } catch (error) {
      console.error("WCSDM: Error rendering modal:", error);
    }

    return this.$modal;
  }

  private cleanup(): void {
    if (!this.$modal) return;

    // Cancel button handler
    this.$modal
      .find('.wcsdm-modal-btn--cancel')
      .off("click.wcsdm-modal");

    // Confirm button handler
    this.$modal
      .find('.wcsdm-modal-btn--confirm')
      .off("click.wcsdm-modal");

    // Remove keydown handler
    this.$modal.off("keydown.wcsdm-modal");
  }
}