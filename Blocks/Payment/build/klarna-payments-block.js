/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./Blocks/Payment/src/css/klarna-payments-block.scss":
/*!***********************************************************!*\
  !*** ./Blocks/Payment/src/css/klarna-payments-block.scss ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!********************************************************!*\
  !*** ./Blocks/Payment/src/js/klarna-payments-block.js ***!
  \********************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _css_klarna_payments_block_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../css/klarna-payments-block.scss */ "./Blocks/Payment/src/css/klarna-payments-block.scss");

var decodeEntities = wp.htmlEntities.decodeEntities;
var getSetting = wc.wcSettings.getSetting;
var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
var applyFilters = wp.hooks.applyFilters;

// Data
var settings = getSetting('klarna_payments_data', {});
var title = applyFilters('kp_blocks_title', decodeEntities(settings.title || 'Klarna'), settings);
var description = applyFilters('kp_blocks_description', decodeEntities(settings.description || ''), settings);
var iconUrl = settings.iconurl;
var canMakePayment = function canMakePayment() {
  return applyFilters('kp_blocks_enabled', true, settings);
};
var Content = function Content(props) {
  return /*#__PURE__*/React.createElement("div", null, description);
};
var Label = function Label(props) {
  var PaymentMethodLabel = props.components.PaymentMethodLabel;
  var icon = /*#__PURE__*/React.createElement("img", {
    src: iconUrl,
    alt: title,
    name: title
  });
  return /*#__PURE__*/React.createElement(PaymentMethodLabel, {
    className: "kp-block-label",
    text: title,
    icon: icon
  });
};

/**
 * Klarna payments method config.
 */
var KlarnaPaymentsOptions = {
  name: 'klarna_payments',
  label: /*#__PURE__*/React.createElement(Label, null),
  content: /*#__PURE__*/React.createElement(Content, null),
  edit: /*#__PURE__*/React.createElement(Content, null),
  placeOrderButtonLabel: settings.orderbuttonlabel,
  canMakePayment: canMakePayment,
  ariaLabel: title
};
registerPaymentMethod(KlarnaPaymentsOptions);
})();

/******/ })()
;
//# sourceMappingURL=klarna-payments-block.js.map