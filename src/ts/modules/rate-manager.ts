import { CONSTANTS } from "./constants";
import { ModalManager } from "./modal-manager";
import jQuery from "jquery";

interface FormEntry {
	name: string;
	value: string;
}

interface FieldData {
	field: string;
	value: string;
}

interface Fields {
	[key: string]: FieldData;
}

export class RateManager {
	private modalManager: ModalManager;

	constructor() {
		this.modalManager = new ModalManager();
	}

	init(): void {
		this.sortRates();
		this.setRateRowsNumber();
		this.setupEventListeners();
	}

	private setupEventListeners(): void {
		this.setupEventListenerAddRate();
		this.setupEventListenerEditRate();
		this.setupEventListenerDeleteRate();
		this.setupEventListenerMoveRate();
	}

	private setupEventListenerAddRate(): void {
		jQuery('.wcsdm-rate-action--add').on("click", (event) =>
			this.handleAddRateClick(event),
		);
	}

	private setupEventListenerEditRate(): void {
		jQuery('.wcsdm-rate-action--edit')
			.off("click")
			.on("click", (event) => this.handleEditRateClick(event));
	}

	private setupEventListenerDeleteRate(): void {
		jQuery('.wcsdm-rate-action--delete')
			.off("click")
			.on("click", (event) => this.handleDeleteRateClick(event));
	}

	private setupEventListenerMoveRate(): void {
		this.setupEventListenerMoveRateUp();
		this.setupEventListenerMoveRateDown();
	}

	private setupEventListenerMoveRateUp(): void {
		const $links = jQuery(".wcsdm-rate-action--move-up");

		$links.each((index, link) => {
			const $link = jQuery(link);
			const $row = $link.closest("tr");
			const $prevRow = $row.prev("tr");

			const currentMaxDistanceInput = $row.find("input[data-key='max_distance']");
			const prevRowMaxDistanceInput = $prevRow.find("input[data-key='max_distance']");

			jQuery(link).off("click.up");

			if (currentMaxDistanceInput.val() !== prevRowMaxDistanceInput.val()) {
				$link.addClass("wcsdm-disabled");
			} else {
				$link.removeClass("wcsdm-disabled");

				jQuery(link).on("click.up", (event) => {
					this.handleMoveRateClick(event, "up");
				});
			}
		});
	}

	private setupEventListenerMoveRateDown(): void {
		const $links = jQuery(".wcsdm-rate-action--move-down");

		$links.each((index, link) => {
			const $link = jQuery(link);
			const $row = $link.closest("tr");
			const $nextRow = $row.next("tr");

			const currentMaxDistanceInput = $row.find("input[data-key='max_distance']");
			const nextRowMaxDistanceInput = $nextRow.find("input[data-key='max_distance']");

			jQuery(link).off("click.down");

			if (currentMaxDistanceInput.val() !== nextRowMaxDistanceInput.val()) {
				$link.addClass("wcsdm-disabled");
			} else {
				$link.removeClass("wcsdm-disabled");

				jQuery(link).on("click.down", (event) => {
					this.handleMoveRateClick(event, "down");
				});
			}
		});
	}

	private handleMoveRateClick(event: JQuery.ClickEvent, direction: "up" | "down"): void {
		event.preventDefault();
		event.stopPropagation();

		const $button = jQuery(event.currentTarget);
		if ($button.hasClass("wcsdm-disabled")) {
			return;
		}

		const $row = $button.closest("tr");
		const $targetRow = direction === "up" ? $row.prev("tr") : $row.next("tr");

		if ($targetRow.length) {
			if (direction === "up") {
				$row.insertBefore($targetRow);
			} else {
				$row.insertAfter($targetRow);
			}
		}

		this.setRateRowsNumber();
		this.setupEventListenerMoveRate();
	}

	private setRateRowsNumber(): void {
		const $rows = jQuery("#wcsdm-table--table_rates--dummy tbody tr");

		$rows.each(function (this: HTMLElement, index: number) {
			const $row = jQuery(this);
			const $col = $row.find("td.wcsdm-col--row_number");
			$col.text((index + 1).toString());
		});
	}

	private handleAddRateClick(event: JQuery.ClickEvent): void {
		event.preventDefault();
		event.stopPropagation();

		const $button = jQuery(event.currentTarget);
		const title = $button.attr("title");
		const fields = $button.data("fields") as Fields;

		// Ensure modalData is a complete object
		const modalData = {
			title,
			fields,
		};

		const $modal = this.modalManager.renderModal(
			CONSTANTS.TEMPLATES.ADD_RATE,
			modalData,
			($modal) => this.handleAddRateConfirm($modal),
		);

		if ($modal) {
			Object.entries(fields).forEach(([key, fieldData]) => {
				const $field = $modal.find(`#woocommerce_wcsdm_fake--field--${key}`);

				if ($field.length) {
					$field.val(fieldData.value).trigger("change");
				}
			});
		}
	}

