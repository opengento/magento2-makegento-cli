<?xml version="1.0" encoding="UTF-8" ?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="{{module_name}}::view" title="{{aclTitleView}}" translate="title" sortOrder="10"/>
                <resource id="{{module_name}}::manage" title="{{aclTitleManage}}" translate="title" sortOrder="15"/>
            </resource>
        </resources>
    </acl>
</config>
