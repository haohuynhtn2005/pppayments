(() => {
    redirectToPaymentLink();
    if (false) {
        renderPaypalButtons();
    }

    function redirectToPaymentLink() {
        const button = document.querySelector("#btn");
        // button.addEventListener("click", () => {
        //     // const popup = window.open(
        //     //     "https://example.com",
        //     //     "myPopup",
        //     //     "width=600,height=700"
        //     // );
        //     window.open(PageConfig.captureUrl, "_blank");
        //     // window.location.href = "about:blank";
        // });
        const a = document.createElement("a");
        a.href = PageConfig.paymentLink;
        a.referrerPolicy = "no-referrer";
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    function renderPaypalButtons() {
        const buttons = paypal.Buttons({
            style: {
                disableMaxWidth: true,
                // height: 45,
            },
            async createOrder() {
                return PageConfig.token;
            },

            async onApprove(data) {
                const url = new URL(PageConfig.captureUrl);
                url.searchParams.set("token", data.orderID);
                url.searchParams.set("PayerID", data.payerID);
                const navigateUrl = url.toString();
                window.location.assign(navigateUrl);
                return;
                const popup = window.open(
                    "https://example.com",
                    "myPopup",
                    "width=600,height=700"
                );
            },

            onError(err) {
                console.error(err);
            },

            onCancel(data) {
                return;
            },
        });

        buttons.render("#paypal-button");
    }
})();
