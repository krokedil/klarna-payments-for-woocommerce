import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import kpUtils from "../helpers/kpUtils";
import tests from "../config/tests.json"
import data from "../config/data.json";

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
				await page.waitForTimeout(2 * timeOutTime);

				// --------------- GO TO CHECKOUT --------------- //

				await page.goto(urls.CHECKOUT);
				await page.waitForTimeout(timeOutTime);

				let paymentSelector = await page.$('label[for="payment_method_klarna_payments_klarna_payments"]')
				await paymentSelector.click()

				await page.waitForTimeout(timeOutTime);

				let klarnaIframe =await page.frames().find((frame) => frame.name() === "klarna-payments-main");

				await page.waitForTimeout(timeOutTime);
				await kpUtils.setPaymentMethod(klarnaIframe, "pay_later");
				await page.waitForTimeout(timeOutTime);

				await page.waitForTimeout(0.5 * timeOutTime);

				// --------------- COUPON HANDLER --------------- //
				await utils.applyCoupons(page, args.coupons);

				// --------------- PLACE ORDER  --------------- //
				await kpUtils.fillWcForm(page, args.customerType);
				await page.waitForTimeout(2 * timeOutTime);

				let kpIframe = await page.frames().find((frame) => frame.name() === "klarna-payments-fullscreen");

				await kpUtils.processKpIframe(page, kpIframe);

			} catch(e) {
				console.log("Error placing order", e)
			}

			// --------------- POST PURCHASE CHECKS --------------- //
			await page.waitForTimeout(3 * timeOutTime);
			const value = await page.$eval(".entry-title", (e) => e.textContent);
			expect(value).toBe("Order received");

	}, 250000);
});
