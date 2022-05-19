import woocommerce from "./woocommerce";

const getWCOrderById = async (id) => woocommerce.getOrderById(id);
const createWCCustomer = async (data) => woocommerce.createCustomer(data);
const getWCCustomers = async () => woocommerce.getCustomers();
const clearWCSession = async () => woocommerce.clearSession();
const updateOptions = async (data) => woocommerce.updateOption(data);
const createWCProduct = async (data) => woocommerce.createProduct(data);
const getWCOrders = async () => woocommerce.getOrders();
const getWCProductById = async (id) => woocommerce.getProductById(id);
const pricesIncludeTax = async (data) => woocommerce.pricesIncludeTax(data);

export default {
	getWCOrderById,
	getWCCustomers,
	createWCCustomer,
	clearWCSession,
	updateOptions,
	createWCProduct,
	getWCOrders,
	getWCProductById,
	pricesIncludeTax,
};
