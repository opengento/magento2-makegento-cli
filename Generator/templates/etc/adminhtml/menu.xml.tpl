<?xml version="1.0" encoding="UTF-8" ?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework/Menu/etc/menu.xsd">
    <menu>
        <add id="{{module_name}}::index"
             title="{{menuEntryTitle}}"
             module="{{module_name}}"
             sortOrder="{{order}}"
             action="{{frontName}}/{{controller}}/{{action}}"
             resource="{{module_name}}::index" />
    </menu>
</config>
