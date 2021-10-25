import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
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
				// @TODO - Modify any other settings here as needed.

				// --------------- ADD PRODUCTS TO CART --------------- //
				await utils.addMultipleProductsToCart(page, args.products, json);
				await page.waitForTimeout(1 * timeOutTime);

				// --------------- GO TO CHECKOUT --------------- //
				await page.goto(urls.CHECKOUT);
				await page.waitForTimeout(timeOutTime);
				// @TODO - Select your payment method.

				// --------------- COUPON HANDLER --------------- //
				await utils.applyCoupons(page, args.coupons);

				// --------------- PLACE ORDER  --------------- //
				// @TODO - Custom code here to place the actual order.
			} catch(e) {
				console.log("Error placing order", e)
			}

			// --------------- POST PURCHASE CHECKS --------------- //
			
			await page.waitForTimeout(5 * timeOutTime);
			const value = await page.$eval(".entry-title", (e) => e.textContent);
			expect(value).toBe("Order received");

			// @TODO - Run any other needed checks here against your tests extra checks.
	}, 190000);
});
