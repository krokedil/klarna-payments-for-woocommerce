import API from "../api/API";
import urls from "./urls";

const timeOutTime = 2500;

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.click('[name="apply_coupon"]');
		});
	}
	await page.waitForTimeout(3 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${urls.ADD_TO_CART}${productSelector}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);
	let ids = [];

	products.forEach( name => {
		data.products.simple.forEach(product => {
			if(name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach(product => {
			product.attribute.options.forEach(variation => {
				if(name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	(async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};


export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
};
