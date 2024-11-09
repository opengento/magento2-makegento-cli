<?xml version="1.0" encoding="UTF-8" ?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Magento_Reports::report">
                    <resource id="Magento_Reports::customers">
                        <resource id="{{module_name}}::listing" title="{{aclTitle}}" translate="title" sortOrder="42"/>
                   </resource>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
