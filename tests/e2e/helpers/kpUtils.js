import { ConsoleMessage } from "puppeteer";

const timeOutTime = 2500;

const setPaymentMethod = async (klarnaIframe, paymentMethod) => {

	if(paymentMethod === 'pay_later') {
		let input = await klarnaIframe.$('#radio-pay_later');
		input.click({clickCount: 3})
	}
}

const selectShippingMethod = async (page, shippingMethod) => {

	let searchString

	if (shippingMethod === 'free_shipping') {
		searchString = 'input[value*="free_shipping"]'
	} else if (shippingMethod === 'flat_rate') {
		searchString = 'input[value*="flat_rate"]'
	}

	let shippingMethodSelector = await page.$(searchString)

	if (shippingMethodSelector) {
		await shippingMethodSelector.focus()
		await shippingMethodSelector.click()
	}

}

const fillWcForm = async (page, customer_type) => {

	if(customer_type === "company") {
		if ( await page.$("#billing_company") ) {
			let inputField = await page.$("#billing_company");
			await inputField.click({clickCount: 3});
			await inputField.type("Krokedil");
		}
	}

	if ( await page.$("#billing_first_name") ) {
		let inputField = await page.$("#billing_first_name");
		await inputField.click({clickCount: 3});
		await inputField.type("Test");
	}

	if ( await page.$("#billing_last_name") ) {
		let inputField = await page.$("#billing_last_name");
		await inputField.click({clickCount: 3});
		await inputField.type("Testsson");
	}

	if ( await page.$("#billing_address_1") ) {
		let inputField = await page.$("#billing_address_1");
		await inputField.click({clickCount: 3});
		await inputField.type("Hamngatan 2");
	}

	if ( await page.$("#billing_postcode") ) {
		let inputField = await page.$("#billing_postcode");
		await inputField.click({clickCount: 3});
		await inputField.type("67131");
	}

	if ( await page.$("#billing_city") ) {
		let inputField = await page.$("#billing_city");
		await inputField.click({clickCount: 3});
		await inputField.type("Arvika");
	}

	if ( await page.$("#billing_phone") ) {
		let inputField = await page.$("#billing_phone");
		await inputField.click({clickCount: 3});
		await inputField.type("0701234567");
	}

	if ( await page.$("#billing_email") ) {
		let inputField = await page.$("#billing_email");
		await inputField.click({clickCount: 3});
		await inputField.type("e2e@krokedil.se");
	}

	if ( await page.$("#terms") ) {
		let input = await page.$("#terms");
		await input.evaluate(i => i.click());
	}


}

const processKpIframe = async (page, kpIframe) => {

	await kpIframe.waitForTimeout(timeOutTime);

	if ( await kpIframe.$("#invoice_kp-purchase-approval-form-national-identification-number") ) {

		let inputField = await kpIframe.$("#invoice_kp-purchase-approval-form-national-identification-number");
		await inputField.click({clickCount: 3});
		await inputField.type("19770111-6050");
	}

	if ( await kpIframe.$("#invoice_kp-purchase-approval-form-continue-button") ) {

		let button = await kpIframe.$("#invoice_kp-purchase-approval-form-continue-button");
		await button.click();
	}
}
export default {
	setPaymentMethod,
	selectShippingMethod,
	fillWcForm,
	processKpIframe
}