	private sortRates(): void {
		const $tableRate = jQuery("#wcsdm-table--table_rates--dummy");
		const $tableRateBody = $tableRate.find("tbody");
		const $rows = $tableRateBody.find("tr");

		const sortedByMaxDistance = $rows.toArray().sort((a, b) => {
			const $inputA = jQuery(a).find<HTMLInputElement>(
				"input[data-key='max_distance']",
			);

			const $inputB = jQuery(b).find<HTMLInputElement>(
				"input[data-key='max_distance']",
			);

			const maxDistanceA = parseFloat($inputA.val() ?? "0") || 0;
			const maxDistanceB = parseFloat($inputB.val() ?? "0") || 0;

			return maxDistanceA - maxDistanceB;
		});

		$tableRateBody.empty().append(sortedByMaxDistance);
	}

	private handleAddRateConfirm(
		$modal: JQuery,
		rowIndex: number | null = null,
	): void {
		const $tableRate = jQuery("#wcsdm-table--table_rates--dummy");
		const $tableRateBody = $tableRate.find("tbody");
		const $form = $modal.find("form");
		const formEntries = $form.serializeArray() as FormEntry[];

		const templateData = {
			dummy: {} as { [key: string]: string },
			hidden: {} as { [key: string]: string },
		};

		formEntries.forEach((formEntry) => {
			const $field = jQuery("#" + formEntry.name);
			const fieldKey = $field.data("key");
			const rateContext = $field.data("rate_context");

			if (Array.isArray(rateContext) && rateContext.includes("dummy")) {
				templateData.dummy[fieldKey] = this.generateDummyText(
					$field,
					formEntry.value,
				);
			}

			if (Array.isArray(rateContext) && rateContext.includes("hidden")) {
				templateData.hidden[fieldKey] = formEntry.value;
			}
		});

		const template = wp.template("wcsdm-dummy-row");
		const html = template(templateData);

		if (rowIndex === null) {
			jQuery(html).appendTo($tableRateBody);
		} else {
			const $rowTarget = $tableRateBody.find("tr").eq(rowIndex);

			$rowTarget.after(html);
			$rowTarget.remove();
		}

		this.sortRates();
		this.setRateRowsNumber();
		this.setupEventListenerEditRate();
		this.setupEventListenerDeleteRate();
		this.setupEventListenerMoveRate();

		this.modalManager.closeModal();
	}

	private handleEditRateClick(event: JQuery.ClickEvent): void {
		event.preventDefault();
		event.stopPropagation();

		const $button = jQuery(event.currentTarget);
		const title = $button.attr("title");
		const $row = $button.closest("tr");
		const rowIndex = $row.index();

		const fields = $row
			.find("input")
			.toArray()
			.reduce<Fields>((acc, input) => {
				const $input = jQuery(input);
				const key = $input.data("key");

				if (key) {
					acc[key] = {
						field: $input.data("field"),
						value: $input.val() as string,
					};
				}

				return acc;
			}, {});

		// Ensure modalData is a complete object
		const modalData = {
			title,
			fields,
		};

		const $modal = this.modalManager.renderModal(
			CONSTANTS.TEMPLATES.ADD_RATE,
			modalData,
			($modal) => this.handleAddRateConfirm($modal, rowIndex),
		);

		if ($modal) {
			Object.entries(fields).forEach(([key, fieldData]) => {
				const $field = $modal.find(`#woocommerce_wcsdm_fake--field--${key}`);

				if ($field.length) {
					$field.val(fieldData.value).trigger("change");
				}
			});
		}
	}

	private handleDeleteRateClick(event: JQuery.ClickEvent): void {
		event.preventDefault();
		event.stopPropagation();

		const $button = jQuery(event.currentTarget);
		const modalTitle = $button.attr("title");
		const rowNumber = $button.closest("tr").index() + 1;

		const modalData = {
			title: modalTitle,
			rowNumber: rowNumber,
		};

		this.modalManager.renderModal(
			CONSTANTS.TEMPLATES.DELETE_RATE,
			modalData,
			() => this.handleDeleteRateConfirm($button),
		);
	}

	private handleDeleteRateConfirm($button: JQuery): void {
		$button.closest("tr").remove();
		this.modalManager.closeModal();
		this.setRateRowsNumber();
		this.setupEventListenerMoveRate();
	}

	private generateDummyText($field: JQuery, value: string): string {
		const options = $field.data("options") || {};
		return options?.[value] ?? value;
	}
}
