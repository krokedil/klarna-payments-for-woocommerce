export type Address = {
	firstName?: string;
	lastName?: string;
	company?: string;
	country?: string;
	address1?: string;
	address2?: string;
	city?: string;
	state?: string;
	postcode?: string;
	phone?: string;
	email?: string;
};

export interface TaxClass {
	name: string;
	rates: TaxRate[];
}

export interface TaxRate {
	name: string;
	rate: string;
	shipping: boolean;
	country?: string;
	state?: string;
	postcode?: string;
	city?: string;
}

export interface Product {
	name: string;
	sku: string;
	type: "simple" | "variable" | "grouped" | "external";
	regular_price?: number;
	sale_price?: number;
	stock_quantity?: number;
	stock_status?: string;
	manage_stock?: boolean;
	tax_class?: string;
	downloadable?: boolean;
	virtual?: boolean;
	categories?: string[];
	tags?: string[];
	images?: string[];
	attributes?: Attribute[];
	variations?: Variation[];
}

export interface Attribute {
	name: string;
	options: string[] | string;
	visible: boolean;
	variation: boolean;
}

export interface Variation {
	sku: string;
	regular_price: number;
	sale_price?: number;
	stock_quantity?: number;
	stock_status?: string;
	manage_stock?: boolean;
	tax_class?: string;
	attributes: VariationAttribute[];
}

export interface VariationAttribute {
	name: string;
	option: string;
}

export interface Coupon {
	code: string;
	amount: number;
	discount_type: string;
	free_shipping?: boolean;
}

export interface ShippingZone {
	name: string;
	locations?: ShippingZoneLocation[];
	methods?: ShippingZoneMethod[];
}

export interface ShippingZoneLocation {
	code: string;
	type: string;
}

export interface ShippingZoneMethod {
	id: string;
	title: string;
	settings?: { [key: string]: string };
}
