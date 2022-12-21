import { APIRequestContext, Locator, Page } from '@playwright/test';
import { Address } from '../utils/Types';
import { GetApiClient } from '../utils/Utils';

export class Cart {
	readonly page: Page;

	constructor(page: Page) {
		this.page = page;
	}

	async goto(productId?: number) {
		await this.page.goto('/cart');
	}

	async addtoCart(sku: string | string[]) {
		if (Array.isArray(sku)) {
			await this.addtoCartMultiple(sku);
		} else {
			await this.addtoCartSingle(sku);
		}
	}

	async addtoCartSingle(sku: string) {
		const product = await this.getProduct(sku);

		await this.page.goto(`/cart/?add-to-cart=${product.id}`);
	}

	async addtoCartMultiple(skus: string[]) {
		// Each SKU can be passed multiple times to add multiple products, so create a temporary object for each SKU, with the value being the number of times it should be added.
		const skusToAdd: { [key: string]: number } = {};

		for (const sku of skus) {
			if (skusToAdd[sku]) {
				skusToAdd[sku]++;
			} else {
				skusToAdd[sku] = 1;
			}
		}

		const products = await this.getProducts(skus);

		for (const product of products) {
			for (let i = 0; i < skusToAdd[product.sku]; i++) {
				await this.page.goto(`/cart/?add-to-cart=${product.id}`);
			}
		}
	}

	async getProduct(sku: string): Promise<any> {
		const apiclient = await GetApiClient();

		const response = await apiclient.get(`products`, {
			params: {
				sku: sku,
			}
		});

		const product = await response.json();

		return product;
	}

	async getProducts(skus: string[]): Promise<any[] | any> {
		const apiclient = await GetApiClient();

		const response = await apiclient.get(`products`, {
			params: {
				sku: skus.join(','),
			}
		});

		const products = await response.json();

		return products;
	}
}
