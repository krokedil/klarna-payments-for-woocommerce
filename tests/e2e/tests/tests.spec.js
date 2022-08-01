import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import kpUtils from "../helpers/kpUtils";
import tests from "../config/tests.json"
import data from "../config/data.json";
import orderManagement from "../helpers/orderManagement";

const options = {
	"headless": false,
	"defaultViewport": null,
	"args": [
		"--disable-infobars",
		"--disable-web-security",
		"--disable-features=IsolateOrigins,site-per-process"
	]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;
let orderID


describe("Test name", () => {
	beforeAll(async () => {
		try {

			json = await setup.setupStore(json);
			utils.setOptions();

		} catch (e) {
			console.log(e);
		}
	}, 250000);

	beforeEach(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
	}),

	afterEach(async () => {
		if (!page.isClosed()) {
			browser.close();
		}
		API.clearWCSession();
	}),

	test.each(tests)(

		"$name",
		async (args) => {
			try {
				// --------------- GUEST/LOGGED IN --------------- //
				if(args.loggedIn) {
					await page.goto(urls.MY_ACCOUNT);
					await utils.login(page, "admin", "password");
				}

				// --------------- SETTINGS --------------- //
				await utils.setPricesIncludesTax({value: args.inclusiveTax});

				// --------------- ADD PRODUCTS TO CART --------------- //
				await utils.addMultipleProductsToCart(page, args.products, json);
				await page.waitForTimeout(2*timeOutTime);

				// --------------- GO TO CHECKOUT --------------- //
				await page.goto(urls.CHECKOUT);

				// --------------- SELECT SHIPPING METHOD ------- //

				await page.waitForTimeout(timeOutTime);
				await kpUtils.selectShippingMethod(page, args.shippingMethod)
				// --------------- SELECT KP -------------------- //

				if(paymentSelector) {
					await paymentSelector.click({clickCount: 3})
				}

				await page.waitForTimeout( timeOutTime);

				// --------------- COUPON HANDLER --------------- //
				await utils.applyCoupons(page, args.coupons);

				// --------------- PLACE ORDER  --------------- //
				await kpUtils.fillWcForm(page, args.customerType);

				await page.waitForTimeout( timeOutTime);

				let paymentSelector = await page.$('label[for="payment_method_klarna_payments_pay_later"]')

				if (paymentSelector) {
					await paymentSelector.click({clickCount: 3})
				}

				await page.waitForTimeout( 2 * timeOutTime);

				if ( await page.$("#place_order") ) {
					let placeOrder = await page.$("button[name='woocommerce_checkout_place_order']");
					await placeOrder.click({clickCount: 3})
				}

				await page.waitForTimeout(2 * timeOutTime);

				let kpIframe = await page.frames().find((frame) => frame.name() === "klarna-pay-later-fullscreen");
				await kpUtils.processKpIframe(page, kpIframe);

			} catch(e) {
				console.log("Error placing order", e)
			}

			// --------------- POST PURCHASE CHECKS --------------- //
			await page.waitForTimeout(3 * timeOutTime);
			const value = await page.$eval(".entry-title", (e) => e.textContent);
			expect(value).toBe("Order received");

			let checkoutURL = await page.evaluate(() => window.location.href)
			orderID = await checkoutURL.split('/')[5]

			if(args.orderManagement != '') {
				await orderManagement.OrderManagementAction(page, orderID, args.orderManagement)
			}
			
	}, 250000);
});
