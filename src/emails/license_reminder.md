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

{% set hasManual = licenses.manual is defined %}
{% set hasAuto = licenses.auto is defined %}

{% if hasManual %}
{% set pl = licenses.manual|length != 1 %}
The following {{ pl ? 'licenses' : 'license' }} will expire soon:

{% for license in licenses.manual %}
- {{ showLicense(license, user) }} will expire on {{ license.expiresOn|date('F j') }}.

{% endfor %}

When a license expires, you can keep using the version that’s currently installed, however you won’t be able to update it to newer versions until you’ve renewed the license.

{% if user %}
To ensure you don’t miss any updates, click on the license key {{ pl ? 'links' : 'link' }} above, and toggle {{ pl ? 'their Auto-Renew settings' : 'its Auto-Renew setting' }}.
{%- if not hasAuto %} (And don’t forget to make sure your [billing info] is up-to-date.){% endif %}
{% else %}
If you’d like to enable auto-renew for {{ pl ? 'these licenses' : 'this license' }} to ensure you don’t miss any updates, follow these steps:

1. Create a [Craft ID](https://id.craftcms.com) account (if you don’t already have one).
2. Save your [billing info] on your account.
3. Go to the [Claim License](https://id.craftcms.com/licenses/claim) page to add {{ pl ? 'these licenses' : 'this license' }} to your account, using the “Claim licenses by your email address” feature.
4. From the license {{ pl ? 'screens' : 'screen' }}, toggle {{ pl ? 'their Auto-Renew settings' : 'its Auto-Renew setting' }}.
{% endif %}
{% endif %}

{% if hasAuto %}
{% set pl = licenses.auto|length != 1 %}
The following {{ pl ? 'licenses are' : 'license is' }} set to auto-renew soon:

{% for license in licenses.auto %}
- {{ showLicense(license, user) }} will auto-renew on {{ license.expiresOn|date('Y-m-d') }} for {{ license.renewalPrice|currency('USD') }}.

{% endfor %}

{% if user %}
Please take a minute to make sure your [billing info] is up-to-date, so there’s no trouble charging your card on the renewal {{ pl ? 'dates' : 'date' }}.
{% endif %}
{% endif %}

Have a great day!

[billing info]: https://id.craftcms.com/account/billing
