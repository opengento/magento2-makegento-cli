<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    {% foreach table_declarations %}
    <table name="{{table_name}}" {% attr table_attr %}>
        {% foreach fields %}
        <column name="{{field_name}}" xsi:type="{{field_type}}" {% attr field_attr %}/>
        {% endforeach fields %}
        <index referenceId="{{index_key}}" indexType="{{index_type}}">
            {% foreach index_columns %}
            <column name="{{index_column_name}}"/>
            {% endforeach index_columns %}
        </index>
        {% if primary %}
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="{{primary_field}}"/>
        </constraint>
        {% endif primary %}
        {% foreach constraints %}
            {% if constraint_not_foreign %}
            <constraint xsi:type="{{constraint_type}}" referenceId="{{constraint_name}}" {% attr constraint_attr %} />
            {% endif constraint_not_foreign %}
            {% if constraint_not_foreign %}
            <constraint xsi:type="{{constraint_type}}" referenceId="{{constraint_name}}" {% attr constraint_attr %} />
            {% foreach constraint_columns %}
                <column name="{{constraint_column_name}}"/>
            {% endforeach constraint_columns %}
            {% endif constraint_not_foreign %}
            </constraint>
        {% endforeach constraints %}
    </table>
    {% endforeach table_declarations %}

</schema>
