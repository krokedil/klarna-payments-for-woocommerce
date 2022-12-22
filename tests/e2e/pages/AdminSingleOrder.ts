import { Locator, Page } from '@playwright/test';

export class AdminSingleOrder {
	readonly page: Page;
	readonly orderId: number | string;

	readonly orderStatusSelect: Locator;
	readonly updateOrderButton: Locator;

	readonly orderItemLines: Locator;
	readonly orderFeeLines: Locator;
	readonly orderShippingLines: Locator;

	readonly refundButton: Locator;
	readonly manualRefundButton: Locator;
	readonly apiRefundButton: Locator;
	readonly refundLineItemQtyInput = (lineItemId: string) => this.page.locator(`[name="refund_order_item_qty[${lineItemId}]"]`);
	readonly refundLineTotalInput = (lineItemId: string) => this.page.locator(`[name="refund_line_total[${lineItemId}]"]`);
	readonly refundLineTaxInput = (lineItemId: string, taxId: string) => this.page.locator(`[name="refund_line_tax[${lineItemId}][${taxId}]"]`);

	readonly orderNotes: Locator;

	constructor(page: Page, orderId: number | string) {
		this.page = page;
		this.orderId = orderId;

		this.orderStatusSelect = this.page.locator('#order_status');
		this.updateOrderButton = this.page.locator('#woocommerce-order-actions button[name="save"]');

		this.orderItemLines = this.page.locator('#order_line_items');
		this.orderFeeLines = this.page.locator('#order_fee_line_items');
		this.orderShippingLines = this.page.locator('#order_shipping_line_items');

		this.refundButton = this.page.locator('button.refund-items');
		this.manualRefundButton = this.page.locator('button.do-manual-refund');
		this.apiRefundButton = this.page.locator('button.do-api-refund');

		this.orderNotes = this.page.locator('.order_notes li');
	}

	async goto() {
		await this.page.goto(`/wp-admin/post.php?post=${this.orderId}&action=edit`);
	}

	async completeOrder() {
		await this.orderStatusSelect.selectOption('Completed');
		await Promise.all([
			this.updateOrderButton.click(),
			this.page.waitForNavigation({ url: `/wp-admin/post.php?post=${this.orderId}&action=edit` }),
		]);
	}

	async cancelOrder() {
		await this.orderStatusSelect.selectOption('Cancelled');
		await this.updateOrderButton.click();
	}

	async refundFullOrder(order: any, manualRefund: boolean = true) {
		this.page.on('dialog', async (dialog) => {
			await dialog.accept();
		});

		await this.refundButton.click();

		// Refund all line items.
		for (const lineItem of order.line_items) {
			await this.refundLineItemQtyInput(lineItem.id).fill('1');
		}

		// Refund all fees.
		for (const fee of order.fee_lines) {
			await this.refundLineTotalInput(fee.id).fill(fee.total);
			for (const tax of fee.taxes) {
				await this.refundLineTaxInput(fee.id, tax.id).fill(tax.total);
			}
		}

		// Refund all shipping lines.
		for (const shipping of order.shipping_lines) {
			await this.refundLineTotalInput(shipping.id).fill(shipping.total);
			for (const tax of shipping.taxes) {
				await this.refundLineTaxInput(shipping.id, tax.id).fill(tax.total);
			}
		}

		if (manualRefund) {
			Promise.all([
				await this.manualRefundButton.click(),
				await this.page.waitForNavigation(),
			]);
		} else {
			Promise.all([
				await this.apiRefundButton.click(),
				await this.page.waitForNavigation(),
			]);
		}
	}

	async refundPartialOrder(order: any, manualRefund: boolean = true) {
		this.page.on('dialog', async (dialog) => {
			await dialog.accept();
		});

		// Same as refundFullOrder, but only refund 50% of the line totals.
		await this.refundButton.click();

		for (const lineItem of order.line_items) {
			await this.refundLineTotalInput(lineItem.id).fill((lineItem.total / 2).toFixed(2));
			for (const tax of lineItem.taxes) {
				await this.refundLineTaxInput(lineItem.id, tax.id).fill((tax.total / 2).toFixed(2));
			}
		}

		for (const fee of order.fee_lines) {
			await this.refundLineTotalInput(fee.id).fill((fee.total / 2).toFixed(2));
			for (const tax of fee.taxes) {
				await this.refundLineTaxInput(fee.id, tax.id).fill((tax.total / 2).toFixed(2));
			}
		}

		for (const shipping of order.shipping_lines) {
			await this.refundLineTotalInput(shipping.id).fill((shipping.total / 2).toFixed(2));
			for (const tax of shipping.taxes) {
				await this.refundLineTaxInput(shipping.id, tax.id).fill((tax.total / 2).toFixed(2));
			}
		}

		if (manualRefund) {
			Promise.all([
				await this.manualRefundButton.click(),
				await this.page.waitForNavigation(),
			]);
		} else {
			Promise.all([
				await this.apiRefundButton.click(),
				await this.page.waitForNavigation(),
			]);
		}
	}

	async hasOrderNoteWithText(text: string) {
		const orderNoteCount = await this.orderNotes.count();

		if (orderNoteCount === 0) {
			return false;
		}

		for (let i = 0; i < orderNoteCount; i++) {
			const note = await this.orderNotes.nth(i).innerText();
			if (note.includes(text)) {
				return true;
			}
		}

		return false;
	}
}
