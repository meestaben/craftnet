{% macro showLicense(license, user) -%}
    {%- if user %} [**`{{ license.getShortKey() }}`**]({{ license.getEditUrl() }})
    {%- else %} **`{{ license.getShortKey() }}`**
    {%- endif %}
    {%- set domain = license.getDomain() %}
    {%- if domain %} ({{ domain }}){% endif %}
{%- endmacro %}

{% set user = user ?? null %}

Hi {{ user.friendlyName ?? 'there' }},

A critical update for {{ name }} is available, which fixes a security vulnerability with a known exploit.

According to our records, the following of your {{ name }} licenses are currently vulnerable:

{% for license in licenses %}
- {{ _self.showLicense(license, user) }}

{% endfor %}

We highly recommend you update these licenses at your earliest opportunity.
