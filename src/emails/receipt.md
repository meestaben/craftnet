Thank you so very much for your purchase!

Clearly you're an important person... a mover and a shaker. You're probably very attractive and intelligent, too.

That's why you just purchased this amazing software. You know it's going to help turn your website and your business into a titan of industry.

Books will be written. Songs will be sung. Parades will be thrown.

Go forth and build something incredible.

---

**Purchase Details**

Order: {{ order.shortNumber|upper }}

Purchase Date: {{ order.datePaid|date('medium') }}

---

{% for lineItem in order.getLineItems() %}
{% set purchasable = lineItem.getPurchasable() %}
{% set developer = purchasable and className(purchasable) matches '/^craftnet\\\\cms\\\\/' ? 'Pixel & Tonic' : (purchasable.getPlugin().getDeveloperName() ?? '') %}

{{ lineItem.getDescription() ~ (developer ? " (by #{developer})") }} — {{ (lineItem.price)|currency('USD') }}

{% for adjustment in lineItem.getAdjustments() %}
- {{ adjustment.name }} — {{ (adjustment.amount)|currency('USD') }}
{% endfor %}

{% endfor %}

---

**Total: {{ order.totalPrice|currency('USD') }}**

---

You can access your licenses from your [Craft ID](https://id.craftcms.com) account.

If you made this purchase with a different email address than the one tied to your
Craft ID account, use the [Claim licenses by email address](https://id.craftcms.com/licenses/claim)
tool to access the licenses and order history.
