{% macro showLicense(license, context) -%}
    {%- if context.user %} [**`{{ license.getShortKey() }}`**]({{ license.getEditUrl() }})
    {%- else %} **`{{ license.getShortKey() }}`**
    {%- endif %}
    {%- set domain = license.getDomain() %}
    {%- if domain %} ({{ domain }}){% endif %}
    {{- " – currently running #{context.name} #{license.lastVersion}" }}
{%- endmacro %}

{% set user = user ?? null %}

Hi {{ user.friendlyName ?? 'there' }},

{{ name }} {{ release.version }} (released on {{ release.time|date('Y-m-d') }}) fixes a critical security vulnerability, which is being actively exploited on numerous Craft sites.

According to our records, the following of your {{ name }} licenses are currently vulnerable:

{% for license in licenses %}
- {{ _self.showLicense(license, _context) }}

{% endfor %}

Please ask your web developer to update these licenses immediately, to avoid your site getting hacked.

Note that if it has been more than a year since you purchased or renewed these licenses, you will need to renew them from [id.craftcms.com](https://id.craftcms.com) before they will be eligible to receive additional updates.
