{% macro showLicense(license, user) -%}
    {{ license.getEdition().getDescription() }}
    {%- if user %} [**`{{ license.getShortKey() }}`**]({{ license.getEditUrl() }})
    {%- else %} **`{{ license.getShortKey() }}`**
    {%- endif %}
    {%- set domain = license.getDomain() %}
    {%- if domain %} ({{ domain }}){% endif %}
{%- endmacro %}

{% from _self import showLicense %}
{% set user = user ?? null %}

Hey {{ user.friendlyName }},

{% if renewedLicenses|length %}
{% set pl = renewedLicenses|length != 1 %}
The following {{ pl ? 'licenses have' : 'license has' }} been auto-renewed:

{% for license in renewedLicenses %}
- {{ showLicense(license, user) }}

{% endfor %}
{% endif %}

{% if expiredLicenses|length %}
{% set pl = expiredLicenses|length != 1 %}
{{ renewedLicenses|length ? 'And the' : 'The' }} following {{ pl ? 'licenses have' : 'license has' }} expired:

{% for license in expiredLicenses %}
- {{ showLicense(license, user) }}

{% endfor %}

{% if autoRenewFailed %}
_(We attempted to auto-renew {{ pl ? 'some of them' : 'it' }}, however there was an issue with your [billing info](https://id.craftcms.com/account/billing).)_
{% endif %}

{% if user %}
To ensure you don’t miss any updates, click on the license key {{ pl ? 'links' : 'link' }} above, and click the “Renew your license” button in the “Updates” section.
{% else %}
To ensure you don’t miss any updates, follow these steps:

1. Create a [Craft ID](https://id.craftcms.com) account (if you don’t already have one).
2. Go to the [Claim License](https://id.craftcms.com/licenses/claim) page to add {{ pl ? 'these licenses' : 'this license' }} to your account, using the “Claim licenses by your email address” feature.
3. From the license {{ pl ? 'screens' : 'screen' }}, click  and click the “Renew your license” button in the “Updates” section.
{% endif %}
{% endif %}

Have a great day!
