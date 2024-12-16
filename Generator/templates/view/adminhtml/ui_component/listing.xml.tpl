<?xml version="1.0" encoding="utf-8"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">
    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">{{ui_component_name}}.{{ui_component_name}}_data_source</item>
            <item name="deps" xsi:type="string">{{ui_component_name}}.{{ui_component_name}}_data_source</item>
        </item>
        <item name="spinner" xsi:type="string">{{columns_name}}</item>
        {{buttons}}
    </argument>

    <dataSource name="{{ui_component_name}}_data_source">
        <argument name="dataProvider" xsi:type="configurableObject">
            <argument name="class" xsi:type="string">{{data_provider}}</argument> <!-- Data provider class -->
            <argument name="name" xsi:type="string">{{ui_component_name}}_data_source</argument> <!-- provider defined above -->
            <argument name="primaryFieldName" xsi:type="string">{{primary_field_name}}</argument> <!-- Primary key -->
            <argument name="requestFieldName" xsi:type="string">id</argument> <!-- URL name parameter -->

            <argument name="data" xsi:type="array">
                <item name="config" xsi:type="array">
                    <item name="component" xsi:type="string">Magento_Ui/js/grid/provider</item>
                    <item name="update_url" xsi:type="url" path="mui/index/render"/>
                    <item name="storageConfig" xsi:type="array">
                        <!-- Primary key column name -->
                        <item name="indexField" xsi:type="string">{{primary_field_name}}</item>
                    </item>
                </item>
            </argument>
        </argument>
    </dataSource>

    <listingToolbar name="listing_top">
        <settings>
            <sticky>true</sticky>
        </settings>
        <bookmark name="bookmarks"/>
        <columnsControls name="columns_controls"/>
        <filterSearch name="fulltext"/>
        <filters name="listing_filters"/>
        <paging name="listing_paging"/>
    </listingToolbar>

    <columns name="{{columns_name}}">
        <selectionsColumn name="ids" sortOrder="0">
            <settings>
                <indexField>{{primary_field_name}}</indexField>
            </settings>
        </selectionsColumn>
        {{columns}}
    </columns>
</listing>
