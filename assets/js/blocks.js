(() => {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { getSetting } = window.wc.wcSettings;
    const { createElement } = window.wp.element;

    const settings = getSetting("pppayments_data", {});
    registerPaymentMethod({
        name: "pppayments",
        label: settings.title || "PPPayments",
        ariaLabel: settings.title || "PPPayments",
        content: createElement("div", {}, settings.description || ""),
        edit: createElement("div", {}, settings.description || ""),
        canMakePayment: () => {
            return true;
        },
        placeOrderButtonLabel: "Proceed to pay",
        supports: {
            features: settings.supports || [],
        },
    });
})();
