{% macro showLicense(license, user) -%}
    {%- if user %}[**`{{ license.getShortKey() }}`**]({{ license.getEditUrl() }})
    {%- else %}**`{{ license.getShortKey() }}`**
    {%- endif %}
    {%- set domain = license.getDomain() %}
    {%- if domain %} ({{ domain }}){% endif %}
{%- endmacro %}

{% from _self import showLicense %}
{% set user = user ?? null %}

Hey {{ user.friendlyName ?? 'there' }},

{{ newPlugin.getDeveloperName() }} recently launched a new plugin, {{ newPlugin.name }}, which replaces {{ oldPlugin.name }}.

They’ve requested that all old {{ oldPlugin.name }} licenses be transferred to {{ newPlugin.name }}.

Here’s a list of your affected licenses:

{% for license in licenses %}
- {{ showLicense(license, user) }}

{% endfor %}

If you decide to update your sites to install {{ newPlugin.name }}, you can use your existing license keys rather than purchasing new ones.

Have a great day!
