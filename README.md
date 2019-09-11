# THRON / Drupal Connector: installation procedure

## Initial setup

The THRON connector can be installed on Drupal version 8.4.0 or later, on an already configured site.

You must first install the modules the Drupal connector depends on. We recommended to exploit the following `composer` commands:

* The "_entity_browser_" module (version 2.1 or later). This can be installed via composer (`composer require drupal/entity_browser`) or by downloading `tar.gz` archives of the module and its dependencies (https://www.drupal.org/project/entity_browser) 
* The "_entity_embed_" module (version 1.0-rc2 or later). This can be installed via composer (`composer require drupal/entity_embed`) or by downloading `tar.gz` archives of the module and its dependencies (https://www.drupal.org/project/entity_embed) 
* The "_media_entity_browser_" module (version 2.0 or later). This can be installed via composer (`composer require "drupal/media_entity_browser:^2.0"`) or by downloading `tar.gz` archives of the module and its dependencies (https://www.drupal.org/project/media_entity_browser) 

You can now extract the "THRON module" zip archive in the path `web/modules/custom` in order to have the following set: `web/modules/custom/thron`.

Lastly, enable the module in the "Extend" section of Drupal which is located at the `/admin/modules` path of the site.

## Configuring Credentials and Integration details

Go to the `/admin/config/services/thron` path in the Drupal settings for entering the credentials and configuring the integration details.

![THRON Application configuration](https://hub-cdn.thron.com/delivery/public/image/hub/99112ae9-7b21-4473-8444-f15334524936/0p5eek/std/1024x768/00-drupal-config "THRON Application configuration")

Fill the panel with the THRON credentials (client ID, app ID, app key) you have been provided and then click on the "Save configuration" button to start the integration process.

Once you have saved the credentials, you will be asked to provide a series of further customizations: the most important are related to he intelligence classifications that will be used to restrict the search on tags to; the connector will search only on the tags on the enabled classifications (for instance, "Topic" and "Target").

## Configuring text format

In the following configuration step you will enable the THRON content embedding tool by adding the relevant button in the CK Editor toolbar. You can choose the text format you want to enable this button on (Basic HTML, Full HTML, or any other text format defined in the Drupal site configuration); for example, if you want to add the THRON plugin to the "Full HTML" text format you will have to go to `/admin/config/content/formats/manage/full_html` in your Drupal site. The URL format for this configuration page is in the form `/admin/config/content/formats/manage/{text format}`.

Drag the "THRON" button from the "Available buttons" to one suitable position in the "Active toolbar".

![Configuring the text format](https://hub-cdn.thron.com/delivery/public/image/hub/8e38eb0c-f023-42fa-a32d-23cf12ad2365/ycswyb/std/1024x162/02-drupal-config "Configuring the text format")

![THRON button in its place](https://hub-cdn.thron.com/delivery/public/image/hub/c4e410a0-4789-4975-8f7a-43eccae34e9e/0dea8j/std/1024x768/025-drupal-config "THRON button in its place")

Make sure that the "Display embedded entities" checkbox is flagged and then save the text format.

![Display embedded entities checkbox](https://hub-cdn.thron.com/delivery/public/image/hub/f2fb21d2-fe11-4c38-a263-736f5b175869/t3zxcd/std/922x455/03-drupal-config "Display embedded entities checkbox")

You will now be able to embed THRON content via the button in the CKEditor window in the desired text format.

![Embed THRON content in page](https://hub-cdn.thron.com/delivery/public/image/hub/8f5cee6b-47bf-4f94-91c6-4c8c61b089a6/tyxv1l/std/1024x488/04-drupal-config "Embed THRON content in page")

For more detail please check [the Drupal Connector's page](https://marketplace.thron.com/EN/apps/drupal-connector) in the [THRON Marketplace](https://marketplace.thron.com).